<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial (step 1): Export LC / Sales Contract with its POs and amendments, and the
 * Commercial Invoice (+ packing list figures) shipped against it. No FKs to inv_* tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_com_export_lcs', function (Blueprint $table) {
            $table->id();
            $table->string('lc_no', 30);
            $table->string('type', 5)->default('lc');
            $table->string('buyer_lc_no', 100);
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->date('lc_date');
            $table->date('last_shipment_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('lc_value', 16, 2);
            $table->decimal('tolerance_percent', 5, 2)->default(0.00);
            $table->unsignedBigInteger('payment_term_id')->nullable();
            $table->unsignedBigInteger('issuing_bank_id')->nullable();
            $table->unsignedBigInteger('lien_bank_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('remarks')->nullable();
            $table->text('attachment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('lc_no');
            $table->index('status');

            $table->foreign('buyer_id')->references('id')->on('msfl_buyers');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('currency_id')->references('id')->on('msfl_currencies')->nullOnDelete();
            $table->foreign('issuing_bank_id')->references('id')->on('msfl_com_banks')->nullOnDelete();
            $table->foreign('lien_bank_id')->references('id')->on('msfl_com_banks')->nullOnDelete();
            $table->foreign('payment_term_id')->references('id')->on('msfl_com_payment_terms')->nullOnDelete();
        });

        Schema::create('msfl_com_export_lc_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_lc_id')->constrained('msfl_com_export_lcs')->cascadeOnDelete();
            $table->foreignId('order_po_id')->unique()->constrained('msfl_order_pos');
            $table->timestamps();
        });

        Schema::create('msfl_com_export_lc_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_lc_id')->constrained('msfl_com_export_lcs')->cascadeOnDelete();
            $table->unsignedSmallInteger('amendment_no');
            $table->date('amendment_date');
            $table->string('field', 30);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('msfl_com_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();
            $table->date('invoice_date');
            $table->foreignId('export_lc_id')->constrained('msfl_com_export_lcs');
            $table->foreignId('buyer_id')->constrained('msfl_buyers');
            $table->unsignedBigInteger('inv_shipment_id')->nullable()->index();
            $table->string('exp_no', 50)->nullable();
            $table->date('exp_date')->nullable();
            $table->foreignId('ship_mode_id')->nullable()->constrained('msfl_ship_modes')->nullOnDelete();
            $table->string('bl_no', 100)->nullable();
            $table->date('bl_date')->nullable();
            $table->string('vessel', 150)->nullable();
            $table->string('container_no', 150)->nullable();
            $table->string('port_of_loading', 100)->nullable();
            $table->string('port_of_discharge', 100)->nullable();
            $table->string('final_destination', 100)->nullable();
            $table->unsignedInteger('total_cartons')->default(0);
            $table->decimal('net_weight', 12, 2)->nullable();
            $table->decimal('gross_weight', 12, 2)->nullable();
            $table->decimal('cbm', 10, 3)->nullable();
            $table->unsignedInteger('total_qty')->default(0);
            $table->decimal('total_value', 16, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_com_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('msfl_com_invoices')->cascadeOnDelete();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos');
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 12, 4);
            $table->decimal('amount', 16, 2);
            $table->unsignedInteger('cartons')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_com_invoice_lines');
        Schema::dropIfExists('msfl_com_invoices');
        Schema::dropIfExists('msfl_com_export_lc_amendments');
        Schema::dropIfExists('msfl_com_export_lc_pos');
        Schema::dropIfExists('msfl_com_export_lcs');
    }
};
