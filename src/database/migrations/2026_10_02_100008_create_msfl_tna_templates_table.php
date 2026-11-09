<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_tna_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            // Gaps (working days) between the production milestones, counted back from shipment.
            $table->unsignedInteger('ship_to_ex_factory_days')->default(3);
            $table->unsignedInteger('ex_factory_to_sewing_end_days')->default(4); // finishing + packing
            $table->unsignedInteger('pcd_to_sewing_start_days')->default(3);      // cutting lead
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_tna_templates');
    }
};
