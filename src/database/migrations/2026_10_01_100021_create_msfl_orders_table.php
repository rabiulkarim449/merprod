<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30);
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->unsignedBigInteger('merchandiser_id')->nullable();
            $table->unsignedBigInteger('factory_id')->nullable();
            $table->unsignedBigInteger('inquiry_id')->nullable();
            $table->string('buyer_order_ref', 100)->nullable();
            $table->date('order_date');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('delivery_term', 10)->nullable();
            $table->unsignedBigInteger('payment_term_id')->nullable();
            $table->unsignedInteger('total_qty')->default(0);
            $table->decimal('total_value', 15, 4)->default(0.0000);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_quick_entry')->default(false);
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('attachment')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('order_no');

            $table->foreign('buyer_id')->references('id')->on('msfl_buyers')->restrictOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('currency_id')->references('id')->on('msfl_currencies')->nullOnDelete();
            $table->foreign('factory_id')->references('id')->on('msfl_factories')->nullOnDelete();
            $table->foreign('inquiry_id')->references('id')->on('msfl_inquiries')->nullOnDelete();
            $table->foreign('merchandiser_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('payment_term_id')->references('id')->on('msfl_com_payment_terms')->nullOnDelete();
            $table->foreign('season_id')->references('id')->on('msfl_seasons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_orders');
    }
};
