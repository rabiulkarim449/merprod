@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Export LC / Sales Contract' }}@else
    <title>{{ websiteTitle('Export LC / Sales Contract') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Export LC / Sales Contract', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Export LC / Sales Contract</h4>
            @can('msfl_export_lc.add')
                <a href="{{ route('msfl.commercial.export-lcs.create') }}" class="btn btn-sm ui-act ui-act-create" title="New LC / SC"><i class="fa-solid fa-plus"></i> New LC / SC</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="LC no / buyer's LC no" value="{{ request('search') }}"></div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $buyer)<option value="{{ $buyer->id }}" @selected(request('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Commercial\ExportLc::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.commercial.export-lcs.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>LC No</th><th>Type</th><th>Buyer's No</th><th>Buyer</th><th>Date</th><th class="text-right">LC Value</th><th class="text-right">Shipped</th><th class="text-right">Balance</th><th class="text-right">POs</th><th>Last Ship</th><th>Expiry</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($lcs as $lc)
                            @php $f = $figures[$lc->id]; $cur = $lc->currency->code ?? ''; @endphp
                            <tr class="{{ $lc->status === 'active' && $f['expiry_days'] !== null && $f['expiry_days'] < 0 ? 'table-danger' : ($lc->status === 'active' && $f['expiry_days'] !== null && $f['expiry_days'] <= 30 ? 'table-warning' : '') }}">
                                <td>{{ $loop->iteration + $lcs->firstItem() - 1 }}</td>
                                <td><a href="{{ route('msfl.commercial.export-lcs.show', $lc) }}">{{ $lc->lc_no }}</a></td>
                                <td>{{ strtoupper($lc->type) }}</td>
                                <td>{{ $lc->buyer_lc_no }}</td>
                                <td>{{ $lc->buyer->name ?? '-' }}</td>
                                <td class="text-nowrap">{{ $lc->lc_date->format('d M Y') }}</td>
                                <td class="text-right text-nowrap">{{ $cur }} {{ number_format((float) $lc->lc_value, 2) }}</td>
                                <td class="text-right">{{ number_format($f['shipped'], 2) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($f['balance'], 2) }}</td>
                                <td class="text-right">{{ $lc->pos_count }}</td>
                                <td class="text-nowrap">{{ $lc->last_shipment_date?->format('d M Y') ?? '-' }}</td>
                                <td class="text-nowrap">{{ $lc->expiry_date?->format('d M Y') ?? '-' }}
                                    @if($lc->status === 'active' && $f['expiry_days'] !== null)<br><small class="{{ $f['expiry_days'] < 0 ? 'text-danger' : 'text-muted' }}">{{ $f['expiry_days'] < 0 ? abs($f['expiry_days']) . ' d expired' : $f['expiry_days'] . ' d left' }}</small>@endif
                                </td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $lc])</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('msfl.commercial.export-lcs.show', $lc) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @if($lc->isEditable())
                                        @can('msfl_export_lc.edit')<a href="{{ route('msfl.commercial.export-lcs.edit', $lc) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>@endcan
                                    @endif
                                    @if($lc->status === 'draft')
                                        @can('msfl_export_lc.delete')<button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteLcModal" data-action="{{ route('msfl.commercial.export-lcs.destroy', $lc) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="text-center text-muted">No Export LC yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($printMode){{ $lcs->links('pagination::bootstrap-5') }}@endunless
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteLcModal', 'label' => 'Export LC'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
