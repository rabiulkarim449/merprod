<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed with the fixed list this master data replaces (MasterRegistry::attachments()
        // used to hardcode these) so every operation/bulletin row already using one of
        // these names keeps matching a valid option.
        $now = now();
        $defaults = ['Guide', 'Pattern', 'Cutter', 'Table', 'Folder', 'Binder', 'Template', 'Jig'];
        DB::table('msfl_attachments')->insert(array_map(fn ($name) => [
            'code' => strtoupper($name),
            'name' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $defaults));
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_attachments');
    }
};
