<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_tna_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tna_template_id')->constrained('msfl_tna_templates')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->string('group_name', 50);
            $table->string('task_code', 50);
            $table->string('task_name');
            // order_confirm, pcd, sewing_start, sewing_end, ex_factory, shipment
            $table->string('anchor', 20);
            $table->integer('offset_days')->default(0); // working days, negative = before the anchor
            // none, order_confirmed, bom_approved, bulletin_approved, sample_submitted:<CODE>, sample_approved:<CODE>
            $table->string('auto_source', 50)->default('none');
            $table->string('condition', 20)->nullable(); // wash = only when the style has a wash type
            $table->boolean('is_mandatory')->default(false);
            $table->unique(['tna_template_id', 'task_code']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_tna_template_tasks');
    }
};
