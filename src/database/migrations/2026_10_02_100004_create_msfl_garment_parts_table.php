<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garment parts master (Front, Back, Sleeve …) — picked in Cutting → Parts Cut,
 * part-wise embroidery, QC / rework and defect rows. Entries store the part name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_garment_parts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('code');
            $table->unique('name');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_garment_parts');
    }
};
