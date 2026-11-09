<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\ReportInput;
use ME\MerchandisingSfl\Support\Lookups;
use ME\MerchandisingSfl\Support\OpsReports\OpsReport;
use ME\MerchandisingSfl\Support\OpsReports\OpsReports;

/** Operations reports (Excel-template layouts): list, screen with typed cells, save, print. */
class OpsReportController extends Controller
{
    public function index(): View
    {
        $this->authorize('msfl_report.view');

        $reports = collect(OpsReports::LIST)->map(fn ($c, $k) => OpsReports::make($k));

        return view('merchandising-sfl::admin.ops-reports.index', compact('reports'));
    }

    public function show(Request $request, string $report): View
    {
        $this->authorize('msfl_report.view');

        return view('merchandising-sfl::admin.ops-reports.show', $this->data($request, $report));
    }

    public function print(Request $request, string $report): View
    {
        $this->authorize('msfl_report.view');
        $data = $this->data($request, $report);
        // Blank print: the empty template (header, KPIs at 0, 5 empty rows) to fill in by hand.
        if ($request->boolean('blank')) {
            $data['built']['rows'] = [];
            $data['built']['kpis'] = $data['report']->kpis([]);
        }

        return view('merchandising-sfl::admin.ops-reports.print', $data);
    }

    /** Saves the typed cells (only those changed are sent) and the shared settings — each checked against the rebuilt report. */
    public function save(Request $request, string $report): RedirectResponse
    {
        abort_unless($this->canEdit($request), 403);
        $r = OpsReports::make($report);
        $built = $r->build($request);
        [$errors, $clean] = $r->check((array) $request->input('cells', []), $built);

        $settings = [];
        foreach ((array) $request->input('settings', []) as $k => $v) {
            if (! in_array($k, $r->settingsShown(), true)) {
                continue;
            }
            $v = trim((string) $v);
            if ($v !== '' && (! is_numeric($v) || (float) $v < 0 || ($k === 'target_margin' && (float) $v > 100))) {
                $errors[] = OpsReport::SETTINGS[$k][0] . ': enter a number' . ($k === 'target_margin' ? ' (0–100).' : ' (0 or more).');
                continue;
            }
            $settings[$k] = $v;
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        $n = 0;
        DB::transaction(function () use ($clean, $settings, $r, &$n) {
            foreach ($clean as $rowKey => $fields) {
                foreach ($fields as $field => $value) {
                    ReportInput::put($r->key(), $rowKey, $field, $value);
                    $n++;
                }
            }
            foreach ($settings as $k => $v) {
                ReportInput::put(ReportInput::SETTINGS, '_global', $k, $v);
                $n++;
            }
        });

        return back()->with('success', $n ? "Saved {$n} cell(s)." : 'Nothing changed.');
    }

    private function canEdit(Request $request): bool
    {
        return (bool) ($request->user()?->can('msfl_report.edit') || $request->user()?->can('msfl_report.all'));
    }

    private function data(Request $request, string $report): array
    {
        $r = OpsReports::make($report);

        return [
            'report' => $r,
            'built' => $r->build($request),
            'canEdit' => $this->canEdit($request),
            'buyers' => in_array('buyer', $r->filters(), true) ? Lookups::buyers() : collect(),
            'styles' => in_array('style', $r->filters(), true) ? Lookups::styles() : collect(),
            'lines' => in_array('line', $r->filters(), true) ? Lookups::lines() : collect(),
        ];
    }
}
