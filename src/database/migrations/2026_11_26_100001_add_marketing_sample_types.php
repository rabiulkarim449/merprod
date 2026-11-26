<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sample types of the Marketing Sample QA Submission Form (SMS / Showroom /
 * Exhibition / Salesman / Photo / Marketing) — added to Masters → Sample Types
 * when not there yet (by code or name), so the form's "Sample type" reads them.
 */
return new class extends Migration
{
    private const TYPES = ['SMS', 'Showroom', 'Exhibition', 'Salesman', 'Photo', 'Marketing'];

    public function up(): void
    {
        $seq = (int) DB::table('msfl_sample_types')->max('sequence');
        foreach (self::TYPES as $name) {
            $exists = DB::table('msfl_sample_types')
                ->where(fn ($q) => $q->whereRaw('LOWER(code) = ?', [strtolower($name)])->orWhereRaw('LOWER(name) = ?', [strtolower($name)]))
                ->exists();
            if (! $exists) {
                DB::table('msfl_sample_types')->insert([
                    'code' => $name, 'name' => $name, 'sequence' => ++$seq,
                    'requires_buyer_approval' => true, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Master rows may already be used by samples — left in place.
    }
};
