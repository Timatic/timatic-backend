<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Database\Seeder;

class ApiClientSeeder extends Seeder
{
    /**
     * The first party clients every deployment runs, from their defaults in config. Their redirect
     * uris come from the environment, so re-run this after changing APP_FRONTEND_URL or
     * EXTENSION_IDS.
     */
    public function run(): void
    {
        /** @var array<string, array{label: string, redirect_uris: list<string>, token_lifetime_days: int, auto_approve: bool}> $clients */
        $clients = config('api_clients.clients', []);

        foreach ($clients as $id => $client) {
            ApiClient::updateOrCreate(['id' => $id], $client);
        }
    }
}
