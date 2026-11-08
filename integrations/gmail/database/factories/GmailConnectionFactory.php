<?php

declare(strict_types=1);

namespace Timatic\Gmail\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Timatic\Gmail\Models\GmailConnection;

/**
 * @extends Factory<GmailConnection>
 */
class GmailConnectionFactory extends Factory
{
    protected $model = GmailConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => 'an-access-token',
            'refresh_token' => 'a-refresh-token',
            'expires_at' => now()->addHour(),
            'history_id' => '1000',
        ];
    }
}
