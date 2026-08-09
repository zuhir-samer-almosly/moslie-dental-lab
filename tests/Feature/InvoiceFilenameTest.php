<?php

use App\Models\Dentist;
use App\Support\InvoiceFilename;

function dentistNamed(string $name, string $gender = 'male'): Dentist
{
    return new Dentist(['name' => $name, 'gender' => $gender]);
}

it('names the file after a male dentist', function () {
    expect(InvoiceFilename::for(dentistNamed('العلي'), '2026-07-31', '2026-09-01'))
        ->toBe('الدكتور العلي المحترم 2026-07-31 - 2026-09-01.pdf');
});

it('uses the feminine honorific for a female dentist', function () {
    expect(InvoiceFilename::for(dentistNamed('سارة', 'female'), '2026-07-31', '2026-09-01'))
        ->toBe('الدكتورة سارة المحترمة 2026-07-31 - 2026-09-01.pdf');
});

it('keeps the generic name when no dentist is selected', function () {
    expect(InvoiceFilename::for(null, '2026-07-31', '2026-09-01'))
        ->toBe('فاتورة-2026-07-31-2026-09-01.pdf');
});

it('strips path separators and other filename-hostile characters', function () {
    expect(InvoiceFilename::for(dentistNamed('أحمد/علي: "ب"'), '2026-07-31', '2026-09-01'))
        ->toBe('الدكتور أحمد علي ب المحترم 2026-07-31 - 2026-09-01.pdf');
});

it('falls back to the generic name when the dentist name sanitizes to nothing', function () {
    expect(InvoiceFilename::for(dentistNamed('///'), '2026-07-31', '2026-09-01'))
        ->toBe('فاتورة-2026-07-31-2026-09-01.pdf');
});
