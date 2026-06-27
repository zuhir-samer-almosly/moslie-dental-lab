<?php

use App\Models\Dentist;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Dentist::class, 'dentist_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->integer('amount');
            $table->enum('status', ['pending', 'completed', 'cancelled', 'recieved'])->default('pending');
            $table->longText('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
