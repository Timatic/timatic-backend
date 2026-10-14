<?php

use App\Integrations\IntegrationTypeRegistry;

it('reports the required config an integration type is missing', function () {
    config(['acme.client_id' => 'a-client-id', 'acme.client_secret' => null]);

    $registry = new IntegrationTypeRegistry;
    $registry->register('acme', [])->requiresConfig(['acme.client_id', 'acme.client_secret']);

    expect($registry->missingConfig('acme'))->toBe(['acme.client_secret']);
});
