@extends('layouts.consent')

@section('subtitle', __('bitbucket::bitbucket.consent.subtitle'))

@section('content')
    <x-consent.notice type="success">
        {{ $justInstalled
            ? __('bitbucket::bitbucket.consent.webhook_installed')
            : __('bitbucket::bitbucket.consent.connected') }}
    </x-consent.notice>
@endsection
