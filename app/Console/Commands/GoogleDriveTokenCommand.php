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
 *
 * IMPORTANT — the OAuth app must be published, or backups silently die after
 * a week. Google expires the refresh tokens of an app whose consent screen is
 * still in "Testing" publishing status after exactly 7 days. This is what
 * killed the first rollout: tokens minted 2026-06-27 uploaded fine nightly
 * through 2026-07-04, then stopped for five weeks with no alert. The failure
 * surfaces from `backup:list` as a misleading "File not found" on the
 * destination folder rather than as an auth error, so read a dead disk as a
 * suspected expired token first, not a missing folder.
 *
 * So, once: Google Cloud Console -> APIs & Services -> OAuth consent screen ->
 * PUBLISH APP. With the drive.file scope set below that is instant and needs
 * no verification, and the refresh token then lasts until it is revoked.
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
        // DRIVE_FILE, not DRIVE. The uploader only ever touches files it
        // created itself, and drive.file is a *non-sensitive* scope: the OAuth
        // app can be published to "In production" without Google's CASA
        // security assessment, which full DRIVE (a restricted scope) requires.
        // That matters because a Testing-status app's refresh tokens expire
        // after 7 days — see the docblock above.
        $client->setScopes([GoogleDrive::DRIVE_FILE]);
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
