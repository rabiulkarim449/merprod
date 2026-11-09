<?php

namespace ME\MerchandisingSfl\Database\Seeders;

use Illuminate\Database\Seeder;
use ME\MerchandisingSfl\Models\Currency;
use ME\MerchandisingSfl\Models\SampleType;
use ME\MerchandisingSfl\Models\ShipMode;

/**
 * Common starting master data (and the Planning defaults). Units and sizes
 * come from Inventory, holidays from HR — not seeded here. Safe to re-run (matches on code / name).
 *   php artisan db:seed --class="ME\MerchandisingSfl\Database\Seeders\MsflDefaultMasterSeeder"
 */
class MsflDefaultMasterSeeder extends Seeder
{
    public function run(): void
    {
        $sampleTypes = [
            ['PROTO', 'Proto Sample', 1, false], ['FIT', 'Fit Sample', 2, true], ['FIT2', '2nd Fit Sample', 3, true],
            ['SMS', 'Salesman Sample', 4, false], ['PHOTO', 'Photo Sample', 5, false], ['SIZESET', 'Size Set Sample', 6, true],
            ['PP', 'PP (Pre-Production) Sample', 7, true], ['TOP', 'TOP / Shipment Sample', 8, true],
        ];
        foreach ($sampleTypes as [$code, $name, $sequence, $buyerApproval]) {
            SampleType::updateOrCreate(['code' => $code], ['name' => $name, 'sequence' => $sequence, 'requires_buyer_approval' => $buyerApproval]);
        }

        foreach ([['SEA', 'Sea'], ['AIR', 'Air'], ['SEA-AIR', 'Sea-Air'], ['COURIER', 'Courier'], ['ROAD', 'Road']] as [$code, $name]) {
            ShipMode::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach ([['USD', 'US Dollar', '$', 1], ['EUR', 'Euro', '€', 1], ['GBP', 'British Pound', '£', 1], ['BDT', 'Bangladeshi Taka', '৳', 1]] as [$code, $name, $symbol, $rate]) {
            Currency::firstOrCreate(['code' => $code], ['name' => $name, 'symbol' => $symbol, 'exchange_rate' => $rate]);
        }

        // Commercial payment terms [code, name, type, days].
        foreach ([
            ['LC-SIGHT', 'LC at Sight', 'sight', 0], ['LC-U30', 'LC Usance 30 days', 'usance', 30], ['LC-U60', 'LC Usance 60 days', 'usance', 60],
            ['LC-U90', 'LC Usance 90 days', 'usance', 90], ['LC-U120', 'LC Usance 120 days', 'usance', 120], ['TT-ADV', 'TT in Advance', 'tt_advance', 0],
            ['TT-30', 'TT 30 days after shipment', 'tt', 30], ['DP', 'DP at Sight', 'dp', 0], ['DA-60', 'DA 60 days', 'da', 60],
        ] as [$code, $name, $type, $days]) {
            \ME\MerchandisingSfl\Models\Commercial\PaymentTerm::firstOrCreate(['code' => $code], ['name' => $name, 'term_type' => $type, 'days' => $days]);
        }

        $this->call(MsflGarmentPartSeeder::class);
        $this->call(MsflPlanningSeeder::class);
    }
}
