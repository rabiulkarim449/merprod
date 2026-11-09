{{-- props: index, line (array), itemOptions, colors, sizes, uoms, suppliers --}}
@php
    $opt = fn ($list, $field, $labelFn) => $list->map(fn ($o) => '<option value="' . $o->id . '"' . ((string) ($line[$field] ?? '') === (string) $o->id ? ' selected' : '') . '>' . e($labelFn($o)) . '</option>')->implode('');
@endphp
<tr data-bom-row>
    <td style="min-width:220px">
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm msfl-select2" data-bom-item>
            <option value="">— Item —</option>
            @foreach($itemOptions as $item)
                <option value="{{ $item->id }}" data-uom="{{ $item->uom_id }}" data-supplier="{{ $item->default_supplier_id }}" data-rate="{{ $item->default_price }}"
                    @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>[{{ ucfirst($item->type) }}] {{ $item->name }} ({{ $item->code }})</option>
            @endforeach
        </select>
    </td>
    <td style="min-width:120px"><select name="items[{{ $index }}][color_id]" class="form-control form-control-sm"><option value="">All</option>{!! $opt($colors, 'color_id', fn ($o) => $o->name) !!}</select></td>
    <td style="min-width:80px"><select name="items[{{ $index }}][size_id]" class="form-control form-control-sm"><option value="">All</option>{!! $opt($sizes, 'size_id', fn ($o) => $o->name) !!}</select></td>
    <td><input type="text" name="items[{{ $index }}][placement]" class="form-control form-control-sm" value="{{ $line['placement'] ?? '' }}" placeholder="e.g. Body, Collar"></td>
    <td style="width:100px"><input type="number" step="any" min="0" name="items[{{ $index }}][consumption]" class="form-control form-control-sm text-right" value="{{ $line['consumption'] ?? '' }}"></td>
    <td style="min-width:80px"><select name="items[{{ $index }}][uom_id]" class="form-control form-control-sm" data-bom-uom><option value=""></option>{!! $opt($uoms, 'uom_id', fn ($o) => $o->code) !!}</select></td>
    <td style="width:80px"><input type="number" step="any" min="0" max="100" name="items[{{ $index }}][wastage_percent]" class="form-control form-control-sm text-right" value="{{ $line['wastage_percent'] ?? '' }}"></td>
    <td style="width:100px"><input type="number" step="any" min="0" name="items[{{ $index }}][rate]" class="form-control form-control-sm text-right" value="{{ $line['rate'] ?? '' }}" data-bom-rate></td>
    <td style="min-width:140px"><select name="items[{{ $index }}][supplier_id]" class="form-control form-control-sm" data-bom-supplier><option value=""></option>{!! $opt($suppliers, 'supplier_id', fn ($o) => $o->name) !!}</select></td>
    <td><input type="text" name="items[{{ $index }}][remarks]" class="form-control form-control-sm" value="{{ $line['remarks'] ?? '' }}"></td>
    <td style="width:40px"><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
</tr>
