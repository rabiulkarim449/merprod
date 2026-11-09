<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packing lists:
 *  - Final Packing List (Production): one buyer PO (all its styles) after floor packing —
 *    order / cut / ship per size + QC's short / excess note per style, approved by the buyer
 *    (figures frozen in `snapshot` on approval).
 *  - Carton Packing List (Commercial invoice): carton ranges with pcs per size per carton,
 *    weights and measurement; the invoice qty / cartons / weights / CBM come from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_prod_final_packing_lists', function (Blueprint $table) {
            $table->id();
            $table->string('fpl_no', 30);
            $table->unsignedBigInteger('order_id');
            $table->string('po_no', 100);
            $table->date('pack_date');
            $table->string('invoice_no', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('buyer_approved_on')->nullable();
            $table->string('buyer_approved_by')->nullable();
            $table->string('approval_file')->nullable();
            $table->text('remarks')->nullable();
            $table->json('snapshot')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('fpl_no');
            $table->index(['order_id', 'po_no']);
            $table->index('status');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('msfl_orders');
        });

        Schema::create('msfl_prod_final_packing_list_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('final_packing_list_id')->constrained('msfl_prod_final_packing_lists')->cascadeOnDelete();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['final_packing_list_id', 'order_po_id'], 'msfl_fpl_notes_unique');
        });

        Schema::create('msfl_com_invoice_cartons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('msfl_com_invoices')->cascadeOnDelete();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos');
            $table->unsignedInteger('carton_from');
            $table->unsignedInteger('carton_to');
            $table->decimal('net_weight', 8, 2)->nullable();
            $table->decimal('gross_weight', 8, 2)->nullable();
            $table->decimal('length', 6, 1)->nullable();
            $table->decimal('width', 6, 1)->nullable();
            $table->decimal('height', 6, 1)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('msfl_com_invoice_carton_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_carton_id')->constrained('msfl_com_invoice_cartons')->cascadeOnDelete();
            $table->unsignedBigInteger('size_id')->index();
            $table->unsignedInteger('qty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_com_invoice_carton_sizes');
        Schema::dropIfExists('msfl_com_invoice_cartons');
        Schema::dropIfExists('msfl_prod_final_packing_list_notes');
        Schema::dropIfExists('msfl_prod_final_packing_lists');
    }
};
