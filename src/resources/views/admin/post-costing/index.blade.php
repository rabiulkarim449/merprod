@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Post Cost Sheet' }}@else
    <title>{{ websiteTitle('Post Cost Sheet') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Post Cost Sheet', 'printPage' => 'A4 landscape'])
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Post Cost Sheet <small class="text-muted">— budget vs actual per PO</small></h4></div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="PO / Style" value="{{ request('search') }}"></div>
                <div class="col-md-3 mb-2"><select name="buyer_id" class="form-control form-control-sm msfl-select2"><option value="">All Buyers</option>
                    @foreach($buyers as $b)<option value="{{ $b->id }}" @selected(request('buyer_id') == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.post-costing.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-nowrap">
                    <thead><tr><th>Order · PO</th><th>Buyer</th><th>Style</th><th class="text-right">Order</th><th class="text-right">Packed</th>
                        <th class="text-right">Material budget</th><th class="text-right">Material actual</th><th class="text-right">CM budget</th><th class="text-right">CM earned</th><th class="text-right">Reject loss</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($rows as $c)
                            @php $b = $c['budget']; $a = $c['actual']; @endphp
                            <tr>
                                <td>{{ $c['po']->order->order_no }} · {{ $c['po']->po_no }}</td><td>{{ $c['po']->order->buyer->name ?? '-' }}</td><td>{{ $c['po']->style->style_no ?? '-' }}</td>
                                <td class="text-right">{{ number_format($c['qty']['order']) }}</td><td class="text-right">{{ number_format($c['qty']['packed']) }}</td>
                                <td class="text-right">{{ $b ? number_format($b['fabric'] + $b['trims'], 2) : '-' }}</td>
                                <td class="text-right {{ $b && $a['material'] > $b['fabric'] + $b['trims'] ? 'text-danger' : '' }}">{{ number_format($a['material'], 2) }}@unless($a['has_rates'])<small class="text-muted" title="Estimate — no store value issued yet"> *</small>@endunless</td>
                                <td class="text-right">{{ $b ? number_format($b['cm'], 2) : '-' }}</td>
                                <td class="text-right {{ $b && $a['cm_earned'] < $b['cm'] ? 'text-danger' : 'text-success' }}">{{ number_format($a['cm_earned'], 2) }}</td>
                                <td class="text-right">{{ number_format($a['reject_loss'], 2) }}</td>
                                <td class="text-right"><a href="{{ route('msfl.post-costing.show', $c['po']) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted">No confirmed order PO yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <small class="text-muted">* estimate (budget material per piece × packed) — nothing with a store value issued against the PO yet.</small>
            {{ $pos->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
