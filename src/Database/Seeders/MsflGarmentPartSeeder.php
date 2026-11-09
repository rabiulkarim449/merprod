<?php

namespace ME\MerchandisingSfl\Database\Seeders;

use Illuminate\Database\Seeder;
use ME\MerchandisingSfl\Models\GarmentPart;

/** Common garment parts; add more in Master Data → Garment Parts. */
class MsflGarmentPartSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['FRONT', 'Front'], ['BACK', 'Back'], ['SLEEVE', 'Sleeve'], ['COLLAR', 'Collar'], ['CUFF', 'Cuff'],
            ['PLACKET', 'Placket'], ['POCKET', 'Pocket'], ['YOKE', 'Yoke'], ['HOOD', 'Hood'], ['WAISTBAND', 'Waistband'],
            ['FLY', 'Fly'], ['BODY', 'Body'],
        ] as [$code, $name]) {
            GarmentPart::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
