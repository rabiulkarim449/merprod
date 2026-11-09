<?php

return [
    'name' => 'Merchandising',

    'route' => [
        'prefix'     => 'admin/merchandising-sfl',
        'as'         => 'msfl.',
        'middleware' => ['web', 'auth'],
    ],

    'company' => [
        'name' => env('COMPANY_NAME', 'Suhana Fashions Limited'),
    ],

    // Uploaded tech packs, style images, BOM / order / sample attachments.
    'upload_disk' => env('MERCHANDISING_SFL_DISK', 'public'),

    // Planning — weekly off day(s), Carbon dayOfWeek (0 = Sunday … 5 = Friday).
    // Public holidays come from HR → Holidays.
    'weekly_off_days' => array_map('intval', explode(',', env('MERCHANDISING_SFL_WEEKLY_OFF', '5'))),

    // Sewing hourly output: start hours of the hour slots (8 = 8-9 AM … 19 = 7-8 PM)
    // and the break hour (no entry; shown as "Break" on the Sewing board).
    'sewing_hours' => range(8, 19),
    'sewing_break_hour' => 13,

    // Cutting: how far above a size's order qty the total cut may go (wastage / excess allowance).
    'cutting_excess_percent' => (float) env('MERCHANDISING_SFL_CUTTING_EXCESS', 5),

    // Document number prefixes — "<PREFIX>-<YYYY>-<0001>"; com_invoice / packing_list
    // are daily: "<PREFIX><YYMMDD>-<001>" (IN261017-001, PL261017-001).
    'number_prefixes' => [
        'inquiry'    => 'INQ',
        'cost_sheet' => 'CST',
        'order'      => 'ORD',
        'bom'        => 'BOM',
        'sample'     => 'SMP',
        'bulletin'   => 'BLT',
        'tna'        => 'TNA',
        'cutting'    => 'CUT',
        'export_lc'  => 'ELC',
        'com_invoice' => 'IN',
        'packing_list' => 'PL',
        'final_packing_list' => 'FPL',
    ],
];
