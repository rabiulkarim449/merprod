<?php

namespace ME\MerchandisingSfl\Database\Seeders;

use App\Models\Approval;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use ME\MerchandisingSfl\Models as M;
use RuntimeException;

/**
 * Demo data for trying every Merchandising v2 process, inquiry → Finish Store,
 * with orders left at different points (all codes start with "D-"):
 *
 *   H&M      ORD 1: PO D-HM-4501 fully produced, packed & in the Finish Store
 *                   PO D-HM-4502 revised after confirm (400→450), in sewing
 *   Primark  ORD 2: PO D-PR-7701 with embroidery (Front sent / partly back to cutting), sewing on line 2
 *   Next     ORD 3: PO D-NX-9901 confirmed, fabric requisition waiting, no cutting
 *   H&M      ORD 4: draft order (shorts)
 *   Zara     new buyer waiting for approval
 * plus samples in every state, cost sheets, BOMs, bulletins, T&A plans,
 * Inventory receive / requisition / issue against the orders.
 *
 * Entered through the application's own routes (same rules, numbering,
 * approvals and side effects as the screens), in one transaction.
 *   php artisan db:seed --class="ME\MerchandisingSfl\Database\Seeders\MsflDemoSeeder"
 */
class MsflDemoSeeder extends Seeder
{
    private $kernel;

    private $session;

    private User $user;

    public function run(): void
    {
        if (M\Buyer::withTrashed()->where('code', 'D-HM')->exists()) {
            $this->command?->warn('Demo data is already there (buyer D-HM) — nothing done.');

            return;
        }
        if (! class_exists(\ME\SflInventory\Models\InvItem::class)) {
            throw new RuntimeException('The Inventory package is needed for the demo (stores, receive, issue).');
        }

        $this->user = User::query()->where('permission_id', 1)->orderBy('id')->firstOrFail(); // a Super Admin
        auth()->setUser($this->user);
        Gate::before(fn () => true);
        Mail::fake();          // approvals would otherwise email the approvers
        Notification::fake();
        app()->instance('middleware.disable', true);
        Event::listen(RouteMatched::class, function ($e) {
            app('router')->substituteBindings($e->route);
            app('router')->substituteImplicitBindings($e->route);
        });
        $this->kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $this->session = app('session.store');

        DB::transaction(fn () => $this->seed());
        app()->instance('middleware.disable', false);

        $this->command?->info('Merchandising v2 demo data created (codes start with "D-").');
    }

    /** Send a form to a route like the screen does; stop on the first error. */
    private function post(string $route, array $params = [], array $data = [], string $method = 'POST'): void
    {
        $this->session->flush();
        $request = Request::create(route($route, $params, false), $method, $data);
        $request->setLaravelSession($this->session);
        $response = $this->kernel->handle($request);
        $error = $this->session->get('errors')?->first() ?: $this->session->get('error');

        // "Saved, but …" (e.g. a T&A whose dates the lines can't meet) is a warning, not a failure.
        $savedWithWarning = $error && ! $this->session->get('errors') && str_contains($error, 'created');
        if ($response->getStatusCode() >= 400 || ($error && ! $savedWithWarning)) {
            throw new RuntimeException("{$route}: " . ($error ?: 'HTTP ' . $response->getStatusCode() . ' ' . substr(strip_tags($response->getContent()), 0, 300)));
        }
        if ($savedWithWarning) {
            $this->command?->warn('  ! ' . $error);

            return;
        }
        $this->command?->line('  ✓ ' . ($this->session->get('success') ?? $route));
    }

    private function master(string $master, array $data): void
    {
        $this->post('msfl.masters.store', ['master' => $master], $data + ['is_active' => 1]);
    }

    private function approve(string $type, int $id): void
    {
        $approval = Approval::query()->pending()->where('approvable_type', $type)->where('approvable_id', $id)->firstOrFail();
        app(ApprovalService::class)->approve($approval, $this->user, 'Demo approval');
    }

    private function d(int $daysAgo): string
    {
        return today()->subDays($daysAgo)->toDateString();
    }

    private function seed(): void
    {
        $sizes = M\Size::query()->whereIn('name', ['S', 'M', 'L', 'XL'])->pluck('id', 'name');
        $color = fn (string $name) => M\Color::query()->where('name', $name)->value('id') ?? M\Color::query()->value('id');
        $unit = fn (string $short) => M\Uom::query()->where('short_name', $short)->value('id');

        // ── Master data that Merchandising owns ─────────────────────────────
        foreach ([['D-HM', 'H&M', 'Sweden'], ['D-PRI', 'Primark', 'Ireland'], ['D-NXT', 'Next', 'United Kingdom'], ['D-ZRA', 'Zara', 'Spain']] as [$code, $name, $country]) {
            $this->master('buyers', ['code' => $code, 'name' => $name, 'country' => $country, 'merchandiser_id' => $this->user->id, 'delivery_term' => 'FOB', 'payment_term_id' => \ME\MerchandisingSfl\Models\Commercial\PaymentTerm::query()->where('code', 'LC-SIGHT')->value('id')]);
        }
        $buyer = M\Buyer::withoutGlobalScopes()->whereIn('code', ['D-HM', 'D-PRI', 'D-NXT', 'D-ZRA'])->pluck('id', 'code');
        foreach (['D-HM', 'D-PRI', 'D-NXT'] as $code) {
            $this->approve(M\Buyer::class, $buyer[$code]); // Zara stays waiting on the Approvals page
        }

        $this->master('seasons', ['code' => 'D-SS27', 'name' => 'Spring/Summer 2027', 'year' => 2027]);
        $this->master('seasons', ['code' => 'D-AW26', 'name' => 'Autumn/Winter 2026', 'year' => 2026]);
        $this->master('product-types', ['code' => 'D-BTM', 'name' => 'Bottom', 'category' => 'woven', 'default_smv' => 22]);
        $this->master('product-types', ['code' => 'D-TOP', 'name' => 'Top / Hoodie', 'category' => 'knit', 'default_smv' => 16]);
        $this->master('product-types', ['code' => 'D-JKT', 'name' => 'Jacket', 'category' => 'woven', 'default_smv' => 28]);
        $this->master('wash-types', ['code' => 'D-ENZ', 'name' => 'Enzyme Wash']);
        $this->master('wash-types', ['code' => 'D-STN', 'name' => 'Stone Wash']);
        $this->master('factories', ['code' => 'D-SFL1', 'name' => 'SFL Unit-1', 'capacity_per_month' => 120000, 'is_own' => 1]);
        $season = M\Season::query()->whereIn('code', ['D-SS27', 'D-AW26'])->pluck('id', 'code');
        $ptype = M\ProductType::query()->whereIn('code', ['D-BTM', 'D-TOP', 'D-JKT'])->pluck('id', 'code');
        $wash = M\WashType::query()->whereIn('code', ['D-ENZ', 'D-STN'])->pluck('id', 'code');
        $factory = M\Factory::query()->where('code', 'D-SFL1')->value('id');

        $this->master('item-categories', ['code' => 'D-FAB', 'name' => 'Fabric', 'type' => 'fabric']);
        $this->master('item-categories', ['code' => 'D-TRM', 'name' => 'Sewing Trims', 'type' => 'trims']);
        $this->master('item-categories', ['code' => 'D-PCK', 'name' => 'Packing', 'type' => 'packing']);
        $cat = M\ItemCategory::query()->whereIn('code', ['D-FAB', 'D-TRM', 'D-PCK'])->pluck('id', 'code');
        $items = [
            ['D-TWL', 'Cotton Twill 240 GSM', 'D-FAB', 'fabric', 'Yrd', 2.80], ['D-FLC', 'Cotton Fleece 280 GSM', 'D-FAB', 'fabric', 'KG', 6.50],
            ['D-DNM', 'Denim 12 oz', 'D-FAB', 'fabric', 'Yrd', 3.60], ['D-THR', 'Poly Thread 40/2', 'D-TRM', 'trims', 'CON', 0.90],
            ['D-ZIP', 'YKK Zipper #5', 'D-TRM', 'trims', 'PCS', 0.18], ['D-LBL', 'Main Label (Woven)', 'D-TRM', 'trims', 'PCS', 0.04],
            ['D-PLY', 'Poly Bag', 'D-PCK', 'packing', 'PCS', 0.03], ['D-CTN', 'Carton 5-ply', 'D-PCK', 'packing', 'PCS', 1.20],
        ];
        foreach ($items as [$code, $name, $c, $type, $u, $price]) {
            $this->master('items', ['code' => $code, 'name' => $name, 'category_id' => $cat[$c], 'type' => $type, 'uom_id' => $unit($u), 'default_price' => $price]);
        }
        $item = M\Item::query()->where('code', 'like', 'D-%')->get()->keyBy('code');

        // Lines (HR floor-lines + planning figures) and their machines (Inventory).
        $hrLines = DB::table('hr_floor_lines')->where('status', 'active')->orderBy('floor_name')->orderBy('line_name')->limit(3)->get();
        foreach ($hrLines as $i => $hr) {
            if (! M\Line::withTrashed()->where('hr_floor_line_id', $hr->id)->exists()) {
                $this->post('msfl.lines.store', [], ['hr_floor_line_id' => $hr->id, 'operators' => [32, 28, 30][$i], 'helpers' => 6, 'working_minutes' => 600, 'efficiency_percent' => [62, 58, 60][$i], 'is_active' => 1]);
            }
        }
        $lines = M\Line::query()->with('floorLine')->whereIn('hr_floor_line_id', $hrLines->pluck('id'))->get()->sortBy('name')->values();
        $machineNo = 1;
        foreach ($lines as $line) {
            // Same names as the planning machine types, so Inventory machines link to them.
            foreach (['Lock Stitch — 1 Needle' => 4, 'Overlock — 4 Thread' => 2, 'Flatlock' => 1, 'Button Hole' => 1] as $type => $count) {
                for ($n = 0; $n < $count; $n++) {
                    \ME\SflInventory\Models\InvMachine::create(['name' => $type, 'code' => sprintf('D-MC-%03d', $machineNo++), 'model' => 'Juki', 'origin' => 'Japan', 'type' => $type, 'line' => $line->code, 'is_active' => true, 'created_by' => $this->user->id]);
                }
            }
        }
        \ME\MerchandisingSfl\Services\InventoryMachines::flush();
        \ME\MerchandisingSfl\Services\InventoryMachines::syncTypes();

        // Inventory materials for the orders (Inventory owns item masters): raw materials
        // live in the Buyer Store, finished garments in the Finish Store.
        $buyerStore = DB::table('inv_stores')->where('type', 'raw_material')->whereNull('deleted_at')->value('id');
        $finishStore = DB::table('inv_stores')->where('type', 'finished_goods')->whereNull('deleted_at')->value('id');
        $invCat = fn (string $name) => DB::table('inv_item_categories')->where('name', $name)->value('id') ?? DB::table('inv_item_categories')->value('id');
        $invItems = [];
        foreach ([
            'twill' => ['D-INV-TWL', 'Cotton Twill 240 GSM (Fabric)', 'Fabrics', 'Yrd'], 'fleece' => ['D-INV-FLC', 'Cotton Fleece 280 GSM (Fabric)', 'Fabrics', 'KG'],
            'denim' => ['D-INV-DNM', 'Denim 12 oz (Fabric)', 'Fabrics', 'Yrd'], 'thread' => ['D-INV-THR', 'Poly Thread 40/2', 'Yarn/Thread', 'CON'],
            'zipper' => ['D-INV-ZIP', 'YKK Zipper #5', 'Trims', 'PCS'], 'label' => ['D-INV-LBL', 'Main Label (Woven)', 'Labels & Branding', 'PCS'],
            'poly' => ['D-INV-PLY', 'Poly Bag', 'Packaging', 'PCS'], 'carton' => ['D-INV-CTN', 'Carton 5-ply', 'Packaging', 'PCS'],
            'fg_chino' => ['D-FG-1001', "Men's Chino D-1001 (Finished)", 'Finished Goods', 'PCS'], 'fg_hoodie' => ['D-FG-1002', 'Kids Hoodie D-1002 (Finished)', 'Finished Goods', 'PCS'],
        ] as $key => [$code, $name, $category, $u]) {
            $invItems[$key] = \ME\SflInventory\Models\InvItem::create([
                'item_code' => $code, 'item_name' => $name, 'category_id' => $invCat($category), 'unit_id' => $unit($u),
                'item_type' => str_starts_with($key, 'fg_') ? 'finished_good' : 'raw_material', 'opening_store_id' => str_starts_with($key, 'fg_') ? $finishStore : $buyerStore,
                'is_active' => true, 'created_by' => $this->user->id,
            ])->id;
        }
        $cuttingDept = DB::table('inv_departments')->where('name', 'like', 'Cutting%')->value('id') ?? DB::table('inv_departments')->value('id');

        // ── Dev: inquiries, styles, cost sheets, samples ────────────────────
        $inquiries = [
            ['D-HM', 'D-SS27', 'D-BTM', 'Men\'s Chino D-1001', 1000, 9.40, 'confirmed', 40],
            ['D-PRI', 'D-AW26', 'D-TOP', 'Kids Hoodie D-1002', 800, 7.20, 'confirmed', 35],
            ['D-NXT', 'D-SS27', 'D-JKT', 'Ladies Denim Jacket D-1003', 500, 14.80, 'confirmed', 30],
            ['D-HM', 'D-SS27', 'D-BTM', 'Cargo Shorts D-1004', 300, 6.10, 'quoted', 10],
            ['D-PRI', 'D-SS27', 'D-TOP', 'Basic Tee', 3000, 1.85, 'lost', 25],
        ];
        $inquiryIds = [];
        foreach ($inquiries as $i => [$b, $s, $p, $ref, $qty, $price, $status, $ago]) {
            $this->post('msfl.inquiries.store', [], [
                'inquiry_date' => $this->d($ago), 'buyer_id' => $buyer[$b], 'season_id' => $season[$s], 'merchandiser_id' => $this->user->id, 'factory_id' => $factory,
                'product_type_id' => $ptype[$p], 'style_ref' => $ref, 'order_qty' => $qty, 'unit_price' => $price, 'confirmation_due_date' => $this->d($ago - 10),
                'target_ship_date' => today()->addDays(60)->toDateString(), 'status' => $status, 'lost_reason' => $status === 'lost' ? 'Price — buyer target 1.60' : null,
            ]);
            $inquiryIds[$i] = M\Inquiry::query()->latest('id')->value('id');
        }

        $styles = [
            'D-1001' => ["Men's Chino", 'D-HM', 0, 'D-SS27', 'D-BTM', 'D-ENZ', 22.0, 'approved'],
            'D-1002' => ['Kids Hoodie (front embroidery)', 'D-PRI', 1, 'D-AW26', 'D-TOP', null, 16.0, 'approved'],
            'D-1003' => ['Ladies Denim Jacket', 'D-NXT', 2, 'D-SS27', 'D-JKT', 'D-STN', 28.0, 'sample_stage'],
            'D-1004' => ['Cargo Shorts', 'D-HM', 3, 'D-SS27', 'D-BTM', null, 18.0, 'in_development'],
        ];
        foreach ($styles as $no => [$name, $b, $inq, $s, $p, $w, $smv, $dev]) {
            // A style is added once in Master Data → Styles; the tech pack then picks it.
            $this->master('styles', ['buyer_id' => $buyer[$b], 'style_no' => $no, 'name' => $name, 'season_id' => $season[$s], 'product_type_id' => $ptype[$p]]);
            $this->post('msfl.styles.store', [], [
                'style_id' => M\Style::query()->where('style_no', $no)->value('id'), 'inquiry_id' => $inquiryIds[$inq], 'season_id' => $season[$s], 'merchandiser_id' => $this->user->id,
                'product_type_id' => $ptype[$p], 'wash_type_id' => $w ? $wash[$w] : null, 'smv' => $smv, 'fabric_sourced_by' => 'self', 'development_status' => $dev, 'is_active' => 1,
            ]);
        }
        // A fifth style only in Master Data (no tech pack yet) — shows up in Tech Pack → New and in Inventory.
        $this->master('styles', ['buyer_id' => $buyer['D-NXT'], 'style_no' => 'D-1005', 'name' => 'Ladies Blouse', 'season_id' => $season['D-SS27'], 'product_type_id' => $ptype['D-TOP']]);
        $style = M\Style::query()->whereIn('style_no', array_keys($styles))->get()->keyBy('style_no');
        $usd = M\Currency::query()->where('code', 'USD')->value('id');

        // [style, fabric item, fabric cons/dz, rate, trims, process per dz, cm per dz, approve?]
        foreach ([
            ['D-1001', 'D-TWL', 15.6, 2.80, [['D-THR', 1.2, 0.90], ['D-ZIP', 12, 0.18], ['D-LBL', 12, 0.04], ['D-PLY', 12, 0.03]], 6.00, 24.0, true],
            ['D-1002', 'D-FLC', 5.4, 6.50, [['D-THR', 1.0, 0.90], ['D-LBL', 12, 0.04], ['D-PLY', 12, 0.03]], 9.60, 20.0, true],
            ['D-1003', 'D-DNM', 21.6, 3.60, [['D-THR', 1.5, 0.90], ['D-ZIP', 12, 0.18], ['D-LBL', 12, 0.04]], 14.4, 36.0, true],
            ['D-1004', 'D-TWL', 9.6, 2.80, [['D-THR', 0.8, 0.90], ['D-PLY', 12, 0.03]], 0, 18.0, false],
        ] as [$no, $fab, $cons, $rate, $trims, $process, $cm, $approve]) {
            $csLines = [['group' => 'fabric', 'item_id' => $item[$fab]->id, 'uom_id' => $item[$fab]->uom_id, 'consumption' => $cons, 'rate' => $rate]];
            foreach ($trims as [$t, $c, $r]) {
                $csLines[] = ['group' => 'trims', 'item_id' => $item[$t]->id, 'uom_id' => $item[$t]->uom_id, 'consumption' => $c, 'rate' => $r];
            }
            if ($process > 0) {
                $csLines[] = ['group' => 'process', 'description' => $no === 'D-1002' ? 'Front embroidery' : 'Garment wash', 'consumption' => 12, 'rate' => round($process / 12, 4)];
            }
            $this->post('msfl.cost-sheets.store', [], [
                'buyer_id' => $style[$no]->buyer_id, 'style_id' => $style[$no]->id, 'currency_id' => $usd, 'costing_date' => $this->d(28), 'order_qty' => 1000,
                'smv' => $style[$no]->smv, 'cm_cost' => $cm, 'commercial_percent' => 2, 'other_cost' => 0.6, 'profit_percent' => 6, 'items' => $csLines,
            ]);
            if ($approve) {
                $this->post('msfl.cost-sheets.approve', ['cost_sheet' => M\CostSheet::query()->latest('id')->value('id')]);
            }
        }

        $type = M\SampleType::query()->pluck('id', 'code');
        $sample = function (string $no, string $code, int $requestedAgo, ?int $submittedAgo, ?string $decision = null, bool $centrally = true) use ($style, $type) {
            $this->post('msfl.samples.store', [], ['style_id' => $style[$no]->id, 'sample_type_id' => $type[$code], 'request_date' => $this->d($requestedAgo), 'required_date' => $this->d($requestedAgo - 7), 'qty' => 2, 'size_ref' => 'M']);
            $s = M\Sample::query()->latest('id')->first();
            if ($submittedAgo === null) {
                return $s;
            }
            $this->post('msfl.samples.submit', ['sample' => $s->id], ['submit_date' => $this->d($submittedAgo), 'courier_name' => 'DHL', 'tracking_no' => 'DHL' . random_int(100000, 999999)]);
            if ($decision === 'approved' && $centrally) {
                $this->approve(M\Sample::class, $s->id);
            } elseif ($decision) {
                $this->post('msfl.samples.decide', ['sample' => $s->id], ['decision' => $decision, 'decision_date' => $this->d(max(0, $submittedAgo - 4)),
                    'buyer_comments' => $decision === 'rejected' ? 'Hood shape too narrow, increase 1 cm' : 'Approved', 'create_revision' => $decision === 'rejected' ? 1 : 0]);
            }

            return $s;
        };
        $sample('D-1001', 'FIT', 30, 26, 'approved');
        $sample('D-1001', 'PP', 18, 14, 'approved');
        $sample('D-1002', 'FIT', 28, 24, 'rejected');          // opens revision 1 (requested)
        $sample('D-1002', 'PP', 15, 11, 'approved', false);    // decided on the sample page
        $sample('D-1003', 'FIT', 20, 16, 'approved');
        $sample('D-1003', 'PP', 8, 3);                         // submitted, waiting on Approvals
        $this->post('msfl.samples.status', ['sample' => $sample('D-1003', 'SIZESET', 5, null)->id], ['status' => 'in_progress']);
        $sample('D-1004', 'PROTO', 4, null);

        // ── Orders, PO lines, BOMs ──────────────────────────────────────────
        $po = function (int $orderId, string $no, string $poNo, string $color, array $qty, int $shipIn, int $pcdIn, array $flags = []) use ($style, $sizes) {
            $this->post('msfl.orders.pos.store', ['order' => $orderId], [
                '_po_form' => 'new', 'style_id' => $style[$no]->id, 'color_id' => M\Color::query()->where('name', $color)->value('id'), 'po_no' => $poNo,
                'unit_price' => ['D-1001' => 9.40, 'D-1002' => 7.20, 'D-1003' => 14.80, 'D-1004' => 6.10][$no],
                'pcd_date' => today()->addDays($pcdIn)->toDateString(), 'shipment_date' => today()->addDays($shipIn)->toDateString(),
                'ship_mode_id' => M\ShipMode::query()->where('code', 'SEA')->value('id'),
                'sizes' => collect($qty)->mapWithKeys(fn ($q, $s) => [$sizes[$s] => $q])->all(),
            ] + $flags + array_fill_keys(['needs_embroidery', 'needs_washing', 'applique_ih', 'studs_stones_ih', 'heat_seal_ih'], 0));

            return M\OrderPo::query()->latest('id')->first();
        };
        $order = function (string $b, int $inq, int $ago) use ($buyer, $inquiryIds, $season, $factory, $usd) {
            $this->post('msfl.orders.store', [], ['buyer_id' => $buyer[$b], 'inquiry_id' => $inquiryIds[$inq], 'season_id' => $season['D-SS27'], 'merchandiser_id' => $this->user->id,
                'factory_id' => $factory, 'buyer_order_ref' => 'BPO-' . random_int(10000, 99999), 'order_date' => $this->d($ago), 'currency_id' => $usd, 'delivery_term' => 'FOB', 'payment_term_id' => \ME\MerchandisingSfl\Models\Commercial\PaymentTerm::query()->where('code', 'LC-SIGHT')->value('id')]);

            return M\Order::query()->latest('id')->first();
        };

        $o1 = $order('D-HM', 0, 25);
        $p4501 = $po($o1->id, 'D-1001', 'D-HM-4501', 'Navy', ['S' => 100, 'M' => 200, 'L' => 200, 'XL' => 100], 12, -6, ['needs_washing' => 1]);
        $p4502 = $po($o1->id, 'D-1001', 'D-HM-4502', 'Black', ['S' => 60, 'M' => 140, 'L' => 140, 'XL' => 60], 30, 4, ['needs_washing' => 1]);
        $o2 = $order('D-PRI', 1, 22);
        $p7701 = $po($o2->id, 'D-1002', 'D-PR-7701', 'Red', ['S' => 200, 'M' => 300, 'L' => 200, 'XL' => 100], 40, 2, ['needs_embroidery' => 1, 'applique_ih' => 1]);
        $o3 = $order('D-NXT', 2, 15);
        $p9901 = $po($o3->id, 'D-1003', 'D-NX-9901', 'Blue', ['S' => 100, 'M' => 150, 'L' => 150, 'XL' => 100], 55, 12, ['needs_washing' => 1, 'studs_stones_ih' => 1]);
        $o4 = $order('D-HM', 3, 2);
        $po($o4->id, 'D-1004', 'D-HM-4601', 'Black', ['M' => 150, 'L' => 150], 70, 35);   // stays draft

        foreach ([$o1, $o2, $o3] as $o) {
            $this->post('msfl.orders.status', ['order' => $o->id], ['status' => 'confirmed']);
        }
        // Buyer revises PO 4502 after confirmation: qty 400 → 450 and shipment +5 days.
        $this->post('msfl.orders.pos.update', ['order' => $o1->id, 'po' => $p4502->id], [
            '_po_form' => (string) $p4502->id, 'style_id' => $p4502->style_id, 'color_id' => $p4502->color_id, 'po_no' => $p4502->po_no, 'unit_price' => 9.40,
            'pcd_date' => $p4502->pcd_date->toDateString(), 'shipment_date' => $p4502->shipment_date->copy()->addDays(5)->toDateString(),
            'ship_mode_id' => $p4502->ship_mode_id, 'needs_washing' => 1, 'needs_embroidery' => 0,
            'sizes' => [$sizes['S'] => 70, $sizes['M'] => 155, $sizes['L'] => 155, $sizes['XL'] => 70],
        ], 'PUT');

        foreach ([[$o1, 'D-1001', [['D-TWL', 1.30, 4], ['D-THR', 0.10, 2], ['D-ZIP', 1, 1], ['D-LBL', 1, 1], ['D-PLY', 1, 0], ['D-CTN', 0.04, 0]]],
                  [$o2, 'D-1002', [['D-FLC', 0.45, 5], ['D-THR', 0.08, 2], ['D-LBL', 1, 1], ['D-PLY', 1, 0], ['D-CTN', 0.05, 0]]],
                  [$o3, 'D-1003', [['D-DNM', 1.80, 4], ['D-THR', 0.12, 2], ['D-ZIP', 1, 1], ['D-LBL', 1, 1], ['D-CTN', 0.05, 0]]]] as [$o, $no, $bomItems]) {
            $this->post('msfl.boms.store', [], ['style_id' => $style[$no]->id, 'order_id' => $o->id, 'bom_type' => 'manual',
                'items' => collect($bomItems)->map(fn ($r) => ['item_id' => $item[$r[0]]->id, 'consumption' => $r[1], 'uom_id' => $item[$r[0]]->uom_id, 'wastage_percent' => $r[2],
                    'rate' => $item[$r[0]]->default_price, 'placement' => $r[0] === 'D-TWL' || $r[0] === 'D-FLC' || $r[0] === 'D-DNM' ? 'Body' : null])->all()]);
            $this->post('msfl.boms.approve', ['bom' => M\Bom::query()->latest('id')->value('id')]);
        }

        // ── Planning: bulletins + T&A ───────────────────────────────────────
        $ops = M\Operation::query()->with('machineType')->orderBy('id')->get();
        foreach (['D-1001' => [0, 18], 'D-1002' => [18, 14], 'D-1003' => [30, 22]] as $no => [$from, $count]) {
            $lineIdx = ['D-1001' => 0, 'D-1002' => 1, 'D-1003' => 2][$no] % max(1, $lines->count());
            $this->post('msfl.bulletins.store', [], [
                'style_id' => $style[$no]->id, 'bulletin_date' => $this->d(12), 'line_id' => $lines[$lineIdx]->id, 'target_per_hour' => 70, 'working_hours' => 10,
                'operations' => $ops->slice($from, $count)->values()->map(fn ($op) => ['operation_id' => $op->id, 'name' => $op->name, 'machine_type_id' => $op->machine_type_id,
                    'attachment' => $op->attachment, 'smv' => $op->default_smv ?: 0.7, 'is_active' => 1])->all(),
            ]);
            $this->post('msfl.bulletins.approve', ['bulletin' => M\Bulletin::query()->latest('id')->value('id')]);
        }
        $template = M\TnaTemplate::query()->where('is_default', true)->value('id') ?? M\TnaTemplate::query()->value('id');
        foreach ([[$o1, 'D-1001', 0], [$o2, 'D-1002', 1], [$o3, 'D-1003', 2]] as [$o, $no, $l]) {
            $this->post('msfl.tna.store', [], ['order_id' => $o->id, 'style_id' => $style[$no]->id, 'tna_template_id' => $template, 'line_ids' => [$lines[$l % $lines->count()]->id]]);
        }
        // Manual T&A work done so far on the H&M plan (booking, LC, file hand-over, PP meeting …).
        $plan1 = M\TnaPlan::query()->where('order_id', $o1->id)->first();
        $done = ['FABRIC_BOOKING' => 24, 'TRIMS_BOOKING' => 22, 'FABRIC_LC' => 21, 'SHADE_BAND_SUBMIT' => 20, 'LAB_DIP' => 17, 'FABRIC_XMILL' => 15,
            'TRIMS_INHOUSE' => 12, 'FILE_HANDOVER' => 12, 'PULLOUT' => 11, 'PILOT_STITCHING' => 11, 'PILOT_WASH' => 10, 'PILOT_REVIEW' => 10, 'PP_MEETING' => 10, 'WASH_STANDARD' => 16];
        $rows = [];
        foreach ($plan1->tasks as $task) {
            $rows[$task->id] = ['revised_date' => $task->revised_date?->toDateString(), 'actual_date' => isset($done[$task->task_code]) ? $this->d($done[$task->task_code]) : $task->actual_date?->toDateString(),
                'is_na' => $task->is_na ? 1 : 0, 'responsible_id' => $this->user->id, 'remarks' => $task->task_code === 'PP_MEETING' ? 'Approved with minor comments on waistband' : $task->remarks];
        }
        $this->post('msfl.tna.tasks.update', ['tna' => $plan1->id], ['tasks' => $rows], 'PUT');

        // ── Inventory: buyer fabric received (Buyer Store GRN) ──────────────
        $grn = function (M\OrderPo $p, string $daysAgoKey, array $lines, int $ago) use ($buyerStore) {
            $this->post('inventory.grns.store', [], [
                'source_type' => 'buyer_supplied', 'store_id' => $buyerStore, 'msfl_buyer_id' => $p->order->buyer_id, 'msfl_style_id' => $p->style_id, 'msfl_order_po_id' => $p->id,
                'challan_invoice_no' => 'CH-' . $daysAgoKey, 'receive_date' => $this->d($ago),
                'items' => collect($lines)->map(fn ($l) => ['item_id' => $l[0], 'received_qty' => $l[1], 'rate' => $l[2]])->all(),
            ]);
        };
        $grn($p4501->load('order'), 'HM1', [[$invItems['twill'], 1250, 330], [$invItems['thread'], 80, 95], [$invItems['zipper'], 1100, 18], [$invItems['label'], 1100, 4], [$invItems['poly'], 1100, 3], [$invItems['carton'], 50, 140]], 14);
        $grn($p4501, 'HM2', [[$invItems['twill'], 400, 330]], 6);    // 2nd consignment
        $grn($p7701->load('order'), 'PR1', [[$invItems['fleece'], 420, 760], [$invItems['thread'], 60, 95], [$invItems['label'], 820, 4]], 9);

        // ── Production ──────────────────────────────────────────────────────
        $requisition = function (M\OrderPo $p, array $lines, int $ago) use ($buyerStore, $cuttingDept) {
            $this->post('msfl.production.requisitions.store', [], ['order_po_id' => $p->id, 'requisition_date' => $this->d($ago), 'store_id' => $buyerStore,
                'department_id' => $cuttingDept, 'requisition_for' => 'fabrics', 'items' => collect($lines)->map(fn ($l) => ['item_id' => $l[0], 'requested_qty' => $l[1]])->all()]);

            return \ME\SflInventory\Models\InvRequisition::query()->latest('id')->with('items')->first();
        };
        $issue = function (\ME\SflInventory\Models\InvRequisition $req, array $rates, int $ago) use ($buyerStore, $cuttingDept) {
            $this->post('inventory.requisitions.approval', ['requisition' => $req->id], ['decision' => 'approve', 'approval_remarks' => 'OK',
                'items' => $req->items->map(fn ($i) => ['id' => $i->id, 'approved_qty' => $i->requested_qty])->all()]);
            $this->post('inventory.issues.store', [], ['requisition_id' => $req->id, 'store_id' => $buyerStore, 'department_id' => $cuttingDept, 'issue_date' => $this->d($ago),
                'items' => $req->items->map(fn ($i) => ['requisition_item_id' => $i->id, 'item_id' => $i->item_id, 'issued_qty' => $i->requested_qty, 'unit_rate' => $rates[$i->item_id] ?? 0])->all()]);
            $issued = \ME\SflInventory\Models\InvIssue::query()->latest('id')->first();
            if ($issued->status === 'authorized') { // older flow: approve posts the stock
                $this->post('inventory.issues.approve', ['issue' => $issued->id]);
            }
        };
        $issue($requisition($p4501, [[$invItems['twill'], 820], [$invItems['thread'], 48]], 13), [$invItems['twill'] => 330, $invItems['thread'] => 95], 12);
        $issue($requisition($p7701, [[$invItems['fleece'], 380]], 8), [$invItems['fleece'] => 760], 7);
        $requisition($p4502, [[$invItems['twill'], 610]], 2);        // waiting for store approval
        $requisition($p9901, [[$invItems['denim'], 960]], 1);        // waiting — no denim received yet

        $flow = app(\ME\MerchandisingSfl\Services\ProductionFlow::class);
        $boardSvc = app(\ME\MerchandisingSfl\Services\SewingBoard::class);
        $cut = function (M\OrderPo $p, array $qty, int $ago, string $table) use ($sizes) {
            $bySize = collect($qty)->mapWithKeys(fn ($q, $s) => [$sizes[$s] => $q])->all();
            $this->post('msfl.production.cuttings.store', [], ['order_po_id' => $p->id, 'cutting_date' => $this->d($ago), 'table_no' => $table, 'lay_count' => 60, 'bundle_size' => 25,
                'fabric_used' => round(array_sum($qty) * 1.3, 2), 'sizes' => $bySize,
                'parts' => [['part_name' => 'Front', 'sizes' => $bySize], ['part_name' => 'Back', 'sizes' => $bySize], ['part_name' => $p->needs_embroidery ? 'Hood' : 'Waistband', 'sizes' => $bySize]]]);
        };
        // Spread $total over the PO's sizes by their order qty, never more than each size can take ($cap: size_id => pcs).
        $spread = function (M\OrderPo $p, int $total, array $cap): array {
            $p->loadMissing('sizes');
            $weights = $p->sizes->mapWithKeys(fn ($s) => [$s->size_id => (int) $s->qty])->all();
            $out = array_fill_keys(array_keys($weights), 0);
            $sum = max(1, array_sum($weights));
            foreach ($weights as $id => $w) {
                $out[$id] = min((int) floor($total * $w / $sum), (int) ($cap[$id] ?? 0));
            }
            for ($left = $total - array_sum($out); $left > 0; $left -= $add) {
                $add = 0;
                foreach ($out as $id => $q) {
                    if ($left - $add > 0 && $q < (int) ($cap[$id] ?? 0)) { $out[$id]++; $add++; }
                }
                if ($add === 0) { break; }
            }

            return array_filter($out);
        };
        $sizeFig = fn (M\OrderPo $p, callable $pick) => $p->sizes->mapWithKeys(fn ($s) => [$s->size_id => (int) $pick($flow->summary($p->fresh(), null, $s->size_id))])->all();
        // A stage entry per size (embroidery / washing: mode input = send, output = receive back).
        $stage = function (string $stage, M\OrderPo $p, int $ago, string $field, int $total, array $extra = []) use ($flow, $spread, $sizeFig) {
            $part = $extra['part_name'] ?? null;
            $row = fn ($sum) => $part ? $flow->partRow($sum, $stage, $part) : $sum[$stage];
            $cap = $sizeFig($p, fn ($sum) => $field === 'input_qty' ? $row($sum)['available'] : $row($sum)['wip'] + ($extra['_with_input'] ?? 0));
            unset($extra['_with_input']);
            foreach ($spread($p, $total, $cap) as $sizeId => $q) {
                $data = [$field => $q] + $extra + ['order_po_id' => $p->id, 'entry_date' => $this->d($ago), 'size_id' => $sizeId];
                if ($field === 'input_qty' && isset($extra['pass_all'])) {
                    $data['pass_qty'] = $q;
                }
                unset($data['pass_all']);
                $this->post('msfl.production.entries.store', ['stage' => $stage], $data);
            }
        };
        // Sewing: line input (from the cutting balance) and hourly output.
        $sewInput = function (M\OrderPo $p, M\Line $line, int $ago, int $total) use ($spread, $sizeFig) {
            $cap = $sizeFig($p, fn ($sum) => $sum['sewing']['available']);
            $this->post('msfl.production.sewing.input.store', [], ['entry_date' => $this->d($ago), 'line_id' => $line->id, 'order_po_id' => $p->id,
                'sizes' => collect($spread($p, $total, $cap))->map(fn ($q) => ['input_qty' => $q])->all()]);
        };
        $hours = function (int $ago) {
            $slots = array_values(array_diff(array_keys(\ME\MerchandisingSfl\Services\ProductionFlow::hourSlots()), [\ME\MerchandisingSfl\Services\ProductionFlow::breakHour()]));
            // Today only the hours already gone; earlier days a full 8-hour day.
            return $ago === 0 ? array_values(array_filter($slots, fn ($h) => $h < (int) now()->format('G'))) : array_slice($slots, 0, 8);
        };
        $sewDay = function (M\OrderPo $p, M\Line $line, int $ago, int $output, int $reject, int $rework) use ($spread, $sizeFig, $hours, $boardSvc) {
            $plan = $boardSvc->defaults($p->fresh(['style']), $line);
            $slots = $hours($ago);
            $n = count($slots);
            foreach ($slots as $i => $h) {
                // A slow first hour, then steady; rejects / rework spread over the day.
                $out = (int) floor($output / $n) + ($i < $output % $n ? 1 : 0);
                $out = $i === 0 ? (int) round($out * 0.7) : ($i === 1 ? $out + ((int) floor($output / $n) - (int) round(floor($output / $n) * 0.7)) : $out);
                $rej = $i % 3 === 1 ? min($reject, (int) ceil($reject / max(1, intdiv($n, 3)))) : 0;
                $rw = $i % 2 === 0 ? (int) ceil($rework / max(1, (int) ceil($n / 2))) : 0;
                $reject -= $rej;
                $rework -= $rw;
                $wip = $sizeFig($p, fn ($sum) => $sum['sewing']['wip']);
                $outs = $spread($p, max(0, $out), $wip);
                $left = collect($wip)->map(fn ($w, $id) => $w - ($outs[$id] ?? 0))->all();
                $rejs = $spread($p, max(0, $rej), $left);
                $rws = $spread($p, max(0, $rw), $wip);
                $rows = [];
                foreach (array_keys($wip) as $id) {
                    $rows[$id] = ['pass_qty' => $outs[$id] ?? 0, 'reject_qty' => $rejs[$id] ?? 0, 'rework_qty' => $rws[$id] ?? 0];
                }
                $this->post('msfl.production.sewing.hourly.store', [], ['entry_date' => $this->d($ago), 'line_id' => $line->id, 'order_po_id' => $p->id, 'hour_slot' => $h, 'sizes' => $rows] + $plan);
            }
        };
        // QC (pass / reject) and Rework (found / fixed) on one size of a stage.
        $qc = function (string $stage, M\OrderPo $p, int $ago, int $pass, int $reject, string $defect, ?string $part = null, ?M\Line $line = null) {
            $this->post('msfl.production.qc.store', [], ['stage' => $stage, 'order_po_id' => $p->id, 'entry_date' => $this->d($ago), 'size_id' => $p->sizes->sortByDesc('qty')->first()->size_id,
                'part_name' => $part, 'line_id' => $line?->id, 'pass_qty' => $pass, 'reject_qty' => $reject,
                'defects' => $reject ? [['type' => 'reject', 'part_name' => $part ?? 'Body', 'defect' => $defect, 'qty' => $reject]] : []]);
        };
        $rework = function (string $stage, M\OrderPo $p, int $ago, int $found, int $pass, string $defect, ?string $part = null, ?M\Line $line = null) {
            $this->post('msfl.production.rework.store', [], ['stage' => $stage, 'order_po_id' => $p->id, 'entry_date' => $this->d($ago), 'size_id' => $p->sizes->sortByDesc('qty')->first()->size_id,
                'part_name' => $part, 'line_id' => $line?->id, 'rework_qty' => $found, 'pass_qty' => $pass,
                'defects' => $found ? [['type' => 'rework', 'part_name' => $part ?? 'Body', 'defect' => $defect, 'qty' => $found]] : []]);
        };
        $total = fn (M\OrderPo $p, string $st, string $k) => (int) $flow->summary($p->fresh())[$st][$k];
        [$l1, $l2] = [$lines[0], $lines[1 % $lines->count()]];
        foreach ([$p4501, $p4502, $p7701] as $p) {
            $p->load('sizes');
        }

        // PO 4501 — the full route, packed and in the Finish Store.
        $cut($p4501, ['S' => 100, 'M' => 200, 'L' => 200, 'XL' => 100], 11, 'T-01');
        $qc('cutting', $p4501, 11, 196, 2, 'Fabric hole', 'Front');
        $sewInput($p4501, $l1, 10, 300);
        $sewInput($p4501, $l1, 9, $total($p4501, 'sewing', 'available'));
        $sewDay($p4501, $l1, 10, 270, 3, 10);
        $sewDay($p4501, $l1, 9, 290, 2, 8);
        $sewDay($p4501, $l1, 8, $total($p4501, 'sewing', 'wip'), 0, 0);
        $qc('sewing', $p4501, 8, 60, 1, 'Broken stitch', 'Waistband', $l1);
        $rework('sewing', $p4501, 8, 2, 2, 'Open seam', 'Body', $l1);
        $stage('washing', $p4501, 7, 'input_qty', $total($p4501, 'washing', 'available'), ['mode' => 'input']);
        $stage('washing', $p4501, 6, 'pass_qty', $total($p4501, 'washing', 'wip'), ['mode' => 'output']);
        $qc('washing', $p4501, 6, 0, 2, 'Shade variation');
        $stage('finishing', $p4501, 4, 'input_qty', $total($p4501, 'finishing', 'available'), ['pass_all' => true]);
        $qc('finishing', $p4501, 4, 100, 2, 'Iron shine');
        $finalIn = $total($p4501, 'final_qc', 'available');
        $stage('final_qc', $p4501, 3, 'input_qty', $finalIn, ['pass_all' => true]);
        $rework('final_qc', $p4501, 3, 3, 3, 'Uncut thread');
        $stage('packing', $p4501, 2, 'input_qty', $total($p4501, 'packing', 'available'), ['pass_all' => true]);
        $this->post('inventory.fg-receives.store', [], ['receive_date' => $this->d(1), 'store_id' => $finishStore, 'msfl_buyer_id' => $p4501->order->buyer_id,
            'msfl_style_id' => $p4501->style_id, 'msfl_order_po_id' => $p4501->id, 'items' => [['item_id' => $invItems['fg_chino'], 'quantity' => $total($p4501, 'packing', 'pass')]]]);

        // PO 4502 — cut, sewing running on line 1 (yesterday and today, hour by hour).
        $cut($p4502, ['S' => 70, 'M' => 155, 'L' => 155, 'XL' => 70], 3, 'T-02');
        $sewInput($p4502, $l1, 1, 250);
        $sewDay($p4502, $l1, 1, 205, 2, 9);
        $sewInput($p4502, $l1, 0, 150);
        $sewDay($p4502, $l1, 0, 150, 2, 6);
        $qc('sewing', $p4502, 0, 40, 1, 'Skip stitch', 'Waistband', $l1);
        $rework('sewing', $p4502, 0, 4, 0, 'Pocket misaligned', 'Pocket', $l1);

        // PO 7701 — embroidery: Front parts sent from cutting, partly back; sewing on line 2.
        $cut($p7701, ['S' => 200, 'M' => 300, 'L' => 200, 'XL' => 100], 6, 'T-03');
        $stage('embroidery', $p7701, 5, 'input_qty', 600, ['mode' => 'input', 'part_name' => 'Front']);
        $stage('embroidery', $p7701, 3, 'pass_qty', 420, ['mode' => 'output', 'part_name' => 'Front']);
        $qc('embroidery', $p7701, 3, 0, 3, 'Misregistration', 'Front');
        $rework('embroidery', $p7701, 2, 6, 4, 'Loose thread', 'Front');
        $sewInput($p7701, $l2, 2, 300);
        $sewDay($p7701, $l2, 2, 210, 2, 6);
        $stage('embroidery', $p7701, 1, 'pass_qty', 100, ['mode' => 'output', 'part_name' => 'Front']);
        $sewInput($p7701, $l2, 0, 150);
        $sewDay($p7701, $l2, 0, 160, 3, 7);

        // Fill the T&A dates the system already knows.
        $planner = app(\ME\MerchandisingSfl\Services\TnaPlanner::class);
        foreach (M\TnaPlan::query()->whereIn('order_id', [$o1->id, $o2->id, $o3->id])->get() as $plan) {
            $planner->syncAutoActuals($plan);
        }
    }
}
