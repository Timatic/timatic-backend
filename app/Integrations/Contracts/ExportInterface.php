<?php

namespace App\Integrations\Contracts;

interface ExportInterface
{
    public function export(string $filePath): void;
}
