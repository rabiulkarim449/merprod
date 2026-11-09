<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Carbon;
use ME\MerchandisingSfl\Models\Holiday;

/** Working-day arithmetic: skips the weekly off day(s) (config weekly_off_days) and active holidays. */
class WorkingCalendar
{
    /** @var array<string, true>|null */
    private ?array $holidays = null;

    public function isWorkingDay(Carbon $date): bool
    {
        if (in_array($date->dayOfWeek, config('merchandising-sfl.weekly_off_days', [5]), true)) {
            return false;
        }

        // HR holidays are ranges (Eid = several days): mark every day in each.
        $this->holidays ??= Holiday::query()->active()->get(['from_date', 'to_date'])
            ->flatMap(fn ($h) => $h->from_date ? iterator_to_array(\Carbon\CarbonPeriod::create($h->from_date, $h->to_date ?? $h->from_date)->map(fn ($d) => $d->format('Y-m-d')), false) : [])
            ->mapWithKeys(fn ($d) => [$d => true])->all();

        return ! isset($this->holidays[$date->format('Y-m-d')]);
    }

    /** Move $days working days forward (positive) or back (negative). 0 = the date itself. */
    public function shift(Carbon $date, int $days): Carbon
    {
        $date = $date->copy()->startOfDay();
        $step = $days < 0 ? -1 : 1;

        for ($moved = 0; $moved < abs($days);) {
            $date->addDays($step);
            if ($this->isWorkingDay($date)) {
                $moved++;
            }
        }

        return $date;
    }

    /** Working days from $from to $to, both included (0 if $to is before $from). */
    public function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $count = 0;
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            if ($this->isWorkingDay($date)) {
                $count++;
            }
        }

        return $count;
    }
}
