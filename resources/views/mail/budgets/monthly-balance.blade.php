@component('mail::message')
# Hi {{ $user->given_name, $user->family_name }}

This is the monthly balance overview of the budgets assigned to you as {{ $role }}.
Please take a look at budgets with high usage and those that are about to expire.

@foreach ($budgetGroups as $frequency => $budgets)
<table style="border-collapse: collapse;">
    <thead>
    <tr>
        <th>{{ ucfirst($frequency ?: 'one time') }} Budget</th>
        <th style="text-align: right;">Remaining Hours</th>
        <th style="text-align: right;">Used Percentage</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($budgets as $budget)
        <tr>
            <td style="padding: 5px 5px 5px 0;">
                <a href="{{ $budget['url'] }}">{{ $budget['title'] }}</a><br/>{{ $budget['customer'] }}
            </td>
            @if($budget['expired'])
                <td colspan="2" style="text-align: right;"><strong>expired at: {{$budget['endedAt']}}</strong><br/></td>
            @else
            <td style="padding: 5px;text-align: right;">{{ $budget['remainingHours'] }}u</td>
            <td style="padding: 5px 0 5px 5px; text-align: right; font-weight:{{$budget['usedPercentage'] > 100 ? 'bold': 'normal'}}">
                @if($budget['usedPercentage'] !== null)
                    {{ $budget['usedPercentage'] }}%
                @endif
            </td>
            @endif
        </tr>
        @if($budget['expiring'] && !$budget['expired'])
        <tr>
            <td colspan="3" style="padding-bottom: 5px; text-align: right;">
                <strong>about to expire at: {{$budget['endedAt']}}</strong><br/>
            </td>
        </tr>
        @endif
    @endforeach
    </tbody>
</table>
<br>
<br>
@endforeach
@endcomponent
