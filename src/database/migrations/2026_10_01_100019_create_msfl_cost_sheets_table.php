<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_cost_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('cost_sheet_no', 30);
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('style_id')->nullable();
            $table->unsignedBigInteger('inquiry_id')->nullable();
            $table->string('style_ref', 150)->nullable();
            $table->string('garment_description')->nullable();
            $table->string('size_range', 100)->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('price_type', 10)->nullable();
            $table->date('costing_date');
            $table->unsignedInteger('order_qty')->nullable();
            $table->decimal('smv', 8, 2)->nullable();
            $table->decimal('cm_minute_rate', 10, 4)->nullable();
            $table->decimal('efficiency_percent', 6, 2)->nullable();
            $table->decimal('fabric_cost', 15, 4)->default(0.0000);
            $table->decimal('trims_cost', 15, 4)->default(0.0000);
            $table->decimal('process_cost', 15, 4)->default(0.0000);
            $table->decimal('cm_cost', 15, 4)->default(0.0000);
            $table->decimal('commercial_percent', 6, 2)->default(0.00);
            $table->decimal('commercial_cost', 15, 4)->default(0.0000);
            $table->decimal('other_cost', 15, 4)->default(0.0000);
            $table->decimal('total_cost', 15, 4)->default(0.0000);
            $table->decimal('profit_percent', 6, 2)->default(0.00);
            $table->decimal('profit_amount', 15, 4)->default(0.0000);
            $table->decimal('offer_price', 15, 4)->default(0.0000);
            $table->decimal('buyer_target_price', 15, 4)->nullable();
            $table->decimal('final_price', 15, 4)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('cost_sheet_no');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('buyer_id')->references('id')->on('msfl_buyers')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('currency_id')->references('id')->on('msfl_currencies')->nullOnDelete();
            $table->foreign('inquiry_id')->references('id')->on('msfl_inquiries')->nullOnDelete();
            $table->foreign('style_id')->references('id')->on('msfl_styles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_cost_sheets');
    }
};
