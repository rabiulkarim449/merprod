<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_order_pos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('style_id');
            $table->unsignedBigInteger('color_id');
            $table->string('po_no', 100);
            $table->unsignedInteger('po_qty')->default(0);
            $table->decimal('unit_price', 12, 4)->default(0.0000);
            $table->decimal('total_value', 15, 4)->default(0.0000);
            $table->date('pcd_date')->nullable();
            $table->date('shipment_date')->nullable();
            $table->unsignedBigInteger('ship_mode_id')->nullable();
            $table->boolean('needs_embroidery')->default(false);
            $table->boolean('needs_washing')->default(false);
            $table->boolean('applique_ih')->default(false);
            $table->boolean('studs_stones_ih')->default(false);
            $table->boolean('heat_seal_ih')->default(false);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->index('color_id', 'msfl_order_pos_color_id_foreign');
            $table->unique(['order_id', 'po_no', 'style_id', 'color_id'], 'msfl_order_pos_unique');

            $table->foreign('order_id')->references('id')->on('msfl_orders')->cascadeOnDelete();
            $table->foreign('ship_mode_id')->references('id')->on('msfl_ship_modes')->nullOnDelete();
            $table->foreign('style_id')->references('id')->on('msfl_styles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_order_pos');
    }
};
