<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production — simple piece-count entry against an order PO:
 *   fabric requisition (to an Inventory store) → cutting (sizes, parts,
 *   bundles) → [embroidery] → sewing → [washing] → finishing → final QC →
 *   packing, with QC (pass / rework / reject + defect detail) at each stage.
 *
 * Sizes, Inventory requisitions and Inventory machines are referenced by id
 * without a foreign key (they belong to the Inventory package).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_prod_fabric_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->restrictOnDelete();
            $table->unsignedBigInteger('inv_requisition_id')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('msfl_prod_cuttings', function (Blueprint $table) {
            $table->id();
            $table->string('cutting_no', 30)->unique();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->restrictOnDelete();
            $table->date('cutting_date');
            $table->string('table_no', 50)->nullable();
            $table->unsignedInteger('lay_count')->nullable();
            $table->decimal('fabric_used', 12, 2)->nullable();
            $table->unsignedInteger('bundle_size')->default(20);
            $table->unsignedInteger('total_qty')->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_prod_cutting_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_id')->constrained('msfl_prod_cuttings')->cascadeOnDelete();
            $table->unsignedBigInteger('size_id')->index();
            $table->unsignedInteger('qty');
        });

        Schema::create('msfl_prod_cutting_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutting_id');
            $table->string('part_name', 100);
            $table->unsignedBigInteger('size_id')->nullable();
            $table->unsignedInteger('qty');
            $table->index('size_id');

            $table->foreign('cutting_id')->references('id')->on('msfl_prod_cuttings')->cascadeOnDelete();
        });

        Schema::create('msfl_prod_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_id')->constrained('msfl_prod_cuttings')->cascadeOnDelete();
            $table->string('bundle_no', 40)->unique();
            $table->unsignedBigInteger('size_id')->index();
            $table->unsignedInteger('qty');
        });

        Schema::create('msfl_prod_entries', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 20);
            $table->string('kind', 12)->default('production');
            $table->date('entry_date');
            $table->unsignedBigInteger('order_po_id');
            $table->unsignedBigInteger('size_id')->nullable();
            $table->string('part_name', 100)->nullable();
            $table->unsignedBigInteger('line_id')->nullable();
            $table->unsignedTinyInteger('hour_slot')->nullable();
            $table->unsignedInteger('input_qty')->default(0);
            $table->unsignedInteger('pass_qty')->default(0);
            $table->unsignedInteger('rework_qty')->default(0);
            $table->unsignedInteger('reject_qty')->default(0);
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['order_po_id', 'stage']);
            $table->index('size_id');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('line_id')->references('id')->on('msfl_lines')->nullOnDelete();
            $table->foreign('order_po_id')->references('id')->on('msfl_order_pos')->restrictOnDelete();
        });

        Schema::create('msfl_prod_entry_defects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_id');
            $table->string('type', 10);
            $table->string('severity', 10)->nullable();
            $table->string('part_name', 100)->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->string('defect', 150)->nullable();
            $table->unsignedInteger('qty');
            $table->index('machine_id');

            $table->foreign('entry_id')->references('id')->on('msfl_prod_entries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_prod_entry_defects');
        Schema::dropIfExists('msfl_prod_entries');
        Schema::dropIfExists('msfl_prod_bundles');
        Schema::dropIfExists('msfl_prod_cutting_parts');
        Schema::dropIfExists('msfl_prod_cutting_sizes');
        Schema::dropIfExists('msfl_prod_cuttings');
        Schema::dropIfExists('msfl_prod_fabric_requisitions');
    }
};
