{{-- Merchandising v2 overview — used on its own Dashboard page and on the home page. props: full (bool) --}}
@php
    try { $mv2 = \ME\MerchandisingSfl\Services\DashboardStats::get(); } catch (\Throwable $e) { report($e); $mv2 = null; }
    $full = $full ?? false;
@endphp
@if($mv2)
<style>
    .mv2-card { background: #fff; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 8px rgba(0,0,0,.06); height: 100%; display: flex; gap: 12px; align-items: center; }
    .mv2-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .mv2-val { font-size: 20px; font-weight: 700; line-height: 1.1; }
    .mv2-lbl { font-size: 11px; color: #777; text-transform: uppercase; letter-spacing: .4px; }
    .mv2-box { background: #fff; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 8px rgba(0,0,0,.06); height: 100%; }
    .mv2-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; border-left: 3px solid #dc3545; padding-left: 8px; margin-bottom: 10px; }
    .mv2-bars { display: flex; align-items: stretch; gap: 4px; height: 110px; border-bottom: 1px solid #e5e7eb; }
    .mv2-bars .day { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; gap: 1px; align-items: stretch; }
    .mv2-bars .s { background: #60a5fa; } .mv2-bars .p { background: #34d399; }
    .mv2-progress { height: 6px; background: #eef0f3; border-radius: 3px; overflow: hidden; } .mv2-progress > div { height: 100%; background: #22c55e; }
</style>

<div class="d-flex align-items-center justify-content-between mb-2 mt-1">
    <h5 class="mb-0"><i class="fa-solid fa-shirt text-danger mr-2"></i>Merchandising &amp; Production</h5>
    @unless($full)<a href="{{ route('msfl.dashboard') }}" class="btn btn-sm ui-act ui-act-nav" title="Open dashboard"><i class="fa-solid fa-gauge"></i> Open dashboard</a>@endunless
</div>

<div class="row">
    @foreach([
        ['Running Orders', $mv2['running_orders'], 'fa-file-signature', '#e0f2fe', '#0369a1', route('msfl.orders.index')],
        ['Order Qty (pcs)', number_format($mv2['running_qty']), 'fa-shirt', '#ede9fe', '#6d28d9', route('msfl.production.status.index')],
        ['Order Value', number_format($mv2['running_value'], 0), 'fa-sack-dollar', '#dcfce7', '#15803d', route('msfl.orders.index')],
        ['Packed Today', number_format($mv2['packed_today']), 'fa-box', '#fef9c3', '#a16207', route('msfl.production.entries.index', ['stage' => 'packing'])],
        ['Open Inquiries', $mv2['open_inquiries'], 'fa-magnifying-glass-dollar', '#f1f5f9', '#334155', route('msfl.inquiries.index')],
        ['Samples Pending', $mv2['samples_pending'] . ($mv2['samples_overdue'] ? ' (' . $mv2['samples_overdue'] . ' late)' : ''), 'fa-vial', '#fce7f3', '#be185d', route('msfl.samples.index')],
        ['T&A Overdue Tasks', $mv2['overdue_tasks'], 'fa-calendar-xmark', '#fee2e2', '#b91c1c', route('msfl.tna-sheet.index')],
    ] as [$label, $value, $icon, $bg, $fg, $url])
        <div class="col-xl-3 col-md-4 col-sm-6 mb-3">
            <a href="{{ $url }}" class="text-reset text-decoration-none"><div class="mv2-card">
                <div class="mv2-icon" style="background:{{ $bg }};color:{{ $fg }}"><i class="fa-solid {{ $icon }}"></i></div>
                <div><div class="mv2-val">{{ $value }}</div><div class="mv2-lbl">{{ $label }}</div></div>
            </div></a>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-lg-6 mb-3"><div class="mv2-box">
        <div class="mv2-title">Production by Stage</div>
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>Stage</th><th class="text-right">Today (pass)</th><th class="text-right">WIP</th><th class="text-right">Reject % (30d)</th></tr></thead>
            <tbody>
                @foreach($mv2['production'] as $stage => $p)
                    <tr><td>{{ $p['label'] }}</td><td class="text-right">{{ number_format($p['today']) }}</td>
                        <td class="text-right">{{ $p['wip'] === null ? '-' : number_format($p['wip']) }}</td>
                        <td class="text-right {{ ($p['reject_pct'] ?? 0) > 3 ? 'text-danger font-weight-bold' : '' }}">{{ $p['reject_pct'] === null ? '-' : $p['reject_pct'] . '%' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div></div>
    <div class="col-lg-6 mb-3"><div class="mv2-box">
        <div class="mv2-title">Output — last 14 days <small class="text-muted text-lowercase">(<span style="color:#60a5fa">■</span> sewing <span style="color:#34d399">■</span> packed)</small></div>
        @php $max = max(1, collect($mv2['trend'])->map(fn ($d) => max($d['sewing'], $d['packing']))->max()); @endphp
        <div class="mv2-bars">
            @foreach($mv2['trend'] as $date => $d)
                <div class="day" title="{{ \Illuminate\Support\Carbon::parse($date)->format('d M') }} — sewing {{ $d['sewing'] }}, packed {{ $d['packing'] }}">
                    <div style="display:flex;gap:1px;align-items:flex-end;height:100%">
                        <div class="s" style="flex:1;height:{{ round($d['sewing'] / $max * 100) }}%"></div>
                        <div class="p" style="flex:1;height:{{ round($d['packing'] / $max * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1"><span>{{ \Illuminate\Support\Carbon::parse(array_key_first($mv2['trend']))->format('d M') }}</span><span>Today</span></div>
    </div></div>
</div>

<div class="row">
    <div class="col-lg-{{ $full ? 6 : 12 }} mb-3"><div class="mv2-box">
        <div class="mv2-title">Shipments due (next 30 days)</div>
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>Ship Date</th><th>Order · PO</th><th>Buyer</th><th>Style</th><th class="text-right">Qty</th><th style="width:28%">Packed</th></tr></thead>
            <tbody>
                @forelse($mv2['shipments'] as $s)
                    <tr class="{{ $s['days'] < 0 && $s['pct'] < 100 ? 'table-danger' : ($s['days'] <= 7 && $s['pct'] < 100 ? 'table-warning' : '') }}">
                        <td>{{ $s['po']->shipment_date->format('d-M') }} <small class="text-muted">({{ $s['days'] >= 0 ? $s['days'] . 'd' : abs($s['days']) . 'd late' }})</small></td>
                        <td><a href="{{ route('msfl.production.status.show', $s['po']) }}">{{ $s['po']->order->order_no ?? '' }} · {{ $s['po']->po_no }}</a></td>
                        <td>{{ $s['po']->order->buyer->name ?? '-' }}</td><td>{{ $s['po']->style->style_no ?? '-' }}</td>
                        <td class="text-right">{{ number_format($s['po']->po_qty) }}</td>
                        <td><div class="mv2-progress"><div style="width:{{ $s['pct'] }}%"></div></div><small>{{ number_format($s['packed']) }} ({{ $s['pct'] }}%)</small></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No shipment due.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    @if($full)
        <div class="col-lg-6 mb-3"><div class="mv2-box">
            <div class="mv2-title">Overdue T&amp;A Tasks</div>
            <table class="table table-sm table-bordered mb-3">
                <thead><tr><th>T&amp;A</th><th>Style</th><th>Task</th><th>Due</th><th>Responsible</th></tr></thead>
                <tbody>
                    @forelse($mv2['overdue'] as $t)
                        <tr><td><a href="{{ route('msfl.tna.show', $t->tna_plan_id) }}">{{ $t->plan->tna_no ?? '' }}</a></td><td>{{ $t->plan->style->style_no ?? '' }}</td>
                            <td>{{ $t->task_name }}</td><td class="text-danger">{{ ($t->revised_date ?? $t->plan_date)?->format('d-M') }}</td><td>{{ $t->responsible->name ?? '-' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Nothing overdue.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mv2-title">Top Defects (30 days)</div>
            <table class="table table-sm table-bordered mb-0">
                <thead><tr><th>Stage</th><th>Part</th><th>Type</th><th class="text-right">Pcs</th></tr></thead>
                <tbody>
                    @forelse($mv2['defects'] as $d)
                        <tr><td>{{ \ME\MerchandisingSfl\Services\ProductionFlow::label($d->stage) }}</td><td>{{ $d->part }}</td><td>{{ ucfirst($d->type) }}</td><td class="text-right">{{ $d->qty }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No defects recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    @endif
</div>
@endif
