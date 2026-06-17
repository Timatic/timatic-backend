<?php

namespace Database\Seeders;

use App\Models\ApiToken;
use Database\Seeders\Dummy\BudgetSeeder;
use Database\Seeders\Dummy\CustomerSeeder;
use Database\Seeders\Dummy\EntrySeeder;
use Database\Seeders\Dummy\OvertimeSeeder;
use Database\Seeders\Dummy\TeamSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class DummySeeder extends Seeder
{
    public function run(): void
    {
        $this->restoreDevApiTokenIfConfigured();

        $this->call([
            TeamSeeder::class,
            CustomerSeeder::class,
            BudgetSeeder::class,
            EntrySeeder::class,
            OvertimeSeeder::class,
        ]);
    }

    private function restoreDevApiTokenIfConfigured(): void
    {
        $plainToken = env('DEV_API_KEY'); // @phpstan-ignore-line

        if (! is_string($plainToken) || $plainToken === '') {
            return;
        }

        $token = ApiToken::create([
            'external_id' => Str::uuid()->toString(),
            'title' => 'Demo API Token',
            'description' => 'Demo token for testing and development',
            'key' => hash('sha512', $plainToken),
            'expires_at' => null,
        ]);

        $permissions = Permission::where('guard_name', 'web')->get();

        if ($permissions->isNotEmpty()) {
            $token->syncPermissions($permissions);
        }
    }
}
