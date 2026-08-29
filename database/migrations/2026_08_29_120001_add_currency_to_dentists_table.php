<?php

// database/migrations/2026_08_29_120001_add_currency_to_dentists_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The currency a dentist's whole relationship with the lab is kept in.
     *
     * This is the AUTHORITY for the money on his rows: a dollar dentist's
     * orders and payments are native dollars — held in cents, converted by
     * nothing, and posted to the dollar accounts. Every existing dentist is
     * SYP, which is what they have always been.
     */
    public function up(): void
    {
        Schema::table('dentists', function (Blueprint $table) {
            $table->string('currency', 3)->default('SYP')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('dentists', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
