@extends('layouts.consent')

@section('subtitle', __('jira::jira.consent.subtitle'))

@section('content')
    <x-consent.notice type="success">
        {{ __('jira::jira.consent.connected') }}
    </x-consent.notice>
@endsection
