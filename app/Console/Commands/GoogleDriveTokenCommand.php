<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Console\Command;

/**
 * One-time helper to mint a Google Drive refresh token for the backup disk.
 *
 * Run this LOCALLY (you need a browser): it prints a consent URL, you sign in
 * with the Google account that owns the backup storage, then paste the
 * `code=...` value Google redirects you to. The command prints the refresh
 * token to put in the production .env as GOOGLE_DRIVE_REFRESH_TOKEN.
 */
class GoogleDriveTokenCommand extends Command
{
    protected $signature = 'backup:google-token';

    protected $description = 'Generate a Google Drive refresh token for off-site backups';

    public function handle(): int
    {
        $clientId = config('filesystems.disks.google.clientId') ?: $this->ask('Google OAuth Client ID');
        $clientSecret = config('filesystems.disks.google.clientSecret') ?: $this->ask('Google OAuth Client Secret');

        if (! $clientId || ! $clientSecret) {
            $this->error('Client ID and secret are required.');

            return self::FAILURE;
        }

        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        // Use the Desktop-app loopback redirect: Google will redirect to a
        // (non-loading) localhost page whose URL contains the auth code.
        $client->setRedirectUri('http://localhost');
        $client->setScopes([GoogleDrive::DRIVE]);
        $client->setAccessType('offline');
        // Force the consent screen so Google always returns a refresh token.
        $client->setPrompt('consent');

        $this->newLine();
        $this->info('1) Open this URL in your browser and sign in with the backup Google account:');
        $this->newLine();
        $this->line($client->createAuthUrl());
        $this->newLine();
        $this->info('2) After approving, your browser will try to open http://localhost/?code=...');
        $this->info('   The page will not load — that is fine. Copy the value of "code" from the address bar.');
        $this->newLine();

        $code = trim((string) $this->ask('Paste the code here'));

        if ($code === '') {
            $this->error('No code provided.');

            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $this->error('Failed: '.($token['error_description'] ?? $token['error']));

            return self::FAILURE;
        }

        if (empty($token['refresh_token'])) {
            $this->error('No refresh token returned. Revoke the app at https://myaccount.google.com/permissions and try again.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Success! Add this to your production .env:');
        $this->newLine();
        $this->line('GOOGLE_DRIVE_REFRESH_TOKEN='.$token['refresh_token']);
        $this->newLine();

        return self::SUCCESS;
    }
}
