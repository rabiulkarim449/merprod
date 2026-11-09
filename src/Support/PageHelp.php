<?php

namespace ME\MerchandisingSfl\Support;

/**
 * "এই পাতা কী কাজে" — the help box shown on top of every screen (partials/page-help).
 *
 * Keyed by the route name without the `msfl.` prefix and the action, e.g. `orders`
 * for msfl.orders.index / create / show; masters by slug (`masters.buyers`),
 * production stages by stage (`production.entries.sewing`), reports by report key.
 * Each entry: title, what (purpose), steps, example (demo data, codes D-…), before / after (process flow).
 * Optional `actions` => [action => extra tip] for create / edit / show pages.
 */
class PageHelp
{
    /** Help for the current request, or null when the page has none. */
    public static function current(): ?array
    {
        $route = request()->route();
        if (! $route || ! $route->getName()) {
            return null;
        }

        $prefix = config('merchandising-sfl.route.as', 'msfl.');
        $name = str_starts_with($route->getName(), $prefix) ? substr($route->getName(), strlen($prefix)) : $route->getName();
        $action = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
        $module = str_contains($name, '.') ? substr($name, 0, strrpos($name, '.')) : $name;

        $key = match ($module) {
            'masters' => 'masters.' . $route->parameter('master'),
            'production.entries' => 'production.entries.' . $route->parameter('stage'),
            'reports' => $action === 'index' ? 'reports' : 'reports.' . $route->parameter('report'),
            'ops-reports' => $action === 'index' ? 'ops-reports' : 'ops-reports.' . $route->parameter('report'),
            default => $module,
        };

        $help = self::all()[$key] ?? null;
        if (! $help) {
            return null;
        }

        $help['key'] = $key;
        $help['tip'] = $help['actions'][$action] ?? null;

        return $help;
    }

    public static function all(): array
    {
        $stage = fn (string $title, string $what, string $before, string $after, string $example) => [
            'title' => $title,
            'what' => $what,
            'steps' => [
                '<strong>+ New Entry</strong> চাপুন, PO (buyer · style · color) আর <strong>Size</strong> বেছে নিন — "can take in" দেখাবে আগের stage থেকে ওই size-এর কত pcs নেওয়া যাবে। প্রতিটা size আলাদা entry।',
                'দিনের <strong>Input</strong> (এই step-এ কত pcs ঢুকল) আর <strong>Output</strong> (কত শেষ হলো) দিন।',
                'Reject / Rework এখানে না — <strong>Production → QC</strong> আর <strong>Production → Rework</strong>-এ step-এর card থেকে দিন। (শুধু Buyer QC-তে Pass / Rework / Reject এখানেই।)',
                'WIP = Input − Output (এখনো এই step-এ আছে)।',
            ],
            'example' => $example,
            'before' => $before,
            'after' => $after,
            'actions' => ['create' => 'Input কখনো "can take in"-এর বেশি হতে পারবে না, আর Pass + Reject এই stage-এ থাকা pcs-এর বেশি হতে পারবে না।'],
        ];

        return [
            // ---------------------------------------------------------------- Dashboard
            'dashboard' => [
                'title' => 'Dashboard',
                'what' => 'পুরো merchandising ও production-এর এক নজরের সারাংশ: চলমান order, আজকের production (stage অনুযায়ী), WIP, reject %, সামনে কোন shipment আছে, কোন T&A task overdue, আর সবচেয়ে বেশি কোন defect হচ্ছে।',
                'steps' => [
                    'এখানে কিছু entry দিতে হয় না — অন্য পাতায় যা entry দেন তা থেকেই সংখ্যাগুলো আসে।',
                    'লাল / overdue দেখলে সেই কার্ডের লিংকে গিয়ে কাজটা শেষ করুন।',
                ],
                'example' => 'ধরুন আজ Sewing-এ 200 pcs pass দেওয়া হলো — Dashboard-এর "Today\'s production"-এ Sewing-এর পাশে 200 দেখাবে।',
                'before' => 'সব পাতার entry',
                'after' => 'যে কাজ বাকি সেই পাতা',
            ],

            // ---------------------------------------------------------------- Master data
            'masters.buyers' => [
                'title' => 'Buyers (ক্রেতা)',
                'what' => 'যে কোম্পানির জন্য পোশাক বানানো হয় (H&M, Primark …) তাদের তালিকা। Inquiry, Style, Order, Inventory — সব জায়গায় buyer এখান থেকেই আসে।',
                'steps' => [
                    '<strong>+ Add</strong> চেপে Code, Name, Country, Merchandiser, Payment / Delivery Term দিন।',
                    'Save করলে buyer <strong>Approvals</strong> পাতায় যায়। Approve না হওয়া পর্যন্ত কোনো dropdown-এ আসবে না।',
                    'Reject হলে edit করে আবার save করুন — আবার approval-এ যাবে।',
                ],
                'example' => 'D-HM = H&M (Sweden) approve হলে order-এ বাছা যায়; D-ZRA = Zara approval-এর অপেক্ষায় থাকলে কোনো dropdown-এ আসে না।',
                'before' => '—',
                'after' => 'Inquiry / Style / Order',
            ],
            'masters.styles' => [
                'title' => 'Styles (স্টাইল)',
                'what' => 'Buyer-ভিত্তিক style-এর তালিকা। Merchandising আর Inventory — দুই জায়গাতেই style এখান থেকে আসে; Inventory-তে Buyer বাছলে সেই buyer-এর style-ই দেখায়। Inventory-তে আর style হাতে লেখা যায় না।',
                'steps' => [
                    '<strong>+ Add</strong> চেপে Buyer, Style No আর Style Name দিন (Season / Product Type চাইলে)।',
                    '<strong>Color</strong> দিন — একটা style-এর একটাই color; Order-এ এই style বাছলে color নিজে বসে যায়।',
                    'Tech pack, ছবি, SMV, CM দরকার হলে <strong>Tech Pack / Styles → New Tech Pack</strong>-এ এই style বেছে নিন — দুই পাতা একই style।',
                    'Order, BOM বা sample-এ ব্যবহার হলে style delete করা যায় না — Inactive করে দিন।',
                ],
                'example' => 'BYSL-এর 266407 = Men\'s Pique Polo Shirt add করলে Inventory → Buyer Supplied Challan-এ BYSL বাছলেই 266407 আসবে।',
                'before' => 'Buyers',
                'after' => 'Order / Inventory (GRN, Requisition, Issue …)',
            ],
            'masters.seasons' => [
                'title' => 'Seasons',
                'what' => 'Buyer কোন মৌসুমের জন্য অর্ডার দিচ্ছে (Spring/Summer, Autumn/Winter)। Inquiry, Style, Order-এ season বেছে নেওয়া হয় — report-এ season অনুযায়ী দেখা যায়।',
                'steps' => ['Code, Name, Year আর (চাইলে) Start / End Date দিন।'],
                'example' => 'D-SS27 = Spring/Summer 2027।',
                'before' => '—',
                'after' => 'Inquiry / Style / Order',
            ],
            'masters.product-types' => [
                'title' => 'Product Types',
                'what' => 'পোশাকের ধরন (Top, Bottom, Jacket …)। এখানে দেওয়া <strong>Default SMV</strong> নতুন style-এ আন্দাজি SMV হিসেবে কাজে লাগে।',
                'steps' => ['Code, Name, Category (knit / woven) আর Default SMV (এক পিস সেলাইয়ে গড়ে কত মিনিট) দিন।'],
                'example' => 'D-BTM = Bottom, woven, Default SMV 22।',
                'before' => '—',
                'after' => 'Inquiry / Style',
            ],
            'masters.wash-types' => [
                'title' => 'Wash Types',
                'what' => 'Garment wash-এর ধরন (Enzyme, Stone …)। Style-এ wash type দিলে বোঝা যায় washing stage লাগবে।',
                'steps' => ['Code আর Name দিন।'],
                'example' => 'D-ENZ = Enzyme Wash — D-1001 Men\'s Chino-তে ব্যবহার হয়েছে।',
                'before' => '—',
                'after' => 'Style',
            ],
            'masters.ship-modes' => [
                'title' => 'Ship Modes',
                'what' => 'মাল কীভাবে পাঠানো হবে — Sea, Air, Courier ইত্যাদি। Order-এর প্রতিটা PO line-এ ship mode বাছা হয়।',
                'steps' => ['Code আর Name দিন। সাধারণত একবার set করলেই হয়ে যায়।'],
                'example' => 'SEA = Sea Freight, AIR = Air Freight।',
                'before' => '—',
                'after' => 'Order → PO line',
            ],
            'masters.factories' => [
                'title' => 'Factories',
                'what' => 'কোন কারখানায় অর্ডার চলবে — নিজের unit না sub-contract, আর মাসে কত pcs বানাতে পারে।',
                'steps' => ['Code, Name, Capacity / Month আর Own Factory (হ্যাঁ / না) দিন।'],
                'example' => 'D-SFL1 = SFL Unit-1, মাসে 1,20,000 pcs, নিজের।',
                'before' => '—',
                'after' => 'Inquiry / Order',
            ],
            'masters.currencies' => [
                'title' => 'Currencies',
                'what' => 'Costing আর order কোন মুদ্রায় (USD, EUR …)। <strong>Exchange Rate (to BDT)</strong> দিয়ে Post Costing-এ Inventory-র টাকার খরচকে order-এর মুদ্রায় আনা হয়।',
                'steps' => ['ISO Code, Symbol আর আজকের Exchange Rate দিন। Rate বদলালে এখানে আপডেট করুন।'],
                'example' => 'USD, $, rate 120 মানে 1 USD = 120 টাকা।',
                'before' => '—',
                'after' => 'Cost Sheet / Order / Post Costing',
            ],
            'masters.item-categories' => [
                'title' => 'Item Categories',
                'what' => 'Fabric আর trims-কে দলে ভাগ করার জন্য (Fabric, Sewing Trims, Packing …)। Cost sheet আর BOM-এ এই ভাগ অনুযায়ী সাজানো হয়।',
                'steps' => ['Code, Name আর Type (fabric / trims / accessories / packing) দিন।'],
                'example' => 'D-TRM = Sewing Trims (type trims)।',
                'before' => '—',
                'after' => 'Items',
            ],
            'masters.items' => [
                'title' => 'Items (Fabric & Trims)',
                'what' => 'Costing আর BOM-এ যে কাপড় ও trims লাগে তাদের তালিকা — unit, supplier আর আন্দাজি দাম সহ।',
                'steps' => [
                    'Type, Category, Unit (Inventory থেকে আসে), Default Supplier, Default Price দিন।',
                    'Fabric হলে Composition আর GSM দিন।',
                ],
                'example' => 'D-TWL = Cotton Twill 240 GSM, unit Yrd, দাম 2.80 — Men\'s Chino-র মূল কাপড়।',
                'before' => 'Item Categories',
                'after' => 'Cost Sheet / BOM',
            ],
            'masters.sample-types' => [
                'title' => 'Sample Types',
                'what' => 'Sample-এর ধাপগুলো (Proto, Fit, Size Set, PP …) কোন ক্রমে হয় আর buyer-এর approval লাগে কিনা।',
                'steps' => ['Code, Name, Sequence (ক্রম) আর Needs Buyer Approval দিন।'],
                'example' => 'PROTO (1) → FIT (2, buyer approval লাগে) → SIZESET (3) → PP (4, Pre-Production)।',
                'before' => '—',
                'after' => 'Samples / T&A',
            ],
            'masters.garment-parts' => [
                'title' => 'Garment Parts',
                'what' => 'পোশাকের part-এর তালিকা (Front, Back, Sleeve, Collar …)। Cutting-এর <strong>Parts Cut</strong>, part-wise <strong>Embroidery</strong>, Cutting / Embroidery-র <strong>QC ও Rework</strong>, আর reject / rework detail-এর Part — সব জায়গায় এখান থেকেই বাছা হয়।',
                'steps' => [
                    'Code আর Name দিন (যেমন FRONT — Front)।',
                    'যে part আর লাগবে না সেটা Inactive করুন — dropdown থেকে সরে যাবে, পুরনো entry ঠিক থাকবে।',
                ],
                'example' => 'HOOD — Hood: Kids Hoodie-র hood-এ embroidery হলে Embroidery entry-তে Part = Hood বাছবেন।',
                'before' => '—',
                'after' => 'Cutting / Embroidery / QC / Rework',
            ],
            'masters.daily-targets' => [
                'title' => 'Daily Targets',
                'what' => 'প্রতিদিনের লক্ষ্য: কত পিস <strong>cutting</strong>, কত পিস <strong>poly / packing</strong>, আর প্রতিটা sewing line-এর দিনে কত টাকার (FOB) কাজ হওয়া দরকার। <strong>Line Wise Output</strong> report-এ দিনের ও মাসের balance এখান থেকে হিসাব হয়।',
                'steps' => [
                    'তারিখ দিন, তারপর Cutting Target, Poly / Packing Target আর Required Value / Line।',
                    'যেদিনের target দেওয়া নেই, report-এ সেদিনের target 0 ধরা হয়।',
                    'Sewing-এর target এখানে না — সেটা Sewing board-এ প্রতিটা line-এর plan থেকে আসে।',
                ],
                'example' => '07-Oct-2026: Cutting 1,500, Poly 1,200, Required Value / Line 4,000।',
                'before' => '—',
                'after' => 'Reports → Line Wise Output (WIP)',
            ],
            'masters.machine-types' => [
                'title' => 'Machine Types',
                'what' => 'সেলাই মেশিনের ধরন (SNLS, OL, FL …)। এগুলো <strong>Inventory-র মেশিন থেকে নিজে থেকেই</strong> আসে — শুধু helper workplace (MAN, IRON) হাতে যোগ করতে হয়।',
                'steps' => [
                    'Inventory-তে নতুন ধরনের মেশিন যোগ করলে এই পাতা খুললেই type যোগ হয়ে যায়।',
                    'যেখানে মেশিন নেই (হাতের কাজ, iron) সেটা "Helper workplace" টিক দিয়ে যোগ করুন।',
                ],
                'example' => 'SNLS = Single Needle Lock Stitch; MAN = হাতের কাজ (helper)।',
                'before' => 'Inventory → Machines',
                'after' => 'Operations Library / Bulletin',
            ],
            'masters.operations' => [
                'title' => 'Operations Library',
                'what' => 'সেলাইয়ের প্রতিটা কাজের (operation) একটা তালিকা — কোন মেশিনে হয়, কী attachment লাগে, আর স্বাভাবিকভাবে কত মিনিট লাগে (Default SMV)। <strong>Bulletin</strong> বানানোর সময় এখান থেকে operation বাছলে নাম, মেশিন, attachment, SMV নিজে থেকেই বসে যায়।',
                'steps' => [
                    'Code, Name, Machine Type, Attachment আর Default SMV দিন।',
                    'Bulletin-এ style অনুযায়ী SMV দরকার হলে বদলানো যায় — এখানকার মান শুধু শুরুর মান।',
                ],
                'example' => '"Shoulder join", মেশিন OL, SMV 0.35 → Bulletin-এ বাছলে Tar/Hr = 60 ÷ 0.35 ≈ 171।',
                'before' => 'Machine Types',
                'after' => 'Bulletin → SMV → T&A capacity',
            ],

            // ---------------------------------------------------------------- Dev (R&D)
            'inquiries' => [
                'title' => 'Inquiry (Buyer-এর জিজ্ঞাসা)',
                'what' => 'Buyer প্রথম যখন জানতে চায় "এই style কত দামে, কবে দিতে পারবে" — সেই তথ্য এখানে রাখা হয়। এখান থেকেই style আর costing শুরু হয়, আর পরে দেখা যায় কতগুলো inquiry order হলো, কতগুলো lost।',
                'steps' => [
                    'Buyer, Season, Product Type, Style Ref, Order Qty, Unit Price, Target Ship Date দিন।',
                    'Status আপডেট করুন: quoted → confirmed (order হলে) বা lost (কারণ লিখে)।',
                ],
                'example' => 'H&M, D-SS27, Men\'s Chino D-1001, 1000 pcs × $9.40, status confirmed।',
                'before' => 'Buyers',
                'after' => 'Style / Cost Sheet',
            ],
            'styles' => [
                'title' => 'Style / Tech Pack',
                'what' => 'একটা পোশাকের ডিজাইনের সব তথ্য: style no, কাপড়, wash, SMV, CM, ছবি আর tech pack file। পরের সব ধাপ (costing, sample, order, BOM, bulletin) এই style ধরে চলে।',
                'steps' => [
                    '<strong>New Tech Pack</strong>-এ Style বাছুন — style (Style No, Name, Buyer) আগে <strong>Master Data → Styles</strong>-এ add করতে হয়; এখানে হাতে লেখা যায় না। তালিকায় শুধু যেসব style-এর tech pack এখনো হয়নি সেগুলো আসে।',
                    'Season, Product Type, Wash Type দিন; inquiry থেকে এলে Inquiry বেছে নিন।',
                    'SMV, Target / Confirm CM (প্রতি dozen) দিন।',
                    'Show পাতায় ছবি আর tech pack file upload করুন।',
                    'Development Status এগিয়ে নিন: in development → sample stage → approved।',
                ],
                'example' => 'D-1001 Men\'s Chino — H&M, Enzyme Wash, SMV 22, status approved।',
                'before' => 'Inquiry',
                'after' => 'Cost Sheet / Samples / Order',
            ],
            'cost-sheets' => [
                'title' => 'Cost Sheet (pre-order costing, প্রতি dozen)',
                'what' => 'Order নেওয়ার আগে হিসাব: এক dozen বানাতে কাপড়, trims, process, CM, commercial আর profit মিলিয়ে কত খরচ — buyer-এর target price-এ পোষাবে কিনা। Approve করা cost sheet-ই পরে Post Costing-এ "budget" হয়।',
                'steps' => [
                    'উপরে Tech Pack / Style বা Inquiry বাছুন — Buyer, Style, Size, Order Qty, SMV, Buyer Target নিজে বসে। Currency আর Price Type (FOB …) দিন।',
                    '<strong>A–G</strong> ভাগে (A Fabric, B Accessories, C Wash, D Stone, E Print, F Heat Seal, G Embroidery / অন্য process) প্রতিটা row-এ item, supplier, consumption (প্রতি dozen) আর unit price — Total /Dz নিজে হিসাব হয়। Library item বাছলে unit, দাম, supplier নিজে বসে।',
                    'SMV, CPM (প্রতি মিনিটের খরচ) আর Efficiency দিলে CM / Dz নিজে হিসাব হয় (চাইলে নিজে লিখুন)। Commercial %, Other, Profit % দিন — ডানের <strong>Summary</strong>-তে DZN / PC / % আর FOB / pc সাথে সাথে দেখায়, target-এর সাথে তুলনাও।',
                    'Show পাতায় পুরো <strong>Open Cost Sheet</strong> (ছবি Tech Pack থেকে) — Print দিলে সেটাই কাগজে আসে।',
                    'Buyer দাম মেনে নিলে Final Price দিয়ে <strong>Approve</strong> করুন।',
                ],
                'example' => 'D-1001: Twill 15.6 yrd/dz × $2.80, thread, zipper, label, polybag; CM $24/dz → FOB প্রায় $9.40/pc।',
                'before' => 'Inquiry / Style',
                'after' => 'Order / Post Costing',
                'actions' => ['create' => 'Consumption সবসময় <strong>প্রতি dozen</strong> (12 pcs) হিসেবে দিন।'],
            ],

            // ---------------------------------------------------------------- Samples
            'samples' => [
                'title' => 'Samples',
                'what' => 'Buyer-কে পাঠানো প্রতিটা sample (Fit, PP, Size Set …) কোন অবস্থায় আছে তা track করা: Requested → In Progress → Submitted → Approved / Rejected।',
                'steps' => [
                    'Style, Sample Type, Qty, Size, Color আর Required (Due) Date দিয়ে request করুন।',
                    'বানানো শুরু হলে status "In Progress" করুন।',
                    'পাঠালে <strong>Submit</strong> করুন (তারিখ, courier, tracking no)।',
                    'Buyer-এর মতামত এলে Approve / Reject করুন — reject হলে নিজে থেকেই পরের revision তৈরি হয়।',
                ],
                'example' => 'D-1002 Kids Hoodie-র FIT sample reject হয়েছে → revision 1 খুলেছে। D-1003-র PP submitted, approval-এর অপেক্ষায়।',
                'before' => 'Style',
                'after' => 'Order / T&A (sample তারিখ নিজে বসে)',
            ],

            // ---------------------------------------------------------------- Order + BOM
            'orders' => [
                'title' => 'Orders (+ PO lines)',
                'what' => 'Buyer-এর পাকা অর্ডার। একটা order-এর ভেতরে এক বা একাধিক <strong>PO line</strong> থাকে (PO × style × color, size অনুযায়ী qty, দাম, PCD, shipment date)। Production, T&A, BOM, report — সব PO line ধরে চলে।',
                'steps' => [
                    'উপরে Order header দিন: Buyer, Season, Factory, Currency, Buyer Order Ref, Payment / Delivery Term।',
                    'নিচে <strong>Sizes of this order</strong>-এ এই order-এ কোন কোন size লাগবে বাছুন (size master থেকে) — শুধু সেগুলোর কলাম আসে।',
                    'একই পাতায় <strong>PO Lines</strong> table: একটা PO-তে একাধিক style থাকলে একই PO No দিয়ে style অনুযায়ী আলাদা row। প্রতিটা row-এ PO No, Style (color style থেকে নিজে আসে), size অনুযায়ী qty, Unit Price, PCD, Shipment Date, Ship Mode, আর Emb / Wash লাগবে কিনা। <strong>Add Row</strong> দিয়ে আরও row; <i class="fa-solid fa-copy"></i> দিয়ে একটা row কপি (একই style অন্য color-এ)। Style বাছলে দাম (cost sheet) আর shipment date নিজে বসে।',
                    'একবার <strong>Save Order</strong> চাপলেই header আর সব PO line একসাথে সেভ হয়। পরে বদলাতে <strong>Edit</strong> — একই পাতা।',
                    'সব ঠিক হলে Status <strong>Confirmed</strong> করুন — শুধু confirmed order production-এ যায়।',
                    'Confirm-এর পর qty / PCD / shipment বদলালে revision হিসেবে লেখা থাকে (T&A Sheet-এ original / revised দেখায়)।',
                ],
                'example' => 'H&M order-এ PO D-HM-4501 (সব তৈরি, Finish Store-এ) আর D-HM-4502 (confirm-এর পর 400 → 450 করা, এখন sewing-এ)।',
                'before' => 'Cost Sheet / Samples',
                'after' => 'BOM / Bulletin / T&A / Production',
            ],
            'boms' => [
                'title' => 'BOM (Bill of Materials)',
                'what' => 'একটা style বা order বানাতে কোন কাপড়, trims, packing কত পরিমাণ লাগবে তার তালিকা। Order BOM হলে order qty দিয়ে মোট দরকারি পরিমাণ (wastage সহ) নিজে হিসাব হয়।',
                'steps' => [
                    'BOM Type বাছুন (style / order), Style বা Order দিন।',
                    'প্রতিটা item-এর consumption আর wastage % দিন — Required Qty নিজে আসে।',
                    'ঠিক থাকলে <strong>Approve</strong> করুন; পরে বদলাতে হলে <strong>Revise</strong> করুন (নতুন version)।',
                ],
                'example' => 'D-1001 order BOM: Twill 1.3 yrd/pc × 1000 pcs + wastage → মোট কত yrd কিনতে হবে।',
                'before' => 'Order / Cost Sheet',
                'after' => 'Fabric Requisition / T&A (BOM approved তারিখ)',
            ],
            'post-costing' => [
                'title' => 'Post Cost Sheet (budget বনাম actual)',
                'what' => 'Production শেষে (বা চলাকালে) একটা PO-তে আসলে কত খরচ হলো তা approved cost sheet-এর budget-এর সাথে মিলিয়ে দেখা — লাভ হলো না ক্ষতি।',
                'steps' => [
                    'এখানে entry দিতে হয় না। PO খুললেই budget (cost sheet × qty) আর actual (Inventory থেকে issue হওয়া মাল) পাশাপাশি দেখায়।',
                    'Store-এ দাম না থাকলে actual আন্দাজে (budget / pc × packed) দেখায় — সেটা চিহ্ন দিয়ে বোঝানো থাকে।',
                    'Reject loss = reject pcs × budget material / pc।',
                ],
                'example' => 'Budget material $6.50/pc × 1000 = $6,500, store থেকে issue $6,800 → material-এ $300 বেশি খরচ।',
                'before' => 'Cost Sheet (approved) / Inventory issue / Production',
                'after' => '—',
            ],

            // ---------------------------------------------------------------- Planning
            'lines' => [
                'title' => 'Lines (sewing line)',
                'what' => 'HR-এর floor-line-এর সাথে planning-এর সংখ্যা যোগ করা: কতজন operator, helper, দিনে কত মিনিট কাজ, efficiency কত %। এখান থেকেই হিসাব হয় একটা line দিনে কত pcs বানাতে পারবে।',
                'steps' => [
                    'HR floor-line বেছে Operators, Helpers, Working Minutes / Day (OT সহ) আর Efficiency % দিন।',
                    'মেশিন সংখ্যা Inventory-র মেশিন থেকে আসে (যে মেশিনের line = এই line-এর নাম)।',
                ],
                'example' => 'ধরুন Line 2: 30 operator × 600 মিনিট × 60% ÷ SMV 22 ≈ দিনে 490 pcs।',
                'before' => 'HR floor-lines / Inventory machines',
                'after' => 'Bulletin / T&A',
            ],
            'tna-templates' => [
                'title' => 'T&A Templates',
                'what' => 'T&A (Time & Action) plan-এর ছাঁচ: কোন কোন কাজ (sample, fabric in-house, PCD, sewing, shipment …) কোন ক্রমে, shipment-এর কত দিন আগে করতে হবে। নতুন T&A বানালে এই template থেকে task আর তারিখ তৈরি হয়।',
                'steps' => [
                    'Template Name আর lead days দিন (Ex-Factory, cutting, finishing + packing)।',
                    'প্রতিটা task-এ কত working day আগে, আর "auto source" দিন — যেমন bom_approved দিলে BOM approve হলেই actual তারিখ নিজে বসে।',
                    'একটা template-কে Default করুন।',
                ],
                'example' => 'Default template (MsflDefaultMasterSeeder) — 34টা task, যেমন FILE_HANDOVER, PILOT_STITCHING, FABRIC_LC।',
                'before' => 'Sample Types',
                'after' => 'T&A Plan',
            ],
            'bulletins' => [
                'title' => 'Bulletin (Operation Breakdown)',
                'what' => 'একটা style সেলাই করতে কোন কোন operation লাগে, প্রতিটা কোন মেশিনে, কত SMV — তার পূর্ণ তালিকা। এখান থেকে মোট SMV, ঘণ্টায় target, কত workplace / manpower লাগবে আর machine summary বের হয়। Approved bulletin-এর SMV দিয়েই T&A-তে line capacity হিসাব হয়।',
                'steps' => [
                    'Style, Target / Hr, Working Hours / Day দিন (চাইলে অন্য bulletin থেকে copy করুন)।',
                    'প্রতিটা row-এ <strong>Operations Library</strong> থেকে operation বাছুন — মেশিন, attachment, SMV নিজে বসে; দরকারে SMV বদলান।',
                    'নিচে Total SMV, Req W-Place, Manpower, Utilization দেখে নিন। Print করে floor-এ দিন।',
                    '<strong>Approve</strong> করুন — নতুন T&A এই SMV ব্যবহার করবে।',
                ],
                'example' => 'ধরুন Men\'s Chino-র সব operation-এর SMV যোগ = 22, target 100 pcs/hr → দরকারি workplace ≈ 22 × 100 ÷ 60 ≈ 37।',
                'before' => 'Operations Library / Style / Lines',
                'after' => 'T&A (capacity, sewing days)',
                'fields' => [
                    'হেডার' => [
                        'Style No' => 'কোন স্টাইলের বুলেটিন — <code>msfl_styles</code> থেকে বাছা হয়; এই স্টাইলের SMV/T&amp;A পরে এখান থেকেই আসবে।',
                        'Description' => 'ফ্রি টেক্সট; ফাঁকা রাখলে স্টাইলের নাম বসে যায়।',
                        'Date' => 'বুলেটিনের তারিখ (<code>bulletin_date</code>) — ডিফল্ট আজকের তারিখ।',
                        'Target / Hr' => '<strong>হাতে লেখা, হিসাব করা না</strong> — ঘণ্টায় কত পিস target, এটাই বাকি সব হিসাবের মূল ভিত্তি (<code>target_per_hour</code>)। Required, 1–100000।',
                        'Working Hours / Day' => 'দিনে কত ঘণ্টা কাজ (<code>working_hours</code>), ডিফল্ট ১০। Reference Line বাছলে সেই লাইনের working_minutes÷৬০ থেকে auto বসে যায় (চাইলে বদলানো যায়)।',
                        'Reference Line' => 'শুধু (ক) Working Hours auto-fill করতে, আর (খ) নিচের মেশিন-সামারিতে এই লাইনে কোন machine type কয়টা লাগবে vs কয়টা আছে তা দেখাতে — SMV/manpower-এর কোনো হিসাবে এটা অংশ নেয় না।',
                        'Copy operations from' => 'অন্য একটা বুলেটিন বেছে নিলে তার Line, Working Hours আর সব operation row হুবহু কপি করে আনে — শুরুর পয়েন্ট হিসেবে; approval status কপি হয় না, নতুনটা সবসময় draft হিসেবে শুরু হয়।',
                    ],
                    'প্রতিটা Operation Row' => [
                        'Section' => 'ফ্রি টেক্সট (যেমন "Back &amp; Front Part") — ফাঁকা রাখলে ঠিক উপরের row-এর section-ই ধরে নেওয়া হয়।',
                        'From Library' => 'Operations Library (Master Data) থেকে বাছলে Machine, Attachment, Operation name, SMV — এই চারটাই auto বসে যায় (<code>msfl_operations</code> টেবিল থেকে); পরে চাইলে হাতে বদলানো যায়, বদলালেও Library-র মূল রেকর্ড বদলায় না।',
                        'M/C (Machine Type)' => 'এই operation কোন machine type-এ হয় — Machine Types Master-এ যেটা "Helper workplace" ফ্ল্যাগ করা আছে (যেমন MAN, IRON — মেশিন না এমন কাজ), তার workplace নিচের সামারিতে Operators-এ না গিয়ে Helpers-এ যোগ হয়।',
                        'Attachment' => 'কাজের সহায়ক টুল/গাইড (Guide, Folder, Jig ইত্যাদি) — Attachments Master থেকে, Library-পিক করলে auto বা হাতেও লেখা যায়।',
                        'Operation' => 'অপারেশনের নাম/বর্ণনা — টেক্সট।',
                        'SMV' => '<strong>এক পিসের জন্য Standard Minute Value — এটাই মূল ইনপুট, নিচের প্রায় সব হিসাব এখান থেকেই বের হয়।</strong> ০-এর বেশি হতে হবে (required)।',
                        'Tar/Hr' => 'শুধু দেখানোর জন্য (সেভ হয় না) — <strong>= round(৬০ ÷ SMV)</strong> — একটা workplace একাই ঘণ্টায় কত পিস দিতে পারে।',
                        'Req W-Place' => 'শুধু দেখানোর জন্য — <strong>= round(SMV × হেডারের Target/Hr ÷ ৬০, ২ দশমিক)</strong> — এই operation-এ target ধরে রাখতে তাত্ত্বিকভাবে কতগুলো workplace লাগবে তার হিসাব।',
                        'W-Place' => 'ফাঁকা রাখলে auto (Req W-Place-কে round-up করে, কমপক্ষে ১টা) — নিজে সংখ্যা লিখলে সেটাই চূড়ান্ত ধরা হয় (জায়গা/লোকবল সীমিত থাকলে override করতে)। সেভ হয় <code>workplaces</code> কলামে।',
                        'P.Target' => 'শুধু দেখানোর জন্য — <strong>= Tar/Hr × চূড়ান্ত W-Place</strong> — এই operation-এ যতগুলো workplace আসলে বসানো হয়েছে তা দিয়ে বাস্তবে ঘণ্টায় কত পিস বের হবে।',
                        'Bottleneck %' => 'শুধু দেখানোর জন্য — <strong>= round(Req W-Place ÷ চূড়ান্ত W-Place × ১০০)</strong> — ১০০%-এর বেশি/সমান হলে লাল-বোল্ড হয়ে যায়, মানে এই operation target ধরতে পারছে না — এটাই সেই operation যেটা লাইনকে আটকে রাখছে (bottleneck)।',
                        'Do (✓)' => 'আনচেক করলে এই row ডিলিট হয় না, শুধু নিচের সব টোটাল হিসাব থেকে বাদ পড়ে যায় — মানে এই নির্দিষ্ট style-এ অপারেশনটা করা হচ্ছে না।',
                        'Remarks' => 'ফ্রি টেক্সট নোট।',
                    ],
                    'উপরের Summary সারি' => [
                        'SMV (Total)' => '= টিক ✓ দেওয়া সব row-এর SMV-র যোগফল।',
                        'Target / Hr' => 'হেডারে যা লিখেছেন তাই হুবহু দেখায় — এখানে আলাদা কোনো হিসাব নেই।',
                        'Target / Day' => '= round(Target/Hr × Working Hours/Day)।',
                        'Operators' => '= টিক ✓ দেওয়া row-গুলোর মধ্যে non-helper machine-এর মোট W-Place যোগফল।',
                        'Helpers' => '= টিক ✓ দেওয়া row-গুলোর মধ্যে helper machine-এর (MAN/IRON ইত্যাদি) মোট W-Place যোগফল।',
                        'Ttl MP (Total Manpower)' => '= Operators + Helpers = টিক ✓ দেওয়া সব row-এর W-Place-এর যোগফল।',
                        'R-SMV (Required SMV)' => '= Ttl MP × ৬০ ÷ Target/Hr — এই পরিমাণ manpower বসিয়ে exactly target ধরতে যে SMV দরকার হতো, তার হিসাব। SMV (Total)-এর সাথে তুলনা করলে বোঝা যায় manpower দরকারের চেয়ে বেশি না কম বসানো হয়েছে।',
                        'Utilization' => '= round(SMV ÷ R-SMV × ১০০)% — বসানো manpower-টা আসলে operation-গুলোর SMV কতটা ব্যবহার করছে তার শতাংশ। ১০০% মানে perfectly matched; কম % মানে কিছু workplace/manpower দরকারের চেয়ে বেশি বসানো আছে।',
                        'Max' => '= টিক ✓ দেওয়া row-গুলোর মধ্যে সবচেয়ে বেশি P.Target (সবচেয়ে দ্রুত operation-টার সম্ভাব্য আউটপুট)।',
                        'Min' => '= সবচেয়ে কম P.Target — <strong>এটাই আসলে পুরো লাইনের বাস্তব ঘণ্টাপ্রতি output</strong>, কারণ সবচেয়ে ধীর/দুর্বল operation-ই পুরো লাইনের গতি ঠিক করে দেয়।',
                        'Bottleneck %' => '= সব row-এর Bottleneck %-এর মধ্যে সবচেয়ে বেশিটা — কোন operation সবচেয়ে চাপে/ঘাটতিতে আছে তা এক নজরে দেখায়।',
                        '(Save Bulletin করলে)' => 'এই একই formula সার্ভারেও আবার হিসাব হয়ে <code>msfl_bulletins</code> টেবিলে (total_smv, target_per_day, operators, helpers, total_manpower, r_smv, utilization_percent, max_p_target, min_p_target, bottleneck_percent) স্থায়ীভাবে সেভ হয়ে যায় — এরপর Show/Print/List পেজ এই সেভ-করা ভ্যালুগুলোই দেখায়, প্রতিবার নতুন করে হিসাব করে না।',
                    ],
                ],
            ],
            'learning-curve' => [
                'title' => 'Learning Curve (Day-wise Ramp-up)',
                'what' => 'নতুন একটা style লাইনে বসানোর পর প্রথম দিনগুলোতে worker-রা পুরো speed-এ কাজ করতে পারে না — output ধাপে ধাপে বাড়ে, আর কখনোই ১০০% efficiency-তে পৌঁছায় না। এই পেজে SMV অনুযায়ী Day 1→10-এ কত % efficiency ধরা হবে তার টেবিল আছে (দুই সেট — Production সবাই দেখে, Shipment শুধু Planning Manager-এর নিজের rough হিসাবের জন্য), আর একটা Calculator যেটা দিয়ে একটা স্টাইলের বাস্তবসম্মত day-wise production estimate বের করা যায়। <strong>এখনো এটা শুধু রেফারেন্স/ক্যালকুলেটর — T&amp;A বা Sewing Board কোনো auto-হিসাবে এটা এখনো যুক্ত হয়নি।</strong>',
                'steps' => [
                    'নিচের টেবিলে প্রতিটা SMV band-এর Day 1–10 % চাইলে বদলে Save করুন (এটাই মাস্টার ডেটা, সবার Calculator-এ এটাই ব্যবহার হবে)।',
                    '<strong>Calculator</strong>-এ যান — Style বাছুন (SMV auto আসবে) বা নিজে SMV দিন, Manpower আর Working Hours/Day দিন।',
                    'চাইলে Order Qty দিন — কোন দিনে cumulative output টার্গেটে পৌঁছাবে তা দেখাবে।',
                    'Shipment curve হলে Washing/Finishing বেছে দিলে sewing শেষের পর Ready Date আনুমানিক দেখাবে।',
                ],
                'example' => 'SMV 14, Manpower 45, Hours 8 হলে Day 1-এ (৪০%) ≈ 617 পিস, Day 10-এ (৯০%) ≈ 1389 পিস — কখনো তত্ত্বীয় সর্বোচ্চ ১৫৪৩ পিস/দিনে পৌঁছায় না।',
                'before' => 'Bulletin (SMV)',
                'after' => 'T&A Plan / Sewing Board (ভবিষ্যতে, এখনো auto-link হয়নি)',
                'actions' => [
                    'calculate' => 'এই পেজের হিসাব কোথাও সেভ হয় না — প্রতিবার খুললে নতুন করে হিসাব করতে হবে। Target Qty আর Start Date দিলে কোন দিনে/কবে সেলাই শেষ হবে তার estimate আরও স্পষ্ট হবে।',
                ],
                'fields' => [
                    'Master টেবিল' => [
                        'SMV Range / Ref. SMV' => 'কোন SMV-এর স্টাইল এই band-এ পড়বে (<code>smv_from</code>–<code>smv_to</code>) — শুধু দেখার জন্য, Ref. SMV হলো উদাহরণের মান।',
                        'Day 1–10' => 'সেই day-তে প্রত্যাশিত efficiency % (<code>day_percents</code>, JSON array) — Save করলেই Calculator-এ সাথে সাথে প্রভাব পড়বে।',
                    ],
                    'Calculator' => [
                        'SMV' => 'Style বাছলে auto আসে (<code>msfl_styles.smv</code>), নাহলে হাতে দিন — এই SMV দিয়েই কোন band ব্যবহার হবে তা ঠিক হয়।',
                        'Manpower × Working Hours/Day' => '= তাত্ত্বিক ১০০% ক্ষমতা হিসাবের ভিত্তি: <code>Manpower × Hours × 60 ÷ SMV</code> — এটাকেই প্রতিদিনের % দিয়ে গুণ করা হয়।',
                        'Day Qty' => '= round(তাত্ত্বিক ক্ষমতা × সেই day-এর %) — ঠিক যেভাবে মূল শীটের সংখ্যাগুলো বের হয়েছে।',
                        'Cumulative Qty' => '= আগের সব দিনের Day Qty-র যোগফল।',
                        'Order Qty মিললে' => 'যে দিনে Cumulative প্রথম Order Qty ছুঁয়ে ফেলে সেই row সবুজ হয়ে যাবে — সেটাই আনুমানিক sewing-শেষের দিন।',
                        'Ready Date (শুধু Shipment)' => '= Start Date + যতদিনে Order Qty মিলল + Washing days + Finishing days।',
                    ],
                ],
            ],
            'tna' => [
                'title' => 'T&A Plan (Time & Action)',
                'what' => 'একটা confirmed order-এর জন্য কাজের সময়সূচি: shipment date থেকে পিছনে working day গুনে প্রতিটা task-এর plan তারিখ, আর line capacity দিয়ে কত দিন sewing লাগবে। Actual তারিখ অনেকগুলো নিজে থেকেই বসে (order confirm, BOM approve, sample, cutting শুরু …)।',
                'steps' => [
                    'Confirmed Order → Style → Line(s) → T&A Template বেছে নিন।',
                    'System হিসাব করে: daily capacity = Σ line (operator × মিনিট × efficiency ÷ SMV), sewing days = qty ÷ capacity।',
                    'PCD আজকের আগে চলে গেলে plan "not feasible" দেখায় — line বাড়ান বা shipment দেখুন।',
                    'Show পাতায় যে task হাতে করতে হয় তার actual তারিখ দিন।',
                ],
                'example' => 'ধরুন 4,500 pcs, দুটো line মিলিয়ে দিনে 900 pcs → 5 দিন sewing; shipment থেকে পিছনে working day গুনে Ex-Factory, Sewing End, PCD বসে।',
                'before' => 'Order / Bulletin / Lines / T&A Template',
                'after' => 'T&A Sheet / Production',
                'actions' => ['show' => 'সবুজ = সময়মতো হয়েছে, লাল = overdue। "auto" চিহ্নের task-এর তারিখ অন্য পাতার কাজ থেকে নিজে আসে।'],
            ],
            'tna-sheet' => [
                'title' => 'T&A Sheet (buyer format)',
                'what' => 'Buyer যে 81-column T&A sheet চায়, প্রতি PO-তে এক row। বেশিরভাগ ঘর আগের ধাপগুলো (order, style, cost sheet, BOM, sample, Buyer Store GRN, T&A task) থেকে নিজে ভরে যায়।',
                'steps' => [
                    'Buyer / তারিখ দিয়ে filter করে Print করুন।',
                    'কোনো ঘর খালি থাকলে সেটা যে পাতা থেকে আসে সেখানে entry দিন (যেমন T&A task-এর actual তারিখ)।',
                    'PCD check: PASS / FAIL / PENDING — cutting সময়মতো শুরু হয়েছে কিনা।',
                ],
                'example' => 'Confirm-এর পর PO qty 400 থেকে 450 করলে sheet-এ original 400, revised 450 দেখাবে।',
                'before' => 'T&A Plan ও আগের সব ধাপ',
                'after' => 'Buyer-কে পাঠানো',
            ],

            // ---------------------------------------------------------------- Production
            'production.status' => [
                'title' => 'Production Status (PO অনুযায়ী)',
                'what' => 'প্রতিটা PO কোন stage-এ কত pcs — কত কাটা হলো, সেলাই, wash, finishing, QC, packing; প্রতিটা stage-এ WIP, rework, reject।',
                'steps' => ['এখানে entry দিতে হয় না। PO খুললে stage-wise আর size-wise হিসাব দেখায়।'],
                'example' => 'ধরুন একটা PO-তে Cutting 1000, Sewing pass 950, sewing WIP 40, reject 10 — এখানে stage-এর পাশাপাশি দেখাবে।',
                'before' => 'Cutting ও stage entry',
                'after' => '—',
            ],
            'production.requisitions' => [
                'title' => 'Fabric Requisition',
                'what' => 'Cutting-এর জন্য store থেকে কাপড় / trims চাওয়া। এটা <strong>Inventory-তে requisition</strong> হিসেবে যায়; approve হলে store মাল issue করে। Issue PO-র সাথে যুক্ত থাকে, তাই Post Costing-এ actual খরচ আসে।',
                'steps' => [
                    'Store, Requesting Department, Buyer, Style আর Order Ref দিন।',
                    'Item, qty দিন — পাশে store-এ কত stock আছে দেখায়।',
                    'Submit করলে approval-এ যায়, তারপর Inventory-তে store issue করে।',
                ],
                'example' => 'Next-এর জন্য Denim 12 oz 1,100 yrd চাওয়া হলো → store issue করার পর cutting শুরু করা যাবে।',
                'before' => 'BOM / Buyer Store GRN (মাল ঢোকা)',
                'after' => 'Cutting',
                'actions' => ['show' => 'উপরে requisition-এর তথ্য, মাঝে item-wise চাওয়া / approve / নেওয়া / বাকি, নিচে store কোন তারিখে কোন challan-এ কত দিয়েছে।', 'index' => 'Requisition No-তে click করলে details দেখবেন। Buyer / style অনুযায়ী দেখতে উপরের <strong>Buyer / Style Details</strong> আর মোট হিসাবের জন্য <strong>Summary</strong> বাটন।', 'create' => 'Buyer Store থেকে issue হয় শুধু তখনই যখন একই buyer + style-এর <strong>posted GRN</strong> আছে। "only has 0 left in the Buyer Store" error মানে ওই style-এর জন্য এখনো মাল receive (GRN) হয়নি — আগে Inventory-তে GRN করুন।'],
            ],
            'production.cuttings' => [
                'title' => 'Cutting',
                'what' => 'কাপড় কেটে কত pcs হলো — size অনুযায়ী, কোন part, আর কত pcs করে bundle। Cutting হলো production-এর প্রথম stage — যা কাটা হয় সবই "pass" ধরা হয়, পরের stage এখান থেকে নেয়।',
                'steps' => [
                    'PO, Cutting Date, Table No, Lay / Ply Count আর Fabric Used দিন।',
                    'Size অনুযায়ী pcs আর Pcs per Bundle দিন — bundle নিজে তৈরি হয় (print করা যায়)।',
                    'পরের stage এই pcs নিয়ে ফেললে cutting আর delete করা যায় না।',
                    'কাটা panel-এ reject বা rework পাওয়া গেলে <strong>Production → QC</strong>-এ Step = Cutting বেছে দিন; rework ঠিক হলে <strong>Production → Rework</strong>-এ।',
                    '<strong>সীমা:</strong> একটা size-এ মোট কাটা = order qty + সর্বোচ্চ <strong>5%</strong> বেশি (wastage)। এর বেশি, PO-তে নেই এমন size, বা ভবিষ্যতের তারিখ দিলে সিস্টেম নেবে না — error-এ লেখা থাকে আর কত কাটা যাবে।',
                ],
                'example' => 'ধরুন 450 pcs কাটা হলো, 25 pcs করে bundle → 18টা bundle।',
                'before' => 'Fabric Requisition (issue)',
                'after' => 'Embroidery (থাকলে) / Sewing',
            ],
            'production.entries.embroidery' => $stage('Embroidery', '<strong>Part-wise</strong> — Cutting থেকে নির্দিষ্ট part (যেমন Front) embroidery-তে যায়, কাজ শেষে আবার Cutting-এ ফেরত আসে। পাঠানো আর ফেরত <strong>আলাদা entry, আলাদা তারিখে</strong> — "Send to Embroidery" আর "Return to Cutting" বাটন। প্রতিটা entry-তে <strong>Part</strong> দিন। Cutting balance = কাটা − embroidery-তে থাকা − sewing-এ দেওয়া; Sewing এই balance থেকে নেয়। শুধু সেই PO-তে যেখানে "needs embroidery" টিক দেওয়া।', 'Cutting (part পাঠানো)', 'Cutting (ফেরত) → Sewing', 'Order 1000 pcs, Red M কাটা 100 pcs। 50 pcs embroidery-তে পাঠানো → cutting balance 50। পরে 30 ফেরত → balance 80, sewing এখান থেকে নেবে।'),
            'production.sewing' => [
                'title' => 'Sewing — Line-wise Daily Production',
                'what' => 'প্রতিটা line-এর দিনের হিসাব: line-এ কত pcs input দেওয়া হলো (তারিখ অনুযায়ী), প্রতি ঘণ্টায় কত output, reject আর rework। Target, manpower, SMV (Planning → Bulletin থেকে) মিলিয়ে <strong>DHU</strong> আর <strong>Efficiency</strong> হিসাব হয়।',
                'steps' => [
                    'উপরের <strong>Line Input</strong> / <strong>Hourly Output</strong> বাটন, অথবা board-এর row-এর ডানের বাটন চাপলে popup খোলে (row থেকে খুললে Line আর Style আগে থেকেই বাছা থাকে)।',
                    '<strong>Line Input</strong>: তারিখ, Line আর Style · Color বাছুন, তারপর size অনুযায়ী কত pcs line-এ দেওয়া হলো লিখুন। শুধু cutting balance থেকে দেওয়া যায় (embroidery-তে থাকা pcs বাদে)।',
                    '<strong>Hourly Output</strong>: তারিখ, Line, Style · Color আর ঘণ্টা (যেমন 9-10 AM) বাছুন। প্রথমবার দিনের Target, Hour, SMV, Operator, Helper দিন (Bulletin থেকে নিজে বসে যায়)। তারপর size অনুযায়ী Output, Reject, Rework লিখুন।',
                    'Board-এ প্রতি ঘণ্টার output, Today, Previous, Grand, Balance, DHU আর Efficiency দেখা যায়। Print চাপলে পুরো sheet print হয়।',
                    'ভুল entry মুছতে <strong>Entries</strong> বাটনে যান।',
                ],
                'example' => 'Line 2: Target 300, 8 ঘণ্টা, SMV 11, 20 জন (10 operator + 10 helper) → Work Min 9,600। দিনে 600 output হলে Produced Min 6,600 → Efficiency 68.75%। 600 output, 12 reject, 18 rework → DHU 4.76%।',
                'before' => 'Cutting (balance) / Embroidery (ফেরত)',
                'after' => 'Washing (থাকলে) / Finishing',
            ],
            'masters.banks' => [
                'title' => 'Banks',
                'what' => 'Commercial কাগজে যে bank গুলো লাগে: <strong>আমাদের bank</strong> (যেখানে LC lien / advise হয়, পরে BTB LC খোলা হবে), <strong>buyer-এর bank</strong> (যে bank buyer-এর LC খোলে), supplier-এর bank। একবার দিলে Export LC-তে বাছাই করা যায়, আর invoice print-এ নাম, branch, SWIFT আসে।',
                'steps' => ['Code, নাম, Type (Our / Buyer\'s / Supplier\'s), Branch, SWIFT, AD Code দিন। আমাদের bank হলে Account No-ও দিন।'],
                'example' => 'DBBL-GUL = Dutch-Bangla Bank, Gulshan, SWIFT DBBLBDDH, Our Bank; NDEA-STO = Nordea Bank, Stockholm, Buyer\'s Bank।',
                'before' => '—',
                'after' => 'Export LC (Issuing / Lien Bank)',
            ],
            'masters.payment-terms' => [
                'title' => 'Payment Terms',
                'what' => 'Buyer কীভাবে, কতদিনে টাকা দেবে: LC at Sight, Usance 30 / 60 / 90 / 120 দিন, TT, DP / DA। দিন গোনা হয় B/L বা document-এর তারিখ থেকে — পরে Bank Submission-এ due date এখান থেকে হিসাব হবে।',
                'steps' => ['Code, নাম, Type আর Days দিন। সাধারণ term গুলো আগে থেকেই দেওয়া আছে।'],
                'example' => 'LC-U90 = Usance 90 days → document accept হওয়ার 90 দিন পর টাকা।',
                'before' => '—',
                'after' => 'Export LC (Payment Term)',
            ],
            'commercial.dashboard' => [
                'title' => 'Commercial Dashboard',
                'what' => 'এক নজরে commercial: চলমান LC / SC কয়টা, মোট LC value, কত ship হলো আর কত বাকি, ৩০ দিনের মধ্যে কোন LC-র মেয়াদ শেষ হচ্ছে, এই মাসের invoice আর শেষ ১০টা invoice।',
                'steps' => ['এখানে entry দিতে হয় না — সংখ্যা আসে Export LC আর Commercial Invoice থেকে।', 'লাল / হলুদ সারির LC-তে আগে নজর দিন: হয় ship করুন, নয় buyer-এর কাছে amendment চান।'],
                'example' => null,
                'before' => 'Export LC / Commercial Invoice',
                'after' => '—',
            ],
            'commercial.export-lcs' => [
                'title' => 'Export LC / Sales Contract',
                'what' => 'Buyer-এর পাঠানো LC বা Sales Contract। কোন কোন PO এই LC-র আওতায়, LC value কত, last shipment আর expiry কবে — আর কত ship হলো, কত বাকি। Order-এর data আবার লিখতে হয় না, শুধু PO টিক দিন।',
                'steps' => [
                    '<strong>New LC / SC</strong>: Buyer, Type (LC / SC), Buyer-এর LC No, তারিখ, Currency, LC Value, Tolerance %, Last Shipment, Expiry দিন; <strong>Payment Term</strong>, <strong>Issuing Bank</strong> আর <strong>Lien Bank</strong> তালিকা থেকে বাছুন (Commercial → Setup); LC-র copy attach করুন।',
                    'নিচে buyer-এর confirmed PO-গুলো আসে — যেগুলো এই LC-র, টিক দিন। উপরে "Picked PO value" বনাম LC value মিলিয়ে দেখায়।',
                    'Save → LC পাতায় <strong>Activate</strong> দিন — তবেই invoice বানানো যায়। সব ship হয়ে গেলে <strong>Close</strong>।',
                    'Activate-এর পর LC value / last shipment / expiry বদলালে সেটা <strong>Amendment</strong> হিসেবে লেখা থাকে।',
                ],
                'example' => 'H&M LC 0012345: value $3,742.80, PO D-HM-4501 + D-HM-4502, last shipment 30-Nov, expiry 15-Dec, at sight।',
                'before' => 'Order (confirmed PO)',
                'after' => 'Commercial Invoice',
            ],
            'commercial.invoices' => [
                'title' => 'Commercial Invoice (+ Packing List)',
                'what' => 'একটা চালানে কী পাঠানো হলো: কোন LC-র বিপরীতে, কোন PO কত pcs, carton, EXP, B/L, vessel, port, weight। দাম PO-র FOB থেকে নিজে আসে। এখান থেকেই <strong>Commercial Invoice</strong> আর <strong>Packing List</strong> print হয়।',
                'steps' => [
                    '<strong>New Invoice</strong> → active Export LC বাছুন। উপরে size ধরে "Can ship" দেখায় (production-এ pack হয়েছে − আগে invoice হয়েছে)।',
                    '<strong>Carton Packing List</strong>: প্রতিটা সারি = একই রকম pack করা কয়েকটা carton — PO, Carton From…To, <strong>প্রতি carton-এ</strong> size ধরে pcs, প্রতি carton-এর NW / GW (kg), মাপ L × W × H (cm)। Copy বাটনে পরের carton নম্বর নিজে বসে।',
                    'উপরের EXP / B/L / vessel / port দিন → Save। Invoice-এর qty, carton, ওজন, CBM আর টাকা carton list থেকে নিজে হিসাব হয়।',
                    'Invoice পাতায় <strong>Commercial Invoice</strong> / <strong>Carton Packing List</strong> বাটনে print।',
                    'মাল পাঠানোর সময় <strong>Create Shipment</strong> চাপুন — Inventory Shipment buyer, invoice no, PO আর qty সহ ভরা অবস্থায় খোলে। Save করলে Gate Pass খোলে; Gate Pass-এর print-ই <strong>Delivery Challan</strong> (invoice-এর carton থেকে size ধরে qty)।',
                ],
                'example' => 'IN261017-001 (packing list PL261017-001): LC ELC-2026-0001, PO D-HM-4501 400 pcs × $9.40 = $3,760, 20 carton, B/L MAEU123।',
                'before' => 'Export LC (active) / Packing / Inventory Shipment',
                'after' => 'LC Status / Export Register (পরে Bank Submission)',
                'actions' => ['create' => 'Size ধরে packed − আগে invoice করা qty-র বেশি দেওয়া যায় না; carton নম্বর দুই সারিতে একই হতে পারে না। LC value + tolerance ছাড়ালে save হয়, কিন্তু সতর্ক করে — amendment লাগতে পারে।'],
            ],
            'commercial.supplier-pis' => [
                'title' => 'Supplier PI',
                'what' => 'Merchandiser fabric / accessories order দেওয়ার পর supplier যে PI (Proforma Invoice) পাঠায়, তার record — PDF সহ। শুধু তথ্য রাখা হয়; stock, order বা production-এ কিছু বদলায় না।',
                'steps' => [
                    '<strong>New Supplier PI</strong>: Supplier, PI No, তারিখ, কী মাল (Fabric / Accessories), কোন Order, কোন Master LC, Currency আর PI Value দিন; PI-এর PDF attach করুন।',
                    'BTB LC খোলা হলে এই PI-টা সেই BTB LC-তে টিক দিলে তালিকায় "BTB LC" কলামে নম্বর দেখায়।',
                ],
                'example' => 'ABC Textiles PI-2045, 18-Oct, Fabric, Order ORD-2026-0011, USD 12,500।',
                'before' => 'Merchandising (fabric / accessories order)',
                'after' => 'Back-to-Back LC',
            ],
            'commercial.btb-lcs' => [
                'title' => 'Back-to-Back LC',
                'what' => 'Supplier PI-এর বিপরীতে আমাদের bank-এ খোলা BTB LC-র record — কোন Master LC-র আওতায়, কোন PI-গুলো, value, expiry, maturity আর LC copy।',
                'steps' => [
                    '<strong>New BTB LC</strong>: BTB LC No, তারিখ, Master LC, Supplier, Bank, Currency, Value, Tenor, Expiry / Maturity দিন; LC copy attach করুন।',
                    'নিচে <strong>Supplier PI(s)</strong>-এ যে PI-গুলোর জন্য LC খোলা হলো সেগুলো বাছুন — যেসব PI-তে এখনো BTB নেই শুধু সেগুলোই আসে।',
                    'LC পাতায় তার PI আর Import Shipment-এর তালিকা দেখা যায়।',
                ],
                'example' => 'BTB 0987/26, Master LC ELC-2026-0001, ABC Textiles, USD 12,500, 120 days, PI-2045।',
                'before' => 'Supplier PI / Export LC',
                'after' => 'Import Shipment',
            ],
            'commercial.import-shipments' => [
                'title' => 'Import Shipment',
                'what' => 'BTB LC-র মাল কবে রওনা দিলো, কবে পৌঁছালো, document আর মাল কবে release হলো — তার record, document সহ। Inventory-র GRN আলাদা, এটা শুধু commercial tracking।',
                'steps' => ['<strong>New Import Shipment</strong>: BTB LC, Supplier, Supplier-এর invoice no / value, Ship Mode, B/L বা AWB, ETD, ETA দিন।', 'মাল পৌঁছালে / document release হলে Edit করে তারিখগুলো বসান।'],
                'example' => 'BTB 0987/26, B/L MAEU556, ETD 01-Nov, ETA 20-Nov।',
                'before' => 'Back-to-Back LC',
                'after' => 'Inventory GRN (মাল store-এ ঢোকা)',
            ],
            'commercial.export-documents' => [
                'title' => 'Export Documents',
                'what' => 'Commercial Invoice-এর export কাগজ — Certificate of Origin, GSP / Form-A, EXP, Shipping Advice, Booking — নম্বর, তারিখ, forwarder আর PDF। শুধু record; invoice বা packing list বদলায় না।',
                'steps' => ['<strong>New Export Document</strong>: Commercial Invoice বাছুন, Document-এর ধরন, নম্বর, তারিখ দিন, PDF attach করুন।', 'Booking / Shipping Advice-এ Freight Forwarder আর Booking Date দিন। এক invoice-এর জন্য যত কাগজ দরকার, তত entry।'],
                'example' => 'IN261017-001: GSP Form-A BD-123456, 20-Oct।',
                'before' => 'Commercial Invoice',
                'after' => 'Bank Realization',
            ],
            'commercial.realizations' => [
                'title' => 'Bank Realization',
                'what' => 'Buyer-এর টাকা bank-এ আসার পর তার record — কোন invoice, কত realized, bank charge, PRC / ERC no আর bank advice-এর PDF। পাতায় invoice value বনাম মোট realized আর বাকি দেখায়।',
                'steps' => ['<strong>New Realization</strong>: Commercial Invoice, তারিখ, Bank, Realized Amount, Bank Charge, PRC / ERC দিন; PDF attach করুন।', 'টাকা কয়েক ভাগে এলে প্রতিটার আলাদা entry — পাতায় মোট যোগ হয়ে দেখায়।'],
                'example' => 'IN261017-001: USD 3,740 realized, bank charge 20, PRC 556677।',
                'before' => 'Commercial Invoice / Export Documents',
                'after' => '—',
            ],
            'production.final-packing-lists' => [
                'title' => 'Final Packing List',
                'what' => 'Floor-এ packing শেষ হলে buyer-এর একটা PO-র (তার সব style) চূড়ান্ত packing list — size ধরে <strong>Order</strong>, <strong>Cut</strong> আর <strong>Ship</strong> (pack হওয়া) qty, আর style ধরে <strong>Short / Excess</strong> ও তার কারণ। Buyer approve করলে এখানে লিখে রাখুন — তখন সংখ্যা আটকে যায়।',
                'steps' => [
                    '<strong>New</strong> → buyer-এর PO বাছুন: Order, Cutting আর Packing থেকে size ধরে সংখ্যা নিজে আসে।',
                    'Short / Excess-এর কারণ: Buyer QC-তে reject থাকলে নিজে বসে; না হলে QC লিখবে (যেমন "01 pcs Marketing Sample Send")।',
                    '<strong>Print</strong> করে buyer-কে দিন। Approve পেলে তারিখ, কে approve করল আর approve করা কপি দিয়ে <strong>Buyer Approved</strong>।',
                    'ভুল হলে <strong>Re-open</strong> — সংখ্যা আবার production থেকে আসে।',
                    '<strong>Invoice No</strong>: Commercial Invoice হলে নিজে বসে; তার আগে Edit-এ হাতে লিখে রাখা যায়।',
                    'নিচের <strong>After packing</strong> বক্সে style ধরে দেখায়: pack হয়েছে → Finish Store-এ এসেছে → shipment হয়েছে → invoice হয়েছে। <strong>Receive in Finish Store</strong> বাটনে Inventory-র Finish Store receive খোলে PO বাছা অবস্থায়।',
                ],
                'example' => 'PO KK-SFL-52-26: 267504 Mustard order 35, cut 35, ship 34 (−1, Marketing Sample); 267505 Khaki 20 / 20 / 19।',
                'before' => 'Packing / Buyer QC',
                'after' => 'Finish Store (Inventory) → Commercial Invoice → Shipment → Delivery Challan',
            ],
            'production.hourly-report' => [
                'title' => 'Daily Hourly Production Report',
                'what' => 'Sewing floor-এর ঘণ্টা ধরে হিসাব: প্রতিটা line × style-এর জন্য তিন সারি: <strong>Output</strong>, <strong>Efficiency</strong> আর <strong>DHU</strong>, প্রতি ঘণ্টায়। সাথে Man Power, SMV, Input Start Date, Running Day, Target Eff % আর Hourly Target। নিচে সব line মিলিয়ে Grand Total।',
                'steps' => [
                    'তারিখ (আর চাইলে line) বেছে <strong>Filter</strong>, তারপর <strong>Print</strong>।',
                    'এখানে entry দিতে হয় না — সংখ্যা আসে Sewing board-এর Line Input আর Hourly Output থেকে। Target, hour, SMV, manpower আসে line-এর দিনের plan থেকে।',
                    'লাল ঘর = সেই ঘণ্টায় output hourly target-এর চেয়ে কম।',
                ],
                'example' => 'Line 1: 33 জন, SMV 20.8, এক ঘণ্টায় 30 pcs → Efficiency = 30 × 20.8 ÷ (33 × 60) = 31.5%। ওই ঘণ্টায় 2 reject + 1 rework → DHU = 3 ÷ 33 = 9.1%।',
                'before' => 'Sewing board (Line Input, Hourly Output)',
                'after' => '—',
            ],
            'production.entries.sewing' => $stage('Sewing', 'Line-এ সেলাই — দিনে কোন line কত pcs নিল, কত pass, rework, reject। Reject কোন part-এ আর কোন মেশিনে হয়েছে তাও দেওয়া যায় (মেশিন optional)।', 'Cutting / Embroidery', 'Washing (থাকলে) / Finishing', 'ধরুন Line 2-এ আজ 200 input, 180 pass, 12 rework (open seam), 3 reject (needle hole — front part, একটা SNLS মেশিনে)।'),
            'production.entries.washing' => $stage('Washing', 'Garment wash — শুধু সেই PO-তে যেখানে "needs washing" টিক দেওয়া। পাঠানো ("Send to Washing") আর ফেরত ("Receive from Washing") আলাদা entry, আলাদা তারিখে।', 'Sewing', 'Finishing', 'Ladies Denim Jacket (Stone Wash): 480 input, 478 pass, 2 reject (shade)।'),
            'production.entries.finishing' => $stage('Finishing', 'Thread cutting, ironing, button, tag লাগানো ইত্যাদি। <strong>Receive in Finishing</strong> (কত pcs এলো) আর <strong>Finishing Output</strong> (কত শেষ হলো) আলাদা entry, আলাদা দিনে। Style · Color বাছলেই Sewing-এর মতো সব size-এর table আসে — কত নেওয়া যায় দেখায়, <strong>Fill all</strong> চাপলে বাকি সবটা বসে যায়।', 'Sewing / Washing', 'Buyer QC', 'ধরুন 950 pcs input, 950 pass।'),
            'production.entries.final_qc' => ['actions' => ['create' => 'Reject / Rework-এর Detail-এ প্রতিটা row-এ <strong>Severity</strong> (Critical / Major / Minor) বাছা বাধ্যতামূলক — DHU রিপোর্টে এটাই গোনা হয়।', 'edit' => 'Detail-এ প্রতিটা row-এ <strong>Severity</strong> বাছুন (আগের entry-তে না থাকলে এখন বাছতে হবে)।']]
                + $stage('Buyer QC', '<strong>Buyer QC</strong> — তৈরি পোশাকের (ready product) QC, Finishing শেষ হওয়া pcs Packing-এর আগে buyer-এর মান অনুযায়ী check। এটা step-wise QC (Production → QC) থেকে আলাদা। Pass হওয়া pcs-ই Packing নিতে পারে; alter / rework ঠিক হলে Production → Rework → Buyer QC card-এ দিন।', 'Finishing', 'Packing', 'ধরুন 950 input, 945 pass, 5 reject (stain)।'),
            'production.entries.packing' => $stage('Packing', 'Polybag, carton-এ ভরা। <strong>Receive in Packing</strong> আর <strong>Packed (Output)</strong> আলাদা entry; size-এর table, <strong>Fill all</strong>। Packed qty-ই Inventory-র <strong>Finish Store</strong>-এ receive করা যায় (তার বেশি না)।', 'Buyer QC', 'Inventory → Finish Store receive', '945 pcs packed → Inventory-তে সর্বোচ্চ 945 pcs Finish Store-এ receive করা যাবে।'),

            'production.qc' => [
                'title' => 'QC (step-wise)',
                'what' => 'প্রতিটা step-এর নিজের QC — Cutting, Embroidery, Sewing, Washing, Finishing। ওই step-এর pass হওয়া pcs check করে কত <strong>Pass</strong> আর কত <strong>Reject</strong> দিন। এখানে rework নেই — rework <strong>Production → Rework</strong>-এ আলাদা। (তৈরি পোশাকের QC <strong>Buyer QC</strong> আলাদা মেনু।)',
                'steps' => [
                    'Step-এর <strong>card</strong>-এ click করুন, তারপর <strong>PO</strong>, তারিখ দিন (Sewing হলে Line; Cutting / Embroidery হলে <strong>Part</strong>)।',
                    '<strong>QC Pass</strong> = check করে ঠিক পাওয়া pcs (শুধু গোনা হয়, pass থেকে কমে না)। <strong>Reject</strong> = বাতিল — ওই step-এর pass থেকে কমে, flow থেকে বেরিয়ে যায়।',
                    'নিচের Detail-এ reject-এর Part, Defect আর (চাইলে) Machine দিন — যোগফল মিলতে হবে।',
                    'প্রতিটা Detail row-এ <strong>Severity</strong> (Critical / Major / Minor) বাছা বাধ্যতামূলক — <strong>Daily Quality DHU & RFT</strong> রিপোর্টে Critical / Major / Minor সংখ্যা এখান থেকেই আসে।',
                ],
                'example' => 'Cutting-এ কাটা 830 pcs check: 827 pass, 3টা Front panel-এ fabric hole (Reject 3) → Cutting pass 827।',
                'before' => 'Cutting / Sewing / … entry',
                'after' => 'পরের step',
                'actions' => ['create' => 'Style · Color বাছলে PO-র <strong>সব size-এর table</strong> আসে — প্রতিটা size-এ কত pass হওয়া pcs এখনো আছে দেখায়; যে size-এ লিখবেন শুধু সেগুলো save হয় (বাকি ফাঁকা রাখুন)। Detail row-এ size বাছুন — প্রতিটা size-এর reject-এর সাথে মিলতে হবে।'],
            ],
            'production.rework' => [
                'title' => 'Rework',
                'what' => 'Rework-এর পুরো হিসাব আলাদা এখানে — যেকোনো step-এর (Buyer QC-সহ)। <strong>Rework Found</strong>: pass হওয়া pcs-এর মধ্যে যেগুলো rework-এ পাঠানো হলো (ঠিক না হওয়া পর্যন্ত ওই step-এ থাকে)। <strong>Passed after rework</strong> / <strong>Reject</strong>: ঠিক করার পর কত pass, কত বাতিল।',
                'steps' => [
                    'Step-এর <strong>card</strong>-এ click করুন, তারপর <strong>PO</strong>, তারিখ দিন (Sewing হলে Line; Cutting / Embroidery হলে <strong>Part</strong>)।',
                    'Rework-এ পাঠালে <strong>Rework Found</strong> দিন (Detail-এ কারণ সহ)।',
                    'ঠিক হলে <strong>Passed after rework</strong> দিন (পরের step নিতে পারবে); ঠিক না হলে <strong>Reject</strong>। একই entry-তে দুটোই দেওয়া যায়, আলাদা দিনেও।',
                    'Detail-এর প্রতিটা row-এ <strong>Severity</strong> (Critical / Major / Minor) বাছতে হবে।',
                ],
                'example' => 'Cutting-এর 5টা sleeve বাঁকা → Rework Found 5 (pass 827 থেকে 822)। পরদিন recut: 4টা ঠিক (Passed 4), 1টা নষ্ট (Reject 1) → Cutting pass 826।',
                'before' => 'QC / step entry',
                'after' => 'পরের step',
                'actions' => ['create' => 'Style · Color বাছলে সব size-এর table আসে — প্রতিটা size-এ pass হওয়া কত pcs আছে (rework-এ পাঠানো যায়) আর কত pcs rework-এ আটকে আছে। এক entry-তে কয়েকটা size একসাথে দেওয়া যায়।'],
            ],

            // ---------------------------------------------------------------- Reports
            'reports' => [
                'title' => 'Reports',
                'what' => 'সব entry থেকে তৈরি report — screen-এ দেখা আর print করা যায়। এখানে কিছু entry দিতে হয় না।',
                'steps' => ['Report বাছুন → buyer / তারিখ / stage / line দিয়ে filter → Print।'],
                'example' => 'Daily Production দিয়ে আজ কোন line কত pass দিল দেখুন।',
                'before' => 'সব পাতার entry',
                'after' => '—',
            ],
            'reports.order-book' => self::report('Order Book', 'চলমান order-এর প্রতিটা PO line: qty, value, তারিখ, কত packed আর কত Finish Store-এ।', 'Order / Packing / Finish Store'),
            'reports.lc-status' => self::report('LC Status', 'প্রতিটা Export LC / Sales Contract: LC value, তার PO-গুলোর value, কত ship হলো (commercial invoice থেকে), কত বাকি, last shipment আর expiry — কত দিন বাকি। ৩০ দিনের মধ্যে expiry হলুদ, পার হলে লাল।', 'Commercial → Export LC, Commercial Invoice'),
            'reports.export-register' => self::report('Export Register', 'তারিখ অনুযায়ী সব commercial invoice: buyer, LC, কোন PO / style, qty, carton, value, EXP আর B/L।', 'Commercial Invoice'),
            'reports.line-output' => self::report('Line Wise Output (WIP)', 'একটা তারিখের sewing floor-এর হিসাব: line × style অনুযায়ী আজকের input, target, output, short / excess, মোট output আর line-এ কত WIP পড়ে আছে। নিচে line অনুযায়ী আজকের ও মাসের (১ তারিখ থেকে) target / output-এর FOB value ও CM, আর factory-র cutting, poly (packing) ও shipout — target, achieve, balance।', 'Sewing board (input, hourly output, plan), Cutting, Packing, Daily Targets, Inventory Shipment'),
            'reports.daily-production' => self::report('Daily Production', 'দিন অনুযায়ী প্রতিটা stage-এর input, pass, rework, reject — line অনুযায়ী filter করা যায়।', 'Cutting ও stage entry'),
            'reports.defects' => self::report('Defect Analysis', 'Reject আর rework কোন stage-এ, কোন part-এ, কোন মেশিনে, কোন defect-এ বেশি হচ্ছে।', 'Stage entry-র defect row'),
            'reports.shipment-status' => self::report('Shipment Status', 'Shipment তারিখ অনুযায়ী PO, কত কাটা / সেলাই / packed, আর সময়মতো হবে কিনা (risk)।', 'Order / Production'),
            'reports.tna-status' => self::report('T&A Status', 'চলমান T&A-র যে task এখনো খোলা — overdue আর সামনের 7 দিনে due।', 'T&A Plan'),
            'reports.requisition-details' => self::report('Requisition Details', 'Buyer / style / PO অনুযায়ী প্রতিটা requisition-এর item: কত চাওয়া হলো, কত approve, কত store থেকে নেওয়া হলো, আর কবে কত নেওয়া হলো (তারিখ: qty, challan)। Style দিয়ে filter করলে এক style-এর সব requisition একসাথে দেখা যায়।', 'Fabric Requisition / Inventory issue'),
            'reports.requisition-summary' => self::report('Requisition Summary', 'Buyer → style → item অনুযায়ী মোট: কয়টা requisition, কত চাওয়া, approve, নেওয়া, কত এখনো বাকি, আর প্রথম ও শেষ কবে নেওয়া হলো।', 'Fabric Requisition / Inventory issue'),
            'reports.sample-turnaround' => self::report('Sample Turnaround', 'প্রতিটা sample: request → submit → decision, কত দিন লাগল।', 'Samples'),
        ] + self::opsReports();
    }

    /**
     * The operations reports (Support\OpsReports) — Excel-template layouts with typed cells.
     * Each: what it is, how to use it, and every column's source / calculation.
     */
    private static function opsReports(): array
    {
        $sys = 'সিস্টেম থেকে আসে (সাদা ঘর)';
        $typed = 'আপনি লিখবেন (হলুদ ঘর)';
        $calc = 'নিজে হিসাব হয় (নীল ঘর)';
        $ops = fn (string $title, string $what, array $extraSteps, array $fields, string $example, string $before, string $after) => [
            'title' => $title,
            'what' => $what,
            'steps' => array_merge([
                'উপরে <strong>Reporting Date</strong> (দরকার হলে From / To, Buyer, Line) বেছে <strong>Filter</strong> চাপুন। Daily রিপোর্ট ওই তারিখের হিসাব দেখায়।',
                'রঙের মানে: <strong>সাদা</strong> = সিস্টেমের ডেটা (বদলানো যায় না) · <strong>হলুদ</strong> = আপনি লিখবেন · <strong>নীল</strong> = formula, নিজে হিসাব হয়। কিছু হলুদ ঘরে ধূসর লেখা থাকে — ওটা সিস্টেমের মান; আপনি নতুন কিছু লিখলে সেটাই চলবে, মুছে দিলে আবার সিস্টেমেরটা।',
                'হলুদ ঘরে লিখে নিচে <strong>Save changes</strong> চাপুন — শুধু বদলানো ঘরগুলো save হয়। কোনো ঘর ভুল হলে (সংখ্যার জায়গায় লেখা, সীমার বেশি, ভুল তারিখ) উপরে লাল error-এ কোন row, কোন column লেখা থাকে, আর <strong>কিছুই save হয় না</strong> — ঠিক করে আবার Save।',
                '<strong>Print</strong> = ডেটাসহ। <strong>Blank Print</strong> = খালি ফর্ম (৫টা খালি row) — হাতে লিখে ব্যবহারের জন্য।',
            ], $extraSteps),
            'fields' => $fields,
            'example' => $example,
            'before' => $before,
            'after' => $after,
            'actions' => ['index' => null],
        ];

        return [
            'ops-reports' => [
                'title' => 'Operations Reports',
                'what' => 'Factory-র ৮টা রিপোর্ট, client-এর Excel template-এর হুবহু কলাম আর KPI সহ: Shipment Readiness, Pre-Production Readiness, Material Shortage, Daily Cutting, Daily Quality DHU & RFT, Daily Finishing & Packing, Daily Line CM & Profit, Order Closing। যে ডেটা সিস্টেমে আছে নিজে আসে, যা নেই রিপোর্টেই লিখে রাখা যায়, আর হিসাব সব নিজে হয়।',
                'steps' => [
                    'একটা রিপোর্ট খুলুন — প্রতিটার উপরে এরকম "এই পাতা কী কাজে" box-এ সেই রিপোর্টের প্রতিটা column কোথা থেকে আসে আর কী হিসাব, লেখা আছে।',
                    'হলুদ ঘরে লিখতে অনুমতি লাগে: Role Setup → <strong>Reports → Edit (report inputs)</strong> বা <strong>All</strong>। শুধু View থাকলে রিপোর্ট দেখা আর print করা যায়।',
                    'ভুল এন্ট্রি আটকানো আছে: Cutting order-এর 5%-এর বেশি না; পরের stage আগের stage-এর বেশি নিতে পারে না; Shipment Finish Store-এর বেশি না; Gate Pass shipment ও stock-এর বেশি না; Production Consumption stock-এর বেশি না; production শুরু হওয়া PO-র style / color বা কাটা size বদলানো যায় না।',
                ],
                'example' => null,
                'before' => 'Order · Production · Inventory · Commercial',
                'after' => 'Print / Client',
            ],

            'ops-reports.shipment-readiness' => $ops('Shipment Readiness & Risk Report',
                'কোন PO ship করার জন্য কতটা তৈরি — production, inspection আর export document মিলিয়ে প্রতিটা PO-র <strong>Overall Risk</strong>। Ex-factory তারিখ সামনে-পিছে (default ১৫ দিন আগে থেকে ৪৫ দিন পর) পড়ে এমন PO দেখায়।',
                ['Packing List, Invoice, EXP, UD / Contract, BL / AWB — Final Packing List ও Commercial-এ দেওয়া থাকলে নিজে আসে; না থাকলে এখানে লিখুন।'],
                [
                    $sys => [
                        'Buyer / Style / PO / Product / Order Qty' => 'Order → PO line থেকে।',
                        'Ex-Factory Date' => 'T&A-র ex-factory তারিখ (PO-র নিজের shipment তারিখ থেকে T&A-র একই ব্যবধান বাদ দিয়ে); T&A না থাকলে shipment তারিখ। লিখে বদলানো যায়।',
                        'Finished Qty' => 'Production → Finishing-এর মোট output।',
                        'Packed Qty' => 'Production → Packing-এর মোট packed (QC reject বাদ)।',
                        'Packing List / Invoice / EXP / UD / Contract / BL / AWB' => 'Final Packing List (Approved / Draft), Commercial Invoice (Invoice, EXP, B/L), Export LC (UD / Contract)। না থাকলে নিজে লিখুন।',
                    ],
                    $typed => [
                        'Inspection Booking' => 'Buyer inspection-এর তারিখ।',
                        'Inspection Status / AQL Result / Carton Status' => 'তালিকা থেকে বাছুন (Not Booked / Booked / Passed / Failed …; Pass / Fail / Pending; Not Started / In Progress / Completed)।',
                        'Freight Booking / Customs Status' => 'Pending / Booked / Not Required; Pending / Submitted / Cleared।',
                        'Required Action / Owner / Remarks' => 'যা করতে হবে, কে দায়িত্বে।',
                    ],
                    $calc => [
                        'Production Ready %' => 'Packed Qty ÷ Order Qty × 100।',
                        'Documents Ready %' => '৭টা document-এর কয়টা হয়েছে ÷ 7 × 100: Packing List (Approved), Invoice, EXP, UD/Contract, BL/AWB, Freight (Booked বা Not Required), Customs (Cleared)।',
                        'Overall Risk' => '<strong>Ready</strong> = 100% packed + AQL Pass + সব document। না হলে: AQL Fail / Inspection Failed / ex-factory ৩ দিন বা কম → <strong>Critical</strong>; ৭ দিন বা কম → <strong>High</strong>; ১৪ দিন বা কম → <strong>Medium</strong>; বাকি <strong>Low</strong>।',
                        'KPI' => 'Shipment POs, Order Qty, Packed Qty, Ready POs, Critical / High — উপরের হিসাবের যোগফল।',
                    ],
                ],
                'DEMO-PO-01: 600 order, 326 packed → 54.3% ready; ex-factory ২ দিন পর আর সব document হয়নি → Critical।',
                'Packing · Final Packing List · Commercial', 'Shipment / Gate Pass'),

            'ops-reports.pre-production' => $ops('Pre-Production Readiness Report',
                'Production শুরুর (PCD) আগে প্রতিটা PO-র সব approval gate হয়েছে কিনা — Tech Pack থেকে PP Meeting। PCD default ১৫ দিন আগে থেকে ৪৫ দিন পর।',
                ['প্রতিটা gate-এ Approved / Pending / In Progress / Not Applicable বাছুন। "Auto: …" দেখালে সিস্টেম নিজে বের করেছে — বদলাতে চাইলে বাছুন।'],
                [
                    $sys => [
                        'Date' => 'PO-র PCD (production শুরুর তারিখ)।',
                        'PP Meeting Plan' => 'T&A-র PP Meeting task-এর তারিখ।',
                        'Tech Pack' => 'Style-এ tech pack file থাকলে Approved।',
                        'PP Sample / Size Set' => 'Samples-এ PP / Size Set sample: approved → Approved, চলমান → In Progress; T&A-তে done থাকলেও Approved।',
                        'Fabric / Trims / PP Meeting' => 'T&A task (Fabric In-house, Trims In-house, PP Meeting) done → Approved, N/A → Not Applicable।',
                        'Wash Approval' => 'PO-তে "needs washing" না থাকলে Not Applicable; থাকলে T&A Wash Standard।',
                    ],
                    $typed => [
                        'Pattern / Marker / Testing / Other Approval' => 'এগুলো সিস্টেমে নেই — নিজে বাছুন। খালি রাখলে Pending ধরা হয়।',
                        'Owner / Missing Item / Action' => 'দায়িত্ব কার। Missing Item খালি রাখলে নিজে লেখে কোন gate বাকি।',
                    ],
                    $calc => [
                        'Readiness %' => 'Approved gate ÷ (মোট gate − Not Applicable) × 100।',
                        'Overall Status' => '<strong>Ready</strong> = 100%। না হলে: PCD আজ বা পার হয়ে গেছে → <strong>Blocked</strong>; ৭ দিনের মধ্যে → <strong>At Risk</strong>; বাকি <strong>In Progress</strong>।',
                    ],
                ],
                'DEMO-PO-02: 9টা gate-এর 4টা Approved → 44.4%, PCD ৩ দিন পর → At Risk।',
                'Tech Pack · Samples · T&A', 'Cutting'),

            'ops-reports.material-shortage' => $ops('Daily Material Shortage Report',
                'Style অনুযায়ী প্রতিটা material: কত লাগবে, কত এসেছে, কত কম, আর কতদিনে লাগবে — কম থাকলে risk। (Inventory-তে GRN PO ধরে না, style ধরে রাখা হয় — তাই style ধরে হিসাব; PO No.-তে style-এর চলমান PO গুলো।)',
                ['Required Qty BOM থেকে আলাদা হলে নিজে লিখুন; Expected Arrival, Owner, Remarks লিখুন।'],
                [
                    $sys => [
                        'Required Qty' => 'Style-এর Inventory requisition-এ যত চাওয়া হয়েছে (লিখে বদলানো যায়)।',
                        'Received Qty' => 'Style-এর posted GRN-এ যত এসেছে (Merchandising style, বা একই buyer + একই style no)।',
                        'Approved / Issued Qty' => 'Requisition-এ approve আর store থেকে দেওয়া।',
                        'Required By Date' => 'Style-এর চলমান PO-গুলোর সবচেয়ে আগের PCD (লিখে বদলানো যায়)।',
                        'Supplier' => 'GRN থেকে — buyer পাঠালে "Buyer supplied", কিনলে supplier-এর নাম।',
                    ],
                    $typed => ['Expected Arrival / Owner / Remarks' => 'কবে আসবে, কে দেখবে।'],
                    $calc => [
                        'Shortage Qty' => 'Required − Received (কম হলে 0)।',
                        'Shortage %' => 'Shortage ÷ Required × 100।',
                        'Days to Need' => 'Required By − Reporting Date (মাইনাস = দেরি হয়ে গেছে)।',
                        'Risk Status' => 'কম নেই → <strong>OK</strong>; কম আছে আর ৩ দিন বা কম → <strong>Critical</strong>; ৭ দিন বা কম → <strong>High</strong>; বাকি <strong>Medium</strong>।',
                    ],
                ],
                'DEMO style-এ Hang tag লাগবে 1000, এসেছে 600 → 400 কম (40%), PCD পার হয়ে গেছে → Critical।',
                'GRN · Requisition', 'Cutting'),

            'ops-reports.cutting-daily' => $ops('Daily Cutting Production Report',
                'একদিনের cutting: plan বনাম আসল কাটা, reject, recut, কত বাকি আর কাপড় খরচ ঠিক আছে কিনা। সেদিন কাটা হয়েছে, এখনো কাটা চলছে বা সপ্তাহের মধ্যে PCD — এমন PO দেখায়।',
                ['Planned Cut, Recut Qty, Marker Efficiency % লিখুন; BOM না থাকলে Standard Cons./Pc লিখুন।'],
                [
                    $sys => [
                        'Actual Cut' => 'Production → Cutting-এ সেদিন কত কাটা।',
                        'Cut Reject' => 'Production → QC (Cutting)-এ সেদিনের reject।',
                        'Fabric Issued (m)' => 'Cutting entry-র Fabric Used (সেদিনের মোট)।',
                        'Standard Cons./Pc' => 'BOM-এর fabric consumption + wastage (লিখে বদলানো যায়)।',
                    ],
                    $typed => [
                        'Planned Cut' => 'সেদিন কত কাটার plan।',
                        'Recut Qty' => 'Reject হওয়া panel আবার কাটা — PO-র মোট cut reject থেকে আগে recut হওয়া বাদ দিয়ে যত বাকি, তার বেশি না।',
                        'Marker Efficiency %' => '0–100।',
                    ],
                    $calc => [
                        'Net Good Cut' => 'Actual Cut − Cut Reject + Recut।',
                        'Cut Balance' => 'Order Qty − (আজ পর্যন্ত মোট কাটা − মোট reject + মোট recut)। মাইনাস = order-এর বেশি কাটা।',
                        'Achievement %' => 'Actual ÷ Planned × 100।',
                        'Actual Cons./Pc' => 'Fabric Issued ÷ Actual Cut।',
                        'Consumption Var. %' => '(Actual − Standard) ÷ Standard × 100 — প্লাস মানে বেশি কাপড় লেগেছে।',
                    ],
                ],
                'DEMO-PO-02: plan 200, কাটা 150, reject 3, recut 3 → net 150, balance 250, achievement 75%; 180 m ÷ 150 = 1.2 vs standard 1.15 → +4.35%।',
                'Cutting · Cutting QC', 'Sewing'),

            'ops-reports.quality-dhu-rft' => $ops('Daily Quality DHU & RFT Report',
                'Line, PO আর inspection point অনুযায়ী মান: কতগুলো check, কতগুলো খুঁত, DHU আর RFT। Inspection point: Sewing End-line (hourly output), প্রতিটা step-এর QC, আর Buyer QC। (Rework entry মেরামতের হিসাব, inspection না — তাই এখানে নেই।)',
                ['Root Cause, Corrective Action, Owner, Due Date, Action Status লিখুন।'],
                [
                    $sys => [
                        'Checked Pcs' => 'Pass + Alteration + Reject।',
                        'Defective Pcs' => 'Alteration (rework) + Reject।',
                        'Total Defects' => 'QC-র Reject / Rework detail row-এর যোগফল (অন্তত Defective Pcs-এর সমান)।',
                        'Alteration Qty / Reject Qty' => 'Rework আর Reject pcs।',
                        'Critical / Major / Minor Defects' => 'Detail row-এর <strong>Severity</strong> অনুযায়ী (QC, Rework, Buyer QC-তে বাছা বাধ্যতামূলক)।',
                        'Top Defect 1 / 2, Count' => 'সবচেয়ে বেশি হওয়া দুটো defect।',
                    ],
                    $typed => ['Root Cause / Corrective Action / Owner / Due Date / Action Status' => 'কেন হলো, কী ব্যবস্থা, কে, কবে, Open / In Progress / Closed।'],
                    $calc => [
                        'DHU %' => 'Total Defects ÷ Checked × 100 (প্রতি ১০০ পিসে কতটা খুঁত)।',
                        'RFT %' => '(Checked − Defective) ÷ Checked × 100 (প্রথমবারেই ঠিক)।',
                        'Alteration % / Reject %' => 'Alteration ÷ Checked, Reject ÷ Checked × 100।',
                    ],
                ],
                'DEMO-PO-01 Sewing End-line: 213 check, 3 খুঁত → DHU 1.41%, RFT 98.6%।',
                'Sewing hourly · QC · Buyer QC', 'Corrective action'),

            'ops-reports.finishing-packing' => $ops('Daily Finishing & Packing Report',
                'Finishing থেকে Packing পর্যন্ত এলাকা একসাথে: সকালে কত ছিল, কত ঢুকল, কত প্যাক হলো, দিনের শেষে কত রইল, order-এর কত বাকি।',
                ['Thread Trimmed, Ironed, Inspected, Metal Detected, Folded, Poly Completed, Carton Qty, QA Release লিখুন। এগুলো সেদিন finishing-এ থাকা pcs (Opening WIP + Received)-এর বেশি দেওয়া যায় না।'],
                [
                    $sys => [
                        'Opening WIP' => 'আগের দিন পর্যন্ত: Finishing-এ ঢোকা − Packed − এলাকার reject।',
                        'Received' => 'সেদিন Finishing-এ ঢোকা (Receive in Finishing)।',
                        'Alteration / Rejected' => 'সেদিন Finishing / Buyer QC / Packing-এর rework আর reject।',
                        'Packed Today / Cumulative Packed' => 'সেদিনের packed আর আজ পর্যন্ত মোট।',
                    ],
                    $typed => [
                        'Thread Trimmed … Poly Completed' => 'প্রতিটা ধাপে কত pcs — সীমা: Opening WIP + Received।',
                        'Carton Qty / QA Release / Owner / Remarks' => 'Carton সংখ্যা, Pending / Released / On Hold।',
                    ],
                    $calc => [
                        'Closing WIP' => 'Opening WIP + Received − Packed Today − Rejected।',
                        'Process Ach. %' => 'Packed Today ÷ (Opening WIP + Received) × 100।',
                        'Order Balance' => 'Order Qty − Cumulative Packed।',
                    ],
                ],
                'DEMO-PO-01: সকালে 58, ঢুকল 120, প্যাক 26 → দিনের শেষে 152; order-এর 274 বাকি।',
                'Buyer QC', 'Finish Store · Shipment'),

            'ops-reports.cm-profit' => $ops('Daily Line CM & Profit Report',
                'প্রতিটা sewing line × PO-র একদিনের আয় (CM) বনাম খরচ — লাভ না লোকসান, target margin ছুঁল কিনা।',
                ['উপরের header-এ একবার rate বসান: BDT/USD, Target Margin, Operator ও Helper-এর ঘণ্টাপ্রতি মজুরি, OT rate, লাইনপ্রতি দিনের Overhead। এগুলো সব দিনের, সব লাইনের জন্য।', 'প্রতিদিন OT Hours আর Rework / Reject / Other Cost লিখুন।'],
                [
                    $sys => [
                        'Good Output' => 'Sewing-এর সেদিনের pass (QC reject বাদ)।',
                        'Selling CM/Pc (USD)' => 'Style-এর Confirm CM (না থাকলে approved Cost Sheet-এর CM, তারপর Target CM) ÷ 12 (dozen থেকে pcs)।',
                        'Operators / Helpers / Working Hours' => 'Sewing board-এর সেদিনের plan।',
                        'Direct Labour (BDT)' => '(Operators × Operator rate + Helpers × Helper rate) × Working Hours।',
                        'OT Cost (BDT)' => 'OT Hours × (Operators + Helpers) × OT rate।',
                        'Allocated OH (BDT)' => 'লাইনের দিনের Overhead, লাইনের PO-গুলোর মধ্যে output অনুযায়ী ভাগ।',
                    ],
                    $typed => ['OT Hours / Rework / Reject / Other Cost' => 'সেদিনের; উপরের ৩টা খরচও চাইলে লিখে বদলানো যায়।'],
                    $calc => [
                        'CM Earned (USD)' => 'Good Output × Selling CM/Pc।',
                        'Total Mfg. Cost (BDT)' => 'Labour + OT + OH + Rework + Reject + Other।',
                        'Mfg. Cost (USD) / per Pc' => 'Total ÷ Exchange Rate; ÷ Good Output।',
                        'Profit / Loss · Margin %' => 'CM Earned − Mfg. Cost (USD); ÷ CM Earned × 100।',
                        'Margin Variance · Status' => 'Margin − Target; লোকসান → Loss, target-এর সমান বা বেশি → On Target, কম → Below Target।',
                    ],
                ],
                'DEMO-PO-01: 208 pcs × $1.50 = $312; খরচ ৳29,597 ÷ 120 = $246.64 → লাভ $65.36, margin 20.9% → On Target।',
                'Sewing board · Style CM', 'Order Closing'),

            'ops-reports.order-closing' => $ops('Style / PO Wise Order Closing Report',
                'Production শেষে (বা চলাকালীন) একটা PO-র পুরো হিসাব: কবে কাটা / সেলাই, কত লোক, কত ঘণ্টা, target বনাম achieve, আর pcs প্রতি লাভ / লোকসান। নিচে signature: IE, Planning, Floor InCharge, QA/QC, Production Manager, MD।',
                ['উপরে <strong>Default CPM</strong> (প্রতি মিনিটের খরচ, USD) বসান — Cost Sheet-এ CPM না থাকলে এটা লাগে।', 'Iron Man Used, Remarks, Management Action লিখুন।'],
                [
                    $sys => [
                        'Shipment Qty' => 'Inventory shipment-এ এই PO-র মোট।',
                        'Cutting Start / Closing · Sewing Start / Completion' => 'প্রথম ও শেষ cutting / sewing তারিখ।',
                        'Operators / Helpers / Hours/Day / SMV / Target Production/day' => 'Sewing plan-এর গড়।',
                        'Working Days' => 'যে কয়দিন sewing output হয়েছে।',
                        'Machines Used' => 'যে লাইনে সেলাই হয়েছে তাদের machine (Line setup)।',
                        'CPM / Cost-Sales Rate' => 'Cost Sheet-এর CPM (না থাকলে Default CPM); Selling CM per pc (Confirm CM ÷ 12)।',
                    ],
                    $typed => ['Iron Man Used / Remarks / Management Action' => 'Iron Man সিস্টেমে নেই — লিখুন।'],
                    $calc => [
                        'Total days in Cutting · Total Used Hours' => 'Closing − Start + 1; Working Days × Hours/Day।',
                        'Achieved Production of the Day · Achievement %' => 'মোট sewing output ÷ Working Days; ÷ Target × 100।',
                        'Hourly Target / Achievement / Avg. Hourly Output' => 'Target ÷ Hours; Achieved ÷ Hours; মোট output ÷ Used Hours।',
                        'Cost/Pc considering Achieved Production' => 'CPM × (Operators + Helpers + Iron Man) × Used Hours × 60 ÷ মোট output।',
                        'PnL / Pcs · PnL of whole Order · CM Margin %' => 'Sales-rate CM − ওপরের cost; × Shipment Qty; ÷ Sales-rate CM × 100।',
                        'Closure Status' => 'Shipment ≥ Order → Closed (বেশি হলে Excess Shipped), না হলে Open — বদলানো যায় (Short Shipped)।',
                    ],
                ],
                'চলমান PO-তে লোকসান দেখাতে পারে — কয়েকদিনের অল্প output-এর বিপরীতে পুরো দিনের লোকবলের খরচ ধরা হয়। PO শেষ হলে আসল ছবি।',
                'Cutting · Sewing · Shipment', 'Management'),
        ];
    }

    private static function report(string $title, string $what, string $source): array
    {
        return [
            'title' => $title . ' Report',
            'what' => $what,
            'steps' => ['উপরের filter দিয়ে buyer / তারিখ বেছে নিন, তারপর <strong>Print</strong>।', 'এখানে entry দিতে হয় না — সংখ্যা আসে: ' . $source . '।'],
            'example' => null,
            'before' => $source,
            'after' => '—',
        ];
    }
}
