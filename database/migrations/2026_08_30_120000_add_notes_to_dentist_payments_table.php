<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the payment actually was: who handed it over, cash or transfer,
     * which orders it settles. Free text, the same nullable `notes` column
     * the expense side has carried since it was built.
     */
    public function up(): void
    {
        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
