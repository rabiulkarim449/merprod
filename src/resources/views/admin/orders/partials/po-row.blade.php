{{-- One PO line row of the order form (color comes from the style when it has one; size cells shown for the picked sizes). props: i (index or '__INDEX__'), row (array of values), formSizes, styles, colors, shipModes --}}
@php
    $n = "pos[{$i}]";
    $sizes = (array) ($row['sizes'] ?? []);
    $bad = fn ($field) => $errors->has("pos.{$i}.{$field}") ? ' is-invalid' : '';
    $flags = ['needs_embroidery' => 'Emb', 'needs_washing' => 'Wash', 'applique_ih' => 'Appl. IH', 'studs_stones_ih' => 'Studs IH', 'heat_seal_ih' => 'Heat Seal IH'];
@endphp
<tr data-po-row>
    <td class="text-nowrap">
        <span data-po-sl></span>
        <input type="hidden" name="{{ $n }}[id]" value="{{ $row['id'] ?? '' }}">
    </td>
    <td><input type="text" name="{{ $n }}[po_no]" class="form-control form-control-sm{{ $bad('po_no') }}" value="{{ $row['po_no'] ?? '' }}" placeholder="PO No" style="min-width:90px"></td>
    <td style="min-width:170px">
        <select name="{{ $n }}[style_id]" class="form-control form-control-sm msfl-select2{{ $bad('style_id') }}" data-po-style>
            <option value="">— Style —</option>
            @foreach($styles as $style)
                <option value="{{ $style->id }}" data-buyer="{{ $style->buyer_id }}" data-color="{{ $style->color_id }}" @selected((string) ($row['style_id'] ?? '') === (string) $style->id)>{{ $style->label() }}</option>
            @endforeach
        </select>
    </td>
    <td style="min-width:120px">
        <select name="{{ $n }}[color_id]" class="form-control form-control-sm msfl-select2{{ $bad('color_id') }}" data-po-color>
            <option value="">— Color —</option>
            @foreach($colors as $color)
                <option value="{{ $color->id }}" @selected((string) ($row['color_id'] ?? '') === (string) $color->id)>{{ $color->name }}</option>
            @endforeach
        </select>
    </td>
    @foreach($formSizes as $size)
        <td style="min-width:58px" data-size-col="{{ $size->id }}"><input type="number" min="0" step="1" name="{{ $n }}[sizes][{{ $size->id }}]" class="form-control form-control-sm text-right px-1{{ $bad('sizes') }}" value="{{ $sizes[$size->id] ?? '' }}" data-po-size></td>
    @endforeach
    <td data-no-size></td>
    <td class="text-right font-weight-bold" data-po-qty>0</td>
    <td style="min-width:80px"><input type="number" step="any" min="0" name="{{ $n }}[unit_price]" class="form-control form-control-sm text-right{{ $bad('unit_price') }}" value="{{ $row['unit_price'] ?? '' }}" data-po-price></td>
    <td class="text-right" data-po-value>0.00</td>
    <td><input type="date" name="{{ $n }}[pcd_date]" class="form-control form-control-sm" value="{{ $row['pcd_date'] ?? '' }}"></td>
    <td><input type="date" name="{{ $n }}[shipment_date]" class="form-control form-control-sm{{ $bad('shipment_date') }}" value="{{ $row['shipment_date'] ?? '' }}" data-po-ship></td>
    <td style="min-width:100px">
        <select name="{{ $n }}[ship_mode_id]" class="form-control form-control-sm msfl-select2">
            <option value="">—</option>
            @foreach($shipModes as $mode)
                <option value="{{ $mode->id }}" @selected((string) ($row['ship_mode_id'] ?? '') === (string) $mode->id)>{{ $mode->name }}</option>
            @endforeach
        </select>
    </td>
    <td class="text-nowrap small" style="min-width:110px">
        @foreach($flags as $flag => $flagLabel)
            <label class="d-block mb-0 font-weight-normal">
                <input type="checkbox" name="{{ $n }}[{{ $flag }}]" value="1" @checked(! empty($row[$flag]))> {{ $flagLabel }}
            </label>
        @endforeach
    </td>
    <td><input type="text" name="{{ $n }}[remarks]" class="form-control form-control-sm" value="{{ $row['remarks'] ?? '' }}" style="min-width:110px"></td>
    <td class="text-nowrap">
        <button type="button" class="btn-custom ui-act ui-act-create" data-po-copy title="Copy this row (new color / PO)"><i class="fa-solid fa-plus"></i></button>
        <button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Remove"><i class="fa-solid fa-trash"></i></button>
    </td>
</tr>
