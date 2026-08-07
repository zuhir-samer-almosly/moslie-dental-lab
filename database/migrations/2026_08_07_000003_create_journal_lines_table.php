<?php

use App\Models\Dentist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            // Subsidiary detail, used only on receivable lines so a dentist's
            // statement is a filter rather than an account of its own.
            $table->foreignIdFor(Dentist::class, 'dentist_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
            $table->timestamps();

            $table->index('account_id');
            $table->index('dentist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
