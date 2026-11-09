<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_lead_times', function (Blueprint $table) {
            $table->id();
            // 'washing' or 'finishing' — the post-sewing step this many days get added for.
            $table->string('category', 20);
            $table->string('name'); // e.g. "Normal Wash", "Acid Wash", "Denim / Over Dye"
            $table->unsignedInteger('days');
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $rows = [
            ['washing', 'Normal Wash', 1, 10],
            ['washing', 'Acid Wash', 4, 20],
            ['washing', 'Denim / Over Dye', 3, 30],
            ['finishing', 'Acid Wash', 2, 10],
            ['finishing', 'Denim / Over Dye', 2, 20],
        ];
        foreach ($rows as $r) {
            DB::table('msfl_lead_times')->insert([
                'category' => $r[0], 'name' => $r[1], 'days' => $r[2], 'sequence' => $r[3],
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_lead_times');
    }
};
