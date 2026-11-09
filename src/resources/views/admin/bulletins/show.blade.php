@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Bulletin ' . $bulletin->bulletin_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    @include('merchandising-sfl::admin.bulletins.partials.sheet-style')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Operation Bulletin — {{ $bulletin->bulletin_no }} <small class="text-muted">Rev {{ $bulletin->version }}</small> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $bulletin])</h4>
            <div>
                <a href="{{ route('msfl.bulletins.print', $bulletin) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i> Print</a>
                @if($bulletin->isEditable())
                    @can('msfl_bulletin.edit')
                        <a href="{{ route('msfl.bulletins.edit', $bulletin) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                    @endcan
                    @can('msfl_bulletin.approve')
                        <form method="POST" action="{{ route('msfl.bulletins.approve', $bulletin) }}" class="d-inline" onsubmit="return confirm('Approve this bulletin? It cannot be edited afterwards.');">
                            @csrf
                            <button type="submit" class="btn btn-sm ui-act ui-act-approve" title="Approve"><i class="fa-solid fa-check"></i> Approve</button>
                        </form>
                    @endcan
                @else
                    @can('msfl_bulletin.add')
                        <form method="POST" action="{{ route('msfl.bulletins.revise', $bulletin) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm ui-act ui-act-revise" title="Revise"><i class="fa-solid fa-code-branch"></i> Revise</button>
                        </form>
                    @endcan
                @endif
                <a href="{{ route('msfl.bulletins.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                @include('merchandising-sfl::admin.bulletins.partials.sheet', ['showInactive' => true])
            </div>

            @if($bulletin->line)
                @php $machineRows = $machineSummary->filter(fn ($r) => $r['required'] > 0 || ($r['available'] ?? 0) > 0); @endphp
                <h6 class="mt-3">Machines needed vs {{ $bulletin->line->name }}</h6>
                <table class="table table-bordered table-sm align-middle" style="max-width:600px">
                    <thead><tr><th>M/C</th><th>Name</th><th class="text-right">Required</th><th class="text-right">On Line</th><th class="text-right">Short</th></tr></thead>
                    <tbody>
                        @foreach($machineRows as $row)
                            <tr @class(['table-danger' => $row['shortage'] > 0])>
                                <td>{{ $row['code'] }}</td><td>{{ $row['name'] }} @if($row['is_helper'])<span class="badge badge-light">helper</span>@endif</td>
                                <td class="text-right">{{ $row['required'] }}</td>
                                <td class="text-right">{{ $row['available'] ?? '—' }}</td>
                                <td class="text-right font-weight-bold">{{ $row['shortage'] ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($bulletin->machineShortage() > 0)
                    <div class="alert alert-danger small">{{ $bulletin->line->name }} is short of {{ $bulletin->machineShortage() }} machine(s) for this style.</div>
                @else
                    <div class="alert alert-success small">{{ $bulletin->line->name }} has every machine this style needs.</div>
                @endif
            @endif
            @if($bulletin->remarks)<p><strong>Remarks:</strong> {{ $bulletin->remarks }}</p>@endif
            @if($bulletin->approver)<p class="small text-muted">Approved by {{ $bulletin->approver->name }}, {{ $bulletin->approved_at->format('d M Y') }}</p>@endif
        </div>
    </div>
</div>
@endsection
