<style>
    body, td, th { font-family: Arial; color:#202020; font-size: 14px; }
    table, td, th { font-family: Arial; color:#202020; border: 1px solid #ccc; border-collapse:collapse; padding: 5px; }
    th { background-color: #ddd; text-align: left; }
    th.right, td.right { text-align: right; }
</style>

Beste {{ $name }},
<br/><br/>
Hierbij de totalen (per percentage) van jouw persoonlijke overuren in de periode {{ $previousMonth->month }}-{{ $previousMonth->year }}
<br/><br/>
<table>
    <tr>
        <th class="right">Percentage</th>
        <th>Uren</th>
    </tr>
    @foreach($percentages as $percentage => $minutes)
        <tr>
            <td class="right">{{ $percentage }}%</td>
            <td>{{ round($minutes/60, 2) }}u</td>
        </tr>
    @endforeach
</table>
