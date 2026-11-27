<?php

namespace App\Certificates;

/**
 * A pasted or uploaded list of participants for Organisatie → Extern attest: one person per
 * line, name and e-mail address separated by ";" or ",", in either order. An Excel export
 * (byte order mark, header line, CRLF) works as is.
 */
final class ParticipantList
{
    /**
     * @param  list<array{name: string, email: string}>  $participants  in input order, duplicates collapsed
     * @param  list<array{line: int, text: string}>  $invalid  1-based line numbers of the original text
     */
    private function __construct(
        public readonly array $participants,
        public readonly array $invalid,
    ) {}

    public static function parse(string $text): self
    {
        // Excel on Windows saves "CSV" as Windows-1252, not UTF-8.
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $text = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;

        $participants = [];
        $invalid = [];
        $seen = [];
        $first = true;

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = array_values(array_filter(
                array_map(trim(...), str_getcsv($line, str_contains($line, ';') ? ';' : ',', '"', '')),
                fn (?string $field) => $field !== null && $field !== '',
            ));

            $isFirst = $first;
            $first = false;

            $emails = array_filter($fields, fn (string $field) => filter_var($field, FILTER_VALIDATE_EMAIL) !== false);

            if (count($fields) === 2 && count($emails) === 1) {
                $email = reset($emails);
                $name = $fields[0] === $email ? $fields[1] : $fields[0];

                $key = mb_strtolower($name)."\n".mb_strtolower($email);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $participants[] = ['name' => $name, 'email' => $email];
                }

                continue;
            }

            // A header line ("Naam;E-mail") on top of an export, not a person whose address lacks an "@".
            if ($isFirst && count($fields) === 2 && self::isHeader($fields)) {
                continue;
            }

            $invalid[] = ['line' => $index + 1, 'text' => trim($line)];
        }

        return new self($participants, $invalid);
    }

    /**
     * Both lists' participants (duplicates collapsed) and invalid lines.
     */
    public function with(self $other): self
    {
        $participants = [];
        foreach ([...$this->participants, ...$other->participants] as $participant) {
            $participants[mb_strtolower($participant['name'])."\n".mb_strtolower($participant['email'])] ??= $participant;
        }

        return new self(array_values($participants), [...$this->invalid, ...$other->invalid]);
    }

    /**
     * @param  list<string>  $fields
     */
    private static function isHeader(array $fields): bool
    {
        foreach ($fields as $field) {
            if (! preg_match('/^[\p{L} -]*(naam|name|mail)[\p{L} -]*$/iu', $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * One participant as a line that parse() reads back, e.g. to put failed people back in the form.
     *
     * @param  array{name: string, email: string}  $participant
     */
    public static function line(array $participant): string
    {
        $name = $participant['name'];

        if (str_contains($name, ';') || str_contains($name, '"')) {
            $name = '"'.str_replace('"', '""', $name).'"';
        }

        return $name.';'.$participant['email'];
    }
}
