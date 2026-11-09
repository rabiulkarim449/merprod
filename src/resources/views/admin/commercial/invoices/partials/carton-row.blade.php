{{-- One carton range of the carton packing list. props: i (index or '__INDEX__'), row (array), avail (po_id => [po, sizes, free]), sizes --}}
@php $n = "cartons[{$i}]"; $rowSizes = (array) ($row['sizes'] ?? []); @endphp
<tr data-ctn-row>
    <td style="min-width:200px">
        <select name="{{ $n }}[order_po_id]" class="form-control form-control-sm" data-ctn-po>
            <option value="">— PO —</option>
            @foreach($avail as $poId => $a)
                <option value="{{ $poId }}" data-sizes="{{ $a['sizes']->pluck('id')->implode(',') }}" @selected((string) ($row['order_po_id'] ?? '') === (string) $poId)>{{ $a['po']->po_no }} · {{ $a['po']->style->style_no ?? '' }} · {{ $a['po']->color->name ?? '' }}</option>
            @endforeach
        </select>
    </td>
    <td style="width:75px"><input type="number" min="1" step="1" name="{{ $n }}[carton_from]" class="form-control form-control-sm text-right px-1" value="{{ $row['carton_from'] ?? '' }}" data-ctn-from></td>
    <td style="width:75px"><input type="number" min="1" step="1" name="{{ $n }}[carton_to]" class="form-control form-control-sm text-right px-1" value="{{ $row['carton_to'] ?? '' }}" data-ctn-to></td>
    <td class="text-right" data-ctn-count>0</td>
    @foreach($sizes as $size)
        <td style="min-width:56px"><input type="number" min="0" step="1" name="{{ $n }}[sizes][{{ $size->id }}]" class="form-control form-control-sm text-right px-1" value="{{ $rowSizes[$size->id] ?? '' }}" data-ctn-size="{{ $size->id }}"></td>
    @endforeach
    <td class="text-right font-weight-bold" data-ctn-per>0</td>
    <td class="text-right font-weight-bold" data-ctn-pcs>0</td>
    @foreach(['net_weight' => 'NW', 'gross_weight' => 'GW', 'length' => 'L', 'width' => 'W', 'height' => 'H'] as $f => $ph)
        <td style="width:66px"><input type="number" min="0" step="any" name="{{ $n }}[{{ $f }}]" class="form-control form-control-sm text-right px-1" value="{{ $row[$f] ?? '' }}" placeholder="{{ $ph }}" data-ctn-{{ $f }}></td>
    @endforeach
    <td class="text-nowrap">
        <button type="button" class="btn-custom ui-act ui-act-copy" data-ctn-copy title="Copy (next cartons)"><i class="fa-solid fa-copy"></i></button>
        <button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Remove"><i class="fa-solid fa-trash"></i></button>
    </td>
</tr>
