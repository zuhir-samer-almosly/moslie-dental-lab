<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An account holds exactly one currency, and an entry never crosses
     * currencies — which is what lets `Ledger::post()`'s balance check stay
     * unchanged, and lets every existing code-keyed lira report keep reading
     * lira and only lira.
     *
     * A SYP account holds whole lira; a USD account holds cents.
     *
     * Every existing account is SYP, which is what it has always been.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('type');
        });

        $now = now();

        DB::table('accounts')->insert([
            [
                'code' => '1001', 'name' => 'صندوق الدولار', 'type' => 'asset',
                'currency' => 'USD', 'category_key' => null, 'is_active' => true,
                'sort_order' => 15, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => '1101', 'name' => 'ذمم الأطباء بالدولار', 'type' => 'asset',
                'currency' => 'USD', 'category_key' => null, 'is_active' => true,
                'sort_order' => 25, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => '4001', 'name' => 'إيرادات بالدولار', 'type' => 'revenue',
                'currency' => 'USD', 'category_key' => null, 'is_active' => true,
                'sort_order' => 45, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['1001', '1101', '4001'])->delete();

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
