@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Sync from Inventory') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Sync from Inventory</h4>
            @can('msfl_inventory_sync.sync')
                @if($available && ($pendingBuyers->count() || $pendingStyles->count()))
                    <form method="POST" action="{{ route('msfl.inventory-sync.sync') }}" onsubmit="return confirm('{{ $pendingBuyers->count() }} buyer(s) + {{ $pendingStyles->count() }} style(s) নিচে লিস্ট করা হয়েছে — সব Merchandising-এ তৈরি করা হবে। চালিয়ে যাবেন?');">
                        @csrf
                        <button type="submit" class="btn btn-sm ui-act ui-act-reset" title="Sync Now"><i class="fa-solid fa-rotate-left"></i> Sync Now</button>
                    </form>
                @endif
            @endcan
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Inventory-তে আগে থেকেই যেসব Buyer আর Style (GRN/FG Receive-এ টেক্সট হিসেবে) আছে, কিন্তু Merchandising-এর নিজের Buyer/Style master-এ এখনো নেই — সেগুলো এখানে এক ক্লিকে তৈরি করে ফেলা যাবে।
                নতুন করে টাইপ করতে হবে না। Buyer auto-approved হয়ে যাবে (ইতিমধ্যে Inventory-তে real/ব্যবহৃত বলে), আলাদা Approval লাগবে না।
            </p>

            @if(! $available)
                <div class="alert alert-info">Inventory module (sfl-inventory) এই সিস্টেমে ইনস্টল করা নেই — sync করার কিছু নেই।</div>
            @else
                <h6>Pending Buyers <span class="badge badge-secondary">{{ $pendingBuyers->count() }}</span></h6>
                @if($pendingBuyers->isEmpty())
                    <p class="text-muted small">Inventory-র সব Buyer-ই আগে থেকে Merchandising-এ আছে — কিছু বাকি নেই।</p>
                @else
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm">
                            <thead><tr><th>Code</th><th>Name</th><th>Address</th><th>Contact</th></tr></thead>
                            <tbody>
                                @foreach($pendingBuyers as $b)
                                    <tr><td>{{ $b->code }}</td><td>{{ $b->name }}</td><td>{{ $b->address }}</td><td>{{ $b->contact }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <h6>Pending Styles <span class="badge badge-secondary">{{ $pendingStyles->count() }}</span></h6>
                @if($pendingStyles->isEmpty())
                    <p class="text-muted small">Inventory-র GRN/FG Receive-এ থাকা সব Style-ই আগে থেকে Merchandising-এ আছে (অথবা কোনো Buyer-এর জন্য এখনো মিল নেই) — কিছু বাকি নেই।</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead><tr><th>Style No (Inventory text)</th><th>Buyer (Merchandising)</th></tr></thead>
                            <tbody>
                                @foreach($pendingStyles as $s)
                                    <tr><td>{{ $s->style_no }}</td><td>{{ $buyerNames[$s->buyer_id] ?? '-' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
