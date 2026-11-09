<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_bom_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('item_id');
            $table->string('item_type', 20);
            $table->unsignedBigInteger('color_id')->nullable();
            $table->unsignedBigInteger('size_id')->nullable();
            $table->string('placement')->nullable();
            $table->decimal('consumption', 14, 4);
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('wastage_percent', 5, 2)->default(0.00);
            $table->decimal('rate', 12, 4)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->index('color_id', 'msfl_bom_items_color_id_foreign');
            $table->index('size_id', 'msfl_bom_items_size_id_foreign');
            $table->index('supplier_id', 'msfl_bom_items_supplier_id_foreign');
            $table->index('uom_id', 'msfl_bom_items_uom_id_foreign');

            $table->foreign('bom_id')->references('id')->on('msfl_boms')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('msfl_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_bom_items');
    }
};
