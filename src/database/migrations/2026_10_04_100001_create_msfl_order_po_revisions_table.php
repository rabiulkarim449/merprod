<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Buyer revisions of a confirmed PO (qty / PCD / shipment), logged
 *   automatically when the PO is edited — feeds the T&A sheet's
 *   "revised 1 / revised 2" columns.
 * - In-house embellishment flags on the PO (Applique / Studs-Stones / Heat seal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_order_po_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->cascadeOnDelete();
            $table->string('field', 20); // po_qty | pcd_date | shipment_date
            $table->string('old_value', 50)->nullable();
            $table->string('new_value', 50)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->index(['order_po_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_order_po_revisions');
    }
};
