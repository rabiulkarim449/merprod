<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_tna_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tna_plan_id')->constrained('msfl_tna_plans')->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('msfl_lines')->restrictOnDelete();
            $table->unsignedInteger('daily_capacity')->default(0); // this line's share, at calculation time
            $table->unique(['tna_plan_id', 'line_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_tna_plan_lines');
    }
};
