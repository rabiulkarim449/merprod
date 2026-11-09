{{-- Pack date, remarks and QC's short / excess note per style. props: fpl, sheet --}}
<div class="row">
    @include('merchandising-sfl::admin.partials.input', ['name' => 'pack_date', 'label' => 'Packing List Date', 'type' => 'date', 'required' => true, 'value' => $fpl->pack_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'invoice_no', 'label' => 'Invoice No', 'value' => $fpl->invoice_no, 'attrs' => 'placeholder="' . e(($sheet['invoices'] ?? '') ?: 'until a Commercial Invoice has this PO') . '"'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6, 'value' => $fpl->remarks])
</div>
<h6>Short / Excess — QC note after final checking <small class="text-muted">(filled from Buyer QC rejects; type the reason, e.g. "01 pcs Marketing Sample Send")</small></h6>
<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
        <thead><tr><th>Style</th><th>Color</th><th class="text-right">Order</th><th class="text-right">Cut</th><th class="text-right">Ship (packed)</th><th class="text-right">Short / Excess</th><th style="min-width:320px">Reason (QC)</th></tr></thead>
        <tbody>
            @foreach($sheet['lines'] as $line)
                <tr>
                    <td>{{ $line['style_no'] }}</td><td>{{ $line['color'] }}</td>
                    <td class="text-right">{{ $line['order_total'] }}</td><td class="text-right">{{ $line['cut_total'] }}</td><td class="text-right">{{ $line['ship_total'] }}</td>
                    <td class="text-right font-weight-bold {{ $line['short'] < 0 ? 'text-danger' : '' }}">{{ $line['short'] > 0 ? '+' : '' }}{{ $line['short'] }}</td>
                    <td><input type="text" name="notes[{{ $line['po_id'] }}]" maxlength="255" class="form-control form-control-sm" value="{{ old('notes.' . $line['po_id'], $line['note']) }}"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
