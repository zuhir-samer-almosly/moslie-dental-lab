<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            // Whether the owner set this day's rate himself, rather than it
            // being remembered from a dollar entry he happened to record.
            // A hand-set rate owns its day: `Rate::remember` leaves it alone.
            //
            // Every existing row came from `Rate::remember`, so `false` is the
            // truthful default for the backfill as well as for new rows.
            $table->boolean('is_manual')->default(false)->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
