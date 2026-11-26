{{--
    Marketing Sample QA Submission Form (copy of the client's Word form), used by Show and Print.
    Needs: $sample, $form (SampleQaForm, may be unsaved), $facts (read from the system by SampleQaFormController::data()).
--}}
@php
    $F = \ME\MerchandisingSfl\Models\SampleQaForm::class;
    $logo = report_logo_url();
    $company = general()->title ?? 'Suhana Fashions Limited';
    $box = fn (bool $on, string $label) => '<span class="qa-box">' . ($on ? '&#9745;' : '&#9744;') . ' ' . e($label) . '</span>';
    $boxes = fn (array $list, $picked) => collect($list)->map(fn ($l, $k) => $box(in_array($k, (array) $picked, true), $l))->implode(' ');
    $date = fn ($d) => $d ? $d->format('d M Y') : '';
    $checks = $form->support_checks ?? [];
    $points = $form->checkpoints ?? [];
    $top = function () use ($logo) {
        return '<div class="qa-logo">' . ($logo ? '<img src="' . e($logo) . '" alt="" onerror="this.remove()">' : '') . '</div>';
    };
@endphp
<div class="qa-form">
    {{-- Page 1 --}}
    <div class="qa-page">
        <div class="qa-top">
            {!! $top() !!}
            <div class="qa-titles">
                <div class="qa-company">{{ $company }}</div>
                <div class="qa-doc">MARKETING SAMPLE QA SUBMISSION FORM</div>
            </div>
            <div class="qa-tag">{{ $company }} &nbsp;|&nbsp; Quality Assurance</div>
        </div>

        <table>
            <colgroup><col style="width:13%"><col style="width:37%"><col style="width:13%"><col style="width:37%"></colgroup>
            <tr><td class="qa-lbl">Customer / Prospect</td><td>{{ $facts->customer }}</td><td class="qa-lbl">Brand / Market</td><td>{{ $form->brand_market }}</td></tr>
            <tr><td class="qa-lbl">Style / Sample ID</td><td>{{ $facts->style_sample }}</td><td class="qa-lbl">Product category</td><td>{{ $facts->product_category }}</td></tr>
            <tr><td class="qa-lbl">Po No. #</td><td>{{ $facts->po_no }}</td><td class="qa-lbl">Color / Size</td><td>{{ $facts->color_size }}</td></tr>
            <tr><td class="qa-lbl">Sample quantity</td><td>{{ $facts->qty }}</td><td class="qa-lbl">Sample type</td><td>{{ $facts->sample_type }}</td></tr>
            <tr><td class="qa-lbl">Marketing purpose</td><td>{{ $form->marketing_purpose }}</td><td class="qa-lbl">Meeting / Event</td><td>{{ $form->meeting_event }}</td></tr>
            <tr><td class="qa-lbl">Required date</td><td>{{ $date($facts->required_date) }}</td><td class="qa-lbl">QA inspection date</td><td>{{ $date($form->qa_inspection_date) }}</td></tr>
            <tr><td class="qa-lbl">Custodian / Owner</td><td>{{ $form->custodian }}</td><td class="qa-lbl">Return due date</td><td>{{ $date($form->return_due_date) }}</td></tr>
            <tr><td class="qa-lbl">Costing ref.</td><td>{{ $facts->costing_ref }}</td><td class="qa-lbl">Tech pack / Design ref.</td>
                <td>@if($facts->tech_pack_url && empty($print))<a href="{{ $facts->tech_pack_url }}" target="_blank">{{ $facts->tech_pack }}</a>@else{{ $facts->tech_pack }}@endif</td></tr>
        </table>

        <div class="qa-section">Commercial Presentation Objective</div>
        <table>
            <colgroup><col style="width:16%"><col style="width:34%"><col style="width:16%"><col style="width:34%"></colgroup>
            <tr><td class="qa-lbl">Target impression</td><td>{!! $boxes($F::TARGET_IMPRESSION, $form->target_impression) !!}</td>
                <td class="qa-lbl">Customer use</td><td>{!! $boxes($F::CUSTOMER_USE, $form->customer_use) !!}</td></tr>
            <tr><td class="qa-lbl">Key selling story</td><td>@if($form->key_selling_story){!! nl2br(e($form->key_selling_story)) !!}@else<span class="qa-line"></span>@endif</td>
                <td class="qa-lbl">Range coordination</td><td>{!! $boxes($F::RANGE_COORDINATION, $form->range_coordination) !!}</td></tr>
            <tr><td class="qa-lbl">Overall QA status</td><td>{!! $boxes($F::QA_STATUS, [$form->qa_status]) !!}</td>
                <td class="qa-lbl">Ownership</td><td>{!! $boxes($F::OWNERSHIP, [$form->ownership]) !!}</td></tr>
        </table>

        <div class="qa-section">Reference and Commercial Support Check</div>
        <table>
            @foreach(array_chunk($F::SUPPORT_CHECKS, 4, true) as $row)
                <tr>
                    @foreach($row as $k => $label)
                        @php $c = $checks[$k] ?? []; @endphp
                        <td class="qa-light">{!! $box(! empty($c['checked']), $label) !!} &nbsp;Ref: <span class="qa-ref">{{ $c['ref'] ?? '' }}</span></td>
                    @endforeach
                </tr>
            @endforeach
            <tr><td colspan="4" class="qa-note">Marketing sample standard: the sample must represent SFL capability at customer-facing quality. Do not release a sample with visible stains, loose threads, poor pressing, wrong branding, missing identity, unsafe hardware or a presentation defect.</td></tr>
        </table>
    </div>

    {{-- Page 2 --}}
    <div class="qa-page qa-page-2">
        <div class="qa-top">
            {!! $top() !!}
            <div class="qa-titles"></div>
            <div class="qa-tag">{{ $company }} &nbsp;|&nbsp; Quality Assurance</div>
        </div>

        <div class="qa-section qa-plain">Appearance, workmanship and customer-facing readiness</div>
        <table class="qa-cp">
            <colgroup><col style="width:4%"><col style="width:17%"><col style="width:26%"><col style="width:31%"><col style="width:11%"><col style="width:11%"></colgroup>
            <tr><th>No.</th><th>Checkpoint</th><th>Requirement / reference</th><th>QA observation</th><th>Result</th><th>Defect class</th></tr>
            @foreach($F::CHECKPOINTS as $no => [$name, $req])
                @php $p = $points[$no] ?? []; @endphp
                <tr>
                    <td class="qa-no">{{ $no }}</td>
                    <td><strong>{{ $name }}</strong></td>
                    <td>{{ $req }}</td>
                    <td>{!! nl2br(e($p['observation'] ?? '')) !!}</td>
                    <td>{!! $boxes($F::RESULTS, [$p['result'] ?? null]) !!}</td>
                    <td>{!! $boxes($F::DEFECT_CLASSES, [$p['defect_class'] ?? null]) !!}</td>
                </tr>
            @endforeach
        </table>

        <div class="qa-section">Final Release Decision</div>
        <table class="qa-sign">
            <tr>@foreach($F::SIGNATORIES as $who)<th>{{ $who }}</th>@endforeach</tr>
            <tr>@foreach($F::SIGNATORIES as $who)<td>Name: <span class="qa-fill" style="min-width:75%"></span></td>@endforeach</tr>
            <tr>@foreach($F::SIGNATORIES as $who)<td>Designation: <span class="qa-fill" style="min-width:62%"></span></td>@endforeach</tr>
            <tr>@foreach($F::SIGNATORIES as $who)<td>Signature: <span class="qa-fill" style="min-width:42%"></span> Date: <span class="qa-fill" style="min-width:18%"></span></td>@endforeach</tr>
        </table>
    </div>
</div>
