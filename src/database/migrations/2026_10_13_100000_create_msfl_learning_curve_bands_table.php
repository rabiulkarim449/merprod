<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_learning_curve_bands', function (Blueprint $table) {
            $table->id();
            // 'production' = the shared floor target everyone sees. 'shipment' =
            // Planning Manager's own, more conservative estimate — gated behind
            // a separate permission (msfl_learning_curve_shipment).
            $table->enum('curve_type', ['production', 'shipment']);
            $table->string('label', 20); // as printed on the source sheet, e.g. ">15", "15.1-20"
            $table->decimal('smv_from', 8, 2)->nullable(); // null = no lower bound
            $table->decimal('smv_to', 8, 2)->nullable();   // null = no upper bound
            $table->decimal('reference_smv', 8, 2)->nullable(); // the sheet's example SMV for this band
            // Day-by-day efficiency %, in order: [day1, day2, ...]. Separate JSON
            // array (not fixed columns) so a band can have more or fewer days.
            $table->json('day_percents');
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $rows = [
            // curve_type, label, smv_from, smv_to, reference_smv, day_percents (1..10)
            ['production', '<15',     null,  15,   14,   [40, 60, 75, 80, 80, 85, 85, 85, 85, 90]],
            ['production', '15.1-20', 15.01, 20,   18.5, [40, 55, 70, 80, 80, 80, 80, 80, 80, 85]],
            ['production', '20.1-25', 20.01, 25,   22.1, [35, 50, 70, 75, 75, 75, 75, 75, 75, 80]],
            ['production', '25.1-30', 25.01, 30,   27.2, [30, 45, 60, 70, 75, 75, 75, 75, 75, 80]],
            ['production', '30.1-40', 30.01, 40,   33.1, [20, 40, 60, 70, 70, 70, 70, 75, 75, 75]],
            ['production', '40.1-50', 40.01, 50,   42.1, [20, 30, 45, 60, 65, 70, 70, 70, 70, 75]],
            ['production', '50.1-60', 50.01, 60,   55.4, [15, 20, 30, 45, 55, 60, 65, 65, 65, 70]],

            ['shipment', '<15',     null,  15,   14,   [30, 45, 60, 70, 75, 80, 80, 80, 80, 80]],
            ['shipment', '15.1-20', 15.01, 20,   18.5, [25, 40, 55, 65, 75, 80, 80, 80, 80, 80]],
            ['shipment', '20.1-25', 20.01, 25,   22.1, [23, 35, 50, 60, 70, 75, 75, 75, 75, 75]],
            ['shipment', '25.1-30', 25.01, 30,   27.2, [20, 35, 50, 60, 70, 70, 70, 70, 70, 70]],
            ['shipment', '30.1-40', 30.01, 40,   33.1, [16, 30, 45, 55, 60, 65, 70, 70, 70, 70]],
            ['shipment', '40.1-50', 40.01, 50,   42.1, [14, 28, 42, 52, 62, 65, 67, 67, 67, 67]],
            ['shipment', '50.1-60', 50.01, 60,   55.4, [12, 25, 35, 45, 55, 60, 65, 65, 65, 65]],
        ];

        foreach ($rows as $i => $r) {
            DB::table('msfl_learning_curve_bands')->insert([
                'curve_type' => $r[0],
                'label' => $r[1],
                'smv_from' => $r[2],
                'smv_to' => $r[3],
                'reference_smv' => $r[4],
                'day_percents' => json_encode($r[5]),
                'sequence' => ($i % 7 + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_learning_curve_bands');
    }
};
