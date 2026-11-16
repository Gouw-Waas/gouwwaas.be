{{-- One function on /wie-is-wie. $variant: bureau | adjunct | district; $groupCount only for districts;
     $showCategory adds a small label with the level (used under "Openstaande functies"). --}}
<article class="role-card role-card-{{ $variant }}" id="functie-{{ $role->id }}">
    <header class="role-card-header">
        @if ($showCategory ?? false)
            <p class="role-card-category">{{ $role->categoryLabel() }}</p>
        @endif
        <h3>{{ $role->title }}</h3>
        @can('admin')
            <a href="{{ route('wie-is-wie.edit', $role) }}" class="button button-ghost button-small role-card-edit" aria-label="{{ $role->title }} bewerken">Bewerken</a>
        @endcan
    </header>

    @if (filled($role->description))
        <div class="role-card-description" data-clamp>
            <div class="role-card-text">{!! $role->descriptionHtml() !!}</div>
        </div>
    @endif

    @include('wie-is-wie._people', ['role' => $role])

    @include('wie-is-wie._contact', ['role' => $role])

    @isset($groupCount)
        <p class="role-card-footer">
            <a href="{{ route('districten') }}#district-{{ $role->key }}">{{ $groupCount }} {{ $groupCount === 1 ? 'groep' : 'groepen' }} bekijken</a>
        </p>
    @endisset
</article>
