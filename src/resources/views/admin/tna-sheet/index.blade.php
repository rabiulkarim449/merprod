@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('T&A Sheet') }}</title>
@endsection

@push('css')
<style>
    .tna-wrap { overflow: auto; max-height: 75vh; border: 1px solid #dee2e6; }
    table.tna-sheet { border-collapse: separate; border-spacing: 0; width: max-content; margin: 0; }
    table.tna-sheet th, table.tna-sheet td { border: 1px solid #dee2e6; padding: 3px 6px; font-size: 12px; white-space: nowrap; vertical-align: middle; }
    table.tna-sheet thead th { position: sticky; top: 0; z-index: 3; background: #e9ecef; color: #000; text-align: center; }
    table.tna-sheet thead tr:nth-child(2) th { top: 26px; min-width: 88px; max-width: 120px; white-space: normal; line-height: 1.15; }
    table.tna-sheet .band { background: #cbd5e1 !important; font-weight: 700; }
    table.tna-sheet .sticky { position: sticky; left: 0; z-index: 2; background: #fff; min-width: 150px; }
    table.tna-sheet thead .sticky { z-index: 4; background: #e9ecef; }
    table.tna-sheet td.wrap { white-space: normal; min-width: 150px; max-width: 220px; }
    .c-green { background: #d1fae5 !important; } .c-amber { background: #fef3c7 !important; } .c-red { background: #fee2e2 !important; }
    .c-grey { background: #f3f4f6 !important; color: #9ca3af; } .c-blue { background: #e8f1ff !important; }
    .tna-hint { color: #6b7280; font-style: italic; }
    .tna-legend-item { display: inline-flex; align-items: center; gap: 5px; margin-right: 12px; white-space: nowrap; }
    .tna-swatch { display: inline-block; width: 13px; height: 13px; border: 1px solid #ccc; border-radius: 3px; }
</style>
@endpush

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0">T&amp;A Sheet</h4>
            <div class="small text-muted">@include('merchandising-sfl::admin.tna-sheet.partials.legend')</div>
            <a href="{{ route('msfl.tna-sheet.print', request()->query()) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i> Print</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="PO / Style" value="{{ request('search') }}"></div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $b)<option value="{{ $b->id }}" @selected(request('buyer_id') == $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-center">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="closed" value="1" class="custom-control-input" id="closedOrders" @checked(request()->boolean('closed'))>
                        <label class="custom-control-label" for="closedOrders">Include closed orders</label>
                    </div>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.tna-sheet.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            @php $groups = $sheet->groups(); @endphp
            <div class="tna-wrap">
                <table class="tna-sheet">
                    <thead>
                        <tr>
                            <th class="sticky band" rowspan="2">Order · PO</th>
                            @foreach($groups as $name => $cols)<th class="band" colspan="{{ count($cols) }}">{{ $name }}</th>@endforeach
                        </tr>
                        <tr>
                            @foreach($groups as $cols)
                                @foreach($cols as [$label, $source])<th title="{{ \ME\MerchandisingSfl\Services\TnaSheet::SOURCES[$source] ?? '' }}">{{ $label }}</th>@endforeach
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pos as $po)
                            @php $row = $sheet->row($po); $plan = $sheet->planFor($po); @endphp
                            <tr>
                                <td class="sticky">
                                    <strong>{{ $po->order->order_no }}</strong> · {{ $po->po_no }}
                                    <div class="small">
                                        @if($plan)<a href="{{ route('msfl.tna.show', $plan) }}">{{ $plan->tna_no }}</a>
                                        @else<a href="{{ route('msfl.tna.create', ['order_id' => $po->order_id]) }}" class="text-danger">Make T&amp;A</a>@endif
                                    </div>
                                </td>
                                @foreach($groups as $cols)
                                    @foreach($cols as $key => $col)
                                        @php $c = $row[$key]; @endphp
                                        <td class="c-{{ $c['color'] }} @if(str_contains($key, 'remarks') || $key === 'pcd_reason' || $key === 'style') wrap @endif"
                                            title="{{ $c['source'] === 'tna' ? 'Enter on the T&A page' : (\ME\MerchandisingSfl\Services\TnaSheet::SOURCES[$c['source']] ?? 'Auto from Production / samples') }}">@if($c['text'] !== ''){{ $c['text'] }}@elseif($c['hint'])<em class="tna-hint">{{ $c['hint'] }}</em>@endif</td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 1 + $sheet->columnCount() }}" class="text-center text-muted">No confirmed order PO yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $pos->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
