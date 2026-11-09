<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_line_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained('msfl_lines')->cascadeOnDelete();
            $table->foreignId('machine_type_id')->constrained('msfl_machine_types')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->unique(['line_id', 'machine_type_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_line_machines');
    }
};
