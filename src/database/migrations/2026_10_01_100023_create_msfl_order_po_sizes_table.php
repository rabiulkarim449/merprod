<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_order_po_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_po_id');
            $table->unsignedBigInteger('size_id');
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();
            $table->unique(['order_po_id', 'size_id']);
            $table->index('size_id', 'msfl_order_po_sizes_size_id_foreign');

            $table->foreign('order_po_id')->references('id')->on('msfl_order_pos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_order_po_sizes');
    }
};
