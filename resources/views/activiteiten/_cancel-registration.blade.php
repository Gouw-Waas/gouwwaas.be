{{-- "Annuleren" for one registration, with its confirmation dialog (public/js/confirm.js).
     Paid: refund in full or minus administrative costs. The refund inputs live in the dialog but
     belong to the form (form="…"), so they are sent along when confirming. --}}
@php
    $formId = 'annuleer-'.$registration->id;
    $paid = $registration->payment_status === 'paid';
@endphp

<form method="POST" id="{{ $formId }}" action="{{ route('activiteiten.inschrijvingen.destroy', [$activity, $registration]) }}"
      data-confirm="Inschrijving van {{ $registration->name }} annuleren?"
      data-confirm-details="{{ $formId }}-details"
      data-confirm-button="{{ $paid ? 'Annuleren en terugbetalen' : 'Inschrijving annuleren' }}"
      data-confirm-cancel="Terug">
    @csrf
    @method('DELETE')
    <button type="submit" class="button button-danger button-small">Annuleren</button>
</form>

<template id="{{ $formId }}-details">
    @if ($paid)
        <p>{{ $registration->name }} betaalde {{ $registration->amountLabel() }}. Het bedrag wordt via Mollie teruggestort.</p>

        <fieldset class="refund-options" data-refund data-refund-amount="{{ $registration->amount_cents }}">
            <legend class="visually-hidden">Terugbetaling</legend>

            <label class="refund-option">
                <input type="radio" name="refund" value="full" form="{{ $formId }}" checked>
                <span>
                    <strong>Volledig terugbetalen</strong>
                    <span class="muted">{{ $registration->amountLabel() }}</span>
                </span>
            </label>

            <label class="refund-option">
                <input type="radio" name="refund" value="minus_costs" form="{{ $formId }}">
                <span>
                    <strong>Terugbetalen min administratiekosten</strong>
                    <span class="refund-cost">
                        <span>Administratiekosten</span>
                        <span class="input-prefix">
                            <span aria-hidden="true">€</span>
                            <input type="text" name="admin_cost" value="1,00" inputmode="decimal" form="{{ $formId }}" aria-label="Administratiekosten in euro" data-refund-cost>
                        </span>
                    </span>
                </span>
            </label>

            <p class="refund-total">Terug te betalen: <strong data-refund-total>{{ $registration->amountLabel() }}</strong></p>
        </fieldset>

        <p>{{ $registration->name }} krijgt een e-mail dat de inschrijving geannuleerd is en het geld binnen enkele werkdagen teruggestort wordt.</p>
    @elseif ($registration->paymentProcessing())
        <div class="confirm-warning">
            <strong class="confirm-warning-title">De betaling wordt nog verwerkt door de bank.</strong>
            <p>Zolang die niet rond is, kan er niets terugbetaald worden. Probeer later opnieuw.</p>
        </div>
    @elseif ($registration->isConfirmed())
        <p>Ben je zeker? {{ $registration->name }} krijgt een e-mail dat de inschrijving geannuleerd is.</p>
    @else
        <p>{{ $registration->name }} heeft niet betaald en is dus niet ingeschreven. De inschrijving wordt verwijderd, zonder e-mail.</p>
    @endif
</template>
