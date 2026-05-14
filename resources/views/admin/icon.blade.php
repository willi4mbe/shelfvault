@props([
    'name',
    'class' => '',
])

@php
    $svgClass = trim('admin-icon '.$class);
@endphp

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="{{ $svgClass }}"
    aria-hidden="true"
>
    @switch($name)
        @case('home')
            <path d="M3 11.5 12 4l9 7.5"></path>
            <path d="M5.5 10.5V20h13V10.5"></path>
            <path d="M9.5 20v-5h5v5"></path>
            @break
        @case('external_link')
            <path d="M14 5h5v5"></path>
            <path d="M10 14 19 5"></path>
            <path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4"></path>
            @break
        @case('dashboard')
            <path d="M4 13h6V4H4z"></path>
            <path d="M14 20h6v-9h-6z"></path>
            <path d="M14 10h6V4h-6z"></path>
            <path d="M4 20h6v-5H4z"></path>
            @break
        @case('collection')
            <path d="M5 6.5h14"></path>
            <path d="M6 5.5v13"></path>
            <path d="M18 5.5v13"></path>
            <path d="M8 8.5h8"></path>
            <path d="M8 12h8"></path>
            <path d="M8 15.5h5"></path>
            @break
        @case('films')
            <path d="M5 6h14v12H5z"></path>
            <path d="M9 6v12"></path>
            <path d="M15 6v12"></path>
            <path d="M5 10h14"></path>
            @break
        @case('video_games')
            <path d="M7 8h10a3 3 0 0 1 3 3v4a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-4a3 3 0 0 1 3-3Z"></path>
            <path d="M9 12h3"></path>
            <path d="M10.5 10.5v3"></path>
            <path d="M15.5 11.5h.01"></path>
            <path d="M17 13h.01"></path>
            @break
        @case('board_games')
            <path d="M5 5.5h14v14H5z"></path>
            <path d="M9 5.5v14"></path>
            <path d="M15 5.5v14"></path>
            <path d="M5 9.5h14"></path>
            <path d="M5 15.5h14"></path>
            @break
        @case('loans')
            <path d="M7 7h10"></path>
            <path d="M7 7v10"></path>
            <path d="M7 17h10"></path>
            <path d="M17 7v10"></path>
            <path d="M10 10.5h4a1.5 1.5 0 0 1 0 3h-2a1.5 1.5 0 0 0 0 3h4"></path>
            @break
        @case('settings')
            <path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"></path>
            <path d="M19 12a7 7 0 0 0-.1-1l2-1.2-2-3.4-2.2.7a7.2 7.2 0 0 0-1.7-1L14.7 3h-5.4l-.4 2.1a7.2 7.2 0 0 0-1.7 1l-2.2-.7-2 3.4 2 1.2A7 7 0 0 0 5 12c0 .4.03.7.1 1l-2 1.2 2 3.4 2.2-.7a7.2 7.2 0 0 0 1.7 1l.4 2.1h5.4l.4-2.1a7.2 7.2 0 0 0 1.7-1l2.2.7 2-3.4-2-1.2c.07-.3.1-.6.1-1Z"></path>
            @break
        @case('drag')
            <path d="M9 7h.01"></path>
            <path d="M9 12h.01"></path>
            <path d="M9 17h.01"></path>
            <path d="M15 7h.01"></path>
            <path d="M15 12h.01"></path>
            <path d="M15 17h.01"></path>
            @break
        @case('logout')
            <path d="M10 17H6.5A2.5 2.5 0 0 1 4 14.5v-5A2.5 2.5 0 0 1 6.5 7H10"></path>
            <path d="M14 9l3 3-3 3"></path>
            <path d="M17 12H9"></path>
            @break
        @case('edit')
            <path d="M5 19h4"></path>
            <path d="M14.5 5.5 18.5 9.5 9 19H5v-4z"></path>
            <path d="M13 7l4 4"></path>
            @break
        @case('trash')
            <path d="M4.5 7h15"></path>
            <path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"></path>
            <path d="M7 7l.8 11.5A1.5 1.5 0 0 0 9.3 20h5.4a1.5 1.5 0 0 0 1.5-1.5L17 7"></path>
            <path d="M10 10.5v5"></path>
            <path d="M14 10.5v5"></path>
            @break
        @case('total_items')
            <path d="M4.5 7.5 12 4l7.5 3.5-7.5 3.5z"></path>
            <path d="M4.5 7.5V16l7.5 3.5 7.5-3.5V7.5"></path>
            <path d="M12 11v8.5"></path>
            @break
        @case('minimize')
            <path d="M7 12h10"></path>
            @break
        @case('expand')
            <path d="M7 9h4V5"></path>
            <path d="M13 5h4v4"></path>
            <path d="M17 15v4h-4"></path>
            <path d="M11 19H7v-4"></path>
            @break
        @case('recent_additions')
            <path d="M12 5v14"></path>
            <path d="M5 12h14"></path>
            <path d="M18 5v4"></path>
            <path d="M16 7h4"></path>
            @break
        @case('overview')
            <path d="M4.5 19.5V6.5a2 2 0 0 1 2-2h3"></path>
            <path d="M9.5 19.5v-8h10v8"></path>
            <path d="M13 4.5h6a2 2 0 0 1 2 2v13"></path>
            @break
        @case('sync')
            <path d="M6 12a6 6 0 0 1 10.8-3.6"></path>
            <path d="M16.8 5.2V8h-2.8"></path>
            <path d="M18 12a6 6 0 0 1-10.8 3.6"></path>
            <path d="M7.2 18.8V16h2.8"></path>
            @break
        @case('coverage')
            <path d="M4 12a8 8 0 1 0 8-8"></path>
            <path d="M12 4v8l5 3"></path>
            @break
        @case('setup')
            <path d="M4.5 12h6.5l1.5-3 2.5 6 1.5-3H19.5"></path>
            @break
        @case('auth')
            <path d="M7 11V8.5a5 5 0 0 1 10 0V11"></path>
            <path d="M6 11h12v8H6z"></path>
            <path d="M12 14v2"></path>
            @break
        @case('modules')
            <path d="M5 7.5 12 4l7 3.5-7 3.5z"></path>
            <path d="M5 12l7 3.5 7-3.5"></path>
            <path d="M5 16.5l7 3.5 7-3.5"></path>
            @break
        @case('admin')
            <path d="M12 3.5 4.5 7v6.5c0 4.5 3.2 7.4 7.5 9 4.3-1.6 7.5-4.5 7.5-9V7z"></path>
            <path d="M12 8v6"></path>
            <path d="M9 11h6"></path>
            @break
        @case('locale')
            <path d="M12 20a8 8 0 1 0-8-8"></path>
            <path d="M12 4a11 11 0 0 0 0 16"></path>
            <path d="M12 4a11 11 0 0 1 0 16"></path>
            @break
        @case('catalog')
            <path d="M6 5.5h12v13H6z"></path>
            <path d="M9 5.5v13"></path>
            <path d="M15 5.5v13"></path>
            <path d="M6 10h12"></path>
            @break
        @default
            <circle cx="12" cy="12" r="9"></circle>
    @endswitch
</svg>
