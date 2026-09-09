<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\InstallationTokenService;
use Timatic\GitHub\Requests\CreateInstallationTokenRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('mints an installation token for the installation', function () {
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privateKey);
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', $privateKey);
    MockClient::global([
        CreateInstallationTokenRequest::class => MockResponse::make([
            'token' => 'ghs_installation_token',
            'expires_at' => '2026-09-09T15:00:00Z',
        ], 201),
    ]);

    $token = app(InstallationTokenService::class)->token(4242);

    expect($token)->toBe('ghs_installation_token');
});

it('mints the token once and serves the cached value afterwards', function () {
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privateKey);
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', $privateKey);
    $mockClient = MockClient::global([
        CreateInstallationTokenRequest::class => MockResponse::make([
            'token' => 'ghs_installation_token',
            'expires_at' => '2026-09-09T15:00:00Z',
        ], 201),
    ]);

    app(InstallationTokenService::class)->token(4242);
    app(InstallationTokenService::class)->token(4242);

    $mockClient->assertSentCount(1);
});

it('signs the app jwt with the app id as issuer', function () {
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privateKey);
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', $privateKey);

    $jwt = app(InstallationTokenService::class)->appJwt();

    $payload = (array) JWT::decode($jwt, new Key(openssl_pkey_get_details(openssl_pkey_get_private($privateKey))['key'], 'RS256'));
    expect($payload['iss'])->toBe('123456')
        ->and($payload['exp'] - $payload['iat'])->toBeLessThanOrEqual(600);
});

it('accepts a private key stored with escaped newlines', function () {
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privateKey);
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', str_replace("\n", '\n', $privateKey));

    $jwt = app(InstallationTokenService::class)->appJwt();

    expect($jwt)->toBeString();
});

it('fails when the private key is not configured', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', null);

    app(InstallationTokenService::class)->appJwt();
})->throws(GitHubException::class, 'GitHub app private key is not configured.');

it('fails when minting the installation token is rejected', function () {
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privateKey);
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', $privateKey);
    MockClient::global([
        CreateInstallationTokenRequest::class => MockResponse::make(['message' => 'Bad credentials'], 401),
    ]);

    app(InstallationTokenService::class)->token(4242);
})->throws(GitHubException::class, 'Minting an installation token for installation 4242 failed with status 401.');

it('fails when the private key is not a usable pem', function () {
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'not-a-pem');

    app(InstallationTokenService::class)->appJwt();
})->throws(GitHubException::class, 'Signing the GitHub app jwt failed');
