@extends('layouts.delegate')

@section('subtitle', 'Bitbucket integratie')

@section('content')
    @php $config = $integration->config ?? []; @endphp

    @if($expired || request('error') === 'link_expired')
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
            Deze koppellink is verlopen. Vraag een nieuwe link aan bij uw Timatic consultant.
        </div>

    @elseif(request('webhook_installed'))
        <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
            Webhook succesvol geïnstalleerd. De integratie is volledig geconfigureerd.
        </div>

    @elseif(filled($config['webhook_uuid'] ?? null))
        <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
            Bitbucket is verbonden en de webhook is geïnstalleerd.
        </div>

    @elseif(filled($config['access_token'] ?? null))
        @if(request('connected'))
            <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700 mb-4">
                Bitbucket succesvol verbonden. Selecteer nu een workspace om de webhook te installeren.
            </div>
        @endif

        @if(request('error') === 'webhook_failed')
            <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700 mb-4">
                Webhook installatie mislukt. Probeer het opnieuw.
            </div>
        @endif

        @include('bitbucket::delegate._workspace_form')

    @else
        <p class="text-sm text-gray-600 mb-6">
            Klik op de knop hieronder om Bitbucket te verbinden met Timatic voor {{ config('timatic.tenant_name') }}.
            U wordt doorgestuurd naar Bitbucket om toestemming te verlenen.
        </p>
        <a href="{{ route('bitbucket.delegate.oauth-redirect', $integration->share_token) }}"
           class="block w-full text-center bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
            Verbinden met Bitbucket
        </a>
    @endif
@endsection
