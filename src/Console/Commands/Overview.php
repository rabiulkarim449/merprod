<?php

namespace ME\MerchandisingSfl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ME\MerchandisingSfl\Models as M;
use ME\MerchandisingSfl\Services\ProductionFlow;
use ME\MerchandisingSfl\Services\TnaPlanner;

/**
 * Read-only snapshot of the whole Merchandising v2 data, one screen per section:
 * masters, orders + PO lines, costing / BOM / bulletin, lines, T&A, production per
 * PO and the Inventory links. Meant for answering "where does this order stand?"
 * without opening every page.
 *   php artisan msfl:overview            (everything)
 *   php artisan msfl:overview orders     (masters | orders | dev | planning | production | inventory | commercial)
 */
class Overview extends Command
{
    protected $signature = 'msfl:overview {section? : masters | orders | dev | planning | production | inventory | commercial}';

    protected $description = 'Merchandising v2: read-only snapshot of masters, orders, planning, production and Inventory links';

    public function handle(ProductionFlow $flow, TnaPlanner $planner): int
    {
        $only = $this->argument('section');
        $sections = ['masters', 'orders', 'dev', 'planning', 'production', 'inventory', 'commercial'];
        if ($only && ! in_array($only, $sections, true)) {
            $this->error('Section must be one of: ' . implode(', ', $sections));

            return self::FAILURE;
        }

        $this->line('<info>Merchandising v2 overview — ' . now()->format('d-M-Y H:i') . '</info>');
        foreach ($sections as $section) {
            if (! $only || $only === $section) {
                $this->{$section}($flow, $planner);
            }
        }

        return self::SUCCESS;
    }

    private function head(string $title): void
    {
        $this->newLine();
        $this->line('<comment>== ' . $title . ' ==</comment>');
    }

    private function masters(): void
    {
        $this->head('Masters (active)');
        $counts = [];
        foreach (\ME\MerchandisingSfl\Support\MasterRegistry::all() as $slug => $def) {
            $counts[] = $def['title'] . ' ' . $def['model']::query()->where('is_active', true)->count();
        }
        $counts[] = 'Lines ' . M\Line::query()->active()->count();
        $this->line(implode(' · ', $counts));

        $this->line('Buyers: ' . M\Buyer::withoutGlobalScopes()->orderBy('name')->get()
            ->map(fn ($b) => $b->name . ($b->approval_status !== 'approved' ? ' [' . $b->approval_status . ']' : '') . ' (' . M\Style::where('buyer_id', $b->id)->count() . ' styles)')->implode(', '));
        $this->line('Styles with a color: ' . (M\Style::whereNotNull('color_id')->count()) . ' / ' . M\Style::count()
            . ' · with tech pack (SMV): ' . M\Style::whereNotNull('smv')->count());
        $this->line('Sizes: ' . M\Size::displaySort(M\Size::query()->where('is_active', true)->get())->pluck('name')->implode(' '));
        $this->line('Currencies: ' . M\Currency::query()->get()->map(fn ($c) => $c->code . '=' . (float) $c->exchange_rate)->implode(' ') . ' (rate to BDT)');
    }

    private function orders(ProductionFlow $flow): void
    {
        $this->head('Orders + PO lines');
        $orders = M\Order::query()->with(['buyer', 'pos.style', 'pos.color', 'pos.sizes.size', 'pos.shipMode'])->orderBy('id')->get();
        if ($orders->isEmpty()) {
            $this->line('(none)');
        }
        foreach ($orders as $o) {
            $this->line("{$o->order_no} [{$o->status}] {$o->buyer?->name} · {$o->order_date?->format('d-M-y')} · ref " . ($o->buyer_order_ref ?: '-')
                . " · qty {$o->total_qty} · value " . number_format((float) $o->total_value, 2));
            foreach ($o->pos as $p) {
                $flags = collect(['needs_embroidery' => 'Emb', 'needs_washing' => 'Wash'])->filter(fn ($l, $f) => $p->{$f})->implode('+');
                $this->line("   PO {$p->po_no} · {$p->style?->style_no} · {$p->color?->name} · "
                    . M\Size::displaySort($p->sizes->pluck('size')->filter())->map(fn ($size) => $size->name . ' ' . $p->sizes->firstWhere('size_id', $size->id)->qty)->implode(', ')
                    . " = {$p->po_qty} @ " . (float) $p->unit_price . " · PCD {$p->pcd_date?->format('d-M')} · ship {$p->shipment_date?->format('d-M-y')}"
                    . ($flags ? " · {$flags}" : '') . " · id {$p->id}");
            }
        }
    }

    private function dev(): void
    {
        $this->head('Dev: inquiries, samples, cost sheets, BOMs, bulletins');
        $this->line('Inquiries: ' . M\Inquiry::count() . ' · Samples: ' . M\Sample::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->map(fn ($c, $s) => "$s $c")->implode(', '));
        foreach (M\CostSheet::with(['style', 'buyer'])->orderBy('id')->get() as $c) {
            $this->line("Cost sheet {$c->cost_sheet_no} [{$c->status}] {$c->buyer?->name} · " . ($c->style->style_no ?? $c->style_ref) . ' · FOB/pc ' . (float) $c->offer_price . ' · final ' . ($c->final_price !== null ? (float) $c->final_price : '-'));
        }
        foreach (M\Bom::with(['style', 'order'])->withCount('items')->orderBy('id')->get() as $b) {
            $this->line("BOM {$b->bom_no} v{$b->version} [{$b->status}] {$b->style?->style_no} · " . ($b->order->order_no ?? 'style BOM') . " · {$b->items_count} lines");
        }
        foreach (M\Bulletin::with(['style', 'line.floorLine'])->withCount('operations')->orderBy('id')->get() as $b) {
            $this->line("Bulletin {$b->bulletin_no} v{$b->version} [{$b->status}] {$b->style?->style_no} · SMV {$b->total_smv} · {$b->target_per_hour}/hr × {$b->working_hours}h · MP {$b->operators}+{$b->helpers} · util {$b->utilization_percent}% · {$b->operations_count} ops · ref line " . ($b->line->name ?? '-'));
        }
    }

    private function planning(ProductionFlow $flow, TnaPlanner $planner): void
    {
        $this->head('Planning: lines, T&A, daily targets');
        foreach (M\Line::with('floorLine')->get()->sortBy('name') as $l) {
            $this->line("Line {$l->name} [" . ($l->is_active ? 'active' : 'inactive') . "] operators {$l->operators} + helpers {$l->helpers} · {$l->working_minutes} min · eff {$l->efficiency_percent}% · id {$l->id}");
        }
        $hrFree = M\FloorLine::query()->whereNotIn('id', M\Line::withTrashed()->pluck('hr_floor_line_id'))->get()->map(fn ($f) => $f->label)->implode(', ');
        $this->line('HR floor-lines not set up in v2: ' . ($hrFree ?: '-'));

        foreach (M\TnaPlan::with(['order', 'style', 'lines.floorLine', 'tasks'])->orderBy('id')->get() as $t) {
            $late = $t->tasks->whereNull('actual_date')->where('is_na', false)->filter(fn ($k) => $k->dueDate()?->lt(today()));
            $this->line("T&A {$t->tna_no} [{$t->status}] {$t->order?->order_no} · {$t->style?->style_no} · qty {$t->order_qty} · SMV {$t->smv} · lines " . $t->lines->map(fn ($l) => $l->name . ' ' . $l->pivot->daily_capacity . '/day')->implode(', ')
                . " · PCD {$t->pcd_date?->format('d-M')} · sew {$t->sewing_start_date?->format('d-M')}→{$t->sewing_end_date?->format('d-M')} · ship {$t->shipment_date?->format('d-M-y')}"
                . ' · done ' . $t->tasks->whereNotNull('actual_date')->count() . '/' . $t->tasks->where('is_na', false)->count()
                . ($late->isNotEmpty() ? ' · LATE: ' . $late->map(fn ($k) => $k->task_name . ' (' . $k->dueDate()->format('d-M') . ')')->implode(', ') : ''));
        }
        $dt = M\DailyTarget::query()->active()->where('target_date', '>=', today()->subDays(7))->orderBy('target_date')->get();
        $this->line('Daily targets (last 7 days →): ' . ($dt->isEmpty() ? '-' : $dt->map(fn ($d) => $d->target_date->format('d-M') . " cut {$d->cutting_target} poly {$d->packing_target} value/line " . (float) $d->line_required_value)->implode(' | ')));
    }

    private function production(ProductionFlow $flow): void
    {
        $this->head('Production per confirmed PO (input / pass / WIP per stage)');
        $pos = M\OrderPo::with(['order', 'style', 'color'])->whereHas('order', fn ($q) => $q->where('status', 'confirmed'))->orderBy('id')->get();
        if ($pos->isEmpty()) {
            $this->line('(no confirmed PO)');
        }
        foreach ($pos as $p) {
            $s = $flow->summary($p);
            $this->line("{$p->order->order_no} PO {$p->po_no} · {$p->style?->style_no} · {$p->color?->name} · qty {$p->po_qty}");
            $this->line('   ' . collect($s)->map(fn ($r, $stage) => ProductionFlow::label($stage) . " {$r['input']}/{$r['pass']}" . ($r['wip'] ? " wip {$r['wip']}" : '') . ($r['reject'] ? " rej {$r['reject']}" : ''))->implode(' → ')
                . ' · cutting balance ' . $flow->cuttingBalance($s));
            $lines = M\Production\Entry::query()->where('order_po_id', $p->id)->where('stage', 'sewing')->whereNotNull('line_id')
                ->selectRaw('line_id, SUM(input_qty) i, SUM(pass_qty) o, MAX(entry_date) last')->groupBy('line_id')->get();
            if ($lines->isNotEmpty()) {
                $this->line('   sewing by line: ' . $lines->map(fn ($r) => (M\Line::with('floorLine')->find($r->line_id)?->name) . " in {$r->i} out {$r->o} (last {$r->last})")->implode('; '));
            }
        }
        $this->line('Entries today: ' . M\Production\Entry::query()->whereDate('entry_date', today())->selectRaw('stage, SUM(' . ProductionFlow::NET_PASS_SQL . ') p')->groupBy('stage')->pluck('p', 'stage')->map(fn ($p, $st) => "$st $p")->implode(', ')
            . ' · cut today ' . (int) M\Production\Cutting::query()->whereDate('cutting_date', today())->sum('total_qty'));
    }

    private function inventory(): void
    {
        $this->head('Inventory links (Buyer Store GRN, requisitions, issues, Finish Store, shipments)');
        if (! Schema::hasTable('inv_grns')) {
            $this->line('(Inventory not installed)');

            return;
        }
        $styleIds = M\OrderPo::query()->pluck('style_id')->unique();
        foreach (M\Style::whereIn('id', $styleIds)->get() as $st) {
            $grn = DB::table('inv_grns')->whereNull('deleted_at')->where('status', 'posted')->where(fn ($q) => $q->where('msfl_style_id', $st->id)->orWhere('style', $st->style_no))->count();
            $this->line("Style {$st->style_no}: posted GRNs {$grn}");
        }
        $this->line('Fabric requisitions (v2): ' . M\Production\FabricRequisition::count()
            . ' · Issues against a PO: ' . DB::table('inv_issues')->whereNull('deleted_at')->whereNotNull('msfl_order_po_id')->count()
            . ' · FG receives against a PO: ' . DB::table('inv_finished_goods_receives')->whereNull('deleted_at')->whereNotNull('msfl_order_po_id')->count()
            . ' · Shipment lines with a PO: ' . (Schema::hasColumn('inv_shipment_items', 'msfl_order_po_id') ? DB::table('inv_shipment_items')->whereNotNull('msfl_order_po_id')->count() : 'n/a'));
        $pending = DB::table('approvals')->where('status', 'pending')->where('approvable_type', 'like', 'ME\\\\MerchandisingSfl%')->count();
        $this->line('Pending v2 approvals (buyers / samples): ' . $pending);
    }

    private function commercial(): void
    {
        $this->head('Commercial: Export LC / SC, commercial invoices');
        if (! Schema::hasTable('msfl_com_export_lcs')) {
            $this->line('(not migrated)');

            return;
        }
        $status = app(\ME\MerchandisingSfl\Services\Commercial\LcStatus::class);
        $lcs = M\Commercial\ExportLc::with(['buyer', 'currency', 'pos'])->orderBy('id')->get();
        if ($lcs->isEmpty()) {
            $this->line('(no LC)');
        }
        foreach ($lcs as $lc) {
            $f = $status->figures($lc);
            $this->line("{$lc->lc_no} [{$lc->status}] " . strtoupper($lc->type) . " {$lc->buyer_lc_no} · {$lc->buyer?->name} · " . ($lc->currency->code ?? '') . ' ' . number_format((float) $lc->lc_value, 2)
                . " · POs {$lc->pos->count()} (value " . number_format($f['po_value'], 2) . ') · shipped ' . number_format($f['shipped'], 2) . ' · balance ' . number_format($f['balance'], 2)
                . " · last ship {$lc->last_shipment_date?->format('d-M-y')} · expiry {$lc->expiry_date?->format('d-M-y')}" . ($f['expiry_days'] !== null ? " ({$f['expiry_days']} d)" : ''));
        }
        foreach (M\Commercial\Invoice::with(['exportLc', 'lines.orderPo'])->orderBy('id')->get() as $i) {
            $this->line("   {$i->invoice_no} {$i->invoice_date?->format('d-M-y')} · {$i->exportLc?->lc_no} · " . $i->lines->map(fn ($l) => ($l->orderPo->po_no ?? '') . ' ' . $l->qty)->implode(', ')
                . " · qty {$i->total_qty} · value " . number_format((float) $i->total_value, 2) . ' · B/L ' . ($i->bl_no ?: '-'));
        }
    }
}
