<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TrackedDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property ?int $id
 * @property ?int $user_id
 * @property ?int $parent_id
 * @property string $domain
 * @property string $path
 * @property int $customer_id
 * @property ?int $budget_id
 * @property bool $is_internal
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Customer $customer
 * @property ?Budget $budget
 * @property ?User $user
 * @property ?TrackedDomain $parent
 *
 * @method static Builder<TrackedDomain> shared(mixed $isShared = true)
 * @method static Builder<TrackedDomain> visibleTo(?int $userId)
 */
class TrackedDomain extends Model
{
    /** @use HasFactory<TrackedDomainFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'domain',
        'path',
        'customer_id',
        'budget_id',
        'is_internal',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'path' => '',
        'is_internal' => false,
    ];

    /**
     * Reduces a url or host to the form that is stored: lowercase, no scheme, no port, no path and
     * without the www prefix, so that both "https://WWW.Acme.com/x" and "acme.com" end up as the
     * same mapping.
     */
    public static function normalise(string $hostOrUrl): string
    {
        $host = strtolower((string) parse_url(self::withScheme($hostOrUrl), PHP_URL_HOST));

        $host = ltrim($host, '.');

        return Str::startsWith($host, 'www.') ? Str::after($host, 'www.') : $host;
    }

    /**
     * Reduces a url path to the form that is stored: a leading slash, no trailing slash and no
     * query or fragment. An empty path means the mapping covers the whole host.
     */
    public static function normalisePath(string $pathOrUrl): string
    {
        $path = trim($pathOrUrl);

        if (! Str::startsWith($path, '/')) {
            $path = (string) parse_url(self::withScheme($path), PHP_URL_PATH);
        }

        $path = rtrim(Str::before(Str::before($path, '?'), '#'), '/');

        return $path === '/' ? '' : $path;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The shared mapping this one was accepted from.
     *
     * @return BelongsTo<TrackedDomain, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Mappings without an owner: the ones the web app shares with everyone, which the extension
     * offers as a suggestion rather than tracking them.
     *
     * @param  Builder<TrackedDomain>  $query
     */
    #[Scope]
    protected function shared(Builder $query, mixed $isShared = true): void
    {
        if (filter_var($isShared, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('user_id');
        } else {
            $query->whereNotNull('user_id');
        }
    }

    /**
     * Shared mappings plus the ones this user keeps to themselves. Another user's private mappings
     * are invisible: their local development domains are none of your business.
     *
     * @param  Builder<TrackedDomain>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?int $userId): void
    {
        $query->where(function (Builder $query) use ($userId) {
            $query->whereNull('user_id');

            if ($userId !== null) {
                $query->orWhere('user_id', $userId);
            }
        });
    }

    /** Lets parse_url do the work for bare hosts too, which it otherwise reads as a path. */
    private static function withScheme(string $hostOrUrl): string
    {
        $value = trim($hostOrUrl);

        return str_contains($value, '://') ? $value : 'https://'.$value;
    }

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }
}
