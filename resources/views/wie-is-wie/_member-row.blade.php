{{-- One person in the function form. $key is the array key ("__KEY__" in the template); $photoUrl is the current photo. --}}
@php
    // After a validation error where "Foto verwijderen" was clicked, keep showing the default picture.
    if ($member['remove_photo'] ?? false) {
        $photoUrl = null;
    }
@endphp
<li class="field-row member-row" data-member-row>
    <input type="hidden" name="members[{{ $key }}][id]" value="{{ $member['id'] ?? '' }}">

    <div class="member-row-grid">
        {{-- Click the picture to choose a photo; it is cropped to a circle in the browser before upload. --}}
        <div class="member-photo" data-photo>
            <button type="button" class="photo-picker" data-photo-pick aria-label="{{ $photoUrl ? 'Andere foto kiezen' : 'Foto kiezen' }}">
                <img src="{{ $photoUrl ?? asset('images/gezocht.png') }}" alt="" class="avatar avatar-large @unless ($photoUrl) avatar-wanted @endunless" width="96" height="96" data-photo-preview data-default="{{ asset('images/gezocht.png') }}">
                <span class="photo-picker-overlay" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                    {{ $photoUrl ? 'Wijzigen' : 'Foto kiezen' }}
                </span>
            </button>
            <input type="file" name="members[{{ $key }}][photo]" accept="image/*" hidden data-photo-input>
            <input type="hidden" name="members[{{ $key }}][remove_photo]" value="{{ ($member['remove_photo'] ?? false) ? 1 : 0 }}" data-photo-remove>
            <button type="button" class="link-button small" data-photo-clear @unless ($photoUrl) hidden @endunless>Foto verwijderen</button>
            @error("members.{$key}.photo")
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="form-grid">
                <div class="field">
                    <label for="members-{{ $key }}-first_name">Voornaam</label>
                    <input id="members-{{ $key }}-first_name" type="text" name="members[{{ $key }}][first_name]" value="{{ $member['first_name'] ?? '' }}" maxlength="100" required @error("members.{$key}.first_name") aria-invalid="true" @enderror>
                    @error("members.{$key}.first_name")
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="members-{{ $key }}-last_name">Achternaam</label>
                    <input id="members-{{ $key }}-last_name" type="text" name="members[{{ $key }}][last_name]" value="{{ $member['last_name'] ?? '' }}" maxlength="100" required @error("members.{$key}.last_name") aria-invalid="true" @enderror>
                    @error("members.{$key}.last_name")
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="members-{{ $key }}-totem">Totem <span class="muted">(optioneel)</span></label>
                    <input id="members-{{ $key }}-totem" type="text" name="members[{{ $key }}][totem]" value="{{ $member['totem'] ?? '' }}" maxlength="100" @error("members.{$key}.totem") aria-invalid="true" @enderror>
                    @error("members.{$key}.totem")
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="members-{{ $key }}-title">Functietitel <span class="muted">(optioneel)</span></label>
                    <input id="members-{{ $key }}-title" type="text" name="members[{{ $key }}][title]" value="{{ $member['title'] ?? '' }}" maxlength="255" placeholder="bv. Districtscommissaris" @error("members.{$key}.title") aria-invalid="true" @enderror>
                    @error("members.{$key}.title")
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="field-row-footer">
        <span></span>
        <div class="field-row-actions">
            <button type="button" class="icon-button" data-move="up" aria-label="Persoon omhoog">↑</button>
            <button type="button" class="icon-button" data-move="down" aria-label="Persoon omlaag">↓</button>
            <button type="button" class="button button-danger button-small" data-remove>Verwijderen</button>
        </div>
    </div>
</li>
