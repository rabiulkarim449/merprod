<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_tna_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tna_plan_id')->constrained('msfl_tna_plans')->cascadeOnDelete();
            $table->foreignId('tna_template_task_id')->nullable()->constrained('msfl_tna_template_tasks')->nullOnDelete();
            // Snapshot of the template step — a later template edit never rewrites an existing T&A.
            $table->unsignedInteger('sequence')->default(0);
            $table->string('group_name', 50);
            $table->string('task_code', 50);
            $table->string('task_name');
            $table->string('anchor', 20);
            $table->integer('offset_days')->default(0);
            $table->string('auto_source', 50)->default('none');
            $table->boolean('is_mandatory')->default(false);
            $table->date('plan_date')->nullable();
            $table->date('revised_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->boolean('is_na')->default(false);
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->unique(['tna_plan_id', 'task_code']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_tna_tasks');
    }
};
