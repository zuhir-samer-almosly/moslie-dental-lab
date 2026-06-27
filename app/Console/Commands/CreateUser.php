<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    /**
     * Public registration is disabled, so staff accounts are created with this
     * command instead. Run interactively (prompts for any missing values):
     *   php artisan app:create-user
     * or non-interactively:
     *   php artisan app:create-user --name="Zoher" --email="me@x.com" --password="..."
     */
    protected $signature = 'app:create-user
        {--name= : The user\'s full name}
        {--email= : The user\'s email address}
        {--password= : The user\'s password (min 8 characters)}';

    protected $description = 'Create a login account (registration is disabled; use this to add yourself or staff)';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $password = $this->option('password') ?: promptPassword('Password (min 8 chars)', required: true);

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // Mark verified so the new account can log in immediately without
        // needing the email-verification step (no SMTP configured by default).
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("✅ Account created for {$user->email}");

        return self::SUCCESS;
    }
}
