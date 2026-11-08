<?php

use App\Models\Integration;
use App\Models\Repository;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;

it('records work pushed to a repository without a customer', function () {
    // Given
    EventFacade::fake();
    $this->travelTo('2026-06-05 10:00:00');
    User::factory()->create(['email' => 'dev@example.com']);
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => ['webhook_secret' => 'secret']]);
    $body = (string) json_encode([
        'push' => ['changes' => [['new' => ['name' => 'main'], 'commits' => [[
            'hash' => 'abc123',
            'message' => 'add vite',
            'date' => '2026-06-05T09:38:30+00:00',
            'author' => ['raw' => 'Dev <dev@example.com>'],
        ]]]]],
        'repository' => ['full_name' => 'acme/new-repo', 'name' => 'New repo'],
    ]);

    // When
    $this->call('POST', route('bitbucket.webhook', $integration), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_EVENT_KEY' => 'repo:push',
        'HTTP_X_HUB_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'secret'),
    ], content: $body)->assertOk();

    // Then
    expect(Repository::sole()->only('integration_id', 'identifier', 'name', 'last_work_at'))->toEqual([
        'integration_id' => $integration->id,
        'identifier' => 'new-repo',
        'name' => 'New repo',
        'last_work_at' => Carbon::parse('2026-06-05 10:00:00'),
    ]);
});
