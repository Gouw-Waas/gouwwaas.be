{{-- Small "Akabe" label after a group's name in the lists. --}}
@if ($group->hasAkabe())
    <span class="badge badge-akabe" title="{{ $group->akabeLabel() }}">Akabe</span>
@endif
