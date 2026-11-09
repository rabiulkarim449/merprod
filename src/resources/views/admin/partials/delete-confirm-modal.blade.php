{{-- props: modalId (string), label (string, e.g. "part") --}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="{{ $modalId }}Form">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title">Delete {{ ucfirst($label) }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete this {{ $label }}? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                    <button type="submit" class="btn btn-sm ui-act ui-act-delete" title="Delete"><i class="fa-solid fa-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('js')
<script>
    $('#{{ $modalId }}').on('show.bs.modal', function (event) {
        const action = $(event.relatedTarget).data('action');
        $('#{{ $modalId }}Form').attr('action', action);
    });
</script>
@endpush
