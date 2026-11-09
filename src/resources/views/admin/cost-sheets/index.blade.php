@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Cost Sheets' }}@else
    <title>{{ websiteTitle('Cost Sheets') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Cost Sheets', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Costing (Pre-order)</h4>
            @can('msfl_cost_sheet.add')
                <a href="{{ route('msfl.cost-sheets.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Cost Sheet"><i class="fa-solid fa-plus"></i> New Cost Sheet</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search No / Style Ref" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $buyer)
                            <option value="{{ $buyer->id }}" @selected(request('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\CostSheet::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.cost-sheets.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Cost Sheet No</th><th>Date</th><th>Buyer</th><th>Style</th><th>Order Qty</th><th>Total Cost / dz</th><th>Offer / pc</th><th>Target / pc</th><th>Final / pc</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($costSheets as $sheet)
                            <tr>
                                <td>{{ $loop->iteration + $costSheets->firstItem() - 1 }}</td>
                                <td>{{ $sheet->cost_sheet_no }}</td>
                                <td>{{ $sheet->costing_date->format('d M Y') }}</td>
                                <td>{{ $sheet->buyer->name ?? '-' }}</td>
                                <td>{{ $sheet->style->style_no ?? $sheet->style_ref ?? '-' }}</td>
                                <td>{{ $sheet->order_qty !== null ? number_format($sheet->order_qty) : '-' }}</td>
                                <td>{{ number_format($sheet->total_cost, 2) }}</td>
                                <td>{{ ($sheet->currency->code ?? '') }} {{ number_format($sheet->offer_price, 4) }}</td>
                                <td>{{ $sheet->buyer_target_price !== null ? number_format($sheet->buyer_target_price, 4) : '-' }}</td>
                                <td>{{ $sheet->final_price !== null ? number_format($sheet->final_price, 4) : '-' }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $sheet])</td>
                                <td class="text-right">
                                    @can('msfl_cost_sheet.view')
                                        <a href="{{ route('msfl.cost-sheets.show', $sheet) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @if($sheet->isEditable())
                                        @can('msfl_cost_sheet.edit')
                                            <a href="{{ route('msfl.cost-sheets.edit', $sheet) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        @endcan
                                        @can('msfl_cost_sheet.delete')
                                            <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteCostSheetModal" data-action="{{ route('msfl.cost-sheets.destroy', $sheet) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-muted">No cost sheets found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $costSheets->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteCostSheetModal', 'label' => 'cost sheet'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
