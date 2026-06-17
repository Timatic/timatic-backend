<style>
    body, td, th { font-family: Arial; color:#202020; font-size: 14px; }
    table, td, th { font-family: Arial; color:#202020; border: 1px solid #ccc; border-collapse:collapse; padding: 5px; }
    th { background-color: #ddd; text-align: left; }
    th.right, td.right { text-align: right; }
</style>

Hierbij een overzicht van alle overuren van de periode {{ $previousMonth->month }}-{{ $previousMonth->year }}
<br/><br/>

<table>
    <tr>
        <th>Engineer</th>
        <th class="right">Percentage</th>
        <th>Uren</th>
    </tr>
    @foreach($overtimes as $email => $percentages)
        <tr>
            <td>
                <strong>
                    @if($employeesByEmail->has($email))
                        {{ $employeesByEmail->get($email)->businessEmail }}
                    @else
                        {{ $email }}
                    @endif
                </strong>
            </td>
            <td></td>
            <td></td>
        </tr>
        @foreach($percentages as $percentage => $minutes)
            <tr>
                <td></td>
                <td class="right">{{ $percentage }}%</td>
                <td>{{ round($minutes/60, 2) }}u</td>
            </tr>
        @endforeach
    @endforeach
</table>
