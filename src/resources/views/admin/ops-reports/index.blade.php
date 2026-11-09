@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Operations Reports') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Operations Reports</h4></div>
        <div class="card-body">
            <div class="row">
                @foreach($reports as $key => $r)
                    <div class="col-md-6 col-lg-4 mb-3">
                        <a href="{{ route('msfl.ops-reports.show', $key) }}" class="card h-100 text-decoration-none" style="border-left:4px solid #0f2a4a">
                            <div class="card-body py-2">
                                <h6 class="mb-1" style="color:#0f2a4a">{{ $r->title() }}</h6>
                                <div class="small text-muted">{{ $r->department() }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
