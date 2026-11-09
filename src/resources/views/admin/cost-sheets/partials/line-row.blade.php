{{-- One cost line (per dozen). props: index (string|"__INDEX__"), group, line (array), itemOptions, uoms, suppliers (id => name) --}}
<tr data-cs-row>
    <td class="text-center" data-cs-sl></td>
    <td style="min-width:170px">
        <input type="hidden" name="items[{{ $index }}][group]" value="{{ $group }}">
        <input type="hidden" name="items[{{ $index }}][remarks]" value="{{ $line['remarks'] ?? '' }}">
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm msfl-select2" data-cs-item>
            <option value="">— Library item —</option>
            @foreach($itemOptions as $item)
                <option value="{{ $item->id }}" data-name="{{ $item->name }}" data-uom="{{ $item->uom_id }}" data-rate="{{ $item->default_price !== null ? (float) $item->default_price : '' }}"
                    data-supplier="{{ $suppliers[$item->default_supplier_id] ?? '' }}" @selected(($line['item_id'] ?? null) == $item->id)>{{ $item->name }} ({{ $item->code }})</option>
            @endforeach
        </select>
    </td>
    <td style="min-width:180px"><input type="text" name="items[{{ $index }}][description]" class="form-control form-control-sm" maxlength="255" value="{{ $line['description'] ?? '' }}" data-cs-desc></td>
    <td style="min-width:150px"><input type="text" name="items[{{ $index }}][supplier_name]" class="form-control form-control-sm" maxlength="150" list="csSupplierList" value="{{ $line['supplier_name'] ?? '' }}" data-cs-supplier></td>
    <td style="width:110px"><input type="number" step="any" min="0" name="items[{{ $index }}][consumption]" class="form-control form-control-sm text-right" value="{{ $line['consumption'] ?? '' }}" data-cs-cons></td>
    <td style="min-width:90px">
        <select name="items[{{ $index }}][uom_id]" class="form-control form-control-sm" data-cs-uom>
            <option value="">—</option>
            @foreach($uoms as $uom)
                <option value="{{ $uom->id }}" @selected(($line['uom_id'] ?? null) == $uom->id)>{{ $uom->code }}</option>
            @endforeach
        </select>
    </td>
    <td style="width:115px"><input type="number" step="any" min="0" name="items[{{ $index }}][rate]" class="form-control form-control-sm text-right" value="{{ $line['rate'] ?? '' }}" data-cs-rate></td>
    <td class="text-right font-weight-bold" style="width:110px" data-cs-amount>-</td>
    <td style="width:40px"><button type="button" class="btn-custom ui-act ui-act-delete" data-cs-remove title="Remove"><i class="fa-solid fa-trash"></i></button></td>
</tr>
