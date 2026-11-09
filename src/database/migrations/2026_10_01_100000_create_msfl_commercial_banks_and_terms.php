<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial masters: Banks (ours / buyers' / suppliers') and Payment Terms (at sight,
 * usance n days, TT, DP / DA). The Export LC picks its issuing bank, lien bank and payment
 * term from them instead of typed text (those text columns held no data yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_com_banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->string('bank_type', 20)->default('our');
            $table->string('branch')->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('ad_code', 30)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_com_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->string('term_type', 20);
            $table->unsignedSmallInteger('days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('code');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // The standard payment terms.
        foreach ([
            ['LC-SIGHT', 'LC at Sight', 'sight', 0], ['LC-U30', 'LC Usance 30 days', 'usance', 30], ['LC-U60', 'LC Usance 60 days', 'usance', 60],
            ['LC-U90', 'LC Usance 90 days', 'usance', 90], ['LC-U120', 'LC Usance 120 days', 'usance', 120], ['TT-ADV', 'TT in Advance', 'tt_advance', 0],
            ['TT-30', 'TT 30 days after shipment', 'tt', 30], ['DP', 'DP at Sight', 'dp', 0], ['DA-60', 'DA 60 days', 'da', 60],
        ] as [$code, $name, $type, $days]) {
            DB::table('msfl_com_payment_terms')->insert(['code' => $code, 'name' => $name, 'term_type' => $type, 'days' => $days, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_com_payment_terms');
        Schema::dropIfExists('msfl_com_banks');
    }
};
