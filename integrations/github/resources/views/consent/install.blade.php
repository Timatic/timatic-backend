@extends('layouts.consent')

@section('subtitle', __('github::github.consent.subtitle'))

@section('content')
    @if ($justConnected)
        <x-consent.notice type="success" class="mb-4">
            {{ __('github::github.consent.just_connected') }}
        </x-consent.notice>
    @endif

    <x-consent.intro>
        {{ __('github::github.consent.install_intro') }}
    </x-consent.intro>

    <x-consent.button :href="$installUrl" target="_blank" rel="noopener">
        {{ __('github::github.consent.install_action') }}
    </x-consent.button>
@endsection
