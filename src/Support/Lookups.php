<?php

namespace ME\MerchandisingSfl\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use ME\MerchandisingSfl\Models;

/** Dropdown option lists shared by the Dev / Order / BOM / Sample forms. */
class Lookups
{
    public static function buyers(): Collection
    {
        return Models\Buyer::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'merchandiser_id']);
    }

    public static function seasons(): Collection
    {
        return Models\Season::query()->active()->orderByDesc('year')->orderBy('name')->get(['id', 'code', 'name']);
    }

    public static function merchandisers(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    public static function factories(): Collection
    {
        return Models\Factory::query()->active()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public static function productTypes(): Collection
    {
        return Models\ProductType::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'default_smv']);
    }

    public static function washTypes(): Collection
    {
        return Models\WashType::query()->active()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public static function currencies(): Collection
    {
        return Models\Currency::query()->active()->orderBy('code')->get(['id', 'code', 'name']);
    }

    public static function colors(): Collection
    {
        return Models\Color::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    public static function sizes(): Collection
    {
        return Models\Size::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    public static function shipModes(): Collection
    {
        return Models\ShipMode::query()->active()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public static function uoms(): Collection
    {
        return Models\Uom::query()->active()->orderBy('short_name')->get(['id', 'name', 'short_name']);
    }

    public static function suppliers(): Collection
    {
        return Models\Supplier::query()->active()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public static function items(): Collection
    {
        return Models\Item::query()->active()->orderBy('type')->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'uom_id', 'default_supplier_id', 'default_price']);
    }

    public static function sampleTypes(): Collection
    {
        return Models\SampleType::query()->active()->orderBy('sequence')->orderBy('name')->get(['id', 'code', 'name']);
    }

    public static function styles(): Collection
    {
        return Models\Style::query()->active()->orderByDesc('id')->get(['id', 'style_no', 'name', 'buyer_id', 'color_id']);
    }

    public static function inquiries(): Collection
    {
        return Models\Inquiry::query()->whereNotIn('status', ['lost', 'cancelled'])->orderByDesc('id')
            ->get(['id', 'inquiry_no', 'buyer_id', 'style_ref']);
    }

    public static function orders(): Collection
    {
        return Models\Order::query()->where('status', '!=', 'cancelled')->orderByDesc('id')
            ->get(['id', 'order_no', 'buyer_id', 'buyer_order_ref']);
    }

    /** Active garment part names (Master Data → Garment Parts). */
    public static function garmentParts(): Collection
    {
        return Models\GarmentPart::query()->active()->orderBy('name')->pluck('name');
    }

    public static function machineTypes(): Collection
    {
        \ME\MerchandisingSfl\Services\InventoryMachines::syncTypes();

        return Models\MachineType::query()->active()->orderBy('is_helper')->orderBy('code')->get(['id', 'code', 'name', 'is_helper']);
    }

    public static function lines(): Collection
    {
        return Models\Line::query()->active()->with('floorLine')
            ->whereHas('floorLine', fn ($q) => $q->active())->get()->sortBy('name')->values();
    }

    public static function operations(): Collection
    {
        return Models\Operation::query()->active()->with('garmentPart:id,name')->orderBy('name')
            ->get(['id', 'code', 'name', 'machine_type_id', 'garment_part_id', 'attachment', 'default_smv']);
    }

    /** POs of confirmed orders — what production can work on. */
    public static function productionPos(): Collection
    {
        return Models\OrderPo::query()
            ->with(['order:id,order_no,buyer_id,status', 'order.buyer:id,name', 'style:id,style_no,name', 'color:id,name'])
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed'))
            ->latest('id')->get();
    }
}
