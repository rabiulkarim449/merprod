@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Commercial Invoices' }}@else
    <title>{{ websiteTitle('Commercial Invoices') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Commercial Invoices', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Commercial Invoices</h4>
            @can('msfl_com_invoice.add')
                <a href="{{ route('msfl.commercial.invoices.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Invoice"><i class="fa-solid fa-plus"></i> New Invoice</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-2 mb-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice / EXP / B/L" value="{{ request('search') }}"></div>
                <div class="col-md-2 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $b)<option value="{{ $b->id }}" @selected(request('buyer_id') == $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select name="export_lc_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All LCs</option>
                        @foreach($lcs as $l)<option value="{{ $l->id }}" @selected(request('export_lc_id') == $l->id)>{{ $l->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" title="From"></div>
                <div class="col-md-2 mb-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" title="To"></div>
                <div class="col-md-2 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.commercial.invoices.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>#</th><th>Invoice</th><th>Date</th><th>Buyer</th><th>LC / SC</th><th>EXP No</th><th>B/L / AWB</th><th>Ship Mode</th><th class="text-right">Qty</th><th class="text-right">Cartons</th><th class="text-right">Value</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($invoices as $inv)
                            <tr>
                                <td>{{ $loop->iteration + $invoices->firstItem() - 1 }}</td>
                                <td><a href="{{ route('msfl.commercial.invoices.show', $inv) }}">{{ $inv->invoice_no }}</a></td>
                                <td class="text-nowrap">{{ $inv->invoice_date->format('d M Y') }}</td>
                                <td>{{ $inv->buyer->name ?? '-' }}</td>
                                <td>{{ $inv->exportLc->lc_no ?? '-' }}<br><small class="text-muted">{{ $inv->exportLc->buyer_lc_no ?? '' }}</small></td>
                                <td>{{ $inv->exp_no ?? '-' }}</td>
                                <td>{{ $inv->bl_no ?? '-' }}</td>
                                <td>{{ $inv->shipMode->name ?? '-' }}</td>
                                <td class="text-right">{{ number_format($inv->total_qty) }}</td>
                                <td class="text-right">{{ number_format($inv->total_cartons) }}</td>
                                <td class="text-right text-nowrap">{{ $inv->exportLc->currency->code ?? '' }} {{ number_format((float) $inv->total_value, 2) }}</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('msfl.commercial.invoices.show', $inv) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @can('msfl_com_invoice.edit')<a href="{{ route('msfl.commercial.invoices.edit', $inv) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>@endcan
                                    @can('msfl_com_invoice.delete')<button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteInvoiceModal" data-action="{{ route('msfl.commercial.invoices.destroy', $inv) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>@endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-muted">No commercial invoice yet.</td></tr>
                        @endforelse
                    </tbody>
                    @if($invoices->isNotEmpty())
                        <tfoot><tr class="font-weight-bold"><td colspan="8" class="text-right">Total (this page)</td><td class="text-right">{{ number_format($invoices->sum('total_qty')) }}</td><td class="text-right">{{ number_format($invoices->sum('total_cartons')) }}</td><td class="text-right">{{ number_format($invoices->sum('total_value'), 2) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            </div>
            @unless($printMode){{ $invoices->links('pagination::bootstrap-5') }}@endunless
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteInvoiceModal', 'label' => 'commercial invoice'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
