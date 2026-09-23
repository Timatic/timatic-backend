@extends('layouts.consent')

@section('subtitle', __('bitbucket::bitbucket.consent.subtitle'))

@section('content')
    <x-consent.intro>
        {{ __('bitbucket::bitbucket.consent.connect_intro', ['tenant' => config('timatic.tenant_name')]) }}
    </x-consent.intro>

    <x-consent.button :href="route('bitbucket.delegate.oauth-redirect', $integration->share_token)">
        {{ __('bitbucket::bitbucket.consent.connect_action') }}
    </x-consent.button>
@endsection
