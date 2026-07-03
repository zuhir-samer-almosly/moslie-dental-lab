<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory;

    /**
     * General-expense categories: key => Arabic label. This is the canonical
     * list — the Store/Update requests validate against its keys and
     * FinanceController maps keys to labels for the breakdown. Keep it in sync
     * with EXPENSE_CATEGORIES in resources/js/types/models.ts.
     */
    public const CATEGORIES = [
        'transport' => 'مواصلات وسفر',
        'taxes' => 'ضرائب',
        'rent' => 'إيجار',
        'utilities' => 'كهرباء وماء',
        'maintenance' => 'صيانة',
        'other' => 'أخرى',
    ];

    protected $fillable = [
        'category',
        'description',
        'amount',
        'expense_date',
        'notes',
    ];
}
