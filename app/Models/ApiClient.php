<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A client that may run the authorization code flow. It owns the redirect uris its codes may
 * travel to, so a code minted for one client can never be delivered to another.
 *
 * @property string $id
 * @property string $label
 * @property list<string> $redirect_uris
 * @property int $token_lifetime_days
 * @property bool $auto_approve
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class ApiClient extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public function allowsRedirectUri(string $redirectUri): bool
    {
        return in_array($redirectUri, $this->redirect_uris, true);
    }

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'auto_approve' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
