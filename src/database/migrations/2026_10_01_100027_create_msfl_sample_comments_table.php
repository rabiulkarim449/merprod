<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_sample_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('msfl_samples')->cascadeOnDelete();
            $table->date('comment_date');
            $table->text('comment');
            $table->boolean('is_buyer_comment')->default(false);
            $table->string('attachment')->nullable();
            $table->foreignId('commented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_sample_comments');
    }
};
