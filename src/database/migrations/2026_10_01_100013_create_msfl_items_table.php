<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('type', 20);
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->unsignedBigInteger('default_supplier_id')->nullable();
            $table->decimal('default_price', 12, 4)->nullable();
            $table->string('composition')->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('code');
            $table->index('default_supplier_id', 'msfl_items_default_supplier_id_foreign');
            $table->index('uom_id', 'msfl_items_uom_id_foreign');

            $table->foreign('category_id')->references('id')->on('msfl_item_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_items');
    }
};
