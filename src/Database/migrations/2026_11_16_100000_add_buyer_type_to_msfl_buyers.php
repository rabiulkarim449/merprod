<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Buyer type: export (default — every existing buyer) or local. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_buyers', function (Blueprint $table) {
            $table->string('buyer_type', 10)->default('export')->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('msfl_buyers', function (Blueprint $table) {
            $table->dropIndex(['buyer_type']);
            $table->dropColumn('buyer_type');
        });
    }
};
