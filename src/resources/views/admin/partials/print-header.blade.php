{{--
    Header of every v2 print (printMaster2), same look as the other packages' prints:
    logo, company name and address centered, then the document / report title.
    props: title, subtitle (optional, e.g. the period), page (optional @page size, default 'A4 portrait')
--}}
@include('merchandising-sfl::admin.partials.print-kit', ['page' => $page ?? 'A4 portrait'])
<div class="print-header">
    <div class="company-info" style="justify-content:center; flex-direction:column; align-items:center; text-align:center;">
        @if(report_logo_url())
            <img src="{{ report_logo_url() }}" alt="Logo" class="company-logo" style="margin-bottom:4px;">
        @endif
        <div class="company-name">{{ general()->title ?? '' }}</div>
        @if(general()?->address_one)
            <div class="company-address">{{ general()->address_one }}</div>
        @endif
    </div>
    <div class="msfl-print-title">{{ $title }}@if(! empty($subtitle)) <small>({{ $subtitle }})</small>@endif</div>
    <div class="print-time"><i>Printed {{ now()->format('d-m-Y H:i') }} · {{ auth()->user()?->name }}</i></div>
    <div style="clear:both"></div>
</div>
