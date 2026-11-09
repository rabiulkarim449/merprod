<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_no', 30)->unique();
            $table->foreignId('style_id')->constrained('msfl_styles')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('msfl_buyers')->restrictOnDelete();
            $table->foreignId('sample_type_id')->constrained('msfl_sample_types')->restrictOnDelete();
            // Sampling usually happens before the order is confirmed.
            $table->foreignId('order_id')->nullable()->constrained('msfl_orders')->nullOnDelete();
            $table->foreignId('merchandiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision_no')->default(1);
            $table->foreignId('parent_sample_id')->nullable()->constrained('msfl_samples')->nullOnDelete();
            $table->date('request_date');
            $table->date('required_date')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->string('size_ref', 100)->nullable();
            $table->string('color_ref', 100)->nullable();
            // requested, in_progress, submitted, approved, rejected, cancelled
            $table->string('status', 20)->default('requested');
            $table->date('submit_date')->nullable();
            $table->string('courier_name', 100)->nullable();
            $table->string('tracking_no', 100)->nullable();
            $table->date('decision_date')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('buyer_comments')->nullable();
            $table->string('attachment')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_samples');
    }
};
