{{-- The people of a function ($role), or "Gezocht!" when there are none. Used on /wie-is-wie and on the map. --}}
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
