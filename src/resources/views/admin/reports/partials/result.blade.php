{{-- props: result — one table, or several under 'sections' (each with a title) --}}
@if(isset($result['sections']))
    @foreach($result['sections'] as $section)
        <h6 class="report-section-title {{ $loop->first ? '' : 'mt-3' }}">{{ $section['title'] }}</h6>
        <div class="table-responsive">@include('merchandising-sfl::admin.reports.partials.table', ['result' => $section])</div>
    @endforeach
@else
    <div class="table-responsive">@include('merchandising-sfl::admin.reports.partials.table', ['result' => $result])</div>
@endif
