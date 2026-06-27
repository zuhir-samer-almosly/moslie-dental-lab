<?php

namespace App\Http\Controllers;

use App\Models\Dentist;

class OutstandingController extends Controller
{
    public function index()
    {
        $dentists = Dentist::query()
            ->withSum('orders as orders_total', 'amount')
            ->withSum('payments as payments_total', 'amount')
            ->get()
            ->map(fn (Dentist $dentist) => [
                'id' => $dentist->id,
                'name' => $dentist->name,
                'phone' => $dentist->phone,
                'orders_total' => (int) $dentist->orders_total,
                'payments_total' => (int) $dentist->payments_total,
                'outstanding' => (int) $dentist->orders_total - (int) $dentist->payments_total,
            ])
            ->sortByDesc('outstanding')
            ->values();

        return inertia('outstanding/index', [
            'dentists' => $dentists,
            'totalOutstanding' => $dentists->sum('outstanding'),
        ]);
    }
}
