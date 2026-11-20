<?php

declare(strict_types=1);

use App\DataTransferObjects\UnmappedRepository;
use App\Mail\UnmappedRepositoriesDigest;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Timatic\Bitbucket\Models\RepositoryMapping as BitbucketRepositoryMapping;

it('mails every admin one digest of the repositories without a customer', function () {
    // Given
    Mail::fake();
    $this->travelTo('2026-06-05 08:00:00');
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->givePermissionTo('integrations.update');
    User::factory()->create(['email' => 'super@example.com'])->assignRole(Role::findOrCreate('super_admin'));
    User::factory()->create(['email' => 'dev@example.com'])->givePermissionTo('integrations.read');
    $bitbucket = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    $github = Integration::create(['title' => 'GitHub', 'type' => 'github', 'config' => []]);
    $shop = Repository::factory()->create(['integration_id' => $bitbucket->id, 'identifier' => 'acme/shop', 'last_work_at' => '2026-06-04 16:12:00']);
    $api = Repository::factory()->create(['integration_id' => $github->id, 'identifier' => 'acme/api', 'last_work_at' => '2026-06-04 16:12:00']);

    // When
    $this->artisan('repositories:unmapped-digest')->assertSuccessful();

    // Then
    $expected = [
        new UnmappedRepository('Bitbucket', 'acme/shop', "https://admin.timatic.test/integrations/{$bitbucket->id}/bitbucket-repositories?search=shop"),
        new UnmappedRepository('GitHub', 'acme/api', "https://admin.timatic.test/integrations/{$github->id}/github-repositories?search=acme%2Fapi"),
    ];
    Mail::assertQueuedCount(2);
    Mail::assertQueued(UnmappedRepositoriesDigest::class, fn (UnmappedRepositoriesDigest $mail): bool => $mail->hasTo('admin@example.com') && $mail->repositories->all() == $expected);
    Mail::assertQueued(UnmappedRepositoriesDigest::class, fn (UnmappedRepositoriesDigest $mail): bool => $mail->hasTo('super@example.com') && $mail->repositories->all() == $expected);
    expect($shop->refresh()->last_notified_at)->toEqual(now())
        ->and($api->refresh()->last_notified_at)->toEqual(now());
});

it('lists the repositories by integration with a link to map each', function () {
    // Given
    $mail = new UnmappedRepositoriesDigest(collect([
        new UnmappedRepository('Bitbucket', 'acme/shop', 'https://localhost/integrations/1/bitbucket-repositories?search=shop'),
    ]));

    // Then
    $mail->assertHasSubject('Werk in 1 repository zonder klant')
        ->assertSeeInOrderInText(['Bitbucket', 'acme/shop'])
        ->assertSeeInHtml('https://localhost/integrations/1/bitbucket-repositories?search=shop');
});

it('does not mention a repository again within a week', function () {
    // Given
    Mail::fake();
    $this->travelTo('2026-06-05 08:00:00');
    User::factory()->create()->givePermissionTo('integrations.update');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    Repository::factory()->create(['integration_id' => $integration->id, 'last_work_at' => '2026-06-04 16:12:00', 'last_notified_at' => '2026-05-30 08:00:00']);

    // When
    $this->artisan('repositories:unmapped-digest')->assertSuccessful();

    // Then
    Mail::assertNothingQueued();
});

it('mentions a repository with new work again after a week', function () {
    // Given
    Mail::fake();
    $this->travelTo('2026-06-05 08:00:00');
    User::factory()->create()->givePermissionTo('integrations.update');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    Repository::factory()->create(['integration_id' => $integration->id, 'last_work_at' => '2026-06-04 16:12:00', 'last_notified_at' => '2026-05-29 08:00:05']);

    // When
    $this->artisan('repositories:unmapped-digest')->assertSuccessful();

    // Then
    Mail::assertQueuedCount(1);
});

it('does not mention an ignored repository', function () {
    // Given
    Mail::fake();
    $this->travelTo('2026-06-05 08:00:00');
    User::factory()->create()->givePermissionTo('integrations.update');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    Repository::factory()->create(['integration_id' => $integration->id, 'last_work_at' => '2026-06-04 16:12:00', 'is_ignored' => true]);

    // When
    $this->artisan('repositories:unmapped-digest')->assertSuccessful();

    // Then
    Mail::assertNothingQueued();
});

it('does not mention a repository mapped since its work was recorded', function () {
    // Given
    Mail::fake();
    $this->travelTo('2026-06-05 08:00:00');
    User::factory()->create()->givePermissionTo('integrations.update');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    Repository::factory()->create(['integration_id' => $integration->id, 'identifier' => 'acme/shop', 'last_work_at' => '2026-06-04 16:12:00']);
    BitbucketRepositoryMapping::create([
        'integration_id' => $integration->id,
        'workspace_slug' => 'acme',
        'repository_slug' => 'shop',
        'repository_name' => 'Shop',
        'customer_id' => Customer::factory()->create()->id,
    ]);

    // When
    $this->artisan('repositories:unmapped-digest')->assertSuccessful();

    // Then
    Mail::assertNothingQueued();
});

it('sends nothing without recent work', function () {
    // Given
    Mail::fake();
    $this->travelTo('2026-06-05 08:00:00');
    User::factory()->create()->givePermissionTo('integrations.update');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    Repository::factory()->create(['integration_id' => $integration->id, 'last_work_at' => '2026-05-20 16:12:00']);

    // When
    $this->artisan('repositories:unmapped-digest')->assertSuccessful();

    // Then
    Mail::assertNothingQueued();
});

it('keeps mentioning a repository that nobody could be mailed about', function () {
    // Given
    Mail::fake();
    $this->travelTo('2026-06-05 08:00:00');
    User::factory()->create()->givePermissionTo('integrations.read');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    $repository = Repository::factory()->create(['integration_id' => $integration->id, 'last_work_at' => '2026-06-04 16:12:00']);

    // When
    $this->artisan('repositories:unmapped-digest')->assertSuccessful();

    // Then
    expect($repository->refresh()->last_notified_at)->toBeNull();
});
