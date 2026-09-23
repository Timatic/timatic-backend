@extends('layouts.consent')

@section('subtitle', __('jira::jira.consent.subtitle'))

@section('content')
    <x-consent.intro>
        {{ __('jira::jira.consent.connect_intro') }}
    </x-consent.intro>

    <x-consent.button :href="route('jira.delegate.oauth-redirect', $integration->share_token)">
        {{ __('jira::jira.consent.connect_action') }}
    </x-consent.button>
@endsection
