{{-- Under a section: which of its functions are listed under "Openstaande functies". $open: those functions. --}}
@if ($open->isNotEmpty())
    <p class="still-open">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <span>
            Nog open:
            @foreach ($open as $role)
                <a href="#functie-{{ $role->id }}">{{ $role->title }}</a>@if (! $loop->last), @endif
            @endforeach
        </span>
    </p>
@endif
