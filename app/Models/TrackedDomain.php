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
 * @property string $domain
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
 *
 * @method static TrackedDomain|Builder<TrackedDomain> active()
 */
class TrackedDomain extends Model
{
    /** @use HasFactory<TrackedDomainFactory> */
    use HasFactory;

    protected $fillable = [
        'domain',
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
        $host = strtolower(trim($hostOrUrl));

        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }

        $host = ltrim(Str::before(Str::before($host, '/'), ':'), '.');

        return Str::startsWith($host, 'www.') ? Str::after($host, 'www.') : $host;
    }

    /**
     * The tracked domain that covers a host, or null when the host is not opted in. A mapping also
     * covers its subdomains, and the most specific mapping wins: with both "acme.com" and
     * "jira.acme.com" stored, "jira.acme.com" resolves to the latter.
     */
    public static function matching(string $hostOrUrl): ?self
    {
        $host = self::normalise($hostOrUrl);

        if ($host === '') {
            return null;
        }

        return self::query()
            ->active()
            ->whereIn('domain', self::candidates($host))
            ->orderByRaw('LENGTH(domain) DESC')
            ->first();
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
