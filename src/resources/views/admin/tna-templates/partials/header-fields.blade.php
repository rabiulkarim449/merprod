{{-- props: template (nullable) --}}
@php $key = $template->id ?? 'new'; @endphp
<div class="row">
    @include('merchandising-sfl::admin.partials.input', ['name' => 'name', 'label' => 'Template Name', 'required' => true, 'col' => 6, 'value' => $template->name ?? null])
    <div class="col-md-3 mb-3 d-flex align-items-center"><div class="custom-control custom-switch">
        <input type="hidden" name="is_default" value="0">
        <input type="checkbox" name="is_default" value="1" class="custom-control-input" id="tplDefault{{ $key }}" @checked(old('is_default', $template->is_default ?? false))>
        <label class="custom-control-label" for="tplDefault{{ $key }}">Default template</label>
    </div></div>
    <div class="col-md-3 mb-3 d-flex align-items-center"><div class="custom-control custom-switch">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="tplActive{{ $key }}" @checked(old('is_active', $template->is_active ?? true))>
        <label class="custom-control-label" for="tplActive{{ $key }}">Active</label>
    </div></div>
    @include('merchandising-sfl::admin.partials.input', ['name' => 'ship_to_ex_factory_days', 'label' => 'Ex-Factory = Shipment − (working days)', 'type' => 'number', 'required' => true, 'col' => 4, 'value' => $template->ship_to_ex_factory_days ?? 3])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'ex_factory_to_sewing_end_days', 'label' => 'Sewing End = Ex-Factory − (finishing + packing days)', 'type' => 'number', 'required' => true, 'col' => 4, 'value' => $template->ex_factory_to_sewing_end_days ?? 4])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'pcd_to_sewing_start_days', 'label' => 'PCD = Sewing Start − (cutting lead days)', 'type' => 'number', 'required' => true, 'col' => 4, 'value' => $template->pcd_to_sewing_start_days ?? 3])
</div>
