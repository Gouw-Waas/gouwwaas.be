{{-- Logo upload with preview of the current logo. $logoUrl: current logo or null. --}}
<div class="field">
    <span class="label">Logo <span class="muted">(optioneel)</span></span>

    <div class="logo-field">
        <div class="logo-preview" data-logo-preview>
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="Huidig logo">
            @else
                <span class="muted small">Geen logo</span>
            @endif
        </div>

        <div class="logo-field-inputs">
            <input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-hint @error('logo') logo-error @enderror" @error('logo') aria-invalid="true" @enderror data-logo-input>
            <p class="hint" id="logo-hint">jpg, png of webp, maximaal 2 MB. Bij voorkeur vierkant of liggend, met transparante achtergrond.</p>
            @error('logo')
                <p class="error" id="logo-error">{{ $message }}</p>
            @enderror

            @if ($logoUrl)
                <label class="checkbox">
                    <input type="hidden" name="remove_logo" value="0">
                    <input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))> Logo verwijderen
                </label>
            @endif
        </div>
    </div>
</div>

<script>
    // Preview the chosen file before saving.
    (() => {
        const input = document.querySelector('[data-logo-input]');
        const preview = document.querySelector('[data-logo-preview]');
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (! file) return;
            const img = document.createElement('img');
            img.alt = 'Nieuw logo';
            img.src = URL.createObjectURL(file);
            preview.replaceChildren(img);
        });
    })();
</script>
