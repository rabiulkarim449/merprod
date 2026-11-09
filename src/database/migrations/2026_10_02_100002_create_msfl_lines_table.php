<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hr_floor_line_id');
            $table->unsignedInteger('operators')->default(0);
            $table->unsignedInteger('helpers')->default(0);
            $table->unsignedInteger('working_minutes')->default(600);
            $table->decimal('efficiency_percent', 5, 2)->default(60.00);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('hr_floor_line_id');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_lines');
    }
};
