@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Cost Sheet — ' . $costSheet->cost_sheet_no }}@else
    <title>{{ websiteTitle('Cost Sheet ' . $costSheet->cost_sheet_no) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Open Cost Sheet', 'printSubtitle' => $costSheet->cost_sheet_no, 'printPage' => 'A4 portrait'])

    @unless($printMode)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Cost Sheet — {{ $costSheet->cost_sheet_no }} @include('merchandising-sfl::admin.partials.status-badge', ['model' => $costSheet])</h4>
                <div>
                    @include('merchandising-sfl::admin.partials.print-button')
                    @if($costSheet->isEditable())
                        @can('msfl_cost_sheet.edit')
                            <a href="{{ route('msfl.cost-sheets.edit', $costSheet) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                        @endcan
                        @can('msfl_cost_sheet.approve')
                            <form method="POST" action="{{ route('msfl.cost-sheets.approve', $costSheet) }}" class="d-inline" onsubmit="return confirm('Approve this cost sheet? It cannot be edited afterwards.');">
                                @csrf
                                <button type="submit" class="btn btn-sm ui-act ui-act-approve" title="Approve"><i class="fa-solid fa-check"></i> Approve</button>
                            </form>
                        @endcan
                    @endif
                    <a href="{{ route('msfl.cost-sheets.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row small">
                    <div class="col-md-3"><strong>Tech Pack:</strong>
                        @if($costSheet->style)<a href="{{ route('msfl.styles.show', $costSheet->style) }}">{{ $costSheet->style->label() }}</a>@else <span class="text-muted">none</span> @endif
                    </div>
                    <div class="col-md-3"><strong>Inquiry:</strong>
                        @if($costSheet->inquiry)<a href="{{ route('msfl.inquiries.show', $costSheet->inquiry) }}">{{ $costSheet->inquiry->inquiry_no }}</a>@else <span class="text-muted">none</span> @endif
                    </div>
                    <div class="col-md-3"><strong>CM basis:</strong>
                        SMV {{ $costSheet->smv ?? '-' }} · CPM {{ $costSheet->cm_minute_rate !== null ? number_format((float) $costSheet->cm_minute_rate, 4) : '-' }} · Eff {{ $costSheet->efficiency_percent !== null ? (float) $costSheet->efficiency_percent . '%' : '-' }}
                    </div>
                    <div class="col-md-3"><strong>Approved:</strong>
                        {{ $costSheet->approver ? $costSheet->approver->name . ', ' . $costSheet->approved_at->format('d M Y') : '-' }}
                    </div>
                </div>
            </div>
        </div>
    @endunless

    <div class="{{ $printMode ? '' : 'card' }}">
        <div class="{{ $printMode ? '' : 'card-body' }}">
            <div class="{{ $printMode ? '' : 'table-responsive' }}">
                <div style="min-width: {{ $printMode ? 'auto' : '900px' }};">
                    @include('merchandising-sfl::admin.cost-sheets.partials.sheet', ['letterhead' => ! $printMode])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
