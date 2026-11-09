{{-- props: entries, showStage (bool), showPo (bool) --}}
@php use ME\MerchandisingSfl\Services\ProductionFlow; @endphp
<table class="table table-bordered table-sm align-middle mb-0">
    <thead>
        <tr>
            <th>Date</th>
            @if($showStage)<th>Stage</th>@endif
            @if($showPo)<th>Buyer</th><th>Order / PO</th><th>Style · Color</th>@endif
            <th>Size</th><th>Part</th><th>Line</th>
            <th class="text-right">In</th><th class="text-right">Pass</th><th class="text-right">Rework</th><th class="text-right">Reject</th>
            <th>Defects (part · machine · defect · pcs)</th><th>Remarks</th><th>By</th>
            @canany(['msfl_prod_entry.edit', 'msfl_prod_entry.delete'])<th class="text-right">Actions</th>@endcanany
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $entry)
            <tr>
                <td>{{ $entry->entry_date->format('d-M-Y') }}
                    @if($entry->kind !== 'production')<br><span class="badge badge-{{ $entry->kind === 'qc' ? 'info' : 'warning' }}">{{ ProductionFlow::KINDS[$entry->kind] }}</span>@endif
                </td>
                @if($showStage)<td>{{ ProductionFlow::label($entry->stage) }}</td>@endif
                @if($showPo)
                    <td>{{ $entry->orderPo->order->buyer->name ?? '-' }}</td>
                    <td><a href="{{ route('msfl.production.status.show', $entry->order_po_id) }}">{{ $entry->orderPo->order->order_no ?? '' }} · {{ $entry->orderPo->po_no ?? '' }}</a></td>
                    <td>{{ $entry->orderPo->style->style_no ?? '' }} · {{ $entry->orderPo->color->name ?? '' }}</td>
                @endif
                <td>{{ $entry->size->name ?? 'All' }}</td>
                <td>{{ $entry->part_name ?? '-' }}</td>
                <td>{{ $entry->line->name ?? '-' }}@if($entry->hour_slot !== null)<br><small class="text-muted">{{ \ME\MerchandisingSfl\Services\ProductionFlow::hourSlots()[$entry->hour_slot] ?? $entry->hour_slot . ':00' }}</small>@endif</td>
                <td class="text-right">{{ $entry->input_qty }}</td>
                <td class="text-right"><strong>{{ $entry->pass_qty }}</strong></td>
                <td class="text-right">{{ $entry->rework_qty }}</td>
                <td class="text-right {{ $entry->reject_qty ? 'text-danger' : '' }}">{{ $entry->reject_qty }}</td>
                <td class="small">
                    @foreach($entry->defects as $d)
                        <div><span class="badge badge-{{ $d->type === 'reject' ? 'danger' : 'warning' }}">{{ ucfirst($d->type) }}</span>
                            {{ collect([$d->part_name, $d->machineLabel(), $d->defect])->filter()->implode(' · ') }} · {{ $d->qty }}</div>
                    @endforeach
                </td>
                <td>{{ $entry->remarks }}</td>
                <td>{{ $entry->creator->name ?? '-' }}</td>
                @canany(['msfl_prod_entry.edit', 'msfl_prod_entry.delete'])
                    <td class="text-right">
                        @can('msfl_prod_entry.edit')
                            @if($entry->kind === 'production')
                                <a href="{{ route('msfl.production.entries.edit', ['stage' => $entry->stage, 'entry' => $entry]) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                            @endif
                        @endcan
                        @can('msfl_prod_entry.delete')
                            <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteEntryModal"
                                data-action="{{ $entry->kind !== 'production' ? route('msfl.production.' . $entry->kind . '.destroy', $entry) : route('msfl.production.entries.destroy', ['stage' => $entry->stage, 'entry' => $entry]) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        @endcan
                    </td>
                @endcanany
            </tr>
        @empty
            <tr><td colspan="16" class="text-center text-muted">No entries yet.</td></tr>
        @endforelse
    </tbody>
</table>
@can('msfl_prod_entry.delete')
    @include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteEntryModal', 'label' => 'entry'])
@endcan
