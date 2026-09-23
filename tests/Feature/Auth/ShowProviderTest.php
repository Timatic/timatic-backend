<?php

it('names the configured identity provider to a guest', function () {
    config([
        'auth.socialite_driver' => 'azure',
        'services.azure.client_id' => 'a-client-id',
    ]);

    $this->getJson(route('auth.provider'))
        ->assertOk()
        ->assertExactJson(['driver' => 'azure', 'label' => 'Microsoft']);
});

it('refuses to serve a provider that has no credentials', function () {
    config([
        'auth.socialite_driver' => 'google',
        'services.google.client_id' => null,
    ]);

    $this->getJson(route('auth.provider'))->assertStatus(503);
});
