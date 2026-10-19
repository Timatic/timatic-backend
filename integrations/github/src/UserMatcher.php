<?php

namespace Timatic\GitHub;

use App\Models\User;
use Timatic\GitHub\Models\CommitEmail;
use Timatic\GitHub\Models\UserLogin;

/**
 * Matches GitHub activity to Timatic users, and learns every commit email and login it can
 * attribute so later commits made with another address still find their author.
 */
class UserMatcher
{
    public function forCommitAuthor(?string $email, ?string $login): ?User
    {
        $email = is_string($email) && $email !== '' ? mb_strtolower($email) : null;
        $login = is_string($login) && $login !== '' ? $login : null;

        $user = ($email === null ? null : CommitEmail::query()->where('email', $email)->first()?->user)
            ?? ($login === null ? null : $this->forLogin($login))
            ?? ($email === null ? null : User::query()->where('email', $email)->first());

        if ($user === null) {
            return null;
        }

        if ($email !== null) {
            CommitEmail::query()->firstOrCreate(['email' => $email], ['user_id' => $user->id]);
        }

        if ($login !== null && ! UserLogin::query()->where('user_id', $user->id)->orWhere('login', $login)->exists()) {
            UserLogin::query()->create(['user_id' => $user->id, 'login' => $login]);
        }

        return $user;
    }

    public function forLogin(?string $login): ?User
    {
        if ($login === null || $login === '') {
            return null;
        }

        return UserLogin::query()->where('login', $login)->first()?->user;
    }

    public function loginOf(User $user): ?string
    {
        return UserLogin::query()->where('user_id', $user->id)->value('login');
    }
}
