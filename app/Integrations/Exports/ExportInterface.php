<?php

namespace App\Integrations\Exports;

interface ExportInterface
{
    public function export(string $filePath): void;
}
