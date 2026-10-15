<?php

use App\Events\EventCreated;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Timatic\GoogleCalendar\ServiceProvider;

uses(RefreshDatabase::class);

it('replaces the instance id of synced recurring events with their sequence id', function () {
    Illuminate\Support\Facades\Event::fake([EventCreated::class]);
    $instance = Event::factory()->create(['source_id' => ServiceProvider::SOURCE_ID, 'external_id' => 'weekly-sync_20261015T090000Z']);
    $allDayInstance = Event::factory()->create(['source_id' => ServiceProvider::SOURCE_ID, 'external_id' => 'holiday_20261015']);
    $oneOff = Event::factory()->create(['source_id' => ServiceProvider::SOURCE_ID, 'external_id' => 'one-off']);
    $otherSource = Event::factory()->create(['source_id' => Source::factory(), 'external_id' => 'weekly-sync_20261015T090000Z']);

    $migration = require base_path('integrations/google-calendar/database/migrations/2026_10_15_000000_store_sequence_id_as_google_calendar_event_external_id.php');
    $migration->up();

    expect($instance->fresh()->external_id)->toBe('weekly-sync')
        ->and($allDayInstance->fresh()->external_id)->toBe('holiday')
        ->and($oneOff->fresh()->external_id)->toBe('one-off')
        ->and($otherSource->fresh()->external_id)->toBe('weekly-sync_20261015T090000Z');
});
