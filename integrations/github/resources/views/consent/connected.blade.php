@extends('layouts.consent')

@section('subtitle', __('github::github.consent.subtitle'))

@section('content')
    <x-consent.notice type="success" class="mb-4">
        {{ __('github::github.consent.connected', ['accounts' => implode(', ', $accounts)]) }}
    </x-consent.notice>

    <x-consent.intro>
        {{ __('github::github.consent.install_another_intro') }}
    </x-consent.intro>

    <x-consent.button :href="$installUrl" target="_blank" rel="noopener">
        {{ __('github::github.consent.install_another_action') }}
    </x-consent.button>
@endsection
