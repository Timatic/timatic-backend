<?php

use App\Models\Integration;
use Illuminate\Support\Facades\Bus;
use Timatic\GitHub\Jobs\ProcessWebhookJob;
use Timatic\GitHub\Models\RepositoryMapping;

it('dispatches the delivery with the mapping of the delivering repository', function () {
    Bus::fake();
    config()->set('github.webhook_secret', 'webhook-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installation_id' => 4242]]);
    $mapping = RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
    ]);
    $body = json_encode(['action' => 'opened', 'repository' => ['full_name' => 'acme/api']]);

    $this->call('POST', route('github.webhook', $integration), [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'pull_request',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Bus::assertDispatched(ProcessWebhookJob::class, function (ProcessWebhookJob $job) use ($mapping) {
        return (new ReflectionProperty($job, 'mapping'))->getValue($job)?->id === $mapping->id
            && (new ReflectionProperty($job, 'eventName'))->getValue($job) === 'pull_request'
            && (new ReflectionProperty($job, 'action'))->getValue($job) === 'opened';
    });
});

it('dispatches without a mapping when the repository is not linked', function () {
    Bus::fake();
    config()->set('github.webhook_secret', 'webhook-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installation_id' => 4242]]);
    $body = json_encode(['repository' => ['full_name' => 'acme/unmapped']]);

    $this->call('POST', route('github.webhook', $integration), [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Bus::assertDispatched(ProcessWebhookJob::class, function (ProcessWebhookJob $job) {
        return (new ReflectionProperty($job, 'mapping'))->getValue($job) === null;
    });
});

it('ignores an archived mapping', function () {
    Bus::fake();
    config()->set('github.webhook_secret', 'webhook-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => ['installation_id' => 4242]]);
    RepositoryMapping::create([
        'integration_id' => $integration->id,
        'installation_id' => 4242,
        'owner_login' => 'acme',
        'repository_name' => 'api',
        'repository_full_name' => 'acme/api',
        'is_archived' => true,
    ]);
    $body = json_encode(['repository' => ['full_name' => 'acme/api']]);

    $this->call('POST', route('github.webhook', $integration), [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Bus::assertDispatched(ProcessWebhookJob::class, function (ProcessWebhookJob $job) {
        return (new ReflectionProperty($job, 'mapping'))->getValue($job) === null;
    });
});

it('rejects a delivery with an invalid signature', function () {
    Bus::fake();
    config()->set('github.webhook_secret', 'webhook-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $body = json_encode(['repository' => ['full_name' => 'acme/api']]);

    $this->call('POST', route('github.webhook', $integration), [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertForbidden();

    Bus::assertNothingDispatched();
});

it('rejects a delivery when no webhook secret is configured', function () {
    Bus::fake();
    config()->set('github.webhook_secret', null);
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $body = json_encode(['repository' => ['full_name' => 'acme/api']]);

    $this->call('POST', route('github.webhook', $integration), [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, ''),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertForbidden();

    Bus::assertNothingDispatched();
});

it('acknowledges a ping without dispatching', function () {
    Bus::fake();
    config()->set('github.webhook_secret', 'webhook-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $body = json_encode(['zen' => 'Keep it logically awesome.', 'hook_id' => 1]);

    $this->call('POST', route('github.webhook', $integration), [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'ping',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    Bus::assertNothingDispatched();
});
