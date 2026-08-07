<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The chart of accounts. Seeded here rather than in a seeder because it
     * is reference data the ledger cannot function without — production runs
     * migrations, not seeders.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // asset|liability|equity|revenue|expense
            // Links an expense account to `expenses.category`. This column is
            // what makes the accounts table the single definition of expense
            // categories, replacing Expense::CATEGORIES.
            $table->string('category_key')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('accounts')->insert(array_map(
            fn (array $row, int $i) => $row + [
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $rows = [
                ['code' => '1000', 'name' => 'الصندوق', 'type' => 'asset', 'category_key' => null],
                ['code' => '1100', 'name' => 'الذمم المدينة', 'type' => 'asset', 'category_key' => null],
                ['code' => '3000', 'name' => 'رأس المال', 'type' => 'equity', 'category_key' => null],
                ['code' => '4000', 'name' => 'إيرادات الأعمال', 'type' => 'revenue', 'category_key' => null],
                ['code' => '5000', 'name' => 'الرواتب', 'type' => 'expense', 'category_key' => null],
                ['code' => '5100', 'name' => 'المواد', 'type' => 'expense', 'category_key' => null],
                ['code' => '5200', 'name' => 'مواصلات وسفر', 'type' => 'expense', 'category_key' => 'transport'],
                ['code' => '5210', 'name' => 'ضرائب', 'type' => 'expense', 'category_key' => 'taxes'],
                ['code' => '5220', 'name' => 'إيجار', 'type' => 'expense', 'category_key' => 'rent'],
                ['code' => '5230', 'name' => 'كهرباء وماء', 'type' => 'expense', 'category_key' => 'utilities'],
                ['code' => '5240', 'name' => 'صيانة', 'type' => 'expense', 'category_key' => 'maintenance'],
                ['code' => '5290', 'name' => 'أخرى', 'type' => 'expense', 'category_key' => 'other'],
                ['code' => '5900', 'name' => 'ديون معدومة', 'type' => 'expense', 'category_key' => null],
            ],
            array_keys($rows),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
