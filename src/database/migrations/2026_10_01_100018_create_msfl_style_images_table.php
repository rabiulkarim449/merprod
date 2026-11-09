<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_style_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('style_id')->constrained('msfl_styles')->cascadeOnDelete();
            $table->string('path');
            $table->string('type', 20)->default('front'); // front, back, detail, artwork, other
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_style_images');
    }
};
