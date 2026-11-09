{{--
    An operations report laid out like its Excel template.
    props: report (OpsReport), built (build() result), editable (bool: typed cells as inputs), print (bool)
--}}
@php
    use ME\MerchandisingSfl\Support\OpsReports\OpsReport;
    $cols = $built['columns'];
    $rows = $built['rows'];
    $companyName = (general()?->title ?? null) ?: config('merchandising-sfl.company.name');
    $companyAddress = (general()?->address_one ?? null) ?: config('sfl-inventory.company.address');
    $companyLogo = report_logo_url();
    $statusClass = function ($v) {
        return match ((string) $v) {
            'Critical', 'Blocked', 'Loss', 'Failed', 'Fail', 'Open' => 'st-red',
            'High', 'At Risk', 'Below Target', 'On Hold', 'Short Shipped', 'Excess Shipped' => 'st-orange',
            'Medium', 'In Progress', 'Pending', 'Re-inspection' => 'st-yellow',
            'Ready', 'OK', 'Closed', 'Approved', 'On Target', 'Released', 'Low', 'Pass', 'Passed', 'Cleared' => 'st-green',
            default => '',
        };
    };
    $right = fn ($c) => in_array($c['type'], ['int', 'num', 'money', 'pct'], true);
@endphp
<style>
    .ops { font-size: 11px; color: #111; }
    /* Header */
    .ops .ops-head { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; padding-bottom: 8px; border-bottom: 3px double #0f2a4a; }
    .ops .ops-logo { max-width: 80px; max-height: 56px; object-fit: contain; }
    .ops .ops-head-c { text-align: center; }
    .ops .ops-co { font-weight: 800; font-size: 22px; letter-spacing: 1px; text-transform: uppercase; color: #0f2a4a; line-height: 1.2; }
    .ops .ops-addr { font-size: 11px; color: #475569; margin-top: 2px; }
    .ops .ops-band { margin: 10px auto 10px; text-align: center; }
    .ops .ops-band span { display: inline-block; background: #0f2a4a; color: #fff; font-weight: 800; font-size: 14px; letter-spacing: 1.5px; padding: 6px 28px; border-radius: 3px; text-transform: uppercase; box-shadow: inset 0 -3px 0 #c89b3c; }
    .ops .ops-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
    .ops .ops-meta > div { padding: 5px 10px; box-shadow: 1px 0 0 #e2e8f0, 0 1px 0 #e2e8f0; }
    .ops .ops-meta .k { display: block; font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
    .ops .ops-meta .v { display: block; font-size: 12px; font-weight: 700; color: #0f172a; }
    .ops .ops-meta .v input { height: 22px; padding: 1px 4px; font-size: 11px; border: 1px solid #e2d3a8; background: #fffdf5; }
    .ops .ops-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 8px; }
    .ops .ops-kpi { border: 1px solid #cbd5e1; border-top: 3px solid #1f4e79; border-radius: 4px; text-align: center; padding: 5px 4px; background: #fff; }
    .ops .ops-kpi:nth-child(4n) { border-top-color: #c89b3c; }
    .ops .ops-kpi .k { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
    .ops .ops-kpi .v { font-size: 17px; font-weight: 800; color: #0f2a4a; line-height: 1.3; }
    .ops .ops-guide { background: #f8f6f1; border-left: 3px solid #c89b3c; color: #57534e; font-size: 10px; padding: 5px 10px; margin-bottom: 8px; }
    .ops .ops-guide b { color: #0f2a4a; }
    .ops .ops-legend { display: inline-block; width: 10px; height: 10px; border: 1px solid #cbd5e1; vertical-align: -1px; margin: 0 2px 0 6px; }
    .ops table.ops-table { border-collapse: collapse; width: 100%; }
    .ops table.ops-table th { background: #0f2a4a !important; color: #fff !important; font-weight: 700; font-size: 10px; padding: 4px 4px; border: 1px solid #334155; text-align: center; vertical-align: middle; white-space: normal; min-width: 60px; }
    .ops table.ops-table td { border: 1px solid #d1d5db; padding: 2px 4px; font-size: 10.5px; vertical-align: middle; white-space: nowrap; }
    .ops table.ops-table td.r { text-align: right; }
    .ops td.c-input { background: #fff8e6; }
    .ops td.c-formula { background: #eaf2fb; }
    .ops td.c-input input, .ops td.c-input select { width: 100%; min-width: 80px; height: 24px; padding: 1px 4px; font-size: 10.5px; border: 1px solid #e2d3a8; background: #fffdf5; }
    .ops td.c-input input[type=number] { text-align: right; min-width: 70px; }
    .ops td.c-input input.changed, .ops td.c-input select.changed { border-color: #2563eb; background: #eef6ff; }
    .ops .auto-note { display: block; font-size: 9px; color: #64748b; }
    .ops .st-red { background: #fee2e2 !important; color: #991b1b; font-weight: 700; }
    .ops .st-orange { background: #ffedd5 !important; color: #9a3412; font-weight: 700; }
    .ops .st-yellow { background: #fef9c3 !important; color: #854d0e; font-weight: 600; }
    .ops .st-green { background: #dcfce7 !important; color: #166534; font-weight: 700; }
    .ops .ops-sign { display: flex; justify-content: space-between; margin-top: 55px; gap: 12px; }
    .ops .ops-sign div { flex: 1; border-top: 1px solid #333; text-align: center; padding-top: 3px; font-weight: 700; font-size: 10.5px; }
    @media print { .ops * { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .ops table.ops-table td { white-space: normal; } }
</style>

<div class="ops">
    <div class="ops-head">
        <div>@if($companyLogo)<img src="{{ $companyLogo }}" class="ops-logo" alt="" onerror="this.remove()">@endif</div>
        <div class="ops-head-c">
            <div class="ops-co">{{ $companyName }}</div>
            <div class="ops-addr">{{ $companyAddress }}</div>
        </div>
        <div></div>
    </div>
    <div class="ops-band"><span>{{ $report->title() }}</span></div>

    <div class="ops-meta">
        <div><span class="k">Reporting Date</span><span class="v">{{ $built['date']->format('d-M-Y') }}</span></div>
        <div><span class="k">Department</span><span class="v">{{ $report->department() }}</span></div>
        <div><span class="k">Prepared By</span><span class="v">{{ auth()->user()?->name }}</span></div>
        <div><span class="k">Printed On</span><span class="v">{{ now()->format('d-M-Y h:i A') }}</span></div>
        @foreach($report->settingsShown() as $sk)
            <div>
                <span class="k">{{ OpsReport::SETTINGS[$sk][0] }}</span>
                <span class="v">
                    @if($editable)
                        <input type="number" step="any" min="0" name="settings[{{ $sk }}]" value="{{ old('settings.' . $sk, \ME\MerchandisingSfl\Models\ReportInput::settings()[$sk] ?? '') }}"
                               placeholder="{{ OpsReport::SETTINGS[$sk][1] ?? '—' }}" data-orig="{{ \ME\MerchandisingSfl\Models\ReportInput::settings()[$sk] ?? '' }}" style="width:90px">
                    @else
                        {{ $built['settings'][$sk] ?? '—' }}
                    @endif
                </span>
            </div>
        @endforeach
    </div>

    @if($kpis = $built['kpis'])
        <div class="ops-kpis">
            @foreach($kpis as [$label, $value])
                <div class="ops-kpi"><div class="k">{{ $label }}</div><div class="v">{{ $value }}</div></div>
            @endforeach
        </div>
    @endif

    <div class="ops-guide"><b>Update guidance:</b> {{ $report->guidance() }}
        <span class="ops-legend" style="background:#fff8e6"></span>manual input
        <span class="ops-legend" style="background:#eaf2fb"></span>automatic formula
    </div>

    <div class="{{ $print ? '' : 'table-responsive' }}">
        <table class="ops-table">
            <thead><tr>@foreach($cols as $c)<th>{{ $c['label'] }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach($cols as $c)
                            @php
                                $v = $row[$c['key']] ?? null;
                                $typed = in_array($c['kind'], ['input', 'auto_input'], true);
                                $cls = ($typed ? 'c-input' : ($c['kind'] === 'formula' ? 'c-formula' : '')) . ($right($c) ? ' r' : '') . (! empty($c['status']) ? ' ' . $statusClass($v) : '');
                            @endphp
                            <td class="{{ $cls }}">
                                @if($editable && $typed)
                                    @php
                                        $name = 'cells[' . $row['_key'] . '][' . $c['key'] . ']';
                                        $manual = $row['_manual'][$c['key']] ?? null;
                                        $cur = old('cells.' . $row['_key'] . '.' . $c['key'], $manual);
                                        $auto = $row['_auto'][$c['key']] ?? null;
                                        $autoText = $c['kind'] === 'auto_input' && $auto !== null && $auto !== '' ? OpsReport::fmt($auto, $c['type']) : '';
                                    @endphp
                                    @if($c['type'] === 'select')
                                        <select name="{{ $name }}" data-orig="{{ $manual }}" data-cell>
                                            <option value="">{{ $autoText !== '' ? 'Auto: ' . $autoText : '—' }}</option>
                                            @foreach($c['options'] as $o)<option value="{{ $o }}" @selected((string) $cur === $o)>{{ $o }}</option>@endforeach
                                        </select>
                                    @elseif($c['type'] === 'date')
                                        <input type="date" name="{{ $name }}" value="{{ $cur }}" data-orig="{{ $manual }}" data-cell>
                                        @if($autoText !== '' && $manual === null)<span class="auto-note">auto: {{ $autoText }}</span>@endif
                                    @elseif(in_array($c['type'], ['int', 'num', 'money', 'pct'], true))
                                        <input type="number" step="{{ $c['type'] === 'int' ? '1' : 'any' }}" min="{{ $c['min'] ?? 0 }}" name="{{ $name }}" value="{{ $cur }}" placeholder="{{ $autoText }}" data-orig="{{ $manual }}" data-cell>
                                    @else
                                        <input type="text" maxlength="255" name="{{ $name }}" value="{{ $cur }}" placeholder="{{ $autoText }}" data-orig="{{ $manual }}" data-cell>
                                    @endif
                                @else
                                    {{ OpsReport::fmt($v, $c['type']) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    @if($print)
                        {{-- Blank form for printing: 5 empty rows to fill in by hand. --}}
                        @for($i = 0; $i < 5; $i++)<tr style="height:24px">@foreach($cols as $c)<td>&nbsp;</td>@endforeach</tr>@endfor
                    @else
                        <tr><td colspan="{{ count($cols) }}" style="text-align:center; color:#6b7280; padding:10px">No data for this date / these filters.</td></tr>
                    @endif
                @endforelse
            </tbody>
        </table>
    </div>

    @if($signatures = $report->signatures())
        <div class="ops-sign">@foreach($signatures as $s)<div>{{ $s }}</div>@endforeach</div>
    @endif
</div>
