<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\View\View;

/** Merchandising v2 → Dashboard: orders, samples, T&A and production at a glance. */
class DashboardController extends Controller
{
    public function index(): View
    {
        $this->authorize('msfl_dashboard.view');

        return view('merchandising-sfl::admin.dashboard');
    }
}
