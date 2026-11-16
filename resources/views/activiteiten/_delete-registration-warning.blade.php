{{-- Content of the confirmation dialog when deleting a registration: deleting never refunds. --}}
<template id="verwijder-inschrijving-{{ $registration->id }}">
    @if ($registration->payment_status === 'paid')
        <div class="confirm-warning">
            <strong class="confirm-warning-title">{{ $registration->name }} heeft {{ $registration->amountLabel() }} betaald.</strong>
            <p>Verwijderen betaalt dit bedrag <strong>niet</strong> terug: het geld blijft bij Mollie staan.</p>
            <p>
                Wil je terugbetalen, doe dat dan zelf in het Mollie-dashboard (betaling {{ $registration->mollie_payment_id }}).
                Mollie kan voor een terugbetaling kosten aanrekenen, en de oorspronkelijke transactiekosten krijg je niet terug.
            </p>
        </div>
    @elseif ($registration->paymentProcessing() || $registration->payment_status === 'open')
        <div class="confirm-warning">
            <strong class="confirm-warning-title">Er loopt nog een betaling van {{ $registration->amountLabel() }}.</strong>
            <p>Komt die alsnog binnen, dan staat het geld bij Mollie zonder dat er nog een inschrijving is. Terugbetalen doe je dan zelf in het Mollie-dashboard.</p>
        </div>
    @endif
    <p>Laat {{ $registration->name }} ({{ $registration->email }}) zelf weten dat de inschrijving geschrapt is: er wordt geen e-mail verstuurd. Dit kan niet ongedaan gemaakt worden.</p>
</template>
