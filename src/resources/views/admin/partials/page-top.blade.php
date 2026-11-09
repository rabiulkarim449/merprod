{{--
    Top of a v2 page that can also print itself (?print=1 → printMaster2):
    on screen alerts + ui-kit, in print the company header. props: printMode, printTitle, printSubtitle, printPage
--}}
@if($printMode ?? false)
    @include('merchandising-sfl::admin.partials.print-header', ['title' => $printTitle ?? '', 'subtitle' => $printSubtitle ?? null, 'page' => $printPage ?? 'A4 portrait'])
@else
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
@endif
