<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('inquiry_no', 30)->unique();
            $table->date('inquiry_date');
            $table->foreignId('buyer_id')->constrained('msfl_buyers')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('msfl_seasons')->nullOnDelete();
            $table->foreignId('merchandiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('factory_id')->nullable()->constrained('msfl_factories')->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained('msfl_product_types')->nullOnDelete();
            $table->string('style_ref', 150)->nullable();
            $table->string('color_ref', 150)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order_qty')->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('total_value', 15, 4)->nullable();
            $table->date('confirmation_due_date')->nullable();
            $table->date('target_ship_date')->nullable();
            $table->date('extended_ship_date')->nullable();
            $table->string('status', 20)->default('open'); // open, quoted, confirmed, lost, cancelled
            $table->string('lost_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_inquiries');
    }
};
