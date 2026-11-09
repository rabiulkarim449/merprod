<?php

namespace ME\MerchandisingSfl\Http\Controllers\Commercial;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Http\Requests\Commercial\InvoiceRequest;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Models\Commercial\Invoice;
use ME\MerchandisingSfl\Services\Commercial\LcStatus;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\PackingFigures;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Commercial → Commercial Invoice: what was shipped against an active Export LC, entered as
 * a carton packing list (carton ranges × pcs per size, capped by packed − already invoiced
 * per size); qty / cartons / weights / CBM and the PO lines (× FOB) follow from it.
 * Prints the Commercial Invoice and the Carton Packing List.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly LcStatus $status)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_com_invoice.list');

        $invoices = Invoice::query()->with(['buyer', 'exportLc.currency', 'shipMode'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('invoice_no', 'like', '%' . $request->search . '%')
                ->orWhere('exp_no', 'like', '%' . $request->search . '%')->orWhere('bl_no', 'like', '%' . $request->search . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('export_lc_id'), fn ($q) => $q->where('export_lc_id', $request->export_lc_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->to))
            ->latest('invoice_date')->latest('id')->paginate($this->perPage(20))->withQueryString();

        return view('merchandising-sfl::admin.commercial.invoices.index', [
            'invoices' => $invoices, 'buyers' => Lookups::buyers(), 'lcs' => ExportLc::with('buyer')->latest('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_com_invoice.add');
        $lc = ExportLc::query()->where('status', 'active')->find($request->integer('export_lc_id'));

        return view('merchandising-sfl::admin.commercial.invoices.create', $this->formData(new Invoice(['invoice_date' => now(), 'port_of_loading' => 'Chattogram, Bangladesh']), $lc));
    }

    public function store(InvoiceRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $lc = ExportLc::findOrFail($request->export_lc_id);
        $invoice = DB::transaction(function () use ($request, $numbers, $lc) {
            $invoice = Invoice::create(Arr::except($request->validated(), ['cartons']) + [
                'invoice_no' => $numbers->daily('com_invoice', Invoice::class, 'invoice_no', $request->validated('invoice_date')), 'buyer_id' => $lc->buyer_id, 'created_by' => auth()->id(),
            ]);
            $this->saveCartons($invoice, $request->validated('cartons'));

            return $invoice;
        });

        return redirect()->route('msfl.commercial.invoices.show', $invoice)->with('success', "Commercial invoice {$invoice->invoice_no} saved." . $this->overLimit($lc));
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('msfl_com_invoice.view');
        $invoice->load(['buyer', 'exportLc.currency', 'shipMode', 'creator', 'lines.orderPo.style', 'lines.orderPo.color', 'lines.orderPo.order', 'cartons.sizes', 'cartons.orderPo.style', 'cartons.orderPo.color']);

        // Inventory shipments carrying this invoice no (+ their gate passes = delivery challans).
        $shipments = Schema::hasTable('inv_shipments') ? DB::table('inv_shipments')->whereNull('deleted_at')->where('invoice_no', $invoice->invoice_no)->get(['id', 'shipment_no', 'shipment_date', 'status']) : collect();
        $challans = $shipments->isNotEmpty() ? DB::table('inv_gate_passes')->whereNull('deleted_at')->whereIn('shipment_id', $shipments->pluck('id'))->get(['id', 'gate_pass_no']) : collect();

        return view('merchandising-sfl::admin.commercial.invoices.show', ['invoice' => $invoice, 'sizes' => $this->cartonSizes($invoice), 'shipments' => $shipments, 'challans' => $challans]);
    }

    /** Commercial Invoice or Packing List on printMaster2. */
    public function print(Invoice $invoice, string $doc): View
    {
        $this->authorize('msfl_com_invoice.view');
        $invoice->load(['buyer', 'exportLc.currency', 'exportLc.paymentTerm', 'exportLc.issuingBank', 'exportLc.lienBank', 'shipMode', 'lines.orderPo.style', 'lines.orderPo.color', 'lines.orderPo.order', 'cartons.sizes', 'cartons.orderPo.style', 'cartons.orderPo.color']);

        return view('merchandising-sfl::admin.commercial.invoices.print', ['invoice' => $invoice, 'doc' => $doc, 'sizes' => $this->cartonSizes($invoice)]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('msfl_com_invoice.edit');

        return view('merchandising-sfl::admin.commercial.invoices.edit', $this->formData($invoice->load('cartons.sizes'), $invoice->exportLc));
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $lc = ExportLc::findOrFail($request->export_lc_id);
        DB::transaction(function () use ($request, $invoice, $lc) {
            $invoice->update(Arr::except($request->validated(), ['cartons']) + ['buyer_id' => $lc->buyer_id]);
            $this->saveCartons($invoice, $request->validated('cartons'));
        });

        return redirect()->route('msfl.commercial.invoices.show', $invoice)->with('success', 'Commercial invoice updated.' . $this->overLimit($lc));
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('msfl_com_invoice.delete');
        $invoice->delete();

        return redirect()->route('msfl.commercial.invoices.index')->with('success', "Commercial invoice {$invoice->invoice_no} deleted.");
    }

    /**
     * Carton packing list → the invoice: carton rows with their size pcs, one invoice line per PO
     * (qty = Σ pcs × cartons, cartons = Σ cartons, price = PO FOB), and the weights / CBM totals.
     */
    private function saveCartons(Invoice $invoice, array $rows): void
    {
        $invoice->cartons()->delete();
        $invoice->lines()->delete();
        $perPo = [];
        $net = $gross = $cbm = 0.0;
        foreach (array_values($rows) as $i => $row) {
            $carton = $invoice->cartons()->create(collect($row)->only(['order_po_id', 'carton_from', 'carton_to', 'net_weight', 'gross_weight', 'length', 'width', 'height'])->all() + ['sort' => $i]);
            foreach ($row['sizes'] as $sizeId => $qty) {
                $carton->sizes()->create(['size_id' => (int) $sizeId, 'qty' => (int) $qty]);
            }
            $carton->load('sizes');
            $count = $carton->cartonCount();
            $perPo[$carton->order_po_id]['qty'] = ($perPo[$carton->order_po_id]['qty'] ?? 0) + $carton->totalPcs();
            $perPo[$carton->order_po_id]['cartons'] = ($perPo[$carton->order_po_id]['cartons'] ?? 0) + $count;
            $net += (float) $carton->net_weight * $count;
            $gross += (float) $carton->gross_weight * $count;
            $cbm += $carton->cbm();
        }

        $prices = \ME\MerchandisingSfl\Models\OrderPo::whereIn('id', array_keys($perPo))->pluck('unit_price', 'id');
        foreach ($perPo as $poId => $p) {
            $invoice->lines()->create(['order_po_id' => $poId, 'qty' => $p['qty'], 'cartons' => $p['cartons'], 'unit_price' => $prices[$poId] ?? 0]);
        }
        $invoice->forceFill(['net_weight' => $net ?: null, 'gross_weight' => $gross ?: null, 'cbm' => $cbm ?: null]);
        $invoice->refreshTotals();
    }

    /** Size columns of the invoice's carton list (sizes of its POs, XS → 3XL). */
    private function cartonSizes(Invoice $invoice): Collection
    {
        return \ME\MerchandisingSfl\Models\Size::displaySort(\ME\MerchandisingSfl\Models\Size::query()
            ->whereIn('id', DB::table('msfl_order_po_sizes')->whereIn('order_po_id', $invoice->cartons->pluck('order_po_id'))->pluck('size_id'))->get());
    }

    /** Warning appended to the success message when the LC's shipped value passes LC value + tolerance. */
    private function overLimit(ExportLc $lc): string
    {
        $shipped = $this->status->shippedValue($lc);

        return $shipped > $lc->maxValue()
            ? ' Note: shipped value ' . number_format($shipped, 2) . ' is above LC value + tolerance (' . number_format($lc->maxValue(), 2) . ') — an LC amendment may be needed.'
            : '';
    }

    private function formData(Invoice $invoice, ?ExportLc $lc): array
    {
        $figures = app(PackingFigures::class);
        $pos = $lc ? $lc->pos()->with(['style', 'color', 'sizes.size'])->get()->sortBy(fn ($p) => $p->po_no . '|' . ($p->style->style_no ?? ''))->values() : collect();
        // Per PO and size: packed, already invoiced (other invoices), still free to ship.
        $avail = $pos->mapWithKeys(function ($po) use ($figures, $invoice) {
            $f = $figures->forPo($po, $invoice->exists ? $invoice->id : null);

            return [$po->id => ['po' => $po, 'sizes' => $f['sizes'], 'packed' => $f['packed'], 'invoiced' => $f['invoiced'],
                'free' => $f['sizes']->mapWithKeys(fn ($s) => [$s->id => max(0, ($f['packed'][$s->id] ?? 0) - ($f['invoiced'][$s->id] ?? 0))])->all()]];
        });
        $sizes = \ME\MerchandisingSfl\Models\Size::displaySort($pos->flatMap(fn ($p) => $p->sizes->pluck('size'))->filter()->unique('id'));

        $rows = old('cartons', $invoice->exists
            ? $invoice->cartons->map(fn ($c) => $c->only(['order_po_id', 'carton_from', 'carton_to', 'net_weight', 'gross_weight', 'length', 'width', 'height'])
                + ['sizes' => $c->sizes->pluck('qty', 'size_id')->all()])->all()
            : []);

        return [
            'invoice' => $invoice,
            'lc' => $lc,
            'lcs' => ExportLc::query()->with('buyer')->where('status', 'active')->orderByDesc('id')->get(),
            'avail' => $avail,
            'sizes' => $sizes,
            'cartonRows' => $rows ?: [[]],
            'shipModes' => Lookups::shipModes(),
        ];
    }
}
