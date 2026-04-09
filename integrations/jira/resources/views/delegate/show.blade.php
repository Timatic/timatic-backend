@extends('layouts.delegate')

@section('subtitle', 'Jira integratie')

@section('content')
    @php $config = $integration->config ?? []; @endphp

    @if($expired || request('error') === 'link_expired')
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
            Deze koppellink is verlopen. Vraag een nieuwe link aan bij uw Timatic consultant.
        </div>

    @elseif(request('connected') || (filled($config['access_token'] ?? null) && filled($config['cloud_id'] ?? null)))
        <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
            Jira succesvol verbonden.
        </div>

    @else
        <p class="text-sm text-gray-600 mb-6">
            Klik op de knop hieronder om Jira te verbinden met Timatic.
            U wordt doorgestuurd naar Jira om toestemming te verlenen.
        </p>
        <a href="{{ route('jira.delegate.oauth-redirect', $integration->share_token) }}"
           class="block w-full text-center bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
            Verbinden met Jira
        </a>
    @endif
@endsection
