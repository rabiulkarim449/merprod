{{-- Line Input modal: pieces given to a line from the cutting balance, size-wise. props: date, pos, lines, sizeRows, sizeNames --}}
@php $mine = old('_modal') === 'input'; @endphp
<div class="modal fade" id="sewInputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('msfl.production.sewing.input.store') }}" id="sewInputForm">
                @csrf
                <input type="hidden" name="_modal" value="input">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-arrow-right-to-bracket"></i> Line Input</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Pieces given to the line from the cutting balance (cut − still at embroidery − already given). Enter by date — as many times a day as the line is loaded.</p>
                    @if($mine && $errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
                    <div class="row">
                        @include('merchandising-sfl::admin.production.sewing.partials.header-fields', ['prefix' => 'sewInput', 'mine' => $mine])
                    </div>
                    <table class="table table-bordered table-sm align-middle mb-2">
                        <thead><tr><th style="width:90px">Size</th><th>Balance</th><th style="width:170px">Input (pcs)</th></tr></thead>
                        <tbody id="sewInputSizeRows"></tbody>
                        <tfoot><tr><td colspan="2" class="text-right"><strong>Total</strong></td><td><strong data-total="input_qty">0</strong></td></tr></tfoot>
                    </table>
                    <div class="row">
                        <div class="col-md-12"><input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks" value="{{ $mine ? old('remarks') : '' }}"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                    <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save Input"><i class="fa-solid fa-floppy-disk"></i> Save Input</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.production.sewing.partials.size-script', [
    'prefix' => 'sewInput', 'mine' => $mine,
    'fields' => ['input_qty' => 'Input'],
    'info' => "return 'Cutting balance: <strong>' + r.available + '</strong> · In lines now: ' + r.wip + ' · Order: ' + r.ordered;",
])
