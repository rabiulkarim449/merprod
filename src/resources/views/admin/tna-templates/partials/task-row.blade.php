{{-- props: index, task (array), autoSources --}}
<tr>
    <td style="width:60px" class="text-nowrap">
        <button type="button" class="btn-custom ui-act ui-act-move-up" data-line-items-up title="Move up"><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="btn-custom ui-act ui-act-move-down" data-line-items-down title="Move down"><i class="fa-solid fa-arrow-down"></i></button>
    </td>
    <td style="min-width:130px"><input type="text" name="tasks[{{ $index }}][group_name]" class="form-control form-control-sm" value="{{ $task['group_name'] ?? '' }}" list="tnaGroups"></td>
    <td style="min-width:120px"><input type="text" name="tasks[{{ $index }}][task_code]" class="form-control form-control-sm" value="{{ $task['task_code'] ?? '' }}" placeholder="PP_APPROVAL"></td>
    <td style="min-width:200px"><input type="text" name="tasks[{{ $index }}][task_name]" class="form-control form-control-sm" value="{{ $task['task_name'] ?? '' }}"></td>
    <td style="min-width:150px">
        <select name="tasks[{{ $index }}][anchor]" class="form-control form-control-sm">
            @foreach(\ME\MerchandisingSfl\Models\TnaTemplateTask::ANCHORS as $value => $label)
                <option value="{{ $value }}" @selected(($task['anchor'] ?? 'pcd') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td style="width:90px"><input type="number" step="1" name="tasks[{{ $index }}][offset_days]" class="form-control form-control-sm text-right" value="{{ $task['offset_days'] ?? 0 }}"></td>
    <td style="min-width:170px">
        <select name="tasks[{{ $index }}][auto_source]" class="form-control form-control-sm">
            @foreach($autoSources as $value => $label)
                <option value="{{ $value }}" @selected(($task['auto_source'] ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td style="min-width:120px">
        <select name="tasks[{{ $index }}][condition]" class="form-control form-control-sm">
            <option value="">Always</option>
            @foreach(\ME\MerchandisingSfl\Models\TnaTemplateTask::CONDITIONS as $value => $label)
                <option value="{{ $value }}" @selected(($task['condition'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td class="text-center">
        <input type="hidden" name="tasks[{{ $index }}][is_mandatory]" value="0">
        <input type="checkbox" name="tasks[{{ $index }}][is_mandatory]" value="1" @checked(! empty($task['is_mandatory']))>
    </td>
    <td style="width:40px"><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
</tr>
