<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_boms', function (Blueprint $table) {
            $table->id();
            $table->string('bom_no', 30)->unique();
            $table->foreignId('style_id')->constrained('msfl_styles')->restrictOnDelete();
            // Order BOM: when set, required quantities are calculated from this
            // order's PO qty of the style; without it the BOM is per piece only.
            $table->foreignId('order_id')->nullable()->constrained('msfl_orders')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('bom_type', 10)->default('manual'); // manual, file (buyer-provided BOM)
            $table->string('bom_file')->nullable();
            $table->string('status', 20)->default('draft'); // draft, approved
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_boms');
    }
};
