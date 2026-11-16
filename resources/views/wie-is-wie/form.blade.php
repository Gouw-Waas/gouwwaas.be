@extends('layouts.app')

@php
    $heading = $role->exists ? $role->title.' bewerken' : 'Nieuwe adjunct';

    // After a validation error, show what was submitted; otherwise the stored people.
    $members = old('members', $role->members->map(fn ($member) => [
        'id' => $member->id,
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'totem' => $member->totem,
        'title' => $member->title,
    ])->all());

    $photoUrls = $role->members->mapWithKeys(fn ($member) => [$member->id => $member->photoUrl()]);
@endphp

@section('title', $heading)

@section('content')
    <section class="page-header">
        <div class="container">
            <p>
                <a href="{{ route('wie-is-wie') }}{{ $role->exists ? '#functie-'.$role->id : '' }}" class="back-link">Terug naar wie is wie</a>
            </p>
            <h1>{{ $heading }}</h1>
        </div>
    </section>

    <section class="container section">
        <form method="POST" action="{{ $role->exists ? route('wie-is-wie.update', $role) : route('wie-is-wie.store') }}" enctype="multipart/form-data" class="form activity-form">
            @csrf
            @if ($role->exists)
                @method('PUT')
            @endif

            @if ($errors->any())
                <p class="flash flash-error" role="alert">
                    Niet alles is correct ingevuld. Bekijk de velden hieronder.
                    Nieuw gekozen foto's moet je opnieuw kiezen.
                </p>
            @endif

            <div class="card">
                <h2>Over de functie</h2>

                @if ($role->isAdjunct())
                    <div class="field">
                        <label for="title">Naam van de functie</label>
                        <input id="title" type="text" name="title" value="{{ old('title', $role->title) }}" required maxlength="255" placeholder="bv. Technieken" @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
                        @error('title')
                            <p class="error" id="title-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div class="field">
                    <label for="description">Beschrijving <span class="muted">(optioneel)</span></label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" aria-describedby="description-hint @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror>{{ old('description', $role->description) }}</textarea>
                    <p class="hint" id="description-hint">Laat een lege regel tussen alinea's.</p>
                    @error('description')
                        <p class="error" id="description-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="card">
                <h2>Contact</h2>
                <p class="muted">Alles is optioneel: op de website verschijnt alleen wat je invult.</p>

                <div class="form-grid">
                    @foreach ([
                        'email' => ['E-mailadres', 'email', 'bv. technieken@gouwwaas.be'],
                        'instagram' => ['Instagram', 'text', 'bv. @gouw.waas of een link'],
                        'facebook' => ['Facebook', 'url', 'https://www.facebook.com/…'],
                        'website' => ['Website', 'url', 'https://…'],
                    ] as $name => [$label, $type, $placeholder])
                        <div class="field">
                            <label for="{{ $name }}">{{ $label }}</label>
                            <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $role->{$name}) }}" maxlength="255" placeholder="{{ $placeholder }}" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
                            @error($name)
                                <p class="error" id="{{ $name }}-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <h2>Personen</h2>
                <p class="muted">
                    Wie vult deze functie in? Klik op een foto om ze te wijzigen. Zonder personen toont de website een lege plaats met <strong>Gezocht!</strong>
                </p>

                @error('members')
                    <p class="error">{{ $message }}</p>
                @enderror

                <ol class="field-builder" data-member-list>
                    @foreach ($members as $key => $member)
                        @include('wie-is-wie._member-row', [
                            'key' => $key,
                            'member' => $member,
                            'photoUrl' => $photoUrls->get((int) ($member['id'] ?? 0)),
                        ])
                    @endforeach
                </ol>

                <p class="muted small" data-member-empty @if (count($members) > 0) hidden @endif>Nog niemand: op de website staat hier <strong>Gezocht!</strong></p>

                <button type="button" class="button button-ghost" data-add-member>+ Persoon toevoegen</button>

                <template data-member-template>
                    @include('wie-is-wie._member-row', ['key' => '__KEY__', 'member' => [], 'photoUrl' => null])
                </template>
            </div>

            <div class="form-actions">
                <button type="submit" class="button">{{ $role->exists ? 'Opslaan' : 'Adjunct toevoegen' }}</button>
                <a href="{{ route('wie-is-wie') }}" class="button button-ghost">Annuleren</a>
            </div>
        </form>

        @if ($role->exists && $role->isAdjunct())
            <form method="POST" action="{{ route('wie-is-wie.destroy', $role) }}" class="danger-zone"
                  onsubmit="return confirm({{ Js::from("\"{$role->title}\" en alle gekoppelde personen definitief verwijderen?") }})">
                @csrf
                @method('DELETE')
                <button type="submit" class="button button-danger">Adjunct verwijderen</button>
            </form>
        @endif
    </section>

    {{-- Circle cropper: only the cropped result is uploaded. The chooser is outside the form, so the original is never sent. --}}
    <input type="file" accept="image/*" hidden data-photo-chooser>
    <dialog class="cropper" data-cropper aria-labelledby="cropper-title">
        <form method="dialog" class="cropper-inner">
            <h2 id="cropper-title">Foto bijsnijden</h2>
            <p class="muted small">Sleep de foto op zijn plaats en zoom in of uit.</p>
            <canvas class="cropper-canvas" width="640" height="640" data-cropper-canvas aria-label="Voorbeeld van de bijgesneden foto"></canvas>
            <label class="cropper-zoom">
                <span>Zoom</span>
                <input type="range" min="1" max="4" step="0.01" value="1" data-cropper-zoom>
            </label>
            <div class="form-actions">
                <button type="submit" value="ok" class="button">Gebruiken</button>
                <button type="submit" value="cancel" class="button button-ghost">Annuleren</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const list = document.querySelector('[data-member-list]');
            const template = document.querySelector('[data-member-template]');
            const empty = document.querySelector('[data-member-empty]');
            let counter = Date.now();

            const refresh = () => { empty.hidden = list.children.length > 0; };

            document.querySelector('[data-add-member]').addEventListener('click', () => {
                const html = template.innerHTML.replaceAll('__KEY__', 'n' + (counter++));
                list.insertAdjacentHTML('beforeend', html);
                list.lastElementChild.querySelector('input[type=text]').focus();
                refresh();
            });

            list.addEventListener('click', (event) => {
                const row = event.target.closest('[data-member-row]');
                if (! row) return;

                if (event.target.matches('[data-remove]')) {
                    row.remove();
                    refresh();
                } else if (event.target.matches('[data-move="up"]') && row.previousElementSibling) {
                    row.previousElementSibling.before(row);
                    event.target.focus();
                } else if (event.target.matches('[data-move="down"]') && row.nextElementSibling) {
                    row.nextElementSibling.after(row);
                    event.target.focus();
                }
            });
        })();

        // Photo picker + circle cropper.
        (() => {
            const OUTPUT = 400; // px, the saved photo is OUTPUT x OUTPUT
            const dialog = document.querySelector('[data-cropper]');
            const canvas = document.querySelector('[data-cropper-canvas]');
            const zoomInput = document.querySelector('[data-cropper-zoom]');
            const ctx = canvas.getContext('2d');
            const size = canvas.width;
            const radius = size * 0.44;
            const center = size / 2;

            let image = null;   // the chosen picture
            let target = null;  // the [data-photo] block being edited
            let baseScale = 1;  // scale where the picture just covers the circle
            let zoom = 1;
            let x = 0, y = 0;   // picture centre, in canvas pixels

            const scale = () => baseScale * zoom;

            // Keep the circle fully covered by the picture.
            const clamp = () => {
                const halfW = image.width * scale() / 2;
                const halfH = image.height * scale() / 2;
                x = Math.min(center - radius + halfW, Math.max(center + radius - halfW, x));
                y = Math.min(center - radius + halfH, Math.max(center + radius - halfH, y));
            };

            const draw = () => {
                const w = image.width * scale();
                const h = image.height * scale();
                ctx.clearRect(0, 0, size, size);
                ctx.drawImage(image, x - w / 2, y - h / 2, w, h);

                // Dim everything outside the circle.
                ctx.save();
                ctx.fillStyle = 'rgba(31, 36, 8, .6)';
                ctx.beginPath();
                ctx.rect(0, 0, size, size);
                ctx.arc(center, center, radius, 0, Math.PI * 2, true);
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.arc(center, center, radius, 0, Math.PI * 2);
                ctx.stroke();
                ctx.restore();
            };

            const setZoom = (value, focusX = center, focusY = center) => {
                const next = Math.min(4, Math.max(1, value));
                // Zoom around the focus point, so it stays under the cursor.
                x = focusX - (focusX - x) * next / zoom;
                y = focusY - (focusY - y) * next / zoom;
                zoom = next;
                zoomInput.value = zoom;
                clamp();
                draw();
            };

            const open = (file, block) => {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    image = img;
                    target = block;
                    baseScale = (radius * 2) / Math.min(img.width, img.height);
                    zoom = 1;
                    zoomInput.value = 1;
                    x = center;
                    y = center;
                    draw();
                    dialog.returnValue = '';
                    dialog.showModal();
                };
                img.onerror = () => {
                    URL.revokeObjectURL(url);
                    alert('Deze foto kan niet geopend worden. Kies een jpg, png of webp.');
                };
                img.src = url;
            };

            // Render only the circle to a new image and put it in the hidden file input.
            const save = () => {
                const out = document.createElement('canvas');
                out.width = out.height = OUTPUT;
                const o = out.getContext('2d');
                const factor = OUTPUT / (radius * 2);
                const w = image.width * scale() * factor;
                const h = image.height * scale() * factor;

                o.beginPath();
                o.arc(OUTPUT / 2, OUTPUT / 2, OUTPUT / 2, 0, Math.PI * 2);
                o.clip();
                o.imageSmoothingQuality = 'high';
                o.drawImage(image, (x - (center - radius)) * factor - w / 2, (y - (center - radius)) * factor - h / 2, w, h);

                const block = target;
                out.toBlob((blob) => {
                    // Browsers without WebP encoding (older Safari) return PNG instead.
                    const ext = blob.type === 'image/webp' ? 'webp' : 'png';
                    const file = new File([blob], 'foto.' + ext, { type: blob.type });
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    block.querySelector('[data-photo-input]').files = transfer.files;
                    block.querySelector('[data-photo-remove]').value = '0';

                    const preview = block.querySelector('[data-photo-preview]');
                    preview.src = URL.createObjectURL(blob);
                    preview.classList.remove('avatar-wanted');
                    block.querySelector('[data-photo-clear]').hidden = false;
                }, 'image/webp', 0.9);
            };

            dialog.addEventListener('close', () => {
                if (dialog.returnValue === 'ok' && image) {
                    save();
                }
                if (image) URL.revokeObjectURL(image.src);
                image = null;
            });

            // Dragging
            let drag = null;
            const toCanvas = (event) => {
                const rect = canvas.getBoundingClientRect();
                return [(event.clientX - rect.left) * size / rect.width, (event.clientY - rect.top) * size / rect.height];
            };
            canvas.addEventListener('pointerdown', (event) => {
                canvas.setPointerCapture(event.pointerId);
                const [px, py] = toCanvas(event);
                drag = { px, py, x, y };
            });
            canvas.addEventListener('pointermove', (event) => {
                if (! drag || ! image) return;
                const [px, py] = toCanvas(event);
                x = drag.x + px - drag.px;
                y = drag.y + py - drag.py;
                clamp();
                draw();
            });
            canvas.addEventListener('pointerup', () => { drag = null; });
            canvas.addEventListener('pointercancel', () => { drag = null; });
            canvas.addEventListener('wheel', (event) => {
                if (! image) return;
                event.preventDefault();
                const [px, py] = toCanvas(event);
                setZoom(zoom * (event.deltaY < 0 ? 1.08 : 1 / 1.08), px, py);
            }, { passive: false });
            zoomInput.addEventListener('input', () => image && setZoom(parseFloat(zoomInput.value)));

            // One shared chooser; cancelling it keeps the current photo.
            const chooser = document.querySelector('[data-photo-chooser]');
            let pickingFor = null;
            chooser.addEventListener('change', () => {
                if (chooser.files[0] && pickingFor) open(chooser.files[0], pickingFor);
            });

            // Picker clicks (rows can be added later, so delegate).
            document.querySelector('[data-member-list]').addEventListener('click', (event) => {
                const block = event.target.closest('[data-photo]');
                if (! block) return;

                if (event.target.closest('[data-photo-pick]')) {
                    pickingFor = block;
                    chooser.value = '';
                    chooser.click();
                } else if (event.target.closest('[data-photo-clear]')) {
                    block.querySelector('[data-photo-input]').value = '';
                    block.querySelector('[data-photo-remove]').value = '1';
                    const preview = block.querySelector('[data-photo-preview]');
                    preview.src = preview.dataset.default;
                    preview.classList.add('avatar-wanted');
                    event.target.closest('[data-photo-clear]').hidden = true;
                }
            });
        })();
    </script>
@endsection
