<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_bulletin_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulletin_id')->constrained('msfl_bulletins')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->string('section', 100)->nullable(); // Back & Front Part, Collar, Lining Part, Assemble …
            $table->foreignId('operation_id')->nullable()->constrained('msfl_operations')->nullOnDelete();
            $table->string('name');
            $table->foreignId('machine_type_id')->nullable()->constrained('msfl_machine_types')->nullOnDelete();
            $table->string('attachment', 50)->nullable();
            $table->decimal('smv', 8, 3);
            // Workplaces given to the operation; blank = required workplaces rounded up.
            $table->unsignedSmallInteger('workplaces')->nullable();
            $table->boolean('is_active')->default(true); // off = not done for this style, left out of every total
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_bulletin_operations');
    }
};
