@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Final Packing List — ' . $fpl->fpl_no }}@else
    <title>{{ websiteTitle('Final Packing List ' . $fpl->fpl_no) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Final Packing List', 'printSubtitle' => $fpl->fpl_no, 'printPage' => 'A4 portrait'])

    @unless($printMode)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h4 class="mb-0">Final Packing List — {{ $fpl->fpl_no }} @include('merchandising-sfl::admin.partials.status-badge', ['model' => $fpl])</h4>
                <div>
                    @include('merchandising-sfl::admin.partials.print-button')
                    @if($fpl->isEditable())
                        @can('msfl_final_packing_list.edit')<a href="{{ route('msfl.production.final-packing-lists.edit', $fpl) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>@endcan
                    @else
                        @can('msfl_final_packing_list.approve')
                            <form method="POST" action="{{ route('msfl.production.final-packing-lists.reopen', $fpl) }}" class="d-inline" onsubmit="return confirm('Re-open? Figures become live from production again.');">
                                @csrf <button type="submit" class="btn btn-sm ui-act ui-act-unlock" title="Re-open"><i class="fa-solid fa-lock-open"></i> Re-open</button>
                            </form>
                        @endcan
                    @endif
                    <a href="{{ route('msfl.production.final-packing-lists.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
                </div>
            </div>
            <div class="card-body py-2">
                @if($fpl->isEditable())
                    <p class="small text-muted mb-2">Figures are live from production (cutting, packing, Buyer QC). Print it, get the buyer's approval, then record it here — the figures are frozen from then on.</p>
                    @can('msfl_final_packing_list.approve')
                        <form method="POST" action="{{ route('msfl.production.final-packing-lists.approve', $fpl) }}" enctype="multipart/form-data" class="row align-items-end">
                            @csrf
                            <div class="col-md-3 mb-2"><label class="form-label">Buyer approved on <span class="text-danger">*</span></label><input type="date" name="buyer_approved_on" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required></div>
                            <div class="col-md-3 mb-2"><label class="form-label">Approved by (buyer side)</label><input type="text" name="buyer_approved_by" class="form-control form-control-sm"></div>
                            <div class="col-md-3 mb-2"><label class="form-label">Approved copy</label><input type="file" name="approval_file" class="form-control form-control-sm"></div>
                            <div class="col-md-3 mb-2"><button type="submit" class="btn btn-sm ui-act ui-act-approve" onclick="return confirm('Mark as Buyer Approved?');" title="Buyer Approved"><i class="fa-solid fa-check"></i> Buyer Approved</button></div>
                        </form>
                    @endcan
                @else
                    <p class="small mb-0"><strong>Buyer approved</strong> on {{ $fpl->buyer_approved_on?->format('d M Y') }}{{ $fpl->buyer_approved_by ? ' by ' . $fpl->buyer_approved_by : '' }}
                        @if($fpl->approval_file) · <a href="{{ \ME\MerchandisingSfl\Services\FileUploadService::link($fpl->approval_file) }}" target="_blank">approved copy</a>@endif
                        — figures frozen.</p>
                @endif
                @if($fpl->remarks)<p class="small mb-0 mt-1"><strong>Remarks:</strong> {{ $fpl->remarks }}</p>@endif
            </div>
        </div>

        {{-- Next steps: Finish Store (Inventory) → Shipment + Delivery Challan (Inventory, Gate Pass) ← Commercial Invoice --}}
        @php
            $fgRoute = \Illuminate\Support\Facades\Route::has('inventory.fg-receives.create');
            $shipRoute = \Illuminate\Support\Facades\Route::has('inventory.shipments.index');
        @endphp
        <div class="card mb-3">
            <div class="card-header py-2"><h6 class="mb-0">After packing — Finish Store → Shipment → Delivery Challan · Commercial Invoice</h6></div>
            <div class="card-body py-2">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-1">
                        <thead><tr><th>Style · Color</th><th class="text-right">Packed</th><th class="text-right">In Finish Store</th><th class="text-right">Shipped</th><th class="text-right">Invoiced</th><th>Next</th></tr></thead>
                        <tbody>
                            @foreach($after as $a)
                                <tr>
                                    <td>{{ $a['po']->style->style_no ?? '' }} · {{ $a['po']->color->name ?? '' }}</td>
                                    <td class="text-right">{{ number_format($a['packed']) }}</td>
                                    <td class="text-right {{ $a['fg'] < $a['packed'] ? 'text-warning' : '' }}">{{ number_format($a['fg']) }}</td>
                                    <td class="text-right">{{ number_format($a['shipped']) }}</td>
                                    <td class="text-right">{{ number_format($a['invoiced']) }}</td>
                                    <td>
                                        @if($fgRoute && $a['fg'] < $a['packed'])
                                            @can('inv_fg_receive.add')<a href="{{ route('inventory.fg-receives.create', ['msfl_order_po_id' => $a['po']->id]) }}" class="btn btn-sm btn-icon ui-act ui-act-create" title="Add"><i class="fa-solid fa-plus"></i></a>@endcan
                                        @endif
                                        @if($a['invoiced'] < $a['packed'])
                                            @can('msfl_com_invoice.add')<a href="{{ route('msfl.commercial.invoices.create') }}" class="btn btn-sm btn-icon ui-act ui-act-create" title="Commercial Invoice"><i class="fa-solid fa-plus"></i></a>@endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0">Finish Store receive is in Inventory (capped by packed qty). Shipment: Inventory → Shipment — pick the Commercial Invoice there and it fills itself; then the Gate Pass prints the <strong>Delivery Challan</strong>.
                    @if($shipRoute) <a href="{{ route('inventory.shipments.index') }}">Open Shipments</a>@endif</p>
            </div>
        </div>
    @endunless

    <div class="{{ $printMode ? '' : 'card' }}"><div class="{{ $printMode ? '' : 'card-body' }}">
        <div class="{{ $printMode ? '' : 'table-responsive' }}"><div style="min-width: {{ $printMode ? 'auto' : '760px' }}">
            @include('merchandising-sfl::admin.production.final-packing-lists.partials.sheet', ['print' => $printMode])
        </div></div>
    </div></div>
</div>
@endsection
