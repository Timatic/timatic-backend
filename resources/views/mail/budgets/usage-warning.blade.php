@component('mail::message')
The budget {{ $budgetTitle }} from {{ $customerName }} has used up more than {{$percentageUsed}}% of its hours.

@component('mail::button', ['url' => $budgetUrl, 'color' => 'success'])
    Open budget in Time
@endcomponent

You are registered as the service manager for this client in the CRM or as supervisor for this budget.
@if ($percentageUsed >= 100)
The negative balance will not be invoiced automatically.
@endif
@endcomponent
