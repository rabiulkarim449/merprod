@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Edit Order — ' . $order->order_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'Edit Order — ' . $order->order_no }}</h4>
            <a href="{{ route('msfl.orders.show', $order) }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            @if($order->status === 'confirmed')
                <div class="alert alert-info py-2 small">This order is confirmed — a change of a PO's qty, PCD or shipment date is saved as a buyer revision (T&amp;A Sheet). A PO that already has production or store work cannot be removed.</div>
            @endif
            <form method="POST" action="{{ route('msfl.orders.update', $order) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('merchandising-sfl::admin.orders.partials.form')
                @include('merchandising-sfl::admin.orders.partials.po-form')
                <button type="submit" class="btn mt-3 btn-sm ui-act ui-act-save" title="Update Order"><i class="fa-solid fa-floppy-disk"></i> Update Order</button>
                <a href="{{ route('msfl.orders.show', $order) }}" class="btn mt-3 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
