{{-- "এই পাতা কী কাজে" — help box from Support/PageHelp, keyed by the current route. Closed by default; only for users with msfl_page_help.view. --}}
@php
    $pageHelp = \ME\MerchandisingSfl\Support\PageHelp::current();
@endphp
@if($pageHelp && auth()->user()?->can('msfl_page_help.view'))
    <style>
        .msfl-help { border-left: 4px solid #17a2b8; background: #f3fbfd; font-size: .875rem; }
        .msfl-help > summary { cursor: pointer; padding: .5rem .75rem; font-weight: 600; color: #0c5460; list-style: none; }
        .msfl-help > summary::-webkit-details-marker { display: none; }
        .msfl-help > summary .msfl-help-toggle { float: right; font-weight: normal; color: #6c757d; }
        .msfl-help[open] > summary .msfl-help-toggle .when-closed, .msfl-help:not([open]) > summary .msfl-help-toggle .when-open { display: none; }
        .msfl-help .msfl-help-body { padding: 0 .75rem .75rem; }
        .msfl-help ol { padding-left: 1.25rem; margin-bottom: .5rem; }
        .msfl-help .msfl-help-flow span { display: inline-block; padding: 0 .4rem; border-radius: 3px; background: #e2e3e5; }
        .msfl-help .msfl-help-flow .current { background: #17a2b8; color: #fff; }
        .msfl-help-fields { width: 100%; margin-bottom: .75rem; border-collapse: collapse; }
        .msfl-help-fields th, .msfl-help-fields td { border: 1px solid #d7e8ec; padding: .3rem .5rem; vertical-align: top; text-align: left; }
        .msfl-help-fields th { background: #e8f6f9; font-size: .8rem; }
        .msfl-help-fields td:first-child { font-weight: 600; white-space: nowrap; }
        .msfl-help-fields-group td { background: #eef9fb; font-weight: 700; }
    </style>
    <details class="msfl-help card mb-3" data-help-key="{{ $pageHelp['key'] }}">
        <summary>
            <i class="fa-solid fa-circle-info"></i> এই পাতা কী কাজে — {{ $pageHelp['title'] }}
            <span class="msfl-help-toggle"><span class="when-open">লুকান ▲</span><span class="when-closed">দেখুন ▼</span></span>
        </summary>
        <div class="msfl-help-body">
            <p class="mb-2">{!! $pageHelp['what'] !!}</p>

            <div class="mb-1"><strong>কীভাবে ব্যবহার করবেন</strong></div>
            <ol>
                @foreach($pageHelp['steps'] as $step)
                    <li>{!! $step !!}</li>
                @endforeach
            </ol>

            @if(! empty($pageHelp['fields']))
                <div class="mb-1"><strong>প্রতিটা ফিল্ড — কোথা থেকে আসে, কী হিসাব</strong></div>
                <table class="msfl-help-fields">
                    <thead><tr><th style="width:170px">Field</th><th>Source / Calculation</th></tr></thead>
                    <tbody>
                        @foreach($pageHelp['fields'] as $group => $rows)
                            <tr class="msfl-help-fields-group"><td colspan="2">{{ $group }}</td></tr>
                            @foreach($rows as $label => $detail)
                                <tr><td>{{ $label }}</td><td>{!! $detail !!}</td></tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($pageHelp['tip'])
                <div class="alert alert-warning py-1 px-2 mb-2"><i class="fa-solid fa-lightbulb"></i> {!! $pageHelp['tip'] !!}</div>
            @endif

            @if(! empty($pageHelp['example']))
                <div class="mb-2"><strong>উদাহরণ:</strong> {!! $pageHelp['example'] !!}</div>
            @endif

            <div class="msfl-help-flow">
                <strong>Process-এ কোথায়:</strong>
                <span>{{ $pageHelp['before'] }}</span> →
                <span class="current">{{ $pageHelp['title'] }}</span> →
                <span>{{ $pageHelp['after'] }}</span>
            </div>
        </div>
    </details>
@endif
