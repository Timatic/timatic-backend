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
 * @property string $domain
 * @property string $path
 * @property int $customer_id
 * @property ?int $budget_id
 * @property bool $is_internal
 * @property bool $is_active
 * @property ?int $created_by_user_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Customer $customer
 * @property ?Budget $budget
 * @property ?User $createdBy
 * @property ?User $user
 *
 * @method static Builder<TrackedDomain> active()
 * @method static Builder<TrackedDomain> visibleTo(?int $userId)
 */
class TrackedDomain extends Model
{
    /** @use HasFactory<TrackedDomainFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'path',
        'customer_id',
        'budget_id',
        'is_internal',
        'is_active',
        'created_by_user_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'path' => '',
        'is_internal' => false,
        'is_active' => true,
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
     * The tracked domain that covers a url, or null when it is not opted in. A mapping covers its
     * subdomains and everything below its path, and the most specific mapping wins: with both
     * "acme.com" and "jira.acme.com/projects/TIM" stored, a url under that project resolves to the
     * latter. A user's own mapping beats a shared one on the same url.
     */
    public static function matching(string $hostOrUrl, ?int $userId = null): ?self
    {
        $host = self::normalise($hostOrUrl);

        if ($host === '') {
            return null;
        }

        $path = self::normalisePath($hostOrUrl);

        return self::query()
            ->active()
            ->visibleTo($userId)
            ->whereIn('domain', self::candidates($host))
            ->orderByRaw('user_id IS NULL, LENGTH(domain) DESC, LENGTH(path) DESC')
            ->get()
            ->first(fn (self $trackedDomain): bool => $trackedDomain->covers($path));
    }

    /** Whether this mapping's path covers the given path: an empty path covers the whole host. */
    public function covers(string $path): bool
    {
        if ($this->path === '') {
            return true;
        }

        return $path === $this->path || str_starts_with($path, $this->path.'/');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  Builder<TrackedDomain>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
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

    /**
     * The host itself plus every parent domain, so that a mapping on a parent covers subdomains.
     *
     * @return array<int, string>
     */
    private static function candidates(string $host): array
    {
        $labels = explode('.', $host);
        $candidates = [];

        for ($index = 0; $index < count($labels) - 1; $index++) {
            $candidates[] = implode('.', array_slice($labels, $index));
        }

        return $candidates === [] ? [$host] : $candidates;
    }

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
