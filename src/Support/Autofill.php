<?php

namespace ME\MerchandisingSfl\Support;

use ME\MerchandisingSfl\Models;

/**
 * What earlier steps already know, for forms to fill themselves when a source
 * is picked (partials/autofill): buyer → terms / merchandiser, inquiry →
 * buyer / season / qty / price …, style → buyer / inquiry / SMV / price …
 * Maps are id => [field => value]; empty values are left out.
 */
class Autofill
{
    public static function buyers(): array
    {
        return Models\Buyer::query()->get(['id', 'merchandiser_id', 'delivery_term', 'payment_term_id'])
            ->mapWithKeys(fn ($b) => [$b->id => self::clean([
                'merchandiser_id' => $b->merchandiser_id, 'delivery_term' => $b->delivery_term, 'payment_term_id' => $b->payment_term_id,
            ])])->all();
    }

    public static function inquiries(): array
    {
        $smv = Models\ProductType::query()->pluck('default_smv', 'id');

        return Models\Inquiry::query()->get()->mapWithKeys(fn ($i) => [$i->id => self::clean([
            'buyer_id' => $i->buyer_id, 'season_id' => $i->season_id, 'merchandiser_id' => $i->merchandiser_id, 'factory_id' => $i->factory_id,
            'product_type_id' => $i->product_type_id, 'style_ref' => $i->style_ref, 'order_qty' => $i->order_qty,
            'unit_price' => self::num($i->unit_price), 'target_ship_date' => $i->target_ship_date?->toDateString(),
            'color_ref' => $i->color_ref, 'description' => $i->description, 'smv' => self::num($smv[$i->product_type_id] ?? null),
        ])])->all();
    }

    /**
     * Per style: its master / tech pack data, the inquiry's qty and price, the
     * approved cost sheet's price and currency, the latest order and its PO colors / sizes.
     */
    public static function styles(): array
    {
        $inquiries = self::inquiries();
        $costSheets = Models\CostSheet::query()->whereNotNull('style_id')
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")->orderByDesc('id')->get()->unique('style_id')->keyBy('style_id');
        $pos = Models\OrderPo::query()->with(['color:id,name', 'sizes.size:id,name', 'order:id,currency_id'])->orderByDesc('id')->get()->groupBy('style_id');

        return Models\Style::query()->get()->mapWithKeys(function ($s) use ($inquiries, $costSheets, $pos) {
            $inq = $inquiries[$s->inquiry_id] ?? [];
            $cs = $costSheets->get($s->id);
            $stylePos = $pos->get($s->id, collect());

            return [$s->id => self::clean([
                'buyer_id' => $s->buyer_id, 'inquiry_id' => $s->inquiry_id, 'season_id' => $s->season_id, 'product_type_id' => $s->product_type_id,
                'merchandiser_id' => $s->merchandiser_id ?? ($inq['merchandiser_id'] ?? null), 'wash_type_id' => $s->wash_type_id,
                'style_ref' => $s->style_no, 'garment_description' => $s->name,
                'smv' => self::num($s->smv) ?? ($inq['smv'] ?? null),
                'order_qty' => $inq['order_qty'] ?? null,
                'buyer_target_price' => $inq['unit_price'] ?? null,
                // PO price: the agreed cost sheet price, else the inquiry's.
                'unit_price' => self::num($cs?->final_price) ?? ($inq['unit_price'] ?? null),
                'currency_id' => $cs?->currency_id,
                'shipment_date' => $inq['target_ship_date'] ?? null,
                'order_id' => $stylePos->first()?->order_id,
                'color_ref' => $stylePos->pluck('color.name')->filter()->unique()->implode(', ') ?: ($inq['color_ref'] ?? null),
                'size_ref' => $stylePos->flatMap(fn ($p) => $p->sizes->pluck('size.name'))->filter()->unique()->implode(', ') ?: null,
            ])];
        })->all();
    }

    /** Per order: buyer (sample form picks the order's style list from it). */
    public static function orders(): array
    {
        return Models\Order::query()->get(['id', 'buyer_id', 'merchandiser_id', 'currency_id'])
            ->mapWithKeys(fn ($o) => [$o->id => self::clean(['buyer_id' => $o->buyer_id, 'merchandiser_id' => $o->merchandiser_id, 'currency_id' => $o->currency_id])])->all();
    }

    /**
     * Per style: the approved (else latest) cost sheet's material lines as BOM rows —
     * consumption per dozen ÷ 12 = per piece.
     */
    public static function bomLinesFromCostSheet(): array
    {
        $items = Models\Item::query()->get(['id', 'default_supplier_id'])->keyBy('id');

        return Models\CostSheet::query()->whereNotNull('style_id')->with('items')
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")->orderByDesc('id')->get()->unique('style_id')
            ->mapWithKeys(fn ($cs) => [$cs->style_id => [
                'cost_sheet' => $cs->cost_sheet_no ?? ('#' . $cs->id),
                'lines' => $cs->items->whereNotNull('item_id')->values()->map(fn ($i) => [
                    'item_id' => $i->item_id, 'uom_id' => $i->uom_id,
                    'consumption' => round((float) $i->consumption / 12, 4),
                    'rate' => self::num($i->rate),
                    'supplier_id' => $items[$i->item_id]->default_supplier_id ?? null,
                ])->all(),
            ]])->all();
    }

    private static function clean(array $row): array
    {
        return array_filter($row, fn ($v) => $v !== null && $v !== '');
    }

    private static function num($v): ?float
    {
        return $v === null || (float) $v == 0.0 ? null : (float) $v;
    }
}
