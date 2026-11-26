<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Sample QA Submission Form — one per sample. The sample / style /
 * buyer facts are read from the sample; only what QA types or ticks is kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_sample_qa_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->unique()->constrained('msfl_samples')->cascadeOnDelete();
            $table->string('brand_market', 150)->nullable();
            $table->string('marketing_purpose')->nullable();
            $table->string('meeting_event')->nullable();
            $table->date('qa_inspection_date')->nullable();
            $table->string('custodian', 150)->nullable();
            $table->date('return_due_date')->nullable();
            $table->json('target_impression')->nullable();   // ticked keys
            $table->json('customer_use')->nullable();
            $table->json('range_coordination')->nullable();
            $table->text('key_selling_story')->nullable();
            $table->string('qa_status', 20)->nullable();      // ready / conditional / hold
            $table->string('ownership', 20)->nullable();      // returnable / retain / gift
            $table->json('support_checks')->nullable();      // {key: {checked, ref}}
            $table->json('checkpoints')->nullable();         // {no: {observation, result, defect_class}}
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_sample_qa_forms');
    }
};
