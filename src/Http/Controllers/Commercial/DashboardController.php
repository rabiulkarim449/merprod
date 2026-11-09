<?php

namespace ME\MerchandisingSfl\Http\Controllers\Commercial;

use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Models\Commercial\Invoice;
use ME\MerchandisingSfl\Services\Commercial\LcStatus;

/** Commercial at a glance: active LCs (value / shipped / balance), LCs expiring soon, this month's invoices. */
class DashboardController extends Controller
{
    public function index(LcStatus $status): View
    {
        $this->authorize('msfl_com_dashboard.view');

        $active = ExportLc::query()->with(['buyer', 'currency', 'pos'])->where('status', 'active')->orderBy('expiry_date')->get()
            ->map(fn ($lc) => ['lc' => $lc] + $status->figures($lc));
        $month = Invoice::query()->whereBetween('invoice_date', [today()->startOfMonth(), today()->endOfMonth()]);

        return view('merchandising-sfl::admin.commercial.dashboard', [
            'active' => $active,
            'expiring' => $active->filter(fn ($r) => $r['expiry_days'] !== null && $r['expiry_days'] <= 30)->values(),
            'totals' => [
                'lcs' => $active->count(),
                'value' => $active->sum(fn ($r) => (float) $r['lc']->lc_value),
                'shipped' => $active->sum('shipped'),
                'balance' => $active->sum('balance'),
                'month_invoices' => (clone $month)->count(),
                'month_value' => (float) (clone $month)->sum('total_value'),
                'drafts' => ExportLc::where('status', 'draft')->count(),
            ],
            'recent' => Invoice::with(['buyer', 'exportLc'])->latest('invoice_date')->latest('id')->limit(10)->get(),
        ]);
    }
}
