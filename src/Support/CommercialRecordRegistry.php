<?php

namespace ME\MerchandisingSfl\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use ME\MerchandisingSfl\Models;
use ME\MerchandisingSfl\Models\Commercial;
use ME\MerchandisingSfl\Services\FileUploadService;

/**
 * Commercial record-keeping screens (route segment => definition): data entry with the
 * document file, nothing else posts from them. One controller (Commercial\RecordController)
 * and one set of views (commercial/records) render all of them.
 *
 * Field keys: name, label, type (text|textarea|date|decimal|select|pick|file), required, col, max,
 *   options (Closure(Model $record): [value => label]) for select / pick, exists (table) for select,
 *   pick = several rows of another record (value: Closure(Model): ids, sync: Closure(Model, ids)).
 * Column keys: label, value (attribute name or Closure(Model): string), class.
 * Optional: with, search (columns), related (Closure(Model): [[title, head[], rows[][]]]),
 *   delete_blocked (Closure(Model): ?string reason), totals (Closure(Model): [label => value]).
 */
class CommercialRecordRegistry
{
    public static function all(): array
    {
        $suppliers = fn () => Models\Supplier::query()->active()->orderBy('name')->pluck('name', 'id')->all();
        $currencies = fn () => Models\Currency::query()->active()->orderBy('code')->pluck('code', 'id')->all();
        $ourBanks = fn () => Commercial\Bank::query()->active()->where('bank_type', 'our')->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => $b->label()])->all();
        $exportLcs = fn () => Commercial\ExportLc::query()->with('buyer')->latest('id')->get()->mapWithKeys(fn ($lc) => [$lc->id => $lc->label()])->all();
        $invoices = fn () => Commercial\Invoice::query()->with(['buyer', 'exportLc.currency'])->latest('id')->get()
            ->mapWithKeys(fn ($i) => [$i->id => $i->invoice_no . ' · ' . ($i->buyer->name ?? '-') . ' (' . ($i->exportLc->currency->code ?? '') . ' ' . number_format((float) $i->total_value, 2) . ')'])->all();
        $btbLcs = fn () => Commercial\BtbLc::query()->with(['supplier', 'currency'])->latest('id')->get()->mapWithKeys(fn ($b) => [$b->id => $b->label()])->all();
        $money = fn ($cur, $v) => $v === null ? '-' : trim(($cur ?? '') . ' ' . number_format((float) $v, 2));
        $date = fn ($d) => $d?->format('d M Y') ?? '-';
        $remarks = ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 12];
        $file = fn (string $label) => ['name' => 'attachment', 'label' => $label, 'type' => 'file'];
        // Paperclip + file count; opens the first file (the record page lists them all).
        $fileColumn = ['label' => 'Files', 'value' => function ($m) {
            $files = FileUploadService::list($m->attachment);

            return $files ? '<a href="' . e(FileUploadService::link($files[0]['path'])) . '" target="_blank" title="' . e(implode(', ', array_column($files, 'name'))) . '"><i class="fa-solid fa-paperclip"></i> ' . count($files) . '</a>' : '';
        }, 'html' => true, 'class' => 'text-nowrap'];

        return [
            'supplier-pis' => [
                'title' => 'Supplier PI', 'singular' => 'Supplier PI', 'model' => Commercial\SupplierPi::class, 'permission' => 'msfl_com_supplier_pi',
                'fields' => [
                    ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'required' => true, 'options' => $suppliers, 'exists' => 'inv_suppliers'],
                    ['name' => 'pi_no', 'label' => 'PI No', 'type' => 'text', 'required' => true, 'max' => 100],
                    ['name' => 'pi_date', 'label' => 'PI Date', 'type' => 'date', 'required' => true],
                    ['name' => 'goods_type', 'label' => 'Goods', 'type' => 'select', 'options' => fn () => Commercial\SupplierPi::GOODS_TYPES],
                    ['name' => 'order_id', 'label' => 'Order', 'type' => 'select', 'options' => fn () => Models\Order::query()->with('buyer')->latest('id')->get()->mapWithKeys(fn ($o) => [$o->id => $o->order_no . ' · ' . ($o->buyer->name ?? '-')])->all(), 'exists' => 'msfl_orders'],
                    ['name' => 'export_lc_id', 'label' => 'Master LC / SC', 'type' => 'select', 'options' => $exportLcs, 'exists' => 'msfl_com_export_lcs'],
                    ['name' => 'currency_id', 'label' => 'Currency', 'type' => 'select', 'options' => $currencies, 'exists' => 'msfl_currencies'],
                    ['name' => 'pi_value', 'label' => 'PI Value', 'type' => 'decimal', 'required' => true],
                    $file('PI copy / documents'),
                    $remarks,
                ],
                'columns' => [
                    ['label' => 'PI No', 'value' => 'pi_no'],
                    ['label' => 'Date', 'value' => fn ($m) => $date($m->pi_date)],
                    ['label' => 'Supplier', 'value' => fn ($m) => $m->supplier->name ?? '-'],
                    ['label' => 'Goods', 'value' => fn ($m) => Commercial\SupplierPi::GOODS_TYPES[$m->goods_type] ?? '-'],
                    ['label' => 'Order', 'value' => fn ($m) => $m->order->order_no ?? '-'],
                    ['label' => 'Master LC', 'value' => fn ($m) => $m->exportLc->lc_no ?? '-'],
                    ['label' => 'Value', 'value' => fn ($m) => $money($m->currency->code ?? '', $m->pi_value), 'class' => 'text-right text-nowrap'],
                    ['label' => 'BTB LC', 'value' => fn ($m) => $m->btbLc->btb_lc_no ?? 'Not opened'],
                    $fileColumn,
                ],
                'with' => ['supplier', 'order', 'exportLc', 'currency', 'btbLc'],
                'search' => ['pi_no', 'remarks'],
                'filters' => ['supplier_id' => ['label' => 'All Suppliers', 'options' => $suppliers]],
            ],

            'btb-lcs' => [
                'title' => 'Back-to-Back LC', 'singular' => 'BTB LC', 'model' => Commercial\BtbLc::class, 'permission' => 'msfl_com_btb_lc',
                'fields' => [
                    ['name' => 'btb_lc_no', 'label' => 'BTB LC No', 'type' => 'text', 'required' => true, 'max' => 100],
                    ['name' => 'lc_date', 'label' => 'LC Date', 'type' => 'date', 'required' => true],
                    ['name' => 'export_lc_id', 'label' => 'Master LC / SC', 'type' => 'select', 'options' => $exportLcs, 'exists' => 'msfl_com_export_lcs'],
                    ['name' => 'supplier_id', 'label' => 'Supplier (beneficiary)', 'type' => 'select', 'required' => true, 'options' => $suppliers, 'exists' => 'inv_suppliers'],
                    ['name' => 'bank_id', 'label' => 'Opening Bank (ours)', 'type' => 'select', 'options' => $ourBanks, 'exists' => 'msfl_com_banks'],
                    ['name' => 'currency_id', 'label' => 'Currency', 'type' => 'select', 'options' => $currencies, 'exists' => 'msfl_currencies'],
                    ['name' => 'lc_value', 'label' => 'LC Value', 'type' => 'decimal', 'required' => true],
                    ['name' => 'payment_term_id', 'label' => 'Payment Term / Tenor', 'type' => 'select', 'options' => fn () => Commercial\PaymentTerm::query()->active()->orderBy('days')->orderBy('name')->pluck('name', 'id')->all(), 'exists' => 'msfl_com_payment_terms'],
                    ['name' => 'last_shipment_date', 'label' => 'Last Shipment Date', 'type' => 'date'],
                    ['name' => 'expiry_date', 'label' => 'Expiry Date', 'type' => 'date'],
                    ['name' => 'maturity_date', 'label' => 'Maturity / Payment Date', 'type' => 'date'],
                    $file('LC copy / documents'),
                    // Supplier PIs this BTB LC is opened against: still open ones, plus its own.
                    [
                        'name' => 'supplier_pi_ids', 'label' => 'Supplier PI(s)', 'type' => 'pick', 'col' => 12,
                        'options' => fn ($record) => Commercial\SupplierPi::query()->with(['supplier', 'currency'])
                            ->where(fn ($q) => $q->whereNull('btb_lc_id')->when($record?->exists, fn ($q) => $q->orWhere('btb_lc_id', $record->id)))
                            ->latest('id')->get()->mapWithKeys(fn ($pi) => [$pi->id => $pi->label()])->all(),
                        'value' => fn ($record) => $record->exists ? $record->supplierPis()->pluck('id')->all() : [],
                        'sync' => function ($record, array $ids) {
                            Commercial\SupplierPi::query()->where('btb_lc_id', $record->id)->whereNotIn('id', $ids)->update(['btb_lc_id' => null]);
                            Commercial\SupplierPi::query()->whereIn('id', $ids)->where(fn ($q) => $q->whereNull('btb_lc_id')->orWhere('btb_lc_id', $record->id))->update(['btb_lc_id' => $record->id]);
                        },
                    ],
                    $remarks,
                ],
                'columns' => [
                    ['label' => 'BTB LC No', 'value' => 'btb_lc_no'],
                    ['label' => 'Date', 'value' => fn ($m) => $date($m->lc_date)],
                    ['label' => 'Supplier', 'value' => fn ($m) => $m->supplier->name ?? '-'],
                    ['label' => 'Master LC', 'value' => fn ($m) => $m->exportLc->lc_no ?? '-'],
                    ['label' => 'Bank', 'value' => fn ($m) => $m->bank->name ?? '-'],
                    ['label' => 'Value', 'value' => fn ($m) => $money($m->currency->code ?? '', $m->lc_value), 'class' => 'text-right text-nowrap'],
                    ['label' => 'PIs', 'value' => fn ($m) => $m->supplier_pis_count, 'class' => 'text-right'],
                    ['label' => 'Expiry', 'value' => fn ($m) => $date($m->expiry_date)],
                    ['label' => 'Maturity', 'value' => fn ($m) => $date($m->maturity_date)],
                    $fileColumn,
                ],
                'with' => ['supplier', 'exportLc', 'bank', 'currency'],
                'with_count' => ['supplierPis'],
                'search' => ['btb_lc_no', 'remarks'],
                'filters' => ['supplier_id' => ['label' => 'All Suppliers', 'options' => $suppliers]],
                'related' => fn ($m) => [
                    [
                        'title' => 'Supplier PIs',
                        'head' => ['PI No', 'Date', 'Goods', 'Order', 'Value'],
                        'rows' => $m->supplierPis()->with(['order', 'currency'])->get()->map(fn ($pi) => [
                            $pi->pi_no, $date($pi->pi_date), Commercial\SupplierPi::GOODS_TYPES[$pi->goods_type] ?? '-', $pi->order->order_no ?? '-', $money($pi->currency->code ?? '', $pi->pi_value),
                        ])->all(),
                    ],
                    [
                        'title' => 'Import Shipments',
                        'head' => ['Invoice No', 'B/L / AWB', 'ETD', 'ETA', 'Arrival', 'Doc Release'],
                        'rows' => $m->importShipments()->get()->map(fn ($s) => [
                            $s->invoice_no ?: '-', $s->bl_no ?: '-', $date($s->etd), $date($s->eta), $date($s->arrival_date), $date($s->doc_release_date),
                        ])->all(),
                    ],
                ],
                'delete_blocked' => fn ($m) => $m->importShipments()->exists() ? 'This BTB LC has import shipments — delete those first.' : null,
                'before_delete' => fn ($m) => $m->supplierPis()->update(['btb_lc_id' => null]),
            ],

            'import-shipments' => [
                'title' => 'Import Shipment', 'singular' => 'Import Shipment', 'model' => Commercial\ImportShipment::class, 'permission' => 'msfl_com_import_shipment',
                'fields' => [
                    ['name' => 'btb_lc_id', 'label' => 'BTB LC', 'type' => 'select', 'options' => $btbLcs, 'exists' => 'msfl_com_btb_lcs'],
                    ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'options' => $suppliers, 'exists' => 'inv_suppliers'],
                    ['name' => 'invoice_no', 'label' => "Supplier's Invoice No", 'type' => 'text', 'max' => 100],
                    ['name' => 'invoice_value', 'label' => 'Invoice Value', 'type' => 'decimal'],
                    ['name' => 'ship_mode_id', 'label' => 'Ship Mode', 'type' => 'select', 'options' => fn () => Models\ShipMode::query()->active()->orderBy('name')->pluck('name', 'id')->all(), 'exists' => 'msfl_ship_modes'],
                    ['name' => 'bl_no', 'label' => 'B/L / AWB No', 'type' => 'text', 'max' => 100],
                    ['name' => 'bl_date', 'label' => 'B/L / AWB Date', 'type' => 'date'],
                    ['name' => 'etd', 'label' => 'ETD', 'type' => 'date'],
                    ['name' => 'eta', 'label' => 'ETA', 'type' => 'date'],
                    ['name' => 'arrival_date', 'label' => 'Arrival (port / factory)', 'type' => 'date'],
                    ['name' => 'doc_release_date', 'label' => 'Document Release Date', 'type' => 'date'],
                    ['name' => 'goods_release_date', 'label' => 'Goods Release Date', 'type' => 'date'],
                    $file('Shipment documents (invoice, packing list, B/L, bill of entry…)'),
                    $remarks,
                ],
                'columns' => [
                    ['label' => 'BTB LC', 'value' => fn ($m) => $m->btbLc->btb_lc_no ?? '-'],
                    ['label' => 'Supplier', 'value' => fn ($m) => $m->supplier->name ?? ($m->btbLc->supplier->name ?? '-')],
                    ['label' => 'Invoice No', 'value' => 'invoice_no'],
                    ['label' => 'B/L / AWB', 'value' => 'bl_no'],
                    ['label' => 'Mode', 'value' => fn ($m) => $m->shipMode->name ?? '-'],
                    ['label' => 'ETD', 'value' => fn ($m) => $date($m->etd)],
                    ['label' => 'ETA', 'value' => fn ($m) => $date($m->eta)],
                    ['label' => 'Arrival', 'value' => fn ($m) => $date($m->arrival_date)],
                    ['label' => 'Doc Release', 'value' => fn ($m) => $date($m->doc_release_date)],
                    $fileColumn,
                ],
                'with' => ['btbLc.supplier', 'supplier', 'shipMode'],
                'search' => ['invoice_no', 'bl_no', 'remarks'],
                'filters' => ['btb_lc_id' => ['label' => 'All BTB LCs', 'options' => $btbLcs]],
            ],

            'export-documents' => [
                'title' => 'Export Documents', 'singular' => 'Export Document', 'model' => Commercial\ExportDocument::class, 'permission' => 'msfl_com_export_document',
                'fields' => [
                    ['name' => 'invoice_id', 'label' => 'Commercial Invoice', 'type' => 'select', 'required' => true, 'options' => $invoices, 'exists' => 'msfl_com_invoices', 'col' => 6],
                    ['name' => 'doc_type', 'label' => 'Document', 'type' => 'select', 'required' => true, 'options' => fn () => Commercial\ExportDocument::TYPES],
                    ['name' => 'doc_no', 'label' => 'Document No', 'type' => 'text', 'max' => 100],
                    ['name' => 'doc_date', 'label' => 'Document Date', 'type' => 'date'],
                    ['name' => 'forwarder', 'label' => 'Freight Forwarder / Shipping Line', 'type' => 'text'],
                    ['name' => 'booking_date', 'label' => 'Booking Date', 'type' => 'date'],
                    $file('Document files'),
                    $remarks,
                ],
                'columns' => [
                    ['label' => 'Invoice', 'value' => fn ($m) => $m->invoice->invoice_no ?? '-'],
                    ['label' => 'Buyer', 'value' => fn ($m) => $m->invoice->buyer->name ?? '-'],
                    ['label' => 'Document', 'value' => fn ($m) => Commercial\ExportDocument::TYPES[$m->doc_type] ?? $m->doc_type],
                    ['label' => 'No', 'value' => 'doc_no'],
                    ['label' => 'Date', 'value' => fn ($m) => $date($m->doc_date)],
                    ['label' => 'Forwarder', 'value' => 'forwarder'],
                    ['label' => 'Booking', 'value' => fn ($m) => $date($m->booking_date)],
                    $fileColumn,
                ],
                'with' => ['invoice.buyer'],
                'search' => ['doc_no', 'forwarder', 'remarks'],
                'filters' => ['doc_type' => ['label' => 'All Documents', 'options' => fn () => Commercial\ExportDocument::TYPES]],
            ],

            'realizations' => [
                'title' => 'Bank Realization', 'singular' => 'Realization', 'model' => Commercial\Realization::class, 'permission' => 'msfl_com_realization',
                'fields' => [
                    ['name' => 'invoice_id', 'label' => 'Commercial Invoice', 'type' => 'select', 'required' => true, 'options' => $invoices, 'exists' => 'msfl_com_invoices', 'col' => 6],
                    ['name' => 'realization_date', 'label' => 'Realization Date', 'type' => 'date', 'required' => true],
                    ['name' => 'bank_id', 'label' => 'Bank (ours)', 'type' => 'select', 'options' => $ourBanks, 'exists' => 'msfl_com_banks'],
                    ['name' => 'realized_amount', 'label' => 'Realized Amount', 'type' => 'decimal', 'required' => true],
                    ['name' => 'bank_charge', 'label' => 'Bank Charge', 'type' => 'decimal'],
                    ['name' => 'prc_no', 'label' => 'PRC / ERC No', 'type' => 'text', 'max' => 100],
                    ['name' => 'prc_date', 'label' => 'PRC / ERC Date', 'type' => 'date'],
                    $file('PRC / bank advice'),
                    $remarks,
                ],
                'columns' => [
                    ['label' => 'Invoice', 'value' => fn ($m) => $m->invoice->invoice_no ?? '-'],
                    ['label' => 'Buyer', 'value' => fn ($m) => $m->invoice->buyer->name ?? '-'],
                    ['label' => 'Date', 'value' => fn ($m) => $date($m->realization_date)],
                    ['label' => 'Invoice Value', 'value' => fn ($m) => $money($m->invoice->exportLc->currency->code ?? '', $m->invoice->total_value ?? null), 'class' => 'text-right text-nowrap'],
                    ['label' => 'Realized', 'value' => fn ($m) => number_format((float) $m->realized_amount, 2), 'class' => 'text-right'],
                    ['label' => 'Bank Charge', 'value' => fn ($m) => $m->bank_charge === null ? '-' : number_format((float) $m->bank_charge, 2), 'class' => 'text-right'],
                    ['label' => 'PRC / ERC', 'value' => 'prc_no'],
                    $fileColumn,
                ],
                'with' => ['invoice.buyer', 'invoice.exportLc.currency'],
                'search' => ['prc_no', 'remarks'],
                // Invoice value vs everything realized against it so far (part payments add up).
                'totals' => function ($m) use ($money) {
                    if (! $m->invoice) {
                        return [];
                    }
                    $cur = $m->invoice->exportLc->currency->code ?? '';
                    $realized = (float) Commercial\Realization::query()->where('invoice_id', $m->invoice_id)->sum('realized_amount');

                    return ['Invoice Value' => $money($cur, $m->invoice->total_value), 'Realized (all entries)' => $money($cur, $realized), 'Short / Balance' => $money($cur, (float) $m->invoice->total_value - $realized)];
                },
            ],
        ];
    }

    public static function get(string $slug): array
    {
        $definition = self::all()[$slug] ?? abort(404);
        $definition['slug'] = $slug;

        return $definition;
    }

    /** Select / pick options as [value => label] for this record. */
    public static function options(array $field, ?Model $record = null): array
    {
        $options = $field['options'] ?? [];

        return $options instanceof \Closure ? $options($record) : $options;
    }

    public static function rules(array $definition, ?Model $record = null): array
    {
        $rules = [];
        foreach ($definition['fields'] as $field) {
            $name = $field['name'];
            $required = ! empty($field['required']) ? 'required' : 'nullable';
            $rules[$name] = match ($field['type']) {
                'textarea' => [$required, 'string', 'max:5000'],
                'date' => [$required, 'date'],
                'decimal' => [$required, 'numeric', 'min:0', 'max:99999999999999'],
                'select' => [$required, isset($field['exists']) ? Rule::exists($field['exists'], 'id') : Rule::in(array_keys(self::options($field, $record)))],
                'pick' => ['nullable', 'array'],
                'file' => ['nullable', 'array', 'max:20'],
                default => [$required, 'string', 'max:' . ($field['max'] ?? 255)],
            };
            if ($field['type'] === 'pick') {
                $rules[$name . '.*'] = ['integer', Rule::in(array_keys(self::options($field, $record)))];
            }
            if ($field['type'] === 'file') {
                $rules[$name . '.*'] = FileUploadService::RULES;
                $rules['remove_' . $name] = ['nullable', 'array'];
                $rules['remove_' . $name . '.*'] = ['string'];
            }
        }

        return $rules;
    }

    /** A field's value as text on the record page. */
    public static function display(array $field, Model $record): string
    {
        $value = $record->{$field['name']} ?? null;

        return match ($field['type']) {
            'date' => $value?->format('d M Y') ?? '-',
            'decimal' => $value === null ? '-' : number_format((float) $value, 2),
            'select' => $value === null || $value === '' ? '-' : (string) (self::options($field, $record)[$value] ?? $value),
            default => ($value === null || $value === '') ? '-' : (string) $value,
        };
    }
}
