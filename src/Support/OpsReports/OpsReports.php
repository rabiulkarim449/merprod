<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

/** The operations reports, in menu order (key => class). */
class OpsReports
{
    public const LIST = [
        'shipment-readiness' => ShipmentReadiness::class,
        'pre-production' => PreProductionReadiness::class,
        'material-shortage' => MaterialShortage::class,
        'cutting-daily' => CuttingDaily::class,
        'quality-dhu-rft' => QualityDhuRft::class,
        'finishing-packing' => FinishingPacking::class,
        'cm-profit' => CmProfit::class,
        'order-closing' => OrderClosing::class,
    ];

    public static function make(string $key): OpsReport
    {
        return app(self::LIST[$key]);
    }
}
