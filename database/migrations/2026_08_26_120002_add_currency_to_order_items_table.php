<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provenance for an item quoted in dollars. `price` keeps holding the lira
     * the quote converted to, so `orders.amount`, `Order::total` and
     * OrderPosting all keep reading the same column and getting the same
     * answer.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('price');
            $table->integer('original_amount')->nullable()->after('currency');
            $table->decimal('rate', 15, 6)->nullable()->after('original_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['currency', 'original_amount', 'rate']);
        });
    }
};
