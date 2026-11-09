<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_styles', function (Blueprint $table) {
            $table->id();
            $table->string('style_no', 100);
            $table->string('name');
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('inquiry_id')->nullable();
            $table->unsignedBigInteger('season_id')->nullable();
            $table->unsignedBigInteger('merchandiser_id')->nullable();
            $table->unsignedBigInteger('product_type_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->unsignedBigInteger('wash_type_id')->nullable();
            $table->decimal('smv', 8, 2)->nullable();
            $table->decimal('target_cm', 12, 4)->nullable();
            $table->decimal('confirm_cm', 12, 4)->nullable();
            $table->string('fabric_sourced_by', 10)->default('self');
            $table->text('fabric_description')->nullable();
            $table->text('description')->nullable();
            $table->string('tech_pack_file')->nullable();
            $table->string('artwork_file')->nullable();
            $table->string('size_chart_file')->nullable();
            $table->string('development_status', 20)->default('new');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['buyer_id', 'style_no']);
            $table->index('color_id');

            $table->foreign('buyer_id')->references('id')->on('msfl_buyers')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('inquiry_id')->references('id')->on('msfl_inquiries')->nullOnDelete();
            $table->foreign('merchandiser_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('product_type_id')->references('id')->on('msfl_product_types')->nullOnDelete();
            $table->foreign('season_id')->references('id')->on('msfl_seasons')->nullOnDelete();
            $table->foreign('wash_type_id')->references('id')->on('msfl_wash_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_styles');
    }
};
