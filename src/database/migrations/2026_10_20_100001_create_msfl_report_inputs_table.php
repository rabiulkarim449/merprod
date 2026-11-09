<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-entered cells of the operations reports (Shipment Readiness, DHU & RFT, Cutting …) —
 * the columns the system has no source for. One value per report + row + column; row_key
 * names what the row is (e.g. "po:26", "d:2026-10-20|p:26"). report '_settings' holds the
 * shared figures (BDT/USD rate, target margin, labour rates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_report_inputs', function (Blueprint $table) {
            $table->id();
            $table->string('report', 40);
            $table->string('row_key', 120);
            $table->string('field', 40);
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['report', 'row_key', 'field']);
            $table->index(['report', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_report_inputs');
    }
};
