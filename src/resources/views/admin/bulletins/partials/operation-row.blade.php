{{-- props: index, op (array), operationOptions, machineTypes --}}
<tr data-op-row>
    <td class="text-nowrap" style="width:52px">
        <button type="button" class="btn-custom ui-act ui-act-move-up" data-line-items-up title="Move up"><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="btn-custom ui-act ui-act-move-down" data-line-items-down title="Move down"><i class="fa-solid fa-arrow-down"></i></button>
    </td>
    <td class="text-center" data-op-sl></td>
    <td style="min-width:130px"><input type="text" name="operations[{{ $index }}][section]" class="form-control form-control-sm" value="{{ $op['section'] ?? '' }}" list="bulletinSections" data-op-section placeholder="(same as above)"></td>
    <td style="width:170px;min-width:170px;max-width:170px">
        <select name="operations[{{ $index }}][operation_id]" class="form-control form-control-sm msfl-select2" data-op-library>
            <option value="">— Library —</option>
            @foreach($operationOptions as $libraryOp)
                <option value="{{ $libraryOp->id }}" data-name="{{ $libraryOp->name }}" data-machine="{{ $libraryOp->machine_type_id }}" data-section="{{ $libraryOp->garmentPart->name ?? '' }}" data-attachment="{{ $libraryOp->attachment }}" data-smv="{{ $libraryOp->default_smv }}"
                    @selected((string) ($op['operation_id'] ?? '') === (string) $libraryOp->id)>{{ $libraryOp->name }}</option>
            @endforeach
        </select>
    </td>
    <td style="min-width:85px">
        <select name="operations[{{ $index }}][machine_type_id]" class="form-control form-control-sm" data-op-machine>
            <option value=""></option>
            @foreach($machineTypes as $type)
                <option value="{{ $type->id }}" data-helper="{{ $type->is_helper ? 1 : 0 }}" title="{{ $type->name }}" @selected((string) ($op['machine_type_id'] ?? '') === (string) $type->id)>{{ $type->code }}</option>
            @endforeach
        </select>
    </td>
    <td style="min-width:95px">
        <select name="operations[{{ $index }}][attachment]" class="form-control form-control-sm" data-op-attachment>
            <option value=""></option>
            @foreach(\ME\MerchandisingSfl\Support\MasterRegistry::attachments() as $attachment)
                <option value="{{ $attachment }}" @selected(($op['attachment'] ?? '') === $attachment)>{{ $attachment }}</option>
            @endforeach
        </select>
    </td>
    <td style="min-width:240px"><input type="text" name="operations[{{ $index }}][name]" class="form-control form-control-sm" value="{{ $op['name'] ?? '' }}" data-op-name></td>
    <td style="min-width:82px"><input type="number" step="any" min="0" name="operations[{{ $index }}][smv]" class="form-control form-control-sm text-right" value="{{ isset($op['smv']) && $op['smv'] !== '' ? (float) $op['smv'] : '' }}" data-op-smv></td>
    <td class="text-right" data-op-tarhr></td>
    <td class="text-right" data-op-req></td>
    <td style="min-width:64px"><input type="number" step="1" min="1" name="operations[{{ $index }}][workplaces]" class="form-control form-control-sm text-right" value="{{ $op['workplaces'] ?? '' }}" data-op-wp title="Leave blank = Req W-Place rounded up"></td>
    <td class="text-right" data-op-ptarget></td>
    <td class="text-right" data-op-btl></td>
    <td class="text-center" title="Untick if this operation is not done for this style">
        <input type="hidden" name="operations[{{ $index }}][is_active]" value="0">
        <input type="checkbox" name="operations[{{ $index }}][is_active]" value="1" data-op-active @checked($op['is_active'] ?? true)>
    </td>
    <td style="min-width:110px"><input type="text" name="operations[{{ $index }}][remarks]" class="form-control form-control-sm" value="{{ $op['remarks'] ?? '' }}"></td>
    <td style="width:30px"><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
</tr>
