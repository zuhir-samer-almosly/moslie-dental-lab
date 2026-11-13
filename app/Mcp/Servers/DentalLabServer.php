<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DeleteRecord;
use App\Mcp\Tools\ListDentists;
use App\Mcp\Tools\ListRecords;
use App\Mcp\Tools\ReferenceData;
use App\Mcp\Tools\SaveDentist;
use App\Mcp\Tools\SaveDentistPayment;
use App\Mcp\Tools\SaveEmployee;
use App\Mcp\Tools\SaveEmployeePayment;
use App\Mcp\Tools\SaveExpense;
use App\Mcp\Tools\SaveMaterialPurchase;
use App\Mcp\Tools\SaveOrder;
use App\Mcp\Tools\SetExchangeRate;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Data entry for the lab over MCP: the same writes the web forms make, driven
 * by an assistant instead of a browser. Started with
 * `php artisan mcp:start dental-lab` (see routes/ai.php).
 */
#[Name('Moslie Dental Lab')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Data entry for a dental lab's books: dentists, their orders and payments, and the lab's expenses (general expenses, material purchases, employee payments). Every write lands in the lab's real accounts, so enter exactly what the user gave and nothing they did not.

    - Resolve names to ids first: `list-dentists` for dentists, `reference-data` for employees, expense categories, work types and today's date. Never guess an id. If a name matches more than one dentist, or none, ask the user rather than picking or creating one.
    - Names, work types and notes are Arabic; keep them in Arabic as given.
    - Money: `amount`/`price` are whole Syrian lira; `usd_amount`/`usd_price` are dollars (12.5 means $12.50). Give one or the other. Each dentist has a billing currency — a USD dentist's orders and payments are dollars only.
    - Dates are YYYY-MM-DD and default to today; when the source gives a date, pass it.
    - Every save tool returns the record as stored. Read the result back against what was asked (dentist, date, total) and tell the user about any difference.
    - When entering a batch, check `list-records` for the same dentist and dates first if there is any chance part of it was already entered.
    - To fix a record, call its save tool again with the `id`. Use `delete-record` only for something entered by mistake, after the user confirms.
    MARKDOWN)]
class DentalLabServer extends Server
{
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        ListDentists::class,
        ReferenceData::class,
        ListRecords::class,
        SaveOrder::class,
        SaveDentistPayment::class,
        SaveExpense::class,
        SaveMaterialPurchase::class,
        SaveEmployeePayment::class,
        SaveDentist::class,
        SaveEmployee::class,
        SetExchangeRate::class,
        DeleteRecord::class,
    ];
}
