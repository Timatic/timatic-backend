<?php

use App\Jobs\RebuildUserDay;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('creating an event dispatches a rebuild for its day', function () {
    Queue::fake();
    $user = User::factory()->create();

    Event::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam'),
    ]);

    Queue::assertPushed(RebuildUserDay::class, fn (RebuildUserDay $job) => $job->userId === $user->id && $job->date === '2026-07-16');
    Queue::assertPushed(RebuildUserDay::class, 1);
});

test('an event spanning midnight dispatches a rebuild for both days', function () {
    Queue::fake();
    $user = User::factory()->create();

    Event::factory()->create([
        'user_id' => $user->id,
        'started_at' => Carbon::parse('2026-07-16 23:50', 'Europe/Amsterdam'),
        'ended_at' => Carbon::parse('2026-07-17 00:30', 'Europe/Amsterdam'),
    ]);

    Queue::assertPushed(RebuildUserDay::class, 2);
    Queue::assertPushed(RebuildUserDay::class, fn (RebuildUserDay $job) => $job->date === '2026-07-16');
    Queue::assertPushed(RebuildUserDay::class, fn (RebuildUserDay $job) => $job->date === '2026-07-17');
});
