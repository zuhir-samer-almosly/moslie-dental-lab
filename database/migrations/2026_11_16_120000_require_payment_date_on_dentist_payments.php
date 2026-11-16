<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A dentist payment's date stops being optional.
 *
 * The column was added nullable and every reader carried a `created_at`
 * fallback for the rows that had none. The forms have required the date ever
 * since, so the fallback only ever covered legacy rows. Any that remain are
 * given the very date the fallback read for them — the day they were entered
 * — so no payment moves to a different day and no ledger entry changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('dentist_payments')->whereNull('payment_date')->update([
            'payment_date' => DB::raw('DATE(COALESCE(created_at, CURRENT_TIMESTAMP))'),
        ]);

        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->date('payment_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->date('payment_date')->nullable()->change();
        });
    }
};
