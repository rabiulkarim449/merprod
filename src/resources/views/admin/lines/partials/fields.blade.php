{{-- props: line (nullable), machineTypes, fromInventory (bool), floorLines, usedFloorLineIds --}}
@php
    $key = $line->id ?? 'new';
    $qty = $line ? $line->machineCounts() : collect();
@endphp
<div class="row">
    {{-- Floor / line are entered once in HR; only the planning figures are set here. --}}
    <div class="col-md-6 mb-3">
        <label class="form-label">Floor / Line <span class="text-danger">*</span> <small class="text-muted">(from HR)</small></label>
        <select name="hr_floor_line_id" class="form-control form-control-sm msfl-select2" required>
            <option value="">— Select HR floor-line —</option>
            @foreach($floorLines as $fl)
                @continue(in_array($fl->id, $usedFloorLineIds) && ($line->hr_floor_line_id ?? null) != $fl->id)
                <option value="{{ $fl->id }}" @selected(old('hr_floor_line_id', $line->hr_floor_line_id ?? null) == $fl->id)>{{ $fl->label }}@if($fl->line_capacity) (capacity {{ $fl->line_capacity }})@endif</option>
            @endforeach
        </select>
        @if($floorLines->isEmpty())
            <span class="form-text text-danger">No active floor-line in HR yet — add it under HR → Floor Lines.</span>
        @endif
    </div>
    <div class="col-md-3 mb-3 d-flex align-items-center"><div class="custom-control custom-switch">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="lineActive{{ $key }}" @checked(old('is_active', $line->is_active ?? true))>
        <label class="custom-control-label" for="lineActive{{ $key }}">Active</label>
    </div></div>
    @include('merchandising-sfl::admin.partials.input', ['name' => 'operators', 'label' => 'Operators', 'type' => 'number', 'required' => true, 'value' => $line->operators ?? 0])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'helpers', 'label' => 'Helpers', 'type' => 'number', 'value' => $line->helpers ?? 0])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'working_minutes', 'label' => 'Working Minutes / Day (incl. OT)', 'type' => 'number', 'required' => true, 'value' => $line->working_minutes ?? 600])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'efficiency_percent', 'label' => 'Efficiency %', 'type' => 'number', 'required' => true, 'value' => $line->efficiency_percent ?? 60])
</div>
<h6>Machines on this line</h6>
@if($fromInventory ?? false)
    {{-- Entered once in Inventory → Machines (type + line); only counted here. --}}
    <p class="small text-muted mb-2">
        Counted from <a href="{{ \Illuminate\Support\Facades\Route::has('inventory.machines.index') ? route('inventory.machines.index') : '#' }}" target="_blank">Inventory → Machines</a>:
        active machines whose <strong>Line</strong> is this HR line's name (e.g. <em>Line 2</em>). Add or move machines there.
    </p>
    @php $onLine = $machineTypes->filter(fn ($t) => $qty->get($t->id)); @endphp
    @forelse($onLine as $type)
        <span class="badge badge-light border mr-1 mb-1">{{ $type->code }} — {{ $type->name }}: <strong>{{ $qty->get($type->id) }}</strong></span>
    @empty
        <div class="alert alert-light border mb-0 small">{{ $line ? 'No Inventory machine is on this line yet.' : 'After saving, set the HR line name as the Line of its machines in Inventory.' }}</div>
    @endforelse
@elseif($machineTypes->isEmpty())
    <div class="alert alert-warning mb-0">No machine types yet — add them in Planning → Setup → Machine Types.</div>
@else
    <div class="row">
        @foreach($machineTypes as $type)
            <div class="col-md-2 col-4 mb-2">
                <label class="form-label small">{{ $type->name }}</label>
                <input type="number" min="0" step="1" name="machines[{{ $type->id }}]" class="form-control form-control-sm" value="{{ $qty->get($type->id) }}">
            </div>
        @endforeach
    </div>
@endif
