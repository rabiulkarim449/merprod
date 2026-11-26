@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Submission Form — ' . $sample->sample_no . ' — Edit') }}</title>
@endsection

@php
    $F = \ME\MerchandisingSfl\Models\SampleQaForm::class;
    $date = fn ($d) => $d ? $d->format('d M Y') : '-';
    $picked = fn (string $k) => (array) old($k, $form->{$k} ?? []);
    $checks = old('support_checks', $form->support_checks ?? []);
    $points = old('checkpoints', $form->checkpoints ?? []);
    $err = fn (string $k) => $errors->has($k) ? ' is-invalid' : '';
@endphp

@push('css')
<style>
    .qa-edit .qa-opts label { margin: 0 12px 0 0; font-weight: normal; white-space: nowrap; }
    .qa-edit .qa-opts input { margin-right: 3px; vertical-align: middle; }
    .qa-edit .qa-fact { background: #f3f6fa; }
    .qa-edit td.qa-req { font-size: 12px; color: #555; }
</style>
@endpush

@section('contents')
<div class="flex-grow-1 msfl-module qa-edit">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <form method="POST" action="{{ route('msfl.samples.qa-form.update', $sample) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-1">
                <h4 class="mb-0">Submission Form — {{ $sample->sample_no }}</h4>
                <a href="{{ route('msfl.samples.qa-form.show', $sample) }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger py-2">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                @endif

                <h6 class="font-weight-bold">From the sample <small class="text-muted">(change these on the Sample / Style)</small></h6>
                <table class="table table-bordered table-sm qa-fact mb-3">
                    <tr><th style="width:16%">Customer / Prospect</th><td style="width:34%">{{ $facts->customer ?: '-' }}</td><th style="width:16%">Product category</th><td>{{ $facts->product_category ?: '-' }}</td></tr>
                    <tr><th>Style / Sample ID</th><td>{{ $facts->style_sample ?: '-' }}</td><th>Color / Size</th><td>{{ $facts->color_size ?: '-' }}</td></tr>
                    <tr><th>Po No.</th><td>{{ $facts->po_no ?: '-' }}</td><th>Sample type</th><td>{{ $facts->sample_type ?: '-' }}</td></tr>
                    <tr><th>Sample quantity</th><td>{{ $facts->qty }}</td><th>Required date</th><td>{{ $date($facts->required_date) }}</td></tr>
                    <tr><th>Costing ref.</th><td>{{ $facts->costing_ref ?: '-' }}</td><th>Tech pack / Design ref.</th><td>{{ $facts->tech_pack ?: '-' }}</td></tr>
                </table>

                <h6 class="font-weight-bold">Sample details</h6>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label">Brand / Market</label>
                        <input type="text" name="brand_market" maxlength="150" class="form-control form-control-sm{{ $err('brand_market') }}" value="{{ old('brand_market', $form->brand_market) }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Marketing purpose</label>
                        <input type="text" name="marketing_purpose" maxlength="255" class="form-control form-control-sm{{ $err('marketing_purpose') }}" value="{{ old('marketing_purpose', $form->marketing_purpose) }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Meeting / Event</label>
                        <input type="text" name="meeting_event" maxlength="255" class="form-control form-control-sm{{ $err('meeting_event') }}" value="{{ old('meeting_event', $form->meeting_event) }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">QA inspection date</label>
                        <input type="date" name="qa_inspection_date" class="form-control form-control-sm{{ $err('qa_inspection_date') }}" value="{{ old('qa_inspection_date', optional($form->qa_inspection_date)->format('Y-m-d')) }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Custodian / Owner</label>
                        <input type="text" name="custodian" maxlength="150" class="form-control form-control-sm{{ $err('custodian') }}" value="{{ old('custodian', $form->custodian) }}"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Return due date</label>
                        <input type="date" name="return_due_date" class="form-control form-control-sm{{ $err('return_due_date') }}" value="{{ old('return_due_date', optional($form->return_due_date)->format('Y-m-d')) }}"></div>
                </div>

                <h6 class="font-weight-bold mt-2">Commercial presentation objective</h6>
                <table class="table table-bordered table-sm align-middle">
                    <tr><th style="width:18%">Target impression</th><td class="qa-opts">
                        @foreach($F::TARGET_IMPRESSION as $k => $l)<label><input type="checkbox" name="target_impression[]" value="{{ $k }}" @checked(in_array($k, $picked('target_impression'), true))>{{ $l }}</label>@endforeach</td></tr>
                    <tr><th>Customer use</th><td class="qa-opts">
                        @foreach($F::CUSTOMER_USE as $k => $l)<label><input type="checkbox" name="customer_use[]" value="{{ $k }}" @checked(in_array($k, $picked('customer_use'), true))>{{ $l }}</label>@endforeach</td></tr>
                    <tr><th>Key selling story</th><td>
                        <textarea name="key_selling_story" rows="2" maxlength="2000" class="form-control form-control-sm{{ $err('key_selling_story') }}">{{ old('key_selling_story', $form->key_selling_story) }}</textarea></td></tr>
                    <tr><th>Range coordination</th><td class="qa-opts">
                        @foreach($F::RANGE_COORDINATION as $k => $l)<label><input type="checkbox" name="range_coordination[]" value="{{ $k }}" @checked(in_array($k, $picked('range_coordination'), true))>{{ $l }}</label>@endforeach</td></tr>
                    <tr><th>Overall QA status</th><td class="qa-opts">
                        @foreach($F::QA_STATUS as $k => $l)<label><input type="radio" name="qa_status" value="{{ $k }}" @checked(old('qa_status', $form->qa_status) === $k)>{{ $l }}</label>@endforeach
                        <label class="text-muted"><input type="radio" name="qa_status" value="" @checked(! old('qa_status', $form->qa_status))>None</label></td></tr>
                    <tr><th>Ownership</th><td class="qa-opts">
                        @foreach($F::OWNERSHIP as $k => $l)<label><input type="radio" name="ownership" value="{{ $k }}" @checked(old('ownership', $form->ownership) === $k)>{{ $l }}</label>@endforeach
                        <label class="text-muted"><input type="radio" name="ownership" value="" @checked(! old('ownership', $form->ownership))>None</label></td></tr>
                </table>

                <h6 class="font-weight-bold mt-2">Reference and commercial support check</h6>
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th style="width:5%" class="text-center">✓</th><th style="width:30%">Check</th><th>Ref</th></tr></thead>
                    @foreach($F::SUPPORT_CHECKS as $k => $l)
                        <tr>
                            <td class="text-center"><input type="hidden" name="support_checks[{{ $k }}][checked]" value="0">
                                <input type="checkbox" id="sc_{{ $k }}" name="support_checks[{{ $k }}][checked]" value="1" @checked(! empty($checks[$k]['checked']))></td>
                            <td><label for="sc_{{ $k }}" class="mb-0">{{ $l }}</label></td>
                            <td><input type="text" name="support_checks[{{ $k }}][ref]" maxlength="255" class="form-control form-control-sm{{ $err('support_checks.' . $k . '.ref') }}" value="{{ $checks[$k]['ref'] ?? '' }}"></td>
                        </tr>
                    @endforeach
                </table>

                <h6 class="font-weight-bold mt-2">Appearance, workmanship and customer-facing readiness</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th style="width:4%">No.</th><th style="width:16%">Checkpoint</th><th style="width:24%">Requirement / reference</th><th>QA observation</th><th style="width:11%">Result</th><th style="width:11%">Defect class</th></tr></thead>
                        <tbody>
                            @foreach($F::CHECKPOINTS as $no => [$name, $req])
                                @php $p = $points[$no] ?? []; @endphp
                                <tr>
                                    <td class="text-center">{{ $no }}</td>
                                    <td><strong>{{ $name }}</strong></td>
                                    <td class="qa-req">{{ $req }}</td>
                                    <td><textarea name="checkpoints[{{ $no }}][observation]" rows="2" maxlength="1000" class="form-control form-control-sm{{ $err('checkpoints.' . $no . '.observation') }}">{{ $p['observation'] ?? '' }}</textarea></td>
                                    <td><select name="checkpoints[{{ $no }}][result]" class="form-control form-control-sm">
                                        <option value="">—</option>
                                        @foreach($F::RESULTS as $k => $l)<option value="{{ $k }}" @selected(($p['result'] ?? null) === $k)>{{ $l }}</option>@endforeach
                                    </select></td>
                                    <td><select name="checkpoints[{{ $no }}][defect_class]" class="form-control form-control-sm">
                                        <option value="">—</option>
                                        @foreach($F::DEFECT_CLASSES as $k => $l)<option value="{{ $k }}" @selected(($p['defect_class'] ?? null) === $k)>{{ $l }}</option>@endforeach
                                    </select></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mb-3">Final Release Decision (Quality Controller, QC Manager, Sample Incharge, Production Manager) is signed by hand on the printed form.</div>

                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                    <a href="{{ route('msfl.samples.qa-form.show', $sample) }}" class="btn btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
