<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses the comma separated list of Chrome extension ids. cors.php derives allowed origins from
 * it and api_clients.php derives allowed redirect uris from it; this keeps both readings of the
 * variable identical.
 */
final class ExtensionIds
{
    /**
     * @return list<string>
     */
    public static function parse(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Each extension id maps to exactly one redirect uri: https://{id}.chromiumapp.org/
     *
     * @return list<string>
     */
    public static function redirectUris(string $value): array
    {
        return array_map(
            fn (string $extensionId): string => 'https://'.$extensionId.'.chromiumapp.org/',
            self::parse($value),
        );
    }
}
