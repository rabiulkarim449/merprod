@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Inquiries' }}@else
    <title>{{ websiteTitle('Inquiries') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Inquiries', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Inquiries</h4>
            @can('msfl_inquiry.add')
                <a href="{{ route('msfl.inquiries.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Inquiry"><i class="fa-solid fa-plus"></i> New Inquiry</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Inquiry No / Style Ref" value="{{ request('search') }}">
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
                        @foreach(\ME\MerchandisingSfl\Models\Inquiry::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.inquiries.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th><th>Inquiry No</th><th>Date</th><th>Buyer</th><th>Season</th><th>Style Ref</th>
                            <th>Qty</th><th>Unit Price</th><th>Total Value</th><th>Ship Date</th><th>Merchandiser</th><th>Status</th><th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inquiries as $inquiry)
                            <tr>
                                <td>{{ $loop->iteration + $inquiries->firstItem() - 1 }}</td>
                                <td>{{ $inquiry->inquiry_no }}</td>
                                <td>{{ $inquiry->inquiry_date->format('d M Y') }}</td>
                                <td>{{ $inquiry->buyer->name ?? '-' }}</td>
                                <td>{{ $inquiry->season->name ?? '-' }}</td>
                                <td>{{ $inquiry->style_ref ?? '-' }}</td>
                                <td>{{ $inquiry->order_qty !== null ? number_format($inquiry->order_qty) : '-' }}</td>
                                <td>{{ $inquiry->unit_price !== null ? number_format($inquiry->unit_price, 2) : '-' }}</td>
                                <td>{{ $inquiry->total_value !== null ? number_format($inquiry->total_value, 2) : '-' }}</td>
                                <td>{{ optional($inquiry->extended_ship_date ?? $inquiry->target_ship_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ $inquiry->merchandiser->name ?? '-' }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $inquiry])</td>
                                <td class="text-right">
                                    @can('msfl_inquiry.view')
                                        <a href="{{ route('msfl.inquiries.show', $inquiry) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @can('msfl_inquiry.edit')
                                        <a href="{{ route('msfl.inquiries.edit', $inquiry) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    @endcan
                                    @can('msfl_inquiry.delete')
                                        <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteInquiryModal" data-action="{{ route('msfl.inquiries.destroy', $inquiry) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="text-center text-muted">No inquiries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $inquiries->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteInquiryModal', 'label' => 'inquiry'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
