@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New T&A') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New T&amp;A</h4>
            <a href="{{ route('msfl.tna.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            {{-- Step 1: confirmed order --}}
            <form method="GET" class="row align-items-end mb-3">
                <div class="col-md-6">
                    <label class="form-label">1. Confirmed Order <span class="text-danger">*</span></label>
                    <select name="order_id" class="form-control form-control-sm msfl-select2" onchange="this.form.submit()">
                        <option value="">— Select a confirmed order —</option>
                        @foreach($orders as $o)
                            <option value="{{ $o->id }}" @selected($order?->id === $o->id)>{{ $o->order_no }} — {{ $o->buyer->name ?? '' }} {{ $o->buyer_order_ref ? '(' . $o->buyer_order_ref . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                @if($orders->isEmpty())
                    <div class="col-md-6 text-muted small">No confirmed orders. A T&amp;A can only be made after the order is confirmed.</div>
                @endif
            </form>

            @if($order)
                @if($templates->isEmpty())
                    <div class="alert alert-warning">No T&amp;A template yet — create one in Planning → Setup → T&amp;A Templates (or run the default seeder).</div>
                @endif
                <form method="POST" action="{{ route('msfl.tna.store') }}">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}">

                    <label class="form-label">2. Style <span class="text-danger">*</span></label>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead><tr><th></th><th>Style</th><th class="text-right">Order Qty</th><th>Shipment (earliest PO)</th><th class="text-right">SMV</th><th>SMV from</th></tr></thead>
                            <tbody>
                                @foreach($styleRows as $row)
                                    <tr @class(['text-muted' => $row['taken']])>
                                        <td class="text-center">
                                            <input type="radio" name="style_id" value="{{ $row['style']->id }}" data-smv="{{ $row['smv'] }}" data-qty="{{ $row['order_qty'] }}" data-has-ship="{{ $row['shipment_date'] ? 1 : 0 }}"
                                                @disabled($row['taken']) @checked((string) old('style_id') === (string) $row['style']->id || ($loop->first && ! old('style_id') && ! $row['taken']))>
                                        </td>
                                        <td>{{ $row['style']->label() }} @if($row['taken'])<span class="badge badge-secondary">has T&amp;A</span>@endif</td>
                                        <td class="text-right">{{ number_format($row['order_qty']) }}</td>
                                        <td>{{ $row['shipment_date']?->format('d M Y') ?? 'Not set' }}</td>
                                        <td class="text-right">{{ $row['smv'] ?? '—' }}</td>
                                        <td>{{ $row['bulletin_id'] ? 'Bulletin' : ($row['smv'] ? 'Style' : 'Missing — enter below') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">3. Line(s) <span class="text-danger">*</span> <small class="text-muted">— more lines = more pieces per day</small></label>
                            <select name="line_ids[]" class="form-control form-control-sm msfl-select2" multiple required data-tna-lines>
                                @foreach($lines as $line)
                                    <option value="{{ $line->id }}" data-operators="{{ $line->operators }}" data-minutes="{{ $line->working_minutes }}" data-efficiency="{{ (float) $line->efficiency_percent }}"
                                        @selected(in_array($line->id, old('line_ids', [])))>{{ $line->name }} ({{ $line->operators }} op · {{ $line->working_minutes }} min · {{ (float) $line->efficiency_percent }}%)</option>
                                @endforeach
                            </select>
                        </div>
                        @include('merchandising-sfl::admin.partials.select', ['name' => 'tna_template_id', 'label' => '4. T&A Template', 'required' => true, 'options' => $templates->pluck('name', 'id'), 'value' => $templates->first()?->id])
                        @include('merchandising-sfl::admin.partials.input', ['name' => 'smv', 'label' => 'SMV (only if missing)', 'type' => 'number', 'value' => null, 'attrs' => 'data-tna-smv'])
                        @include('merchandising-sfl::admin.partials.input', ['name' => 'shipment_date', 'label' => 'Shipment Date (only if not set)', 'type' => 'date', 'value' => null])
                        @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6, 'value' => null])
                    </div>

                    <div class="alert alert-light border" data-tna-preview>Select a style and line(s) to preview the capacity.</div>

                    <button type="submit" class="btn btn-sm ui-act ui-act-auto" @disabled($templates->isEmpty()) title="Create & Calculate T&A"><i class="fa-solid fa-wand-magic-sparkles"></i> Create &amp; Calculate T&amp;A</button>
                </form>
            @endif
        </div>
    </div>
</div>

@push('js')
<script>
    // Preview only — mirrors Line::dailyCapacity(); the server does the real calculation.
    (function () {
        function preview() {
            const box = document.querySelector('[data-tna-preview]');
            if (! box) return;
            const style = document.querySelector('input[name="style_id"]:checked');
            const smv = parseFloat(style?.dataset.smv) || parseFloat(document.querySelector('[data-tna-smv]')?.value) || 0;
            const qty = parseInt(style?.dataset.qty) || 0;
            const lines = Array.from(document.querySelector('[data-tna-lines]').selectedOptions);
            if (! style || ! lines.length || ! smv) { box.textContent = 'Select a style and line(s) (and SMV if missing) to preview the capacity.'; return; }
            let capacity = 0;
            const parts = lines.map(function (o) {
                const c = Math.floor(o.dataset.operators * o.dataset.minutes * (o.dataset.efficiency / 100) / smv);
                capacity += c;
                return o.text.split(' (')[0] + ': ' + o.dataset.operators + ' × ' + o.dataset.minutes + ' × ' + o.dataset.efficiency + '% ÷ ' + smv + ' = ' + c.toLocaleString() + ' pcs/day';
            });
            const days = capacity > 0 ? Math.ceil(qty / capacity) : 0;
            box.innerHTML = parts.join('<br>') + '<br><strong>Daily capacity ' + capacity.toLocaleString() + ' pcs → ' + qty.toLocaleString() + ' pcs needs ' + days + ' sewing day(s)</strong>';
        }
        $(document).on('change input', 'input[name="style_id"], [data-tna-lines], [data-tna-smv]', preview);
        $(preview);
    })();
</script>
@endpush
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
