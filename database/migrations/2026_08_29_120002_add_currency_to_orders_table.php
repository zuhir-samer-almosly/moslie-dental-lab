<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same currency/original_amount pair `order_items` already carries,
     * so an order row stays self-describing: `amount` is its lira (zero for a
     * dollar dentist) and `original_amount` its cents. Both are recomputed
     * from the items on every write, exactly as `amount` already was.
     *
     * No `rate` column: a native dollar order has no rate, and a lira order's
     * per-line rates already live on its items.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('amount');
            $table->integer('original_amount')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'original_amount']);
        });
    }
};
