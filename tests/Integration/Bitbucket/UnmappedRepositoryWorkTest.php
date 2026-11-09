<?php

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;
use Timatic\Bitbucket\Models\RepositoryMapping;

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
    expect(Repository::sole()->only('integration_id', 'identifier', 'last_work_at'))->toEqual([
        'integration_id' => $integration->id,
        'identifier' => 'acme/new-repo',
        'last_work_at' => Carbon::parse('2026-06-05 10:00:00'),
    ]);
});

it('records no work for an ignored repository', function () {
    // Given
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => ['webhook_secret' => 'secret']]);
    Repository::factory()->create(['integration_id' => $integration->id, 'identifier' => 'acme/internal', 'is_ignored' => true]);
    $body = (string) json_encode([
        'push' => ['changes' => [['new' => ['name' => 'main'], 'commits' => [[
            'hash' => 'abc123',
            'message' => 'add vite',
            'date' => '2026-06-05T09:38:30+00:00',
            'author' => ['raw' => 'Dev <dev@example.com>'],
        ]]]]],
        'repository' => ['full_name' => 'acme/internal', 'name' => 'Internal'],
    ]);

    // When
    $this->call('POST', route('bitbucket.webhook', $integration), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_EVENT_KEY' => 'repo:push',
        'HTTP_X_HUB_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'secret'),
    ], content: $body)->assertOk();

    // Then
    expect(Repository::sole()->last_work_at)->toBeNull();
});

it('records no work for a repository with a customer', function () {
    // Given
    EventFacade::fake();
    User::factory()->create(['email' => 'dev@example.com']);
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => ['webhook_secret' => 'secret']]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'acme',
        'repository_slug' => 'shop',
        'repository_name' => 'Shop',
        'customer_id' => Customer::factory()->create()->id,
    ]);
    $body = (string) json_encode([
        'push' => ['changes' => [['new' => ['name' => 'main'], 'commits' => [[
            'hash' => 'abc123',
            'message' => 'add vite',
            'date' => '2026-06-05T09:38:30+00:00',
            'author' => ['raw' => 'Dev <dev@example.com>'],
        ]]]]],
        'repository' => ['full_name' => 'acme/shop', 'name' => 'Shop'],
    ]);

    // When
    $this->call('POST', route('bitbucket.webhook', $integration), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_EVENT_KEY' => 'repo:push',
        'HTTP_X_HUB_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'secret'),
    ], content: $body)->assertOk();

    // Then
    expect(Repository::count())->toBe(0);
});

it('never moves the last work back in time', function () {
    // Given
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    $repository = Repository::factory()->create(['integration_id' => $integration->id, 'identifier' => 'acme/shop', 'last_work_at' => '2026-06-05 10:00:00']);

    // When
    Repository::recordWork($integration->id, 'acme/shop', Carbon::parse('2026-06-04 10:00:00'));

    // Then
    expect($repository->refresh()->last_work_at)->toEqual(Carbon::parse('2026-06-05 10:00:00'));
});
