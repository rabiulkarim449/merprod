{{-- props: model (uses HasStatus) --}}
<span class="badge badge-{{ $model->statusBadge() }}">{{ $model->statusLabel() }}</span>
