<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provenance for a payment that arrived in dollars. `amount` keeps holding
     * the lira — the ledger and every report read it unchanged — and these
     * three record what the money looked like before it became lira.
     *
     * Existing rows default to SYP, which is what they have always been.
     */
    public function up(): void
    {
        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('amount');
            $table->integer('original_amount')->nullable()->after('currency');
            $table->decimal('rate', 15, 6)->nullable()->after('original_amount');
        });
    }

    public function down(): void
    {
        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'original_amount', 'rate']);
        });
    }
};
