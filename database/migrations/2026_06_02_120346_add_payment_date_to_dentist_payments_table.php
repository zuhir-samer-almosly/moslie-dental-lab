<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->date('payment_date')->nullable();
        });
        
        // Backfill existing records with created_at date
        \Illuminate\Support\Facades\DB::table('dentist_payments')->whereNull('payment_date')->update([
            'payment_date' => \Illuminate\Support\Facades\DB::raw('DATE(created_at)')
        ]);
        
        // Make it not nullable if you want, but leaving it nullable is safer for now or we can change it.
        // Schema::table('dentist_payments', function (Blueprint $table) {
        //     $table->date('payment_date')->nullable(false)->change();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dentist_payments', function (Blueprint $table) {
            $table->dropColumn('payment_date');
        });
    }
};
