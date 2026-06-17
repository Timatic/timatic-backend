<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DummySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    protected $signature = 'timatic:install';

    protected $description = 'Set up a fresh Timatic installation with an admin user';

    public function handle(): int
    {
        info('Setting up Timatic...');

        if (Schema::hasTable('users')) {
            warning('The database is already set up. Continuing will erase all existing data.');

            if (! confirm(label: 'Refresh the database?', default: false)) {
                $this->createAdminUser();

                info('Timatic is ready.');

                return self::SUCCESS;
            }
        }

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $user = $this->createAdminUser();

        if (confirm(label: 'Generate an API token?', default: true)) {
            $this->call('apitoken:generate');
        }

        if (confirm(label: 'Seed dummy data?', default: true)) {
            spin(
                callback: function () use ($user) {
                    $this->call(DummySeeder::class);
                    $this->call('timatic:dummy-suggestions', ['--user' => $user->email]);
                },
                message: 'Seeding dummy data...',
            );
        }

        info('Timatic is ready.');

        return self::SUCCESS;
    }

    private function createAdminUser(): User
    {
        $this->info('Create the first admin user.');

        $email = text(
            label: 'Email address',
            placeholder: 'admin@example.com',
            required: true,
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
        );

        $givenName = text(
            label: 'First name',
            placeholder: 'John',
            required: true,
        );

        $familyName = text(
            label: 'Last name',
            placeholder: 'Doe',
            required: true,
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'external_id' => Str::uuid()->toString(),
                'given_name' => $givenName,
                'family_name' => $familyName,
            ]
        );

        $user->syncRoles([Role::where('name', 'super_admin')->firstOrFail()]);

        return $user;
    }
}
