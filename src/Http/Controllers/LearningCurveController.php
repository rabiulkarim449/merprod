<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\LeadTime;
use ME\MerchandisingSfl\Models\LearningCurveBand;
use ME\MerchandisingSfl\Models\Style;

/**
 * Day-wise Learning Curve: a new style never runs at full efficiency from
 * day 1 — this is the ramp-up % table (by SMV band) plus a standalone
 * calculator. Nothing here feeds T&A / Sewing Board automatically yet
 * (Phase 1) — it's a reference table and a planning tool.
 */
class LearningCurveController extends Controller
{
    public function index(): View
    {
        $this->authorize('msfl_learning_curve.list');

        $canShipment = auth()->user()?->can('msfl_learning_curve_shipment.list');

        $productionBands = LearningCurveBand::type('production')->orderBy('sequence')->get();
        $shipmentBands = $canShipment ? LearningCurveBand::type('shipment')->orderBy('sequence')->get() : collect();
        $leadTimes = $canShipment ? LeadTime::orderBy('category')->orderBy('sequence')->get()->groupBy('category') : collect();

        return view('merchandising-sfl::admin.learning-curve.index', compact('productionBands', 'shipmentBands', 'leadTimes', 'canShipment'));
    }

    public function update(Request $request, LearningCurveBand $learning_curve_band): RedirectResponse
    {
        $ability = $learning_curve_band->curve_type === 'shipment' ? 'msfl_learning_curve_shipment.edit' : 'msfl_learning_curve.edit';
        $this->authorize($ability);

        $data = $request->validate([
            'day_percents' => ['required', 'array', 'min:1'],
            'day_percents.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $learning_curve_band->update(['day_percents' => array_values($data['day_percents'])]);

        return back()->with('success', "{$learning_curve_band->label} updated successfully.");
    }

    public function leadTimeUpdate(Request $request, LeadTime $lead_time): RedirectResponse
    {
        $this->authorize('msfl_learning_curve_shipment.edit');

        $data = $request->validate(['days' => ['required', 'integer', 'min:0', 'max:60']]);
        $lead_time->update($data);

        return back()->with('success', "{$lead_time->name} updated successfully.");
    }

    public function calculate(): View
    {
        $this->authorize('msfl_learning_curve.calculate');

        $canShipment = auth()->user()?->can('msfl_learning_curve_shipment.calculate');

        return view('merchandising-sfl::admin.learning-curve.calculate', [
            'canShipment' => $canShipment,
            'productionBands' => LearningCurveBand::active()->type('production')->orderBy('sequence')->get(),
            'shipmentBands' => $canShipment ? LearningCurveBand::active()->type('shipment')->orderBy('sequence')->get() : collect(),
            'leadTimes' => $canShipment ? LeadTime::active()->orderBy('category')->orderBy('sequence')->get() : collect(),
            'styles' => Style::active()->orderByDesc('id')->get(['id', 'style_no', 'name', 'smv']),
        ]);
    }
}
