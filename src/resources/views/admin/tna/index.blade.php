@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'T&A' }}@else
    <title>{{ websiteTitle('T&A') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'T&A', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">T&amp;A (Time And Action)</h4>
            @can('msfl_tna.add')
                <a href="{{ route('msfl.tna.create') }}" class="btn btn-sm ui-act ui-act-create" title="New T&amp;A"><i class="fa-solid fa-plus"></i> New T&amp;A</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search T&A / Order / Style" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="line_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Lines</option>
                        @foreach($lines as $line)
                            <option value="{{ $line->id }}" @selected(request('line_id') == $line->id)>{{ $line->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\TnaPlan::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.tna.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>T&amp;A No</th><th>Order</th><th>Buyer</th><th>Style</th><th>Qty</th><th>Line(s)</th><th>Capacity / Day</th><th>PCD</th><th>Sewing</th><th>Shipment</th><th>Progress</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            @php [$done, $total] = $plan->progress(); $delayed = $plan->delayedCount(); @endphp
                            <tr>
                                <td>{{ $loop->iteration + $plans->firstItem() - 1 }}</td>
                                <td>{{ $plan->tna_no }}</td>
                                <td>{{ $plan->order->order_no ?? '-' }}</td>
                                <td>{{ $plan->order->buyer->name ?? '-' }}</td>
                                <td>{{ $plan->style->style_no ?? '-' }}</td>
                                <td>{{ number_format($plan->order_qty) }}</td>
                                <td>{{ $plan->lines->pluck('name')->implode(', ') }}</td>
                                <td>{{ number_format($plan->daily_capacity) }}</td>
                                <td>{{ optional($plan->pcd_date)->format('d M') }}</td>
                                <td class="text-nowrap">{{ optional($plan->sewing_start_date)->format('d M') }} → {{ optional($plan->sewing_end_date)->format('d M') }} <small class="text-muted">({{ $plan->sewing_days }}d)</small></td>
                                <td>{{ $plan->shipment_date->format('d M Y') }}</td>
                                <td class="text-nowrap">
                                    {{ $done }}/{{ $total }}
                                    @if($delayed)<span class="badge badge-danger">{{ $delayed }} delayed</span>@endif
                                    @unless($plan->is_feasible)<span class="badge badge-danger">Not feasible</span>@endunless
                                </td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $plan])</td>
                                <td class="text-right">
                                    @can('msfl_tna.view')
                                        <a href="{{ route('msfl.tna.show', $plan) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @can('msfl_tna.delete')
                                        <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteTnaModal" data-action="{{ route('msfl.tna.destroy', $plan) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="text-center text-muted">No T&amp;A yet — confirm an order, then create its T&amp;A.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $plans->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteTnaModal', 'label' => 'T&A'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
