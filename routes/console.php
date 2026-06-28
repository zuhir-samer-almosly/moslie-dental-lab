<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily off-site backup to Google Drive, then prune old backups per the
// retention policy in config/backup.php. Requires the scheduler to be running
// (the `laravel-schedule` supervisor program in production).
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');

// Mid-morning health check. A failing `backup:run` notifies on its own, but a
// night the scheduler never ran (e.g. a deploy restarted it across 01:30) is
// silent — backup:monitor catches that. Against the MaximumAgeInDays => 1 rule
// in config/backup.php, a missed overnight backup reads as stale by 09:00
// (~31h old) and fires UnhealthyBackupWasFound to BACKUP_NOTIFICATION_EMAIL.
Schedule::command('backup:monitor')->daily()->at('09:00');
