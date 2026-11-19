<?php

namespace App\Support;

/**
 * The tabs of the main menu, in the header and in the side menu (burger) on narrow screens.
 * Paths rather than route names, so the error pages can use them too.
 */
class MainMenu
{
    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public static function items(): array
    {
        return array_map(fn (array $item) => [
            'label' => $item[0],
            'url' => url($item[1]),
            'active' => request()->is(...$item[2]),
        ], [
            ['Home', '/', ['/']],
            ['Districten', '/districten', ['districten', 'districten/*']],
            ['Wie is wie', '/wie-is-wie', ['wie-is-wie']],
            ['Activiteiten', '/activiteiten', ['activiteiten', 'activiteiten/*']],
        ]);
    }
}
