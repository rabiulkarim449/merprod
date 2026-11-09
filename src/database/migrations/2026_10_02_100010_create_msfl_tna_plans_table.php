<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_tna_plans', function (Blueprint $table) {
            $table->id();
            $table->string('tna_no', 30)->unique();
            $table->foreignId('order_id')->constrained('msfl_orders')->restrictOnDelete();
            $table->foreignId('style_id')->constrained('msfl_styles')->restrictOnDelete();
            $table->foreignId('tna_template_id')->constrained('msfl_tna_templates')->restrictOnDelete();
            $table->foreignId('bulletin_id')->nullable()->constrained('msfl_bulletins')->nullOnDelete();
            // Inputs (refreshed from the order on Recalculate).
            $table->unsignedInteger('order_qty');
            $table->date('shipment_date');
            $table->decimal('smv', 10, 3);
            // Results of TnaPlanner::calculate().
            $table->unsignedInteger('daily_capacity')->default(0);
            $table->unsignedInteger('sewing_days')->default(0);
            $table->date('pcd_date')->nullable();
            $table->date('sewing_start_date')->nullable();
            $table->date('sewing_end_date')->nullable();
            $table->date('ex_factory_date')->nullable();
            $table->boolean('is_feasible')->default(true);
            $table->string('status', 20)->default('active'); // active, completed, cancelled
            $table->timestamp('calculated_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_tna_plans');
    }
};
