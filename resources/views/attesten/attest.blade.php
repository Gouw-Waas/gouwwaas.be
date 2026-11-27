<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Deelnameattest · {{ $registration->name }}</title>
    {{-- Rendered by dompdf: tables and simple CSS only, no flex or grid. --}}
    <style>
        @page { margin: 22mm 22mm 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; line-height: 1.55; color: #1d1d1b; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; padding: 0; }
        .org-logo { width: 34mm; }
        .org-logo img { width: 30mm; }
        .org { font-size: 9.5pt; color: #555555; padding-top: 2mm; }
        .org strong { font-size: 11pt; color: #1d1d1b; }
        h1 { font-size: 24pt; margin: 30mm 0 12mm; }
        .declaration p { margin: 0 0 5mm; }
        .issued { margin-top: 12mm; }
        .signature { margin-top: 12mm; }
        .signature-names { width: 60%; }
        .signature-names p { margin: 0; }
        .signature-names p.signature-role { color: #555555; font-size: 9.5pt; margin-top: 1mm; }
        .stamp { text-align: right; }
        .stamp img { width: 45mm; }
    </style>
</head>
<body>
    <table>
        <tr>
            @if ($logo)
                <td class="org-logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td class="org">
                <strong>{{ $organisation->name }} – Scouts en Gidsen Vlaanderen</strong><br>
                @if ($organisation->building)
                    {{ $organisation->building }}<br>
                @endif
                {{ $organisation->street }}<br>
                {{ $organisation->city }}<br>
                {{ $organisation->email }}
            </td>
        </tr>
    </table>

    <h1>Deelnameattest</h1>

    <div class="declaration">
        <p>
            {{ $organisation->name }} verklaart dat <strong>{{ $registration->name }}</strong> heeft deelgenomen aan
            <strong>{{ $activity->title }}</strong>@if (filled($organiser ?? null)), georganiseerd door {{ $organiser }},@endif te {{ $activity->location }}, {{ $dateText }}.
        </p>
        <p>Deze activiteit is goed voor <strong>{{ $activity->trainingHoursLabel() }} vormingsuren</strong>.</p>
        @if ($price !== null)
            <p>{!! $price === 0 ? 'De deelname was <strong>gratis</strong>.' : 'Het deelnamegeld bedroeg <strong>'.e($registration->amountLabel()).'</strong>.' !!}</p>
        @endif
    </div>

    <p class="issued">Opgemaakt te Sint-Niklaas op {{ $issuedOn }}.</p>

    <table class="signature">
        <tr>
            <td class="signature-names">
                @foreach ($signers as $signer)
                    <p>{{ $signer }}</p>
                @endforeach
                <p class="signature-role">{{ $signers->count() > 1 ? 'Gouwcommissarissen' : 'Gouwcommissaris' }}, {{ $organisation->name }}</p>
            </td>
            <td class="stamp">
                @if ($stamp)
                    <img src="{{ $stamp }}" alt="Stempel">
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
