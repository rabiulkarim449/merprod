{{--
    The Open Cost Sheet as shown and printed: letterhead, particulars + garment pictures,
    sections A–G (per dozen), sketch + summary (DZN / PC / % of FOB) and the per-piece strip.
    props: costSheet (buyer, currency, style.images, items.item / items.uom loaded),
           letterhead (bool, default true — the print page has the company header already)
--}}
@php
    $sum = $costSheet->summary();
    $cur = $costSheet->currency->symbol ?? ($costSheet->currency->code ?? '$');
    $disk = \Illuminate\Support\Facades\Storage::disk(config('merchandising-sfl.upload_disk'));
    $images = $costSheet->style?->images ?? collect();
    $imageUrl = fn ($image) => $image ? \ME\MerchandisingSfl\Services\FileUploadService::link($image->path) : null;
    $front = $imageUrl($images->firstWhere('type', 'front') ?? $images->get(0));
    $back = $imageUrl($images->firstWhere('type', 'back') ?? $images->get(1));
    $sketch = $imageUrl($images->firstWhere('type', 'artwork') ?? $images->firstWhere('type', 'detail'));

    $money = fn ($v, int $dp = 2) => (float) $v != 0.0 ? number_format((float) $v, $dp) : '-';
    // Trims prices at 4 decimals (0.0625), others at 2 unless finer.
    $price = function ($v, string $group) {
        if ($v === null || (float) $v == 0.0) {
            return '-';
        }

        return number_format((float) $v, $group === 'trims' || round((float) $v, 2) != (float) $v ? 4 : 2);
    };
    $qty = fn ($v) => $v !== null && (float) $v != 0.0 ? number_format((float) $v, round((float) $v, 2) != (float) $v ? 4 : 2) : '';
    $pct = fn ($v) => number_format((float) $v, 2) . ' %';
    $rate = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . ' %';
    $sl = 0;
    $lines = $costSheet->items->groupBy('group');
    $groupsMeta = \ME\MerchandisingSfl\Models\CostSheet::GROUPS;
    $st = $sum['strip'];
@endphp

<style>
    .cs-sheet { width: 100%; font-family: Calibri, 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #111; }
    /* Scoped resets: printMaster2 styles every table / th / td. */
    .cs-sheet table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .cs-sheet td, .cs-sheet th { padding: 2px 5px; vertical-align: middle; border: 0; font-size: inherit; text-align: left; }
    .cs-sheet .b td, .cs-sheet .b th { border: 1px solid #555; }
    .cs-sheet .co-name { color: #d99a00; font-size: 24px; font-weight: bold; letter-spacing: 1px; text-align: center; }
    .cs-sheet .co-title { color: #1f4ea0; font-weight: bold; font-size: 14px; text-align: center; }
    .cs-sheet .hk { font-weight: bold; width: 95px; background: #f2f2f2; }
    .cs-sheet th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; text-align: center; }
    .cs-sheet .r { text-align: right; white-space: nowrap; }
    .cs-sheet .c { text-align: center; }
    .cs-sheet td.unit { color: #1f4ea0; text-align: center; }
    .cs-sheet .tot td { background: #fff4b8; color: #1f4ea0; font-weight: bold; }
    .cs-sheet .gap td { border: 0 !important; height: 8px; padding: 0; }
    .cs-sheet .sum-h { background: #f4c542; font-weight: bold; text-align: center; }
    .cs-sheet .blue { color: #1f4ea0; font-weight: bold; }
    .cs-sheet .hl { background: #ffff00 !important; font-weight: bold; }
    .cs-sheet .pics img { max-height: 110px; max-width: 48%; }
    .cs-sheet .sketch img { max-width: 100%; max-height: 240px; }
    .cs-sheet .strip th { font-size: 10px; text-transform: none; }
    .cs-sheet .strip td { text-align: center; white-space: nowrap; }
    .cs-sheet .muted { color: #888; }
    @media print { .cs-sheet .tot td, .cs-sheet .sum-h, .cs-sheet .hl, .cs-sheet th, .cs-sheet .hk { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>

<div class="cs-sheet">
    @if($letterhead ?? true)
        @if(report_logo_url())<img src="{{ report_logo_url() }}" alt="Logo" style="display:block;height:50px;max-width:160px;object-fit:contain;margin:0 auto 4px;">@endif
        <div class="co-name">{{ general()->title ?? '' }}</div>
        <div class="co-title">OPEN COST SHEET</div>
    @endif

    {{-- Particulars | garment pictures | date --}}
    <table style="margin-top:4px;">
        <tr>
            <td style="width:40%; padding:0; vertical-align:top;">
                <table class="b">
                    <tr><td class="hk">BUYER</td><td>{{ $costSheet->buyer->name ?? '-' }}</td></tr>
                    <tr><td class="hk">DESCRIPTION</td><td>{{ $costSheet->garment_description ?? ($costSheet->style->name ?? '') }}</td></tr>
                    <tr><td class="hk">STYLE</td><td>{{ $costSheet->styleLabel() }}</td></tr>
                    <tr><td class="hk">SIZE</td><td>{{ $costSheet->size_range }}</td></tr>
                    <tr><td class="hk">ORDER</td><td>{{ $costSheet->order_qty ? number_format($costSheet->order_qty) . ' Pcs' : '' }}</td></tr>
                    <tr><td class="hk">PRICE TYPE</td><td>{{ $costSheet->price_type ?? '' }}</td></tr>
                </table>
            </td>
            <td class="pics c" style="width:38%;">
                @if($front)<img src="{{ $front }}" alt="Front">@endif
                @if($back)<img src="{{ $back }}" alt="Back" style="margin-left:6px;">@endif
                @if(! $front && ! $back)<span class="muted">Pictures come from the style's Tech Pack.</span>@endif
            </td>
            <td style="width:22%; vertical-align:top; text-align:right;">
                <strong>DATE</strong> : {{ optional($costSheet->costing_date ?? $costSheet->created_at)->format('d-M-y') }}
                <div style="margin-top:4px; color:#666;">{{ $costSheet->cost_sheet_no }}</div>
                <div style="color:#666;">{{ $costSheet->statusLabel() }}{{ $costSheet->approver ? ' · ' . $costSheet->approver->name : '' }}</div>
            </td>
        </tr>
    </table>

    {{-- A–G cost sections (per dozen) --}}
    <table class="b" style="margin-top:6px;">
        @foreach($groupsMeta as $group => [$letter, $title, $totalLabel])
            @if(! $loop->first)
                <tr class="gap"><td colspan="7"></td></tr>
            @endif
            @if($group === 'fabric' || $group === 'trims')
                <tr>
                    <th style="width:6%;">SL. No.</th><th>{{ $title }}</th><th style="width:20%;">Supplier Name</th>
                    <th style="width:10%;">Consumption</th><th style="width:8%;">Units</th>
                    <th style="width:11%;">Unit Price{{ $group === 'fabric' ? ' (' . ($st['fabric_uom'] ?: 'YD') . ')' : '' }}</th>
                    <th style="width:12%;">Total Cost DZN ({{ $cur }})</th>
                </tr>
            @endif
            @forelse($lines->get($group, collect()) as $line)
                <tr>
                    <td class="c">{{ ++$sl }}</td>
                    <td>{{ $line->label() }}</td>
                    <td>{{ $line->supplier_name }}</td>
                    <td class="r">{{ $qty($line->consumption) }}</td>
                    <td class="unit">{{ $line->uom->code ?? '' }}</td>
                    <td class="r">{{ $cur }} {{ $price($line->rate, $group) }}</td>
                    <td class="r">{{ $cur }} {{ $money($line->amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="c">{{ ++$sl }}</td>
                    <td class="c">{{ strtoupper($title) }}</td>
                    <td></td><td></td><td></td>
                    <td class="r">{{ $cur }} -</td>
                    <td class="r">{{ $cur }} -</td>
                </tr>
            @endforelse
            <tr class="tot">
                <td colspan="4" style="border:0; background:#fff;"></td>
                <td colspan="2">{{ $letter }}. {{ strtoupper($totalLabel) }}</td>
                <td class="r">{{ $cur }} {{ $money($sum['groups'][$group]['dz']) }}</td>
            </tr>
        @endforeach
    </table>

    {{-- Sketch | Summary --}}
    <table style="margin-top:8px;">
        <tr>
            <td class="sketch c" style="width:40%; vertical-align:top;">
                @if($sketch)<img src="{{ $sketch }}" alt="Sketch">@endif
            </td>
            <td style="width:60%; vertical-align:top; padding:0;">
                <table class="b">
                    <tr><td colspan="4" class="sum-h">SUMMARY</td></tr>
                    <tr><td></td><td class="c blue">DZN</td><td class="c blue">PC</td><td class="c blue">%</td></tr>
                    @foreach($groupsMeta as $group => [$letter, , $totalLabel])
                        <tr>
                            <td>{{ $letter }}. {{ strtoupper($totalLabel) }}</td>
                            <td class="r">{{ $cur }} {{ $money($sum['groups'][$group]['dz']) }}</td>
                            <td class="r">{{ $cur }} {{ $money($sum['groups'][$group]['pc']) }}</td>
                            <td class="r blue">{{ $pct($sum['groups'][$group]['pct']) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>TOTAL AMOUNT</strong></td>
                        <td class="r">{{ $cur }} {{ $money($sum['materials']['dz']) }}</td>
                        <td class="r">{{ $cur }} {{ $money($sum['materials']['pc']) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><strong>CM</strong> <span style="float:right;">SMV {{ $costSheet->smv !== null ? rtrim(rtrim(number_format((float) $costSheet->smv, 2), '0'), '.') : '-' }}</span></td>
                        <td class="r hl">{{ $cur }} {{ $money($sum['cm']['dz']) }}</td>
                        <td class="r">{{ $cur }} {{ $money($sum['cm']['pc']) }}</td>
                        <td class="r blue">{{ $pct($sum['cm']['pct']) }}</td>
                    </tr>
                    <tr>
                        <td><strong>SUB TOTAL FOB PER</strong></td>
                        <td class="r">{{ $cur }} {{ $money($sum['sub_total']['dz']) }}</td>
                        <td class="r">{{ $cur }} {{ $money($sum['sub_total']['pc']) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><strong>COMMERCIAL COST</strong> <span style="float:right;">{{ $rate($sum['commercial']['rate']) }} on materials</span></td>
                        <td class="r">{{ $cur }} {{ $money($sum['commercial']['dz']) }}</td>
                        <td class="r">{{ $cur }} {{ $money($sum['commercial']['pc']) }}</td>
                        <td class="r blue">{{ $pct($sum['commercial']['pct']) }}</td>
                    </tr>
                    @if($sum['other']['dz'] > 0)
                        <tr>
                            <td><strong>OTHER COST</strong> <span style="float:right;">freight / testing / overhead</span></td>
                            <td class="r">{{ $cur }} {{ $money($sum['other']['dz']) }}</td>
                            <td class="r">{{ $cur }} {{ $money($sum['other']['pc']) }}</td>
                            <td class="r blue">{{ $pct($sum['other']['pct']) }}</td>
                        </tr>
                    @endif
                    @if($sum['profit']['dz'] > 0)
                        <tr>
                            <td><strong>PROFIT</strong> <span style="float:right;">{{ $rate($sum['profit']['rate']) }}</span></td>
                            <td class="r">{{ $cur }} {{ $money($sum['profit']['dz']) }}</td>
                            <td class="r">{{ $cur }} {{ $money($sum['profit']['pc']) }}</td>
                            <td class="r blue">{{ $pct($sum['profit']['pct']) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><strong>TOTAL FOB PER DOZ</strong></td>
                        <td class="r"><strong>{{ $cur }} {{ $money($sum['fob']['dz']) }}</strong></td>
                        <td></td><td></td>
                    </tr>
                    <tr>
                        <td><strong>TOTAL FOB PER PCS</strong></td>
                        <td class="r hl">{{ $cur }} {{ $money($sum['fob']['pc'], 4) }}</td>
                        <td class="c"><strong>TTL B2B</strong></td>
                        <td class="r blue">{{ $pct($sum['b2b_pct']) }}</td>
                    </tr>
                    <tr>
                        <td>BUYER TARGET / PC</td>
                        <td class="r">{{ $costSheet->buyer_target_price !== null ? $cur . ' ' . number_format((float) $costSheet->buyer_target_price, 4) : '-' }}</td>
                        <td class="c"><strong>FINAL / PC</strong></td>
                        <td class="r"><strong>{{ $costSheet->final_price !== null ? $cur . ' ' . number_format((float) $costSheet->final_price, 4) : '-' }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Per-piece strip --}}
    <table class="b strip" style="margin-top:8px;">
        <tr>
            <th>BUYER</th><th>STYLE</th><th>Fabric Price ({{ $st['fabric_uom'] ?: 'YD' }})</th><th>Fabric Cons. (PC)</th><th>Fabric Cost (PC)</th>
            <th>PKT</th><th>Fusing</th><th>Trims</th><th>Wash</th><th>Stone</th><th>Print</th><th>H/SEAL</th><th>EMB</th><th>CM</th><th>COM</th><th class="hl">FOB/PC</th>
        </tr>
        <tr>
            <td>{{ \Illuminate\Support\Str::limit($costSheet->buyer->name ?? '-', 18) }}</td>
            <td>{{ $costSheet->styleLabel() }}</td>
            <td>{{ $st['fabric_price'] !== null ? $cur . ' ' . $price($st['fabric_price'], 'fabric') : '-' }}</td>
            <td>{{ $st['fabric_consumption_pc'] !== null ? number_format($st['fabric_consumption_pc'], 2) . ' ' . ($st['fabric_uom'] ?: '') : '-' }}</td>
            <td>{{ $cur }} {{ $money($st['fabric_cost_pc']) }}</td>
            <td>{{ $cur }} {{ $money($st['pocket_pc']) }}</td>
            <td>{{ $cur }} {{ $money($st['fusing_pc']) }}</td>
            @foreach(['trims', 'wash', 'stone', 'print', 'heat_seal', 'process'] as $group)
                <td>{{ $cur }} {{ $money($sum['groups'][$group]['pc']) }}</td>
            @endforeach
            <td>{{ $cur }} {{ $money($sum['cm']['pc']) }}</td>
            <td>{{ $cur }} {{ $money($sum['commercial']['pc']) }}</td>
            <td class="hl">{{ $cur }} {{ $money($sum['fob']['pc'], 4) }}</td>
        </tr>
    </table>
    @if($costSheet->remarks)
        <div style="margin-top:6px;"><strong>Remarks:</strong> {!! nl2br(e($costSheet->remarks)) !!}</div>
    @endif
</div>
