{{-- One function on /wie-is-wie. $variant: bureau | adjunct | district; $district only for districts;
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

    {{-- From 3 people on, side by side with smaller photos, so a big team doesn't stretch the whole row. --}}
    <ul @class(['people', 'people-compact' => $role->members->count() >= 3])>
        @forelse ($role->members as $member)
            <li class="person">
                @if ($member->photoUrl())
                    <img src="{{ $member->photoUrl() }}" alt="" class="avatar" width="64" height="64" loading="lazy">
                @else
                    <span class="avatar avatar-initials" aria-hidden="true">{{ $member->initials() }}</span>
                @endif
                <div class="person-info">
                    <p class="person-name">{{ $member->fullName() }}</p>
                    @if ($member->totem)
                        <p class="person-totem">{{ $member->totem }}</p>
                    @endif
                    @if ($member->title)
                        <p class="person-title">{{ $member->title }}</p>
                    @endif
                </div>
            </li>
        @empty
            {{-- Nobody fills this function yet. --}}
            <li class="person person-wanted">
                <img src="{{ asset('images/gezocht.png') }}" alt="" class="avatar avatar-wanted" width="64" height="64" loading="lazy">
                <div class="person-info">
                    <p class="person-name">Gezocht!</p>
                </div>
            </li>
        @endforelse
    </ul>

    @if ($links = $role->contactLinks())
        <ul class="contact-links">
            @foreach ($links as $link)
                <li>
                    <a href="{{ $link['url'] }}" @if ($link['type'] !== 'email') rel="noopener" @endif>
                        @include('wie-is-wie._icon', ['type' => $link['type']])
                        <span>{{ $link['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @isset($district)
        <p class="role-card-footer">
            <a href="{{ route('districten') }}#district-{{ $role->key }}">{{ count($district['groups']) }} groepen bekijken</a>
        </p>
    @endisset
</article>
