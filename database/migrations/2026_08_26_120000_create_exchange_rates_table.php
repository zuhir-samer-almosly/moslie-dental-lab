<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            // One rate per day. The rate in effect on any date is the newest
            // row on or before it, so days without a row inherit the last one.
            $table->date('rate_date')->unique();
            // Lira per 1 USD. Six decimals is far more precision than the
            // street rate ever carries, and costs nothing to keep.
            $table->decimal('rate', 15, 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
