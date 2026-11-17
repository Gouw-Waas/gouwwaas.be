<?php

namespace App\Support;

use App\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * "Zet in je agenda": an iCalendar file (.ics, for Apple, Outlook and most other calendar apps)
 * and links that open Google Calendar or Outlook.com with the activity filled in.
 */
class ActivityCalendar
{
    public function __construct(private readonly Activity $activity) {}

    public static function for(Activity $activity): self
    {
        return new self($activity);
    }

    public function filename(): string
    {
        return "{$this->activity->slug}.ics";
    }

    /**
     * The activity as an iCalendar (RFC 5545) document. The UID stays the same for the activity,
     * so importing it again updates the event instead of adding a second one.
     */
    public function ics(): string
    {
        $activity = $this->activity;
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'gouwwaas.be';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('app.name').'//Activiteiten//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:activity-{$activity->id}@{$host}",
            'DTSTAMP:'.$this->utc(now()),
            'DTSTART:'.$this->utc($this->start()),
            'DTEND:'.$this->utc($this->end()),
            'SUMMARY:'.$this->escape($activity->title),
            'LOCATION:'.$this->escape($activity->location),
            'DESCRIPTION:'.$this->escape($this->description()),
            'URL:'.$this->url(),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    public function googleUrl(): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->activity->title,
            'dates' => $this->utc($this->start()).'/'.$this->utc($this->end()),
            'details' => $this->description(),
            'location' => $this->activity->location,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function outlookUrl(): string
    {
        return 'https://outlook.live.com/calendar/0/action/compose?'.http_build_query([
            'subject' => $this->activity->title,
            'startdt' => $this->start()->utc()->format('Y-m-d\TH:i:s\Z'),
            'enddt' => $this->end()->utc()->format('Y-m-d\TH:i:s\Z'),
            'location' => $this->activity->location,
            'body' => $this->description(),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function icsUrl(): string
    {
        return route('activiteiten.agenda', $this->activity);
    }

    private function start(): Carbon
    {
        return $this->activity->starts_at->copy();
    }

    /**
     * The same end as everywhere else (Activity::endedAt()): its end, or one day after its start.
     */
    private function end(): Carbon
    {
        return $this->activity->endedAt()->copy();
    }

    private function url(): string
    {
        return route('activiteiten.show', $this->activity);
    }

    /**
     * A short description with a link back to the full activity page.
     */
    private function description(): string
    {
        $text = Str::limit(trim((string) preg_replace('/\R{3,}/', "\n\n", $this->activity->description)), 1000);

        return $text."\n\n".$this->url();
    }

    private function utc(Carbon $moment): string
    {
        return $moment->copy()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Text values: backslashes, semicolons and commas escaped, newlines as \n (RFC 5545, 3.3.11).
     */
    private function escape(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);

        return (string) preg_replace('/\R/u', '\\n', $value);
    }

    /**
     * Lines longer than 75 bytes continue on the next line, starting with a space (RFC 5545, 3.1).
     * Cut on character boundaries, so multi-byte characters (é, €…) stay whole.
     */
    private function fold(string $line): string
    {
        $parts = [];

        while (strlen($line) > 75) {
            $limit = $parts ? 74 : 75; // continuation lines start with a space
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
        }

        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
