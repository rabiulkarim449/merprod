@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Commercial Dashboard') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="row">
        @foreach([
            ['Active LC / SC', number_format($totals['lcs']), 'fa-file-contract', $totals['drafts'] ? $totals['drafts'] . ' draft' : null],
            ['LC Value (active)', number_format($totals['value'], 2), 'fa-sack-dollar', null],
            ['Shipped', number_format($totals['shipped'], 2), 'fa-ship', null],
            ['Balance to ship', number_format($totals['balance'], 2), 'fa-scale-balanced', null],
            ['Invoices this month', number_format($totals['month_invoices']), 'fa-file-invoice-dollar', number_format($totals['month_value'], 2)],
        ] as [$label, $value, $icon, $sub])
            <div class="col-md mb-3">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="small text-muted"><i class="fa-solid {{ $icon }}"></i> {{ $label }}</div>
                    <div class="h4 mb-0">{{ $value }}</div>
                    @if($sub)<div class="small text-muted">{{ $sub }}</div>@endif
                </div></div>
            </div>
        @endforeach
    </div>
    <p class="small text-muted mt-n2">Values are in each LC's own currency, added together.</p>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">LC / SC expiring in 30 days (or expired)</h5>
            <a href="{{ route('msfl.reports.show', 'lc-status') }}" class="btn btn-sm ui-act ui-act-view" title="LC Status report"><i class="fa-solid fa-eye"></i> LC Status report</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead><tr><th>LC No</th><th>Buyer</th><th class="text-right">LC Value</th><th class="text-right">Shipped</th><th class="text-right">Balance</th><th>Last Ship</th><th>Expiry</th><th class="text-right">Days</th></tr></thead>
                    <tbody>
                        @forelse($expiring as $r)
                            <tr class="{{ $r['expiry_days'] < 0 ? 'table-danger' : 'table-warning' }}">
                                <td><a href="{{ route('msfl.commercial.export-lcs.show', $r['lc']) }}">{{ $r['lc']->lc_no }}</a> <small class="text-muted">{{ $r['lc']->buyer_lc_no }}</small></td>
                                <td>{{ $r['lc']->buyer->name ?? '-' }}</td>
                                <td class="text-right">{{ $r['lc']->currency->code ?? '' }} {{ number_format((float) $r['lc']->lc_value, 2) }}</td>
                                <td class="text-right">{{ number_format($r['shipped'], 2) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($r['balance'], 2) }}</td>
                                <td>{{ $r['lc']->last_shipment_date?->format('d M Y') ?? '-' }}</td>
                                <td>{{ $r['lc']->expiry_date?->format('d M Y') ?? '-' }}</td>
                                <td class="text-right">{{ $r['expiry_days'] < 0 ? abs($r['expiry_days']) . ' expired' : $r['expiry_days'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No active LC expires in the next 30 days.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Latest commercial invoices</h5>
            <a href="{{ route('msfl.commercial.invoices.index') }}" class="btn btn-sm ui-act ui-act-nav" title="All invoices">All invoices</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Buyer</th><th>LC</th><th class="text-right">Qty</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                        @forelse($recent as $inv)
                            <tr>
                                <td><a href="{{ route('msfl.commercial.invoices.show', $inv) }}">{{ $inv->invoice_no }}</a></td>
                                <td>{{ $inv->invoice_date->format('d M Y') }}</td>
                                <td>{{ $inv->buyer->name ?? '-' }}</td>
                                <td>{{ $inv->exportLc->lc_no ?? '-' }}</td>
                                <td class="text-right">{{ number_format($inv->total_qty) }}</td>
                                <td class="text-right">{{ number_format((float) $inv->total_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No invoice yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
