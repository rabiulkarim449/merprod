{{-- props: index, d (old values), garmentParts (Master Data), machines (Inventory; optional pick, for machine-wise rejection), withSize (bool: Buyer QC rows name their size — options filled by the form's script) --}}
<tr>
    @if($withSize ?? false)
        <td><select name="defects[{{ $index }}][size_id]" class="form-control form-control-sm" data-defect-size data-old="{{ $d['size_id'] ?? '' }}"><option value="">— Size —</option></select></td>
    @endif
    <td>
        <select name="defects[{{ $index }}][type]" class="form-control form-control-sm" data-defect-type>
            <option value="reject" @selected(($d['type'] ?? 'reject') === 'reject')>Reject</option>
            <option value="rework" @selected(($d['type'] ?? '') === 'rework')>Rework</option>
        </select>
    </td>
    <td>
        <select name="defects[{{ $index }}][severity]" class="form-control form-control-sm" data-defect-severity>
            <option value="">— Severity —</option>
            @foreach(\ME\MerchandisingSfl\Models\Production\EntryDefect::SEVERITIES as $sv => $sl)<option value="{{ $sv }}" @selected(($d['severity'] ?? '') === $sv)>{{ $sl }}</option>@endforeach
        </select>
    </td>
    <td>
        <select name="defects[{{ $index }}][part_name]" class="form-control form-control-sm">
            <option value="">— Part (optional) —</option>
            @foreach($garmentParts as $gp)<option value="{{ $gp }}" @selected(($d['part_name'] ?? '') === $gp)>{{ $gp }}</option>@endforeach
        </select>
    </td>
    <td>
        <select name="defects[{{ $index }}][machine_id]" class="form-control form-control-sm" data-machine-select>
            <option value="">— Machine (optional) —</option>
            @foreach($machines as $m)
                <option value="{{ $m->id }}" data-line="{{ strtolower(trim((string) $m->line)) }}" @selected(($d['machine_id'] ?? null) == $m->id)>{{ $m->code }} — {{ $m->name }}{{ $m->type ? ' (' . $m->type . ')' : '' }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" name="defects[{{ $index }}][defect]" class="form-control form-control-sm" value="{{ $d['defect'] ?? '' }}" placeholder="e.g. Broken stitch, Oil spot"></td>
    <td><input type="number" min="0" step="1" name="defects[{{ $index }}][qty]" class="form-control form-control-sm text-right" value="{{ $d['qty'] ?? '' }}" data-defect-qty></td>
    <td><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
</tr>
