<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Services\CapacityCalendar;

/** Planning → Capacity Calendar: capacity of every line per day (client's Factory Capacity Per Day Calendar), screen and print. */
class CapacityCalendarController extends Controller
{
    public function index(Request $request, CapacityCalendar $calendar): View
    {
        $this->authorize('msfl_line.list');

        return view('merchandising-sfl::admin.capacity-calendar.index', $this->data($request, $calendar) + ['lines' => $calendar->lines()]);
    }

    public function print(Request $request, CapacityCalendar $calendar): View
    {
        $this->authorize('msfl_line.list');

        return view('merchandising-sfl::admin.capacity-calendar.print', $this->data($request, $calendar));
    }

    private function data(Request $request, CapacityCalendar $calendar): array
    {
        [$from, $to] = $calendar->period($request);
        $rows = $calendar->rows($request);

        return ['rows' => $rows, 'summary' => $calendar->summary($rows), 'from' => $from, 'to' => $to];
    }
}
