@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Learning Curve') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Learning Curve — Production <small class="text-muted">(সবাই দেখবে)</small></h4>
            @can('msfl_learning_curve.calculate')
                <a href="{{ route('msfl.learning-curve.calculate') }}" class="btn btn-sm btn-icon ui-act ui-act-nav" title="Calculator"><i class="fa-solid fa-calculator"></i></a>
            @endcan
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">SMV যত বেশি, operation তত জটিল — প্রথম দিনগুলোতে efficiency তত কম থাকে, আর সর্বোচ্চও কম ওঠে (কখনো ১০০% না)। নিচে প্রতিটা SMV band-এর Day 1–10 efficiency % — দরকার হলে বদলে দিন।</p>
            @include('merchandising-sfl::admin.learning-curve.partials.bands-table', ['bands' => $productionBands, 'editable' => true])
        </div>
    </div>

    @if($canShipment)
        <div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0">Learning Curve — Shipment <small class="text-muted">(শুধু Planning Manager)</small></h4>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">এটা বেশি conservative ধরে রাখা হিসাব — ডেলিভারি কমিটমেন্ট দেওয়ার আগে নিজে যাচাই করার জন্য, ফ্লোরে প্রকাশ করার জন্য না।</p>
                @include('merchandising-sfl::admin.learning-curve.partials.bands-table', ['bands' => $shipmentBands, 'editable' => true])
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Lead Time (সেলাই শেষের পর)</h4>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">সেলাই শেষ হওয়ার পর Wash/Finish-এ আরও এই কয়দিন লাগবে — Shipment calculator-এ ready-date বের করতে ব্যবহার হয়।</p>
                @foreach($leadTimes as $category => $rows)
                    <h6 class="text-capitalize">{{ $category }}</h6>
                    <table class="table table-bordered table-sm" style="max-width:420px">
                        <thead><tr><th>Name</th><th style="width:110px">Days</th><th style="width:70px"></th></tr></thead>
                        <tbody>
                            @foreach($rows as $lt)
                                <tr>
                                    <form id="lt-form-{{ $lt->id }}" method="POST" action="{{ route('msfl.learning-curve.lead-times.update', $lt) }}">
                                        @csrf @method('PUT')
                                    </form>
                                    <td class="align-middle">{{ $lt->name }}</td>
                                    <td><input type="number" min="0" max="60" name="days" value="{{ $lt->days }}" form="lt-form-{{ $lt->id }}" class="form-control form-control-sm"></td>
                                    <td><button type="submit" form="lt-form-{{ $lt->id }}" class="btn btn-sm btn-icon ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
