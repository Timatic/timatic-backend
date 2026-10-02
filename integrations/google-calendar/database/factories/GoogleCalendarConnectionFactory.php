<?php

namespace Timatic\GoogleCalendar\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;

/**
 * @extends Factory<GoogleCalendarConnection>
 */
class GoogleCalendarConnectionFactory extends Factory
{
    protected $model = GoogleCalendarConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => 'an-access-token',
            'refresh_token' => 'a-refresh-token',
            'expires_at' => now()->addHour(),
        ];
    }
}
