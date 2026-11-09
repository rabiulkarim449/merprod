<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sewing hourly production:
 *  - msfl_prod_entries.hour_slot — the hour an output / reject / rework entry
 *    belongs to (start hour, 8 = 8-9 AM); null for input and older entries.
 *  - msfl_prod_sewing_plans — one line's day on one PO: target, working hours,
 *    SMV, operators, helpers (efficiency / DHU on the Sewing board).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_prod_sewing_plans', function (Blueprint $table) {
            $table->id();
            $table->date('plan_date');
            $table->foreignId('line_id')->constrained('msfl_lines')->restrictOnDelete();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->restrictOnDelete();
            $table->unsignedInteger('target')->default(0);            // pcs for the day
            $table->decimal('working_hours', 4, 1)->default(0);
            $table->decimal('smv', 8, 3)->default(0);
            $table->unsignedSmallInteger('operators')->default(0);
            $table->unsignedSmallInteger('helpers')->default(0);
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['plan_date', 'line_id', 'order_po_id'], 'msfl_sewing_plan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_prod_sewing_plans');
    }
};
