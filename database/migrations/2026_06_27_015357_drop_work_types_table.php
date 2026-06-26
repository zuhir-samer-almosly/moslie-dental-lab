<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Work types are now stored per-dentist in the `dentists.price_list` JSON
     * column, so the global `work_types` table is no longer used.
     */
    public function up(): void
    {
        Schema::dropIfExists('work_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('work_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
