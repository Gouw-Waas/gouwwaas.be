{{-- The configured contact details of a function ($role). --}}
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
