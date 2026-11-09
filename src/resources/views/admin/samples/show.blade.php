@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Sample ' . $sample->sample_no }}@else
    <title>{{ websiteTitle('Sample ' . $sample->sample_no) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Sample — ' . $sample->sample_no, 'printPage' => 'A4 portrait'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Sample — {{ $sample->sample_no }} <small class="text-muted">{{ $sample->sampleType->name ?? '' }} · Rev {{ $sample->revision_no }}</small></h4>
            <div>
                @can('msfl_sample.edit')
                    @if($sample->status === 'requested')
                        <form method="POST" action="{{ route('msfl.samples.status', $sample) }}" class="d-inline">
                            @csrf <input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="btn btn-sm ui-act ui-act-start" title="Start Making"><i class="fa-solid fa-play"></i> Start Making</button>
                        </form>
                    @endif
                    @if($sample->isEditable())
                        <button type="button" class="btn btn-sm ui-act ui-act-save" data-toggle="modal" data-target="#submitSampleModal" title="Submit to Buyer"><i class="fa-solid fa-floppy-disk"></i> Submit to Buyer</button>
                    @endif
                @endcan
                @can('msfl_sample.approve')
                    @if($sample->status === 'submitted')
                        <button type="button" class="btn btn-sm ui-act ui-act-approve" data-toggle="modal" data-target="#decideSampleModal" title="Buyer Decision"><i class="fa-solid fa-check"></i> Buyer Decision</button>
                    @endif
                @endcan
                @can('msfl_sample.edit')
                    @if($sample->isEditable())
                        <a href="{{ route('msfl.samples.edit', $sample) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                        <form method="POST" action="{{ route('msfl.samples.status', $sample) }}" class="d-inline" onsubmit="return confirm('Cancel this sample?');">
                            @csrf <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        </form>
                    @endif
                @endcan
                @include('merchandising-sfl::admin.partials.print-button')
                <a href="{{ route('msfl.samples.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><strong>Style:</strong> <a href="{{ route('msfl.styles.show', $sample->style) }}">{{ $sample->style->label() }}</a></div>
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $sample->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Order:</strong>
                    @if($sample->order)<a href="{{ route('msfl.orders.show', $sample->order) }}">{{ $sample->order->order_no }}</a>@else - @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Status:</strong> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $sample])
                    @if($sample->isOverdue())<span class="badge badge-danger">Overdue</span>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Qty:</strong> {{ $sample->qty }} pcs</div>
                <div class="col-md-3 mb-2"><strong>Size(s) / Color(s):</strong> {{ $sample->size_ref ?? '-' }} / {{ $sample->color_ref ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Merchandiser:</strong> {{ $sample->merchandiser->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Attachment:</strong>
                    @if($sample->attachment)<a href="{{ $sample->attachmentUrl() }}" target="_blank">View</a>@else - @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Requested:</strong> {{ $sample->request_date->format('d M Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Required By:</strong> {{ optional($sample->required_date)->format('d M Y') ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Submitted:</strong> {{ optional($sample->submit_date)->format('d M Y') ?? '-' }}
                    @if($sample->courier_name || $sample->tracking_no)<small class="text-muted">({{ $sample->courier_name }} {{ $sample->tracking_no }})</small>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Decision:</strong> {{ optional($sample->decision_date)->format('d M Y') ?? '-' }}
                    @if($sample->decider)<small class="text-muted">by {{ $sample->decider->name }}</small>@endif
                </div>
                @if($sample->parent)
                    <div class="col-md-3 mb-2"><strong>Revision of:</strong> <a href="{{ route('msfl.samples.show', $sample->parent) }}">{{ $sample->parent->sample_no }}</a></div>
                @endif
                @if($sample->revisions->isNotEmpty())
                    <div class="col-md-3 mb-2"><strong>Next Revision:</strong>
                        @foreach($sample->revisions as $revision)<a href="{{ route('msfl.samples.show', $revision) }}">{{ $revision->sample_no }}</a>@endforeach
                    </div>
                @endif
                @if($sample->remarks)
                    <div class="col-md-6 mb-2"><strong>Remarks:</strong> {!! nl2br(e($sample->remarks)) !!}</div>
                @endif
                @if($sample->buyer_comments)
                    <div class="col-md-6 mb-2"><strong>Buyer Comments:</strong> {!! nl2br(e($sample->buyer_comments)) !!}</div>
                @endif
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Comments</h6>
                @can('msfl_sample.edit')
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-toggle="modal" data-target="#commentModal" title="Add Comment"><i class="fa-solid fa-plus"></i> Add Comment</button>
                @endcan
            </div>
            <table class="table table-bordered table-sm align-middle">
                <thead><tr><th style="width:110px">Date</th><th style="width:90px">From</th><th>Comment</th><th style="width:160px">By</th><th style="width:80px">File</th></tr></thead>
                <tbody>
                    @forelse($sample->comments as $comment)
                        <tr>
                            <td>{{ $comment->comment_date->format('d M Y') }}</td>
                            <td><span class="badge badge-{{ $comment->is_buyer_comment ? 'warning' : 'light' }}">{{ $comment->is_buyer_comment ? 'Buyer' : 'Internal' }}</span></td>
                            <td>{!! nl2br(e($comment->comment)) !!}</td>
                            <td>{{ $comment->author->name ?? '-' }}</td>
                            <td>@if($comment->attachment)<a href="{{ $comment->attachmentUrl() }}" target="_blank">View</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No comments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('msfl_sample.edit')
    <div class="modal fade" id="submitSampleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('msfl.samples.submit', $sample) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Submit to Buyer</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Submit Date <span class="text-danger">*</span></label>
                            <input type="date" name="submit_date" class="form-control form-control-sm" value="{{ old('submit_date', today()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Courier</label>
                            <input type="text" name="courier_name" class="form-control form-control-sm" value="{{ old('courier_name') }}" placeholder="DHL, FedEx, Hand Carry …">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tracking No</label>
                            <input type="text" name="tracking_no" class="form-control form-control-sm" value="{{ old('tracking_no') }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Submit"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('msfl.samples.comments.store', $sample) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Comment</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="comment_date" class="form-control form-control-sm" value="{{ today()->format('Y-m-d') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comment <span class="text-danger">*</span></label>
                            <textarea name="comment" rows="4" class="form-control form-control-sm" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Attachment</label>
                            <input type="file" name="attachment" class="form-control form-control-sm">
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_buyer_comment" value="0">
                            <input type="checkbox" name="is_buyer_comment" value="1" class="custom-control-input" id="isBuyerComment">
                            <label class="custom-control-label" for="isBuyerComment">Buyer's comment</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcan

@can('msfl_sample.approve')
    <div class="modal fade" id="decideSampleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('msfl.samples.decide', $sample) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Buyer Decision</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" name="decision" value="approved" id="decisionApproved" class="custom-control-input" @checked(old('decision', 'approved') === 'approved')>
                                <label class="custom-control-label text-success" for="decisionApproved">Approved</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" name="decision" value="rejected" id="decisionRejected" class="custom-control-input" @checked(old('decision') === 'rejected')>
                                <label class="custom-control-label text-danger" for="decisionRejected">Rejected</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Decision Date <span class="text-danger">*</span></label>
                            <input type="date" name="decision_date" class="form-control form-control-sm" value="{{ old('decision_date', today()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Buyer Comments <small class="text-muted">(required when rejected)</small></label>
                            <textarea name="buyer_comments" rows="4" class="form-control form-control-sm">{{ old('buyer_comments') }}</textarea>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="create_revision" value="1" class="custom-control-input" id="createRevision" checked>
                            <label class="custom-control-label" for="createRevision">If rejected, request the next revision automatically</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save Decision"><i class="fa-solid fa-floppy-disk"></i> Save Decision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcan
@endsection
