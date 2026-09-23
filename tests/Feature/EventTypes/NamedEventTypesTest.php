<?php

use App\Models\EventType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives every seeded event type a name to show in the interface', function () {
    $eventTypes = EventType::query()->pluck('name', 'id');

    expect($eventTypes)->not->toBeEmpty()
        ->and($eventTypes->filter(fn (?string $name): bool => $name === null))->toBeEmpty();
});
