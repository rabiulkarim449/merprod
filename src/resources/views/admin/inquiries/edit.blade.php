@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Edit Inquiry — ' . $inquiry->inquiry_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'Edit Inquiry — ' . $inquiry->inquiry_no }}</h4>
            <a href="{{ route('msfl.inquiries.show', $inquiry) }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.inquiries.update', $inquiry) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('merchandising-sfl::admin.inquiries.partials.form')
                <button type="submit" class="btn mt-3 btn-sm ui-act ui-act-save" title="Update Inquiry"><i class="fa-solid fa-floppy-disk"></i> Update Inquiry</button>
                <a href="{{ route('msfl.inquiries.show', $inquiry) }}" class="btn mt-3 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
