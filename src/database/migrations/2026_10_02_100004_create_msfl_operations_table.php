<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_operations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedBigInteger('machine_type_id')->nullable();
            $table->unsignedBigInteger('garment_part_id')->nullable();
            $table->string('attachment', 50)->nullable();
            $table->decimal('default_smv', 8, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('code');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('garment_part_id')->references('id')->on('msfl_garment_parts')->nullOnDelete();
            $table->foreign('machine_type_id')->references('id')->on('msfl_machine_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_operations');
    }
};
