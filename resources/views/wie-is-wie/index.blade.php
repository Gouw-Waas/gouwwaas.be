@extends('layouts.app')

@section('title', 'Wie is wie')

@section('content')
    <section class="page-header">
        <div class="container">
            <h1>Wie is wie</h1>
            <p class="lead">En hoe zit dat dan juist in elkaar?</p>
        </div>
    </section>

    <section class="container section">
        <div class="section-heading">
            <h2>Het gouwbureau</h2>
            <p>
                De dagelijkse leiding van de gouw ligt bij het gouwbureau. Dit bureau bestaat uit drie functies.
            </p>
        </div>

        <div class="roles">
            @foreach ($bureau as $role)
                @include('wie-is-wie._role', ['role' => $role, 'variant' => 'bureau'])
            @endforeach
        </div>
    </section>

    <section class="section section-tinted">
        <div class="container">
            <div class="section-heading-row">
                <div class="section-heading">
                    <h2>Adjuncten</h2>
                    <p>
                        Uiteraard kan onze gouw niet werken zonder nog een hele hoop andere medewerkers, elk met hun eigen
                        specialiteit. Momenteel hebben we voor de volgende thema's adjuncten:
                    </p>
                </div>
                @can('admin')
                    <a href="{{ route('wie-is-wie.create') }}" class="button">Adjunct toevoegen</a>
                @endcan
            </div>

            <div class="role-grid">
                @forelse ($adjuncten as $role)
                    @include('wie-is-wie._role', ['role' => $role, 'variant' => 'adjunct'])
                @empty
                    <p class="muted">Er zijn nog geen adjuncten.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="container section">
        <div class="section-heading">
            <h2>Districtsploegen</h2>
            <p>
                Tot slot maken ook de districtsploegen deel uit van de gouw. Zij komen op onze samenkomsten hun
                districten vertegenwoordigen en zorgen dat alle belangrijke info tot bij de groepen geraakt.
            </p>
        </div>

        <div class="role-grid role-grid-wide">
            @foreach ($districtRoles as $role)
                @include('wie-is-wie._role', ['role' => $role, 'variant' => 'district', 'district' => $districts[$role->key]])
            @endforeach
        </div>
    </section>

    <script>
        // Long descriptions are cut off after a few lines, with a button to read the rest.
        // Without JavaScript the full text is shown.
        (() => {
            const blocks = document.querySelectorAll('[data-clamp]');

            const update = () => blocks.forEach((block) => {
                if (block.classList.contains('is-open')) return;
                const text = block.querySelector('.role-card-text');
                block.classList.add('is-clamped');
                const overflows = text.scrollHeight > text.clientHeight + 2;
                block.classList.toggle('is-clamped', overflows);

                let button = block.querySelector('[data-clamp-toggle]');
                if (overflows && ! button) {
                    button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'link-button small';
                    button.dataset.clampToggle = '';
                    button.textContent = 'Lees meer';
                    button.setAttribute('aria-expanded', 'false');
                    block.append(button);
                }
                if (button) button.hidden = ! overflows;
            });

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-clamp-toggle]');
                if (! button) return;
                const block = button.closest('[data-clamp]');
                const open = block.classList.toggle('is-open');
                block.classList.toggle('is-clamped', ! open);
                button.textContent = open ? 'Minder tonen' : 'Lees meer';
                button.setAttribute('aria-expanded', String(open));
            });

            update();
            addEventListener('resize', update);
        })();
    </script>
@endsection
