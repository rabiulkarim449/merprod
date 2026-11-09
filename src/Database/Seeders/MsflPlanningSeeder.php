<?php

namespace ME\MerchandisingSfl\Database\Seeders;

use Illuminate\Database\Seeder;
use ME\MerchandisingSfl\Models\MachineType;
use ME\MerchandisingSfl\Models\Operation;
use ME\MerchandisingSfl\Models\TnaTemplate;

/**
 * Planning starting data: machine types (bulletin sheet codes), an operations library and the
 * default T&A template. Safe to re-run — existing records (and an existing
 * default template's edited steps) are left alone.
 *   php artisan db:seed --class="ME\MerchandisingSfl\Database\Seeders\MsflPlanningSeeder"
 */
class MsflPlanningSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, helper workplace?] — codes as used on the operation bulletin sheet.
        $machines = [
            ['LS1', 'Lock Stitch — 1 Needle', false], ['LS2', 'Lock Stitch — 2 Needle', false],
            ['OL3', 'Overlock — 3 Thread', false], ['OL4', 'Overlock — 4 Thread', false], ['OL5', 'Overlock — 5 Thread', false],
            ['CS1', 'Chain Stitch — 1 Needle', false], ['CS2', 'Chain Stitch — 2 Needle', false],
            ['FL', 'Flatlock', false], ['KAN', 'Kansai (Multi-needle)', false], ['ZZ', 'Zigzag', false],
            ['BA', 'Button Attach', false], ['BH', 'Button Hole', false], ['EH', 'Eyelet Hole', false],
            ['BT', 'Bartack', false], ['SNP', 'Snap Button', false], ['FOA', 'Feed-off-Arm', false],
            ['APW', 'Auto Pocket Welt', false], ['BLIND', 'Blind Stitch', false],
            ['VB', 'Vacuum Board', true], ['IRON', 'Iron', true], ['MAN', 'Manual / Helper Table', true],
        ];
        foreach ($machines as [$code, $name, $helper]) {
            MachineType::firstOrCreate(['code' => $code], ['name' => $name, 'is_helper' => $helper]);
        }
        $type = MachineType::pluck('id', 'code');

        // [name, M/C, attachment, SMV] — taken from a Vest (Blazer) bulletin, to start the library from.
        $operations = [
            ['Flap mark & flap make & edge cut', 'LS1', 'Guide', 0.70], ['Bone & dart position mark', 'MAN', 'Pattern', 0.65],
            ['Front dart make', 'LS1', 'Guide', 0.80], ['Front bone join with flap', 'LS1', 'Guide', 0.75],
            ['Corner cut & turn', 'MAN', 'Pattern', 0.70], ['Bone rolling & edge stitch & edge cut for match', 'LS1', 'Guide', 0.65],
            ['Flap 1/4 T/S', 'LS1', 'Guide', 0.60], ['Bone tack & lower T/S', 'LS1', 'Cutter', 0.75],
            ['Bone pocketing join', 'LS2', 'Guide', 0.80], ['Bone pocketing close', 'LS1', 'Guide', 0.75],
            ['Top stitch welt pocket upper', 'LS1', 'Guide', 0.65], ['Reinforcement tack & label tack', 'LS1', 'Guide', 0.60],
            ['Band join with collar & T/S with mark & pair', 'LS1', 'Guide', 0.75], ['Collar make mark & make', 'LS1', 'Guide', 0.85],
            ['Collar & sleeve bust iron', 'IRON', 'Table', 0.75], ['Panel join (lining)', 'LS1', 'Guide', 0.60],
            ['Front bone join (lining)', 'LS1', 'Guide', 0.75], ['Mark welt pocket position', 'MAN', 'Pattern', 0.60],
            ['Sew welt pocket', 'LS1', 'Guide', 0.65], ['Cut & turn welt pocket (lining)', 'LS1', 'Cutter', 0.75],
            ['Bone tack & label join', 'LS1', 'Guide', 0.75], ['Bone pocketing join (lining)', 'LS1', 'Guide', 0.75],
            ['Bone pocketing close (lining)', 'LS1', 'Guide', 0.80], ['Back panel join & opening tack', 'LS1', 'Guide', 0.80],
            ['Back panel hem press lining show', 'IRON', 'Table', 1.30], ['Back panel hem close', 'LS1', 'Guide', 0.75],
            ['Back panel hem nose tack', 'LS1', 'Guide', 0.70], ['Front two part match for mark', 'MAN', 'Table', 0.55],
            ['Front part join nose cut & turn', 'LS1', 'Guide', 0.90], ['Sleeve panel join & make (lining)', 'LS1', 'Guide', 1.00],
            ['Sleeve panel join & make (shell)', 'LS1', 'Guide', 1.00], ['Bottom & box placket press', 'IRON', 'Table', 1.30],
            ['Bottom hem tack & hem lining close', 'LS1', 'Guide', 0.90], ['Lining & shell body match', 'MAN', 'Table', 0.60],
            ['Shell shoulder join', 'LS1', 'Guide', 0.75], ['Lining shoulder join', 'LS1', 'Table', 0.80],
            ['Shell side join', 'LS1', 'Guide', 1.00], ['Lining side join', 'LS1', 'Table', 1.00],
            ['Bone pocket bartack & collar mark', 'BT', 'Guide', 0.60], ['Collar mark for match', 'MAN', 'Table', 0.70],
            ['Collar join', 'LS1', 'Guide', 0.70], ['Collar close', 'LS1', 'Guide', 0.80],
            ['Sleeve match fork mark', 'MAN', 'Table', 0.70], ['Sleeve join tack (shell)', 'LS1', 'Guide', 1.20],
            ['Sleeve close (shell)', 'LS1', 'Guide', 1.00], ['Sleeve join tack (lining)', 'LS1', 'Guide', 1.00],
            ['Sleeve close (lining)', 'LS1', 'Guide', 1.00], ['Close collar with front lapel collar & shoulder edge', 'MAN', 'Table', 0.80],
            ['Button hole mark', 'MAN', 'Table', 1.30], ['Button hole', 'BH', 'Guide', 0.92],
            ['Button position mark', 'MAN', 'Table', 1.30], ['Button attach', 'BA', 'Guide', 0.95],
            ['Thread trimming & remove sticker', 'MAN', 'Table', 0.68],
        ];
        foreach ($operations as [$name, $machine, $attachment, $smv]) {
            $code = substr(strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $name)), 0, 50);
            Operation::firstOrCreate(['code' => trim($code, '_')], [
                'name' => $name, 'machine_type_id' => $type[$machine] ?? null, 'attachment' => $attachment, 'default_smv' => $smv,
            ]);
        }

        if (TnaTemplate::where('name', 'Standard T&A')->exists()) {
            return;
        }

        $template = TnaTemplate::create([
            'name' => 'Standard T&A', 'is_default' => ! TnaTemplate::where('is_default', true)->exists(),
            'ship_to_ex_factory_days' => 3, 'ex_factory_to_sewing_end_days' => 4, 'pcd_to_sewing_start_days' => 3,
        ]);

        // [group, code, name, anchor, ± working days, actual date from, condition, mandatory]
        $steps = [
            ['Order', 'ORDER_CONFIRM', 'Order Confirmation', 'order_confirm', 0, 'order_confirmed', null, true],
            ['Order', 'TNA_PREPARED', 'T&A Prepared & Shared', 'order_confirm', 2, 'none', null, false],
            ['Material', 'BOM_APPROVAL', 'BOM Approval', 'order_confirm', 5, 'bom_approved', null, true],
            ['Material', 'FABRIC_BOOKING', 'Fabric Booking', 'pcd', -45, 'none', null, true],
            ['Material', 'TRIMS_BOOKING', 'Trims & Accessories Booking', 'pcd', -35, 'none', null, true],
            ['Material', 'SHADE_BAND_SUBMIT', 'Shade Band Submission', 'pcd', -33, 'none', null, false],
            ['Material', 'FABRIC_LC', 'Bulk Fabric LC Opened', 'pcd', -40, 'none', null, false],
            ['Material', 'LAB_DIP', 'Lab Dip / Shade Band Approval', 'pcd', -30, 'none', null, false],
            ['Sample', 'FIT_APPROVAL', 'Fit Sample Approval', 'pcd', -25, 'sample_approved:FIT', null, false],
            ['Sample', 'PP_SUBMIT', 'PP Sample Submission', 'pcd', -15, 'sample_submitted:PP', null, true],
            ['Sample', 'PP_APPROVAL', 'PP Sample Approval', 'pcd', -10, 'sample_approved:PP', null, true],
            ['Sample', 'WASH_STANDARD', 'Wash Standard Approval', 'pcd', -18, 'none', 'wash', false],
            ['Material', 'FABRIC_XMILL', 'Bulk Fabric Ex-Mill', 'pcd', -14, 'none', null, false],
            ['Pre-Production', 'FILE_HANDOVER', 'File Hand-over to Production', 'pcd', -14, 'none', null, true],
            ['Pre-Production', 'PULLOUT', 'Size Set Fabric & Trims Pull-out', 'pcd', -12, 'none', null, false],
            ['Material', 'FABRIC_INHOUSE', 'Fabric In-house', 'pcd', -7, 'fabric_inhouse', null, true],
            ['Material', 'TRIMS_INHOUSE', 'Trims In-house', 'pcd', -5, 'none', null, true],
            ['Material', 'FABRIC_INSPECTION', 'Fabric Inspection (4-Point)', 'pcd', -4, 'none', null, false],
            ['Pre-Production', 'BULLETIN_APPROVAL', 'Bulletin / Line Layout Approval', 'pcd', -5, 'bulletin_approved', null, false],
            ['Pre-Production', 'PILOT_STITCHING', 'Pilot Stitching', 'pcd', -6, 'none', null, false],
            ['Pre-Production', 'PILOT_WASH', 'Pilot Wash', 'pcd', -5, 'none', 'wash', false],
            ['Pre-Production', 'PILOT_REVIEW', 'Pilot Review', 'pcd', -3, 'none', null, false],
            ['Sample', 'SIZE_SET', 'Size Set Approval', 'pcd', -3, 'sample_approved:SIZESET', null, false],
            ['Pre-Production', 'PP_MEETING', 'PP Meeting', 'pcd', -2, 'none', null, true],
            ['Pre-Production', 'PCD', 'Cutting Start (PCD)', 'pcd', 0, 'cutting_started', null, true],
            ['Production', 'SEWING_START', 'Sewing Start', 'sewing_start', 0, 'sewing_started', null, true],
            ['Production', 'INLINE_INSPECTION', 'Inline Inspection', 'sewing_start', 2, 'none', null, false],
            ['Production', 'SEWING_END', 'Sewing Complete', 'sewing_end', 0, 'sewing_done', null, true],
            ['Production', 'WASH', 'Wash Send & Receive', 'sewing_end', 2, 'washing_done', 'wash', false],
            ['Production', 'FINISHING', 'Finishing Complete', 'ex_factory', -2, 'finishing_done', null, true],
            ['Production', 'PACKING', 'Packing Complete', 'ex_factory', -1, 'packing_done', null, true],
            ['Shipment', 'FINAL_INSPECTION', 'Final Inspection', 'ex_factory', -1, 'final_qc_done', null, true],
            ['Shipment', 'EX_FACTORY', 'Ex-Factory', 'ex_factory', 0, 'none', null, true],
            ['Shipment', 'SHIPMENT', 'Shipment / ETD', 'shipment', 0, 'none', null, true],
        ];
        foreach ($steps as $i => [$group, $code, $name, $anchor, $offset, $auto, $condition, $mandatory]) {
            $template->tasks()->create([
                'sequence' => ($i + 1) * 10, 'group_name' => $group, 'task_code' => $code, 'task_name' => $name,
                'anchor' => $anchor, 'offset_days' => $offset, 'auto_source' => $auto, 'condition' => $condition, 'is_mandatory' => $mandatory,
            ]);
        }
    }
}
