<?php

namespace ME\MerchandisingSfl\Support;

use App\Models\User;
use Illuminate\Validation\Rule;
use ME\MerchandisingSfl\Models;

/**
 * Every Master Data screen (route segment => definition). One controller
 * (MasterController) and one view (masters/index) render all of them.
 *
 * Field keys:
 *   name, label, type (text|email|textarea|number|decimal|date|select|boolean),
 *   required (bool), unique (bool, or 'active' to ignore soft-deleted rows), max (int), col (bootstrap col-md-*),
 *   options (array, or callable returning [value => label]) for select.
 * Column keys: label, value (attribute name, or Closure(Model): string).
 * Optional: filters (column => options array or Closure), search (columns,
 * default code + name), delete_blocked (Closure(Model): ?string reason).
 * `is_active` is added to every master automatically.
 */
class MasterRegistry
{
    public static function all(): array
    {
        $users = fn () => User::query()->orderBy('name')->pluck('name', 'id')->all();
        $active = fn (string $model) => fn () => $model::query()->active()->orderBy('name')->pluck('name', 'id')->all();
        $code = ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 50];
        $name = ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255];
        $codeName = [['label' => 'Code', 'value' => 'code'], ['label' => 'Name', 'value' => 'name']];

        return [
            'buyers' => [
                'title' => 'Buyers', 'singular' => 'Buyer', 'model' => Models\Buyer::class, 'permission' => 'msfl_buyer',
                'fields' => [
                    $code, $name,
                    ['name' => 'short_name', 'label' => 'Short Name', 'type' => 'text', 'max' => 50],
                    ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'type' => 'select', 'options' => $users, 'exists' => 'users'],
                    ['name' => 'country', 'label' => 'Country', 'type' => 'text', 'max' => 100],
                    ['name' => 'agent_name', 'label' => 'Agent / Buying House', 'type' => 'text'],
                    ['name' => 'contact_person', 'label' => 'Contact Person', 'type' => 'text'],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'max' => 50],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'payment_term_id', 'label' => 'Payment Term', 'type' => 'select', 'options' => fn () => Models\Commercial\PaymentTerm::query()->active()->orderBy('days')->orderBy('name')->pluck('name', 'id')->all(), 'exists' => 'msfl_com_payment_terms'],
                    ['name' => 'delivery_term', 'label' => 'Delivery Term', 'type' => 'select', 'options' => Models\Order::DELIVERY_TERMS],
                    ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'col' => 12],
                ],
                'columns' => [
                    ...$codeName,
                    ['label' => 'Country', 'value' => 'country'],
                    ['label' => 'Merchandiser', 'value' => fn ($m) => $m->merchandiser->name ?? '-'],
                    ['label' => 'Contact', 'value' => fn ($m) => collect([$m->contact_person, $m->phone])->filter()->implode(' / ') ?: '-'],
                    ['label' => 'Delivery Term', 'value' => 'delivery_term'],
                    ['label' => 'Approval', 'value' => fn ($m) => $m->approvalLabel()],
                ],
                'with' => ['merchandiser'],
                'note' => fn () => 'A new buyer goes to <strong>Approvals</strong> and can be used (orders, Inventory) only once approved. Editing a rejected buyer sends it again. Inventory has no buyer list of its own — it shows these.',
            ],
            // The style list itself (same table as Tech Pack / Styles): quick add by
            // buyer + style no; Inventory picks buyer → style from here.
            'styles' => [
                'title' => 'Styles', 'singular' => 'Style', 'model' => Models\Style::class, 'permission' => 'msfl_style',
                'fields' => [
                    ['name' => 'buyer_id', 'label' => 'Buyer', 'type' => 'select', 'required' => true, 'options' => $active(Models\Buyer::class), 'exists' => 'msfl_buyers'],
                    // Unique per buyer, not globally — two different buyers legitimately reuse the same style number.
                    ['name' => 'style_no', 'label' => 'Style No', 'type' => 'text', 'required' => true, 'unique' => true, 'unique_scope' => 'buyer_id', 'max' => 100],
                    ['name' => 'name', 'label' => 'Style Name', 'type' => 'text', 'required' => true, 'max' => 255],
                    ['name' => 'season_id', 'label' => 'Season', 'type' => 'select', 'options' => $active(Models\Season::class), 'exists' => 'msfl_seasons'],
                    ['name' => 'product_type_id', 'label' => 'Product Type', 'type' => 'select', 'options' => $active(Models\ProductType::class), 'exists' => 'msfl_product_types'],
                    ['name' => 'color_id', 'label' => 'Color', 'type' => 'select', 'options' => $active(Models\Color::class), 'exists' => 'inv_colors'],
                ],
                'columns' => [
                    ['label' => 'Style No', 'value' => 'style_no'],
                    ['label' => 'Name', 'value' => 'name'],
                    ['label' => 'Buyer', 'value' => fn ($m) => $m->buyer->name ?? '-'],
                    ['label' => 'Season', 'value' => fn ($m) => $m->season->name ?? '-'],
                    ['label' => 'Product Type', 'value' => fn ($m) => $m->productType->name ?? '-'],
                    ['label' => 'Color', 'value' => fn ($m) => $m->color->name ?? '-'],
                ],
                'with' => ['buyer', 'season', 'productType', 'color'],
                'filters' => ['buyer_id' => fn () => Models\Buyer::query()->orderBy('name')->pluck('name', 'id')->all()],
                'search' => ['style_no', 'name'],
                'delete_blocked' => fn ($m) => ($m->orderPos()->exists() || $m->boms()->exists() || $m->samples()->exists())
                    ? 'This style is used in an order, BOM or sample — it cannot be deleted.'
                    : null,
                'note' => fn () => 'One list of styles for Merchandising <strong>and Inventory</strong> (Buyer → Style in every Inventory form). The <strong>Color</strong> set here is taken by the style\'s PO lines on the order. Tech pack details, files and images: <a href="' . e(route('msfl.styles.index')) . '">Tech Pack / Styles</a>.',
                'order_by' => ['style_no', 'asc'],
            ],
            'seasons' => [
                'title' => 'Seasons', 'singular' => 'Season', 'model' => Models\Season::class, 'permission' => 'msfl_season',
                'fields' => [
                    $code, $name,
                    ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'min' => 2000, 'max' => 2100],
                    ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date'],
                    ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date'],
                ],
                'columns' => [
                    ...$codeName,
                    ['label' => 'Year', 'value' => 'year'],
                    ['label' => 'Period', 'value' => fn ($m) => $m->start_date ? $m->start_date->format('d M Y') . ' – ' . optional($m->end_date)->format('d M Y') : '-'],
                ],
            ],
            'product-types' => [
                'title' => 'Product Types', 'singular' => 'Product Type', 'model' => Models\ProductType::class, 'permission' => 'msfl_product_type',
                'fields' => [
                    $code, $name,
                    ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => ['woven' => 'Woven', 'knit' => 'Knit', 'denim' => 'Denim', 'sweater' => 'Sweater']],
                    ['name' => 'default_smv', 'label' => 'Default SMV', 'type' => 'decimal'],
                ],
                'columns' => [...$codeName, ['label' => 'Category', 'value' => fn ($m) => ucfirst((string) $m->category) ?: '-'], ['label' => 'Default SMV', 'value' => 'default_smv']],
            ],
            'wash-types' => [
                'title' => 'Wash Types', 'singular' => 'Wash Type', 'model' => Models\WashType::class, 'permission' => 'msfl_wash_type',
                'fields' => [$code, $name],
                'columns' => $codeName,
            ],
            'ship-modes' => [
                'title' => 'Ship Modes', 'singular' => 'Ship Mode', 'model' => Models\ShipMode::class, 'permission' => 'msfl_ship_mode',
                'fields' => [$code, $name],
                'columns' => $codeName,
            ],
            'factories' => [
                'title' => 'Factories', 'singular' => 'Factory', 'model' => Models\Factory::class, 'permission' => 'msfl_factory',
                'fields' => [
                    $code, $name,
                    ['name' => 'capacity_per_month', 'label' => 'Capacity / Month (pcs)', 'type' => 'number', 'min' => 0],
                    ['name' => 'is_own', 'label' => 'Own Factory', 'type' => 'boolean', 'default' => true],
                    ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'col' => 12],
                ],
                'columns' => [...$codeName, ['label' => 'Capacity / Month', 'value' => 'capacity_per_month'], ['label' => 'Own / Sub-contract', 'value' => fn ($m) => $m->is_own ? 'Own' : 'Sub-contract']],
            ],
            'currencies' => [
                'title' => 'Currencies', 'singular' => 'Currency', 'model' => Models\Currency::class, 'permission' => 'msfl_currency',
                'fields' => [
                    ['name' => 'code', 'label' => 'Code (ISO)', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 10],
                    $name,
                    ['name' => 'symbol', 'label' => 'Symbol', 'type' => 'text', 'max' => 10],
                    ['name' => 'exchange_rate', 'label' => 'Exchange Rate (to BDT)', 'type' => 'decimal', 'required' => true],
                ],
                'columns' => [...$codeName, ['label' => 'Symbol', 'value' => 'symbol'], ['label' => 'Rate (BDT)', 'value' => 'exchange_rate']],
            ],
            'item-categories' => [
                'title' => 'Item Categories', 'singular' => 'Item Category', 'model' => Models\ItemCategory::class, 'permission' => 'msfl_item_category',
                'fields' => [$code, $name, ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => self::itemTypes()]],
                'columns' => [...$codeName, ['label' => 'Type', 'value' => fn ($m) => self::itemTypes()[$m->type] ?? $m->type]],
            ],
            'items' => [
                'title' => 'Items (Fabric & Trims)', 'singular' => 'Item', 'model' => Models\Item::class, 'permission' => 'msfl_item',
                'fields' => [
                    $code, $name,
                    ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => self::itemTypes()],
                    ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => $active(Models\ItemCategory::class), 'exists' => 'msfl_item_categories'],
                    ['name' => 'uom_id', 'label' => 'Unit', 'type' => 'select', 'options' => $active(Models\Uom::class), 'exists' => 'inv_units'],
                    ['name' => 'default_supplier_id', 'label' => 'Default Supplier', 'type' => 'select', 'options' => $active(Models\Supplier::class), 'exists' => 'inv_suppliers'],
                    ['name' => 'default_price', 'label' => 'Default Price', 'type' => 'decimal'],
                    ['name' => 'composition', 'label' => 'Composition', 'type' => 'text'],
                    ['name' => 'gsm', 'label' => 'GSM', 'type' => 'decimal'],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'col' => 12],
                ],
                'columns' => [
                    ...$codeName,
                    ['label' => 'Type', 'value' => fn ($m) => self::itemTypes()[$m->type] ?? $m->type],
                    ['label' => 'Category', 'value' => fn ($m) => $m->category->name ?? '-'],
                    ['label' => 'Unit', 'value' => fn ($m) => $m->uom->code ?? '-'],
                    ['label' => 'Supplier', 'value' => fn ($m) => $m->defaultSupplier->name ?? '-'],
                    ['label' => 'Price', 'value' => 'default_price'],
                ],
                'with' => ['category', 'uom', 'defaultSupplier'],
                'filters' => ['type' => self::itemTypes()],
            ],
            'sample-types' => [
                'title' => 'Sample Types', 'singular' => 'Sample Type', 'model' => Models\SampleType::class, 'permission' => 'msfl_sample_type',
                'fields' => [
                    $code, $name,
                    ['name' => 'sequence', 'label' => 'Sequence', 'type' => 'number', 'min' => 0],
                    ['name' => 'requires_buyer_approval', 'label' => 'Needs Buyer Approval', 'type' => 'boolean', 'default' => true],
                ],
                'columns' => [...$codeName, ['label' => 'Sequence', 'value' => 'sequence'], ['label' => 'Buyer Approval', 'value' => fn ($m) => $m->requires_buyer_approval ? 'Yes' : 'No']],
                'order_by' => ['sequence', 'asc'],
            ],

            'garment-parts' => [
                'title' => 'Garment Parts', 'singular' => 'Garment Part', 'model' => Models\GarmentPart::class, 'permission' => 'msfl_garment_part',
                'fields' => [$code, ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 100]],
                'columns' => $codeName,
                'note' => fn () => 'Parts (Front, Back, Sleeve …) picked in Cutting → Parts Cut, part-wise Embroidery, QC / Rework and defect rows. Renaming a part doesn\'t change entries already saved with the old name.',
                'order_by' => ['name', 'asc'],
            ],

            // Planning → Setup
            'machine-types' => [
                'title' => 'Machine Types', 'singular' => 'Machine Type', 'model' => Models\MachineType::class, 'permission' => 'msfl_machine_type',
                'fields' => [$code, $name, ['name' => 'is_helper', 'label' => 'Helper workplace (no machine — MAN, IRON)', 'type' => 'boolean', 'col' => 6]],
                'columns' => [...$codeName, ['label' => 'Inventory Type', 'value' => fn ($m) => $m->inv_type ?? '-'], ['label' => 'Counted as', 'value' => fn ($m) => $m->is_helper ? 'Helper' : 'Operator']],
                // Machine types come from Inventory → Machines; only helper workplaces are added here.
                'before_index' => fn () => \ME\MerchandisingSfl\Services\InventoryMachines::syncTypes(),
                'note' => fn () => \ME\MerchandisingSfl\Services\InventoryMachines::available()
                    ? 'Machine types are filled from <a href="' . e(\Illuminate\Support\Facades\Route::has('inventory.machines.index') ? route('inventory.machines.index') : '#') . '" target="_blank">Inventory → Machines</a> (their <strong>Type</strong>) — don\'t add machines here. Set a short code (e.g. SNLS) for each, and add helper workplaces (MAN, IRON) that aren\'t machines.'
                    : null,
                'order_by' => ['code', 'asc'],
            ],
            'attachments' => [
                'title' => 'Attachments', 'singular' => 'Attachment', 'model' => Models\Attachment::class, 'permission' => 'msfl_attachment',
                'fields' => [$code, $name],
                'columns' => $codeName,
                'note' => fn () => 'Work aids (Guide, Pattern, Cutter, Table …) pickable on an Operation Library entry and on a bulletin\'s operation rows.',
                'order_by' => ['name', 'asc'],
            ],
            'operations' => [
                'title' => 'Operations Library', 'singular' => 'Operation', 'model' => Models\Operation::class, 'permission' => 'msfl_operation',
                'before_index' => fn () => \ME\MerchandisingSfl\Services\InventoryMachines::syncTypes(),
                'fields' => [
                    $code, $name,
                    ['name' => 'machine_type_id', 'label' => 'Machine Type (M/C)', 'type' => 'select', 'options' => fn () => Models\MachineType::query()->active()->orderBy('code')->get()->mapWithKeys(fn ($t) => [$t->id => $t->code . ' — ' . $t->name])->all(), 'exists' => 'msfl_machine_types'],
                    // Reuses the Garment Parts master (not a new one) so sections
                    // stay dynamic/admin-addable — see Master Data → Garment Parts.
                    ['name' => 'garment_part_id', 'label' => 'Section', 'type' => 'select', 'options' => fn () => Models\GarmentPart::query()->active()->orderBy('name')->pluck('name', 'id')->all(), 'exists' => 'msfl_garment_parts'],
                    ['name' => 'attachment', 'label' => 'Attachment', 'type' => 'select', 'options' => fn () => self::attachments()],
                    ['name' => 'default_smv', 'label' => 'Default SMV', 'type' => 'decimal'],
                ],
                'columns' => [...$codeName, ['label' => 'Section', 'value' => fn ($m) => $m->garmentPart->name ?? '-'], ['label' => 'M/C', 'value' => fn ($m) => $m->machineType->code ?? '-'], ['label' => 'Attachment', 'value' => 'attachment'], ['label' => 'Default SMV', 'value' => 'default_smv']],
                'with' => ['machineType', 'garmentPart'],
            ],
            // Commercial → Setup
            'banks' => [
                'title' => 'Banks', 'singular' => 'Bank', 'model' => Models\Commercial\Bank::class, 'permission' => 'msfl_com_bank',
                'fields' => [
                    $code, $name,
                    ['name' => 'short_name', 'label' => 'Short Name', 'type' => 'text', 'max' => 50],
                    ['name' => 'bank_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => Models\Commercial\Bank::TYPES],
                    ['name' => 'branch', 'label' => 'Branch', 'type' => 'text'],
                    ['name' => 'swift_code', 'label' => 'SWIFT / BIC', 'type' => 'text', 'max' => 20],
                    ['name' => 'ad_code', 'label' => 'AD Code', 'type' => 'text', 'max' => 30],
                    ['name' => 'account_no', 'label' => 'Our Account No', 'type' => 'text', 'max' => 50],
                    ['name' => 'country', 'label' => 'Country', 'type' => 'text', 'max' => 100],
                    ['name' => 'contact_person', 'label' => 'Contact Person', 'type' => 'text'],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'max' => 50],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'col' => 12],
                ],
                'columns' => [
                    ...$codeName,
                    ['label' => 'Type', 'value' => fn ($m) => Models\Commercial\Bank::TYPES[$m->bank_type] ?? $m->bank_type],
                    ['label' => 'Branch', 'value' => 'branch'],
                    ['label' => 'SWIFT', 'value' => 'swift_code'],
                    ['label' => 'Account', 'value' => 'account_no'],
                    ['label' => 'Country', 'value' => 'country'],
                ],
                'filters' => ['bank_type' => Models\Commercial\Bank::TYPES],
                'note' => fn () => '<strong>Our Bank</strong> = where our LCs are lien / advised and BTB LCs are opened; <strong>Buyer\'s Bank</strong> = the bank that issues the buyer\'s LC. Picked on Commercial → Export LC.',
                'order_by' => ['name', 'asc'],
            ],
            'payment-terms' => [
                'title' => 'Payment Terms', 'singular' => 'Payment Term', 'model' => Models\Commercial\PaymentTerm::class, 'permission' => 'msfl_com_payment_term',
                'fields' => [
                    $code, $name,
                    ['name' => 'term_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => Models\Commercial\PaymentTerm::TYPES],
                    ['name' => 'days', 'label' => 'Days (usance / credit)', 'type' => 'number', 'min' => 0, 'max' => 365],
                ],
                'columns' => [...$codeName, ['label' => 'Type', 'value' => fn ($m) => Models\Commercial\PaymentTerm::TYPES[$m->term_type] ?? $m->term_type], ['label' => 'Days', 'value' => 'days']],
                'note' => fn () => 'Days count from the B/L / document date — e.g. "Usance 90 days": payment due 90 days after acceptance. Picked on Commercial → Export LC.',
                'order_by' => ['days', 'asc'],
            ],
            'daily-targets' => [
                'title' => 'Daily Targets', 'singular' => 'Daily Target', 'model' => Models\DailyTarget::class, 'permission' => 'msfl_daily_target',
                'fields' => [
                    ['name' => 'target_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'unique' => 'active'],
                    ['name' => 'cutting_target', 'label' => 'Cutting Target (pcs)', 'type' => 'number', 'min' => 0],
                    ['name' => 'packing_target', 'label' => 'Poly / Packing Target (pcs)', 'type' => 'number', 'min' => 0],
                    ['name' => 'line_required_value', 'label' => 'Required Value / Line (FOB)', 'type' => 'decimal'],
                    ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'text', 'col' => 8],
                ],
                'columns' => [
                    ['label' => 'Date', 'value' => fn ($m) => $m->target_date->format('d M Y (D)')],
                    ['label' => 'Cutting Target', 'value' => 'cutting_target'],
                    ['label' => 'Poly / Packing Target', 'value' => 'packing_target'],
                    ['label' => 'Required Value / Line', 'value' => 'line_required_value'],
                    ['label' => 'Remarks', 'value' => 'remarks'],
                ],
                'search' => ['remarks'],
                'order_by' => ['target_date', 'desc'],
                'note' => fn () => 'Read by the <a href="' . e(route('msfl.reports.show', 'line-output')) . '">Line Wise Output</a> report (day and month-to-date balances). Sewing targets come from each line\'s Sewing Plan, not from here.',
            ],
        ];
    }

    public static function get(string $slug): array
    {
        $definition = self::all()[$slug] ?? abort(404);
        $definition['slug'] = $slug;

        return $definition;
    }

    public static function itemTypes(): array
    {
        return ['fabric' => 'Fabric', 'trims' => 'Trims', 'accessories' => 'Accessories', 'packing' => 'Packing'];
    }

    /** Work aids on a bulletin operation — the "attachments" master data, not a fixed list. */
    public static function attachments(): array
    {
        return Models\Attachment::query()->active()->orderBy('name')->pluck('name', 'name')->all();
    }

    /** Select options as [value => label], resolving lazy (callable) option lists. */
    public static function options(array $field): array
    {
        $options = $field['options'] ?? [];

        return $options instanceof \Closure ? $options() : $options;
    }

    public static function rules(array $definition, $ignoreId = null): array
    {
        $table = (new $definition['model'])->getTable();
        $rules = ['is_active' => ['nullable', 'boolean']];

        foreach ($definition['fields'] as $field) {
            $fieldRules = [! empty($field['required']) ? 'required' : 'nullable'];

            $fieldRules = array_merge($fieldRules, match ($field['type']) {
                'email' => ['email', 'max:255'],
                'textarea' => ['string', 'max:5000'],
                'number' => array_filter(['integer', 'min:' . ($field['min'] ?? 0), isset($field['max']) ? 'max:' . $field['max'] : null]),
                'decimal' => ['numeric', 'min:0'],
                'date' => ['date'],
                'boolean' => ['boolean'],
                'select' => isset($field['exists'])
                    ? [Rule::exists($field['exists'], 'id')]
                    : [Rule::in(array_keys(self::options($field)))],
                default => ['string', 'max:' . ($field['max'] ?? 255)],
            });

            if (! empty($field['unique'])) {
                $unique = Rule::unique($table, $field['name'])->ignore($ignoreId);
                // e.g. style_no unique per buyer_id, not globally — scope to that other field's submitted value.
                if (! empty($field['unique_scope'])) {
                    $unique = $unique->where($field['unique_scope'], request()->input($field['unique_scope']));
                }
                // 'active': a soft-deleted row doesn't block adding the same value again.
                $fieldRules[] = $field['unique'] === 'active' ? $unique->whereNull('deleted_at') : $unique;
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }

    /** Display value of one index column for a record. */
    public static function columnValue(array $column, $record): string
    {
        $value = $column['value'] instanceof \Closure ? ($column['value'])($record) : $record->{$column['value']};

        return ($value === null || $value === '') ? '-' : (string) $value;
    }
}
