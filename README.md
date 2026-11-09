# Merchandising v2 (`mestiaque/merchandising-sfl`)

The merchandising **and production** module of the Suhana ERP (Laravel 12), shown in the sidebar as three menus: **Merchandising**, **Planning** and **Production**.
It replaced the old `merchandising-trace` and `production-trace` packages (removed 2026-10-05).

> Working on this package with Claude Code? Read [`CLAUDE.md`](CLAUDE.md) — ownership of master data, domain rules, conventions and how to verify changes.

| Section | Screens |
|---|---|
| **Dashboard** | Running orders, today's production by stage, WIP, reject %, shipments due, overdue T&A, top defects (also a widget on the home page) |
| **Master Data** | Buyers (need approval), Seasons, Product Types, Wash Types, Ship Modes, Factories, Currencies, Item Categories, Items (fabric & trims) — colors, sizes, units, suppliers and machines come from **Inventory**; departments, holidays and floor-lines from **HR** |
| **Dev (R&D)** | Inquiries → Tech Pack / Styles → Costing (pre-order cost sheet, per dozen) |
| **Order + BOM** | Orders with PO lines (PO × style × color, sizes; buyer revisions after confirmation are logged) → BOM → Post Cost Sheet (budget vs actual) |
| **Sample Stages** | Requested → In Progress → Submitted → Approved / Rejected (next revision), also decided from the central Approvals page |
| **Planning** | Setup (Machine Types, Lines = HR floor-lines + planning figures, Operations Library, T&A Templates) → Bulletin → T&A (dates from capacity; actuals fill themselves) → T&A Sheet (81-column buyer format, print) |
| **Production** | Fabric Requisition (to an Inventory store) → Cutting (sizes, parts, bundles) → [Embroidery] → Sewing → [Washing] → Finishing → Final QC → Packing, with QC / rework / reject and defect detail at every stage → Finish Store (Inventory) |
| **Reports** | Order Book, Daily Production, Defect Analysis, Shipment Status, T&A Status, Sample Turnaround (screen + print) |

## Naming

| | |
|---|---|
| Namespace | `ME\MerchandisingSfl` |
| Tables | `msfl_*` |
| Routes | `admin/merchandising-sfl/...`, names `msfl.*` |
| Views | `merchandising-sfl::` |
| Permissions | group `MERCHANDISING_SFL`, keys `msfl_*` (e.g. `msfl_order.approve`) |

Needs the host's HR (`hr_*`) and Inventory (`mestiaque/sfl-inventory`) packages.

## Install

```bash
# host composer.json: path repo -> /home/estiaque/Desktop/NIT/SFL/merchandising-sfl, require "mestiaque/merchandising-sfl": "@dev"
composer update mestiaque/merchandising-sfl
php artisan migrate
php artisan storage:link   # uploads go to the "public" disk
# starting data (sample types, ship modes, currencies, machine types, operations, default T&A template):
php artisan db:seed --class="ME\MerchandisingSfl\Database\Seeders\MsflDefaultMasterSeeder"
# optional demo data to try every process (codes start with "D-"):
php artisan db:seed --class="ME\MerchandisingSfl\Database\Seeders\MsflDemoSeeder"
```

Then tick the **MERCHANDISING_SFL** permissions for the role in Roles Setup — the sidebar only shows what the role may see.

## Code map

- `Support/MasterRegistry.php` — field/column definitions of every master; one `MasterController` + `masters/index` view render all of them.
- `Models/CostSheet::recalculate()` — the only place cost-sheet totals are derived (mirrored live in the form JS).
- `Models/Order::styleQty()` / `Bom::orderQtyFor()` / `BomItem::requiredQty()` — order BOM quantities.
- `Services/ProductionFlow` — production route, balances and guards; `Services/TnaPlanner` — T&A dates and auto actuals; `Services/TnaSheet` — the 81-column sheet; `Services/PostCosting`, `Services/Reports`, `Services/DashboardStats`.
- `Services/InventoryMachines` — machine types and line machines read from Inventory.
- `Services/DocumentNumberService` — `INQ / CST / ORD / BOM / SMP / BLT / TNA / CUT-YYYY-0001` numbers (prefixes in `Config/config.php`).

## Planning formulas

- **Line capacity / day** = operators × working minutes × efficiency ÷ SMV (`Line::dailyCapacity`)
- **Bulletin** (factory sheet format, `Models/Bulletin`): input Target/Hr + working hours; per operation Tar/Hr = 60 ÷ SMV, Req W-Place = SMV × Target/Hr ÷ 60, W-Place = override or ⌈Req⌉, P.Target = Tar/Hr × W-Place, Bottleneck % = Req ÷ W-Place. Totals: Ttl MP = Σ W-Place (helper machine types MAN / IRON / VB = helpers, rest = operators), R-SMV = Ttl MP × 60 ÷ Target/Hr, Utilization = SMV ÷ R-SMV, Max / Min P.Target. Machine requirement = Σ W-Place per M/C, compared with the reference line (machines counted from Inventory). Printable sheet: `bulletins/{id}/print`.
- **T&A** (`Services/TnaPlanner`), counted back from the earliest PO shipment date in working days (`Services/WorkingCalendar`, weekly off = `MERCHANDISING_SFL_WEEKLY_OFF`, default Friday, plus HR holidays):
  Ex-Factory = Shipment − template gap · Sewing End = Ex-Factory − gap · Sewing Start = Sewing End − (⌈qty ÷ Σ line capacity⌉ − 1) · PCD = Sewing Start − gap · every step = its anchor ± its days.
  Actual dates fill themselves from order confirmation, BOM / bulletin approval, sample submit / approve, the first Buyer Store fabric receive (Inventory) and production (cutting, sewing start / complete, washing, finishing, final QC, packing). **Recalculate** re-reads the order and moves only the steps not done yet.
- **Production balances** (`Services/ProductionFlow`): can take in = previous stage pass − this stage input; WIP = input − pass − reject (rework stays in WIP until it passes).

