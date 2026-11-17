{{-- "Op betaald zetten" for one registration: the money came in outside the website (cash, transfer…). --}}
@php($formId = 'betaald-'.$registration->id)

<form method="POST" id="{{ $formId }}" class="mark-paid" action="{{ route('activiteiten.inschrijvingen.paid', [$activity, $registration]) }}"
      data-confirm="{{ $registration->name }} op betaald zetten?"
      data-confirm-details="{{ $formId }}-details"
      data-confirm-button="Op betaald zetten"
      data-confirm-cancel="Terug"
      data-confirm-tone="neutral">
    @csrf
    <button type="submit" class="button button-ghost button-small">Op betaald zetten</button>
</form>

<template id="{{ $formId }}-details">
    <p>
        Zet dit op betaald als je {{ $registration->amountLabel() }} van {{ $registration->name }} buiten de website om
        ontving, bv. cash of via overschrijving. Bij annuleren betaal je dat bedrag zelf terug.
    </p>
    @unless ($registration->confirmation_sent_at)
        <label class="checkbox">
            <input type="hidden" name="send_confirmation" value="0" form="{{ $formId }}">
            <input type="checkbox" name="send_confirmation" value="1" form="{{ $formId }}" checked>
            <span>Stuur {{ $registration->name }} een bevestigingsmail</span>
        </label>
    @endunless
</template>
