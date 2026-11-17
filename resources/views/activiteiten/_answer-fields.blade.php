{{-- The activity's own questions (activity_fields). $optional: nothing required (organisers adding someone by hand). --}}
@foreach ($fields as $field)
    @php($required = $field->required && ! ($optional ?? false))
    @php($name = "answers[{$field->id}]")
    @php($key = "answers.{$field->id}")
    @php($id = "field-{$field->id}")

    <div class="field">
        @if ($field->type === 'checkbox')
            <label class="checkbox">
                <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked(old($key)) @required($required) @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
                <span>{{ $field->label }} @if ($required)<span class="required" aria-hidden="true">*</span>@endif</span>
            </label>
        @else
            <label for="{{ $id }}">{{ $field->label }} @if ($required)<span class="required" aria-hidden="true">*</span>@endif</label>

            @if ($field->type === 'textarea')
                <textarea id="{{ $id }}" name="{{ $name }}" rows="4" maxlength="5000" @required($required) @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>{{ old($key) }}</textarea>
            @elseif ($field->type === 'select')
                <select id="{{ $id }}" name="{{ $name }}" @required($required) @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
                    <option value="">Maak een keuze</option>
                    @foreach ($field->options ?? [] as $option)
                        <option value="{{ $option }}" @selected(old($key) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @else
                <input id="{{ $id }}" type="{{ $field->type }}" name="{{ $name }}" value="{{ old($key) }}"
                       @if ($field->type === 'number') step="any" inputmode="decimal" @endif
                       @required($required) @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
            @endif
        @endif

        @error($key)
            <p class="error" id="{{ $id }}-error">{{ $message }}</p>
        @enderror
    </div>
@endforeach
