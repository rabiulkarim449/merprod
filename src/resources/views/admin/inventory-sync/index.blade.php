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

                <hr>
                <h6>Pending Items (Fabric &amp; Trims) <span class="badge badge-secondary">{{ $pendingItems->count() }}</span></h6>
                <p class="text-muted small mb-2">
                    Store-এর যে item এখনো Merchandising-এর Items-এ নেই — Sync করলে Merchandising Item তৈরি হবে আর Store item-এর সাথে যুক্ত থাকবে
                    (BOM-এ বাছাই করা যাবে, MRP সেই সংযোগ দিয়ে Store-এর stock / purchase / GRN পড়বে)। একই Code-এর Merchandising item আগে থেকে থাকলে নতুন না বানিয়ে শুধু যুক্ত করা হবে।
                    শুধু টিক দেওয়া category-র item আসবে।
                </p>
                @if($itemCategories->isEmpty())
                    <p class="text-muted small">Store-এর সব item-ই আগে থেকে Merchandising-এর সাথে যুক্ত — কিছু বাকি নেই।</p>
                @else
                    <form method="GET" class="mb-2">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach($itemCategories as $c)
                                <div class="custom-control custom-checkbox mr-3">
                                    <input type="checkbox" class="custom-control-input" name="cats[]" value="{{ $c->id }}" id="cat{{ $c->id }}" @checked(in_array($c->id, $pickedCategories, true))>
                                    <label class="custom-control-label" for="cat{{ $c->id }}">{{ $c->name }} <span class="text-muted">({{ $c->pending }})</span></label>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Show"><i class="fa-solid fa-filter"></i> Show</button>
                    </form>

                    @if($pendingItems->isNotEmpty())
                        @can('msfl_inventory_sync.sync')
                            <form method="POST" action="{{ route('msfl.inventory-sync.items') }}" class="mb-2" onsubmit="return confirm('{{ $pendingItems->count() }} item Merchandising-এ তৈরি / যুক্ত করা হবে। চালিয়ে যাবেন?');">
                                @csrf
                                @foreach($pickedCategories as $id)<input type="hidden" name="cats[]" value="{{ $id }}">@endforeach
                                <button type="submit" class="btn btn-sm ui-act ui-act-reset" title="Sync Items"><i class="fa-solid fa-rotate-left"></i> Sync {{ $pendingItems->count() }} Items</button>
                            </form>
                        @endcan
                        <div class="table-responsive" style="max-height: 50vh; overflow: auto;">
                            <table class="table table-bordered table-sm">
                                <thead><tr><th>#</th><th>Code</th><th>Name</th><th>Category</th><th>Unit</th></tr></thead>
                                <tbody>
                                    @foreach($pendingItems as $i)
                                        <tr><td>{{ $loop->iteration }}</td><td>{{ $i->item_code }}</td><td>{{ $i->item_name }}</td><td>{{ $i->category }}</td><td>{{ $i->unit }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted small">কোনো category টিক দেওয়া নেই।</p>
                    @endif
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
