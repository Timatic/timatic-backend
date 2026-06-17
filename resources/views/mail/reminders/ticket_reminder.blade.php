<p>
    {{ trans('messages.tickets_reminder') }}
</p>
<ul>
    @foreach ($customers as $customer)
        <li>
            {{ $customer->hours }} uur @if($customer->minutes) {{ $customer->minutes }} min @endif voor {{ $customer->name }} aan
            @foreach ($customer->tickets as $ticketNumber => $link)
                <a href="{{ $link }}">{{ $ticketNumber }}</a>@if (! $loop->last), @endif
            @endforeach
        </li>
    @endforeach
</ul>
