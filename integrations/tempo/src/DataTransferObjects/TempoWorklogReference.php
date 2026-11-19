<?php

namespace Timatic\Tempo\DataTransferObjects;

readonly class TempoWorklogReference
{
    public function __construct(public string $tempoWorklogId) {}
}
