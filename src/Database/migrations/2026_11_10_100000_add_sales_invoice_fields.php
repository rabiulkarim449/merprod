<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields of the client's "Commercial Sales Invoice" print that were not entered anywhere:
 * consignee, origin, shipment date, exchange rate, discount / freight / advance and the declaration
 * on the invoice; a remark per invoice line (per PO); the HS code on the style.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_com_invoices', function (Blueprint $table) {
            $table->string('consignee_name')->nullable()->after('buyer_id');      // empty = the buyer
            $table->text('consignee_address')->nullable()->after('consignee_name'); // empty = the buyer's address
            $table->string('country_of_origin', 100)->nullable()->default('Bangladesh')->after('final_destination');
            $table->date('shipment_date')->nullable()->after('bl_date');          // empty = B/L date
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('shipment_date');
            $table->decimal('discount', 16, 2)->default(0)->after('total_value');
            $table->decimal('freight_other', 16, 2)->default(0)->after('discount');
            $table->decimal('advance_received', 16, 2)->default(0)->after('freight_other');
            $table->text('special_declaration')->nullable()->after('remarks');
        });

        Schema::table('msfl_com_invoice_lines', function (Blueprint $table) {
            $table->string('remarks')->nullable()->after('cartons');
        });

        Schema::table('msfl_styles', function (Blueprint $table) {
            $table->string('hs_code', 30)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_styles', fn (Blueprint $table) => $table->dropColumn('hs_code'));
        Schema::table('msfl_com_invoice_lines', fn (Blueprint $table) => $table->dropColumn('remarks'));
        Schema::table('msfl_com_invoices', fn (Blueprint $table) => $table->dropColumn([
            'consignee_name', 'consignee_address', 'country_of_origin', 'shipment_date', 'exchange_rate',
            'discount', 'freight_other', 'advance_received', 'special_declaration',
        ]));
    }
};
