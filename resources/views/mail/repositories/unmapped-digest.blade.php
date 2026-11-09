@component('mail::message')
{{ __('integrations.unmapped_repositories_digest.intro') }}

@foreach ($repositoriesByIntegration as $integrationTitle => $repositories)
**{{ $integrationTitle }}**

@foreach ($repositories as $repository)
- [{{ $repository->repositoryName }}]({{ $repository->mappingPageUrl }})
@endforeach

@endforeach
{{ __('integrations.unmapped_repositories_digest.outro') }}
@endcomponent
