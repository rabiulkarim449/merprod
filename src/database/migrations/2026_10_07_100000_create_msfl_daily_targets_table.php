<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planning → Daily Targets: one row per date — factory cutting target, packing (poly)
 * target and the FOB value each sewing line should make that day. Read by the
 * Line Wise Output report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_daily_targets', function (Blueprint $table) {
            $table->id();
            $table->date('target_date')->index();
            $table->unsignedInteger('cutting_target')->nullable();
            $table->unsignedInteger('packing_target')->nullable();
            $table->decimal('line_required_value', 14, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_daily_targets');
    }
};
