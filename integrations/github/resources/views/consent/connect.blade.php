@extends('layouts.consent')

@section('subtitle', __('github::github.consent.subtitle'))

@section('content')
    <x-consent.intro>
        {{ __('github::github.consent.connect_intro', ['tenant' => config('timatic.tenant_name')]) }}
    </x-consent.intro>

    <x-consent.button :href="route('github.delegate.oauth-redirect', $integration->share_token)">
        {{ __('github::github.consent.connect_action') }}
    </x-consent.button>
@endsection
