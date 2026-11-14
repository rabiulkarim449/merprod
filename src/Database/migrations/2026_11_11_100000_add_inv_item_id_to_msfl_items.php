<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional link from a Merchandising item to the Inventory (store) item it is — so reports such as
 * the MRP can read store stock / purchase / GRN figures for a BOM line. Soft reference (no foreign
 * key), like every other column pointing at Inventory: v2 keeps working without the Inventory package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_items', function (Blueprint $table) {
            // One store item per live v2 item — checked in validation (soft-deleted rows ignored), so a plain index here.
            $table->unsignedBigInteger('inv_item_id')->nullable()->after('code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('msfl_items', function (Blueprint $table) {
            $table->dropIndex(['inv_item_id']);
            $table->dropColumn('inv_item_id');
        });
    }
};
