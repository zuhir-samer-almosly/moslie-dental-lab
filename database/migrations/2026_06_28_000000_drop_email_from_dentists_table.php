<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dentists.email column was never wired up — no form, request, or
     * factory ever populated it. Drop it (and its unique index).
     */
    public function up(): void
    {
        Schema::table('dentists', function (Blueprint $table) {
            $table->dropUnique('dentists_email_unique');
            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        Schema::table('dentists', function (Blueprint $table) {
            $table->string('email')->unique()->nullable()->after('name');
        });
    }
};
