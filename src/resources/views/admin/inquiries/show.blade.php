@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Inquiry ' . $inquiry->inquiry_no }}@else
    <title>{{ websiteTitle('Inquiry ' . $inquiry->inquiry_no) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Inquiry — ' . $inquiry->inquiry_no, 'printPage' => 'A4 portrait'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Inquiry — {{ $inquiry->inquiry_no }}</h4>
            <div>
                @can('msfl_style.add')
                    <a href="{{ route('msfl.styles.create', ['inquiry_id' => $inquiry->id]) }}" class="btn btn-sm ui-act ui-act-create" title="Create Tech Pack"><i class="fa-solid fa-plus"></i> Create Tech Pack</a>
                @endcan
                @can('msfl_cost_sheet.add')
                    <a href="{{ route('msfl.cost-sheets.create', ['inquiry_id' => $inquiry->id]) }}" class="btn btn-sm ui-act ui-act-create" title="Create Cost Sheet"><i class="fa-solid fa-plus"></i> Create Cost Sheet</a>
                @endcan
                @can('msfl_inquiry.edit')
                    <a href="{{ route('msfl.inquiries.edit', $inquiry) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                @endcan
                @include('merchandising-sfl::admin.partials.print-button')
                <a href="{{ route('msfl.inquiries.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><strong>Inquiry Date:</strong> {{ $inquiry->inquiry_date->format('d M Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $inquiry->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Season:</strong> {{ $inquiry->season->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Status:</strong> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $inquiry])</div>
                <div class="col-md-3 mb-2"><strong>Style Ref:</strong> {{ $inquiry->style_ref ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Product Type:</strong> {{ $inquiry->productType->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Color(s):</strong> {{ $inquiry->color_ref ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Merchandiser:</strong> {{ $inquiry->merchandiser->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Order Qty:</strong> {{ $inquiry->order_qty !== null ? number_format($inquiry->order_qty) : '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Unit Price:</strong> {{ $inquiry->unit_price !== null ? number_format($inquiry->unit_price, 4) : '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Total Value:</strong> {{ $inquiry->total_value !== null ? number_format($inquiry->total_value, 2) : '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Allocated Factory:</strong> {{ $inquiry->factory->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Confirmation Due:</strong> {{ optional($inquiry->confirmation_due_date)->format('d M Y') ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Target Ship Date:</strong> {{ optional($inquiry->target_ship_date)->format('d M Y') ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Extended Ship Date:</strong> {{ optional($inquiry->extended_ship_date)->format('d M Y') ?? '-' }}</div>
                @if($inquiry->status === 'lost')
                    <div class="col-md-3 mb-2"><strong>Lost Reason:</strong> {{ $inquiry->lost_reason }}</div>
                @endif
                @if($inquiry->description)
                    <div class="col-12 mb-2"><strong>Description:</strong> {!! nl2br(e($inquiry->description)) !!}</div>
                @endif
                @if($inquiry->remarks)
                    <div class="col-12 mb-2"><strong>Remarks:</strong> {!! nl2br(e($inquiry->remarks)) !!}</div>
                @endif
            </div>

            <div class="row">
                <div class="col-md-6">
                    <h6>Tech Packs / Styles</h6>
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>Style No</th><th>Name</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($inquiry->styles as $style)
                                <tr>
                                    <td><a href="{{ route('msfl.styles.show', $style) }}">{{ $style->style_no }}</a></td>
                                    <td>{{ $style->name }}</td>
                                    <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $style])</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">No tech pack yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Cost Sheets</h6>
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>Cost Sheet No</th><th>Date</th><th>Offer Price / pc</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($inquiry->costSheets as $sheet)
                                <tr>
                                    <td><a href="{{ route('msfl.cost-sheets.show', $sheet) }}">{{ $sheet->cost_sheet_no }}</a></td>
                                    <td>{{ $sheet->costing_date->format('d M Y') }}</td>
                                    <td>{{ number_format($sheet->offer_price, 4) }}</td>
                                    <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $sheet])</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No cost sheet yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
