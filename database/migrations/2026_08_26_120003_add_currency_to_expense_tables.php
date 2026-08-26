<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The expense side of the same provenance the income side already has:
     * materials bought in dollars, rent agreed in dollars, a salary paid in
     * dollars. `amount` keeps holding lira, so LedgerReports and every
     * expense report read exactly what they read before.
     */
    private const TABLES = ['expenses', 'material_purchases', 'employee_payments'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('currency', 3)->default('SYP')->after('amount');
                $blueprint->integer('original_amount')->nullable()->after('currency');
                $blueprint->decimal('rate', 15, 6)->nullable()->after('original_amount');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['currency', 'original_amount', 'rate']);
            });
        }
    }
};
