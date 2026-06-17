<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;

trait LoginUser
{
    use WithFaker;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function loginUser(array $attributes = [], array $permissions = ['user']): Authenticatable
    {
        $attributes['user_id'] = $attributes['user_id'] ?? $this->faker->uuid();

        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['external_id' => $attributes['user_id']],
            array_merge(User::factory()->make()->toArray(), $attributes)
        );

        $user->givePermissionTo($permissions);

        Auth::setUser($user);

        return Auth::user();
    }
}
