<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A text block on the home page ("Gouw watte?", "En wat doen jullie dan?"…). Admins add,
 * edit and remove them; they are shown in order of position.
 *
 * The text is light Markdown: a blank line starts a new paragraph, **bold**, *italic*,
 * [link](https://…) and lists. HTML is shown as text, never run.
 */
#[Fillable(['title', 'body'])]
class PageBlock extends Model
{
    /** Replaced by the current number of scouting groups. */
    public const GROUP_COUNT = '{aantal groepen}';

    protected static function booted(): void
    {
        // New blocks go at the end.
        static::creating(function (PageBlock $block) {
            $block->position = (int) static::max('position') + 1;
        });
    }

    /** @param Builder<PageBlock> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function bodyHtml(): string
    {
        $body = str_replace(self::GROUP_COUNT, (string) ScoutGroup::count(), $this->body);

        return Str::markdown($body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
