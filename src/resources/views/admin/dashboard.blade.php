@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Merchandising Dashboard') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    @include('merchandising-sfl::admin.partials.dashboard-widget', ['full' => true])
</div>
@endsection
