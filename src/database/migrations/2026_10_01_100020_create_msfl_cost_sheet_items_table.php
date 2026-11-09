<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_cost_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cost_sheet_id');
            $table->string('group', 20);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('description')->nullable();
            $table->string('supplier_name', 150)->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('consumption', 14, 4)->default(0.0000);
            $table->decimal('rate', 14, 4)->default(0.0000);
            $table->decimal('amount', 15, 4)->default(0.0000);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->index('uom_id', 'msfl_cost_sheet_items_uom_id_foreign');

            $table->foreign('cost_sheet_id')->references('id')->on('msfl_cost_sheets')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('msfl_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_cost_sheet_items');
    }
};
