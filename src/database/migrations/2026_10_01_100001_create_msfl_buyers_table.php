<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_buyers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->unsignedBigInteger('merchandiser_id')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('agent_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('payment_term_id')->nullable();
            $table->string('delivery_term', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('approval_status', 20)->default('approved');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_remarks', 1000)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('approval_status');
            $table->unique('code');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('merchandiser_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('payment_term_id')->references('id')->on('msfl_com_payment_terms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_buyers');
    }
};
