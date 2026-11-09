<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_bulletins', function (Blueprint $table) {
            $table->id();
            $table->string('bulletin_no', 30)->unique();
            $table->foreignId('style_id')->constrained('msfl_styles')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->date('bulletin_date');
            $table->string('description')->nullable();
            // Reference line: its machines are compared against what the style needs.
            $table->foreignId('line_id')->nullable()->constrained('msfl_lines')->nullOnDelete();
            // Inputs.
            $table->unsignedInteger('target_per_hour');
            $table->decimal('working_hours', 4, 1)->default(10);
            // Cached by Bulletin::recalculate().
            $table->decimal('total_smv', 10, 3)->default(0);
            $table->unsignedInteger('target_per_day')->default(0);   // target / hr × hours
            $table->unsignedInteger('operators')->default(0);        // machine workplaces
            $table->unsignedInteger('helpers')->default(0);          // helper workplaces (manual, iron)
            $table->unsignedInteger('total_manpower')->default(0);   // operators + helpers
            $table->decimal('r_smv', 10, 2)->default(0);             // manpower × 60 ÷ target / hr
            $table->decimal('utilization_percent', 6, 2)->default(0); // SMV ÷ R-SMV
            $table->unsignedInteger('max_p_target')->default(0);
            $table->unsignedInteger('min_p_target')->default(0);      // the line's real hourly output
            $table->decimal('bottleneck_percent', 6, 2)->default(0);  // highest operation Req ÷ W-Place
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
        Schema::dropIfExists('msfl_bulletins');
    }
};
