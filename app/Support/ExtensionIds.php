<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses the comma separated list of Chrome extension ids. Config files are loaded in alphabetical
 * order, so cors.php cannot read config('extension.ids') yet and has to parse the same variable
 * itself; this keeps both readings of it identical.
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
}
