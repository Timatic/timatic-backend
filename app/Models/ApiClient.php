<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\UnknownApiClientException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A client that may run the authorization code flow. It owns the redirect uris its codes may
 * travel to, so a code minted for one client can never be delivered to another.
 *
 * @property string $id
 * @property string $label
 * @property list<string> $redirect_uris
 * @property int $token_lifetime_days
 * @property bool $sliding_expiration A token of this client expires that many days after it was last used, not after it was issued
 * @property bool $auto_approve
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class ApiClient extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * The ids a client may identify itself by. An empty table is a deployment that was never
     * seeded rather than a caller sending a bad id, so it fails loudly here instead of rejecting
     * every authorization request as if the caller were at fault.
     *
     * @return Collection<int, string>
     *
     * @throws UnknownApiClientException
     */
    public static function ids(): Collection
    {
        $ids = self::query()->pluck('id');

        return $ids->isNotEmpty() ? $ids : throw UnknownApiClientException::noneRegistered();
    }

    /**
     * A native app such as a command line tool receives its code on a loopback listener, and the
     * port it can bind is only known at runtime. So a registered loopback uri matches any port, as
     * RFC 8252 section 7.3 prescribes; everything else about the uri still has to match exactly.
     */
    public function allowsRedirectUri(string $redirectUri): bool
    {
        if (in_array($redirectUri, $this->redirect_uris, true)) {
            return true;
        }

        $withoutPort = self::withoutLoopbackPort($redirectUri);

        return $withoutPort !== null && in_array($withoutPort, $this->redirect_uris, true);
    }

    /**
     * Only plain http on the loopback ip literals qualifies. The name localhost is left out on
     * purpose: it may resolve to something other than this machine.
     */
    private static function withoutLoopbackPort(string $redirectUri): ?string
    {
        $matched = preg_match('~^(http://(?:127\.0\.0\.1|\[::1\])):\d{1,5}(/[^?#]*)$~', $redirectUri, $parts);

        return $matched === 1 ? $parts[1].$parts[2] : null;
    }

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'auto_approve' => 'boolean',
            'sliding_expiration' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
