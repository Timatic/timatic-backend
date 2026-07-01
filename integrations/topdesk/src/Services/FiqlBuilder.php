<?php

namespace Timatic\Topdesk\Services;

use App\Models\Customer;
use App\Models\Integration;

final class FiqlBuilder
{
    private const RECENT_WEEKS = 3;

    public static function ticketKeyPattern(): string
    {
        $pattern = Integration::where('type', 'topdesk')->value('config->ticket_key_pattern');

        return $pattern ?? '[A-Z]+\s?\d+';
    }

    public static function build(
        TopdeskBranchResolver $branchResolver,
        ?Customer $customer,
        ?string $search,
        string $branchField,
    ): ?string {
        $parts = [];

        if ($customer !== null) {
            $branchId = $branchResolver->resolveBranchId($customer);

            if ($branchId === null) {
                return null;
            }

            $parts[] = $branchField.'=='.$branchId;
        } elseif ($search === null || $search === '') {
            return null;
        }

        if ($search !== null && $search !== '') {
            $parts[] = preg_match('/^'.self::ticketKeyPattern().'$/i', $search)
                ? 'number=='.$search
                : 'briefDescription=contains='.$search;
        } else {
            $since = now()->subWeeks(self::RECENT_WEEKS)->toIso8601String();
            $parts[] = "modificationDate=gt='{$since}'";
        }

        return implode(';', $parts);
    }
}
