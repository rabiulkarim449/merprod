<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\Buyer;
use ME\MerchandisingSfl\Services\TnaCalendar;
use ME\MerchandisingSfl\Support\Lookups;

/** Planning → T&A Calendar: one row per milestone of each T&A (client's Time & Action Calendar), screen and print. */
class TnaCalendarController extends Controller
{
    public function index(Request $request, TnaCalendar $calendar): View
    {
        $this->authorize('msfl_tna.list');

        $plans = $calendar->plans($request)->paginate(10)->withQueryString();
        $calendar->prepare($plans->getCollection());

        return view('merchandising-sfl::admin.tna-calendar.index', [
            'plans' => $plans, 'calendar' => $calendar, 'summary' => $calendar->summary($request), 'buyers' => Lookups::buyers(),
        ]);
    }

    public function print(Request $request, TnaCalendar $calendar): View
    {
        $this->authorize('msfl_tna.view');

        $plans = $calendar->plans($request)->limit(300)->get();
        $calendar->prepare($plans);

        return view('merchandising-sfl::admin.tna-calendar.print', [
            'plans' => $plans, 'calendar' => $calendar, 'summary' => $calendar->summary($request),
            'buyer' => $request->filled('buyer_id') ? Buyer::find($request->buyer_id) : null,
        ]);
    }
}
