<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            // The business date, deliberately not created_at: it mirrors the
            // date column the source record is reported by (due_date,
            // payment_date, purchase_date, expense_date).
            $table->date('entry_date');
            $table->string('description');
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
