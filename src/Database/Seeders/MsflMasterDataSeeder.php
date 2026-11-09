<?php

namespace ME\MerchandisingSfl\Database\Seeders;

use App\Models\Approval;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use ME\MerchandisingSfl\Models as M;
use RuntimeException;

/**
 * Starting master data for real use (no "D-" demo codes): the Inventory buyers as
 * approved v2 buyers (same name, so Inventory keeps linking), their Buyer Store GRN
 * style numbers as styles, plus seasons, product types, wash types, factory,
 * item categories and common fabric / trims items. Ship modes, currencies,
 * sample types, garment parts and planning data come from MsflDefaultMasterSeeder.
 *
 * Entered through the Master Data routes (same validation / approvals as the screen),
 * in one transaction. Safe to re-run — existing codes are skipped.
 *   php artisan db:seed --class="ME\MerchandisingSfl\Database\Seeders\MsflMasterDataSeeder"
 */
class MsflMasterDataSeeder extends Seeder
{
    private $kernel;

    private $session;

    private User $user;

    public function run(): void
    {
        $this->user = User::query()->where('permission_id', 1)->orderBy('id')->firstOrFail(); // a Super Admin
        auth()->setUser($this->user);
        Gate::before(fn () => true);
        Mail::fake();
        Notification::fake();
        app()->instance('middleware.disable', true);
        Event::listen(RouteMatched::class, function ($e) {
            app('router')->substituteBindings($e->route);
            app('router')->substituteImplicitBindings($e->route);
        });
        $this->kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $this->session = app('session.store');

        DB::transaction(function () {
            $this->call(MsflDefaultMasterSeeder::class);
            $this->seed();
        });
        app()->instance('middleware.disable', false);

        $this->command?->info('Merchandising v2 master data added.');
    }

    /** Add one master record through its screen route, unless the key already exists. */
    private function master(string $master, array $data, string $key = 'code'): void
    {
        $class = \ME\MerchandisingSfl\Support\MasterRegistry::get($master)['model'];
        if ($class::withoutGlobalScopes()->where($key, $data[$key])->exists()) {
            return;
        }

        $this->session->flush();
        $request = Request::create(route('msfl.masters.store', ['master' => $master], false), 'POST', $data + ['is_active' => 1]);
        $request->setLaravelSession($this->session);
        $response = $this->kernel->handle($request);
        $error = $this->session->get('errors')?->first() ?: $this->session->get('error');
        if ($response->getStatusCode() >= 400 || $error) {
            throw new RuntimeException("{$master} {$data[$key]}: " . ($error ?: 'HTTP ' . $response->getStatusCode()));
        }
        $this->command?->line("  ✓ {$master}: {$data[$key]}");
    }

    private function seed(): void
    {
        $unit = fn (string $short) => M\Uom::query()->where('short_name', $short)->value('id');
        $merchandiser = User::query()->where('name', 'Merchandiser')->value('id') ?? $this->user->id;

        // ── Buyers: the Inventory buyers, approved so they reach every dropdown ──
        $invBuyers = DB::table('inv_buyers')->whereNull('deleted_at')->where('is_active', 1)->orderBy('id')->get();
        foreach ($invBuyers as $b) {
            $this->master('buyers', [
                'code' => $b->code, 'name' => $b->name, 'country' => 'Bangladesh', 'merchandiser_id' => $merchandiser,
                'phone' => $b->contact, 'address' => $b->address, 'delivery_term' => 'FOB', 'payment_term_id' => \ME\MerchandisingSfl\Models\Commercial\PaymentTerm::query()->where('code', 'LC-SIGHT')->value('id'),
            ]);
        }
        $pending = Approval::query()->pending()->where('approvable_type', M\Buyer::class)
            ->whereIn('approvable_id', M\Buyer::withoutGlobalScopes()->whereIn('code', $invBuyers->pluck('code'))->pluck('id'))->get();
        foreach ($pending as $approval) {
            app(ApprovalService::class)->approve($approval, $this->user, 'Initial master data');
        }

        // ── Seasons ──
        foreach ([
            ['SS26', 'Spring/Summer 2026', 2026, '2026-01-01', '2026-06-30'], ['AW26', 'Autumn/Winter 2026', 2026, '2026-07-01', '2026-12-31'],
            ['SS27', 'Spring/Summer 2027', 2027, '2027-01-01', '2027-06-30'], ['AW27', 'Autumn/Winter 2027', 2027, '2027-07-01', '2027-12-31'],
        ] as [$code, $name, $year, $start, $end]) {
            $this->master('seasons', ['code' => $code, 'name' => $name, 'year' => $year, 'start_date' => $start, 'end_date' => $end]);
        }

        // ── Product types [code, name, category, default SMV] ──
        foreach ([
            ['TSH', 'T-Shirt', 'knit', 8], ['POLO', 'Polo Shirt', 'knit', 14], ['SWT', 'Sweatshirt', 'knit', 16],
            ['HOOD', 'Hoodie', 'knit', 20], ['JOG', 'Jogger / Trouser (Knit)', 'knit', 15], ['SHIRT', 'Shirt', 'woven', 22],
            ['PANT', 'Trouser / Pant', 'woven', 24], ['SHORT', 'Shorts', 'woven', 14], ['JEANS', 'Denim Jeans', 'denim', 26],
            ['DJKT', 'Denim Jacket', 'denim', 35], ['JKT', 'Jacket', 'woven', 32], ['VEST', 'Vest / Blazer', 'woven', 30],
            ['SWTR', 'Sweater', 'sweater', 25],
        ] as [$code, $name, $category, $smv]) {
            $this->master('product-types', ['code' => $code, 'name' => $name, 'category' => $category, 'default_smv' => $smv]);
        }

        // ── Wash types ──
        foreach ([
            ['NW', 'Normal / Garment Wash'], ['ENZ', 'Enzyme Wash'], ['STN', 'Stone Wash'], ['ESTN', 'Enzyme Stone Wash'],
            ['ACID', 'Acid Wash'], ['BLCH', 'Bleach Wash'], ['SIL', 'Silicone Wash'], ['PIG', 'Pigment Dye Wash'], ['RNS', 'Rinse Wash'],
        ] as [$code, $name]) {
            $this->master('wash-types', ['code' => $code, 'name' => $name]);
        }

        // ── Factory ──
        $this->master('factories', ['code' => 'SFL', 'name' => 'Suhana Fashions Ltd', 'is_own' => 1]);

        // ── Item categories [code, name, type] ──
        foreach ([
            ['FAB', 'Fabric', 'fabric'], ['LIN', 'Lining & Interlining', 'fabric'], ['THR', 'Sewing Thread', 'trims'],
            ['LBL', 'Labels & Tags', 'trims'], ['BTN', 'Buttons, Zippers & Rivets', 'accessories'], ['ACC', 'Other Accessories', 'accessories'],
            ['PCK', 'Packing', 'packing'],
        ] as [$code, $name, $type]) {
            $this->master('item-categories', ['code' => $code, 'name' => $name, 'type' => $type]);
        }
        $cat = M\ItemCategory::query()->pluck('id', 'code');

        // ── Items [code, name, category, unit, price (USD), composition, GSM] ──
        foreach ([
            ['FAB-SJ160', 'Single Jersey 160 GSM', 'FAB', 'KG', 4.20, '100% Cotton', 160],
            ['FAB-SJ180', 'Single Jersey 180 GSM', 'FAB', 'KG', 4.40, '100% Cotton', 180],
            ['FAB-PIQ', 'Pique 220 GSM', 'FAB', 'KG', 4.80, '100% Cotton', 220],
            ['FAB-FLC', 'Fleece 280 GSM', 'FAB', 'KG', 5.20, '80% Cotton 20% Polyester', 280],
            ['FAB-RIB', 'Rib 1x1', 'FAB', 'KG', 4.60, '95% Cotton 5% Elastane', 220],
            ['FAB-TWL', 'Cotton Twill', 'FAB', 'Yrd', 2.60, '100% Cotton', 240],
            ['FAB-POP', 'Poplin', 'FAB', 'Yrd', 1.80, '100% Cotton', 120],
            ['FAB-DNM', 'Denim 12 oz', 'FAB', 'Yrd', 3.50, '98% Cotton 2% Elastane', null],
            ['LIN-POC', 'Pocketing', 'LIN', 'Yrd', 0.80, '65% Polyester 35% Cotton', null],
            ['LIN-FUS', 'Fusible Interlining', 'LIN', 'Yrd', 0.60, null, null],
            ['THR-SEW', 'Sewing Thread 40/2', 'THR', 'CON', 0.90, '100% Spun Polyester', null],
            ['THR-OL', 'Overlock Thread', 'THR', 'CON', 0.80, '100% Polyester', null],
            ['LBL-MAIN', 'Main Label (Woven)', 'LBL', 'PCS', 0.04, null, null],
            ['LBL-CARE', 'Care Label', 'LBL', 'PCS', 0.02, null, null],
            ['LBL-SIZE', 'Size Label', 'LBL', 'PCS', 0.01, null, null],
            ['LBL-HANG', 'Hang Tag', 'LBL', 'PCS', 0.05, null, null],
            ['LBL-PRICE', 'Price Ticket', 'LBL', 'PCS', 0.02, null, null],
            ['BTN-BTN', 'Button', 'BTN', 'PCS', 0.03, null, null],
            ['BTN-SNAP', 'Snap Button', 'BTN', 'PCS', 0.05, null, null],
            ['BTN-ZIP', 'Zipper', 'BTN', 'PCS', 0.18, null, null],
            ['BTN-RVT', 'Rivet', 'BTN', 'PCS', 0.02, null, null],
            ['ACC-ELS', 'Elastic', 'ACC', 'Mtr', 0.10, null, null],
            ['ACC-DRW', 'Drawcord', 'ACC', 'PCS', 0.06, null, null],
            ['ACC-TAPE', 'Twill Tape', 'ACC', 'Mtr', 0.04, null, null],
            ['PCK-POLY', 'Poly Bag', 'PCK', 'PCS', 0.03, null, null],
            ['PCK-CTN', 'Carton 5-ply', 'PCK', 'PCS', 1.20, null, null],
            ['PCK-TISS', 'Tissue Paper', 'PCK', 'PCS', 0.01, null, null],
            ['PCK-STKR', 'Sticker / Barcode', 'PCK', 'PCS', 0.01, null, null],
            ['PCK-HNGR', 'Hanger', 'PCK', 'PCS', 0.08, null, null],
        ] as [$code, $name, $c, $u, $price, $composition, $gsm]) {
            $this->master('items', array_filter([
                'code' => $code, 'name' => $name, 'type' => M\ItemCategory::query()->where('code', $c)->value('type'),
                'category_id' => $cat[$c], 'uom_id' => $unit($u), 'default_price' => $price, 'composition' => $composition, 'gsm' => $gsm,
            ], fn ($v) => $v !== null));
        }

        // ── Styles: style numbers already received in the Buyer Store (posted buyer-supplied GRNs) ──
        $buyerIds = M\Buyer::withoutGlobalScopes()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id]);
        $grnStyles = DB::table('inv_grns as g')->join('inv_buyers as b', 'b.id', '=', 'g.buyer_id')
            ->where('g.source_type', 'buyer_supplied')->where('g.status', 'posted')->whereNull('g.deleted_at')
            ->whereNotNull('g.style')->where('g.style', '<>', '')
            ->select('b.name as buyer', DB::raw('TRIM(g.style) as style'))->distinct()->orderBy('style')->get();
        foreach ($grnStyles as $g) {
            $buyerId = $buyerIds[mb_strtolower(trim($g->buyer))] ?? null;
            if ($buyerId) {
                $this->master('styles', ['buyer_id' => $buyerId, 'style_no' => $g->style, 'name' => $g->style], 'style_no');
            }
        }
    }
}
