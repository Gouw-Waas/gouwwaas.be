<?php

namespace Tests\Feature;

use App\Certificates\ParticipantList;
use Tests\TestCase;

class ParticipantListTest extends TestCase
{
    public function test_either_order_and_either_separator(): void
    {
        $list = ParticipantList::parse("An Peeters;an@example.com\nbert@example.com, Bert Claes");

        $this->assertSame([
            ['name' => 'An Peeters', 'email' => 'an@example.com'],
            ['name' => 'Bert Claes', 'email' => 'bert@example.com'],
        ], $list->participants);
        $this->assertSame([], $list->invalid);
    }

    public function test_excel_export_with_bom_header_and_crlf(): void
    {
        $list = ParticipantList::parse("\u{FEFF}Naam;E-mail\r\nZoë Ölçer;zoe@example.com\r\n\r\n");

        $this->assertSame([['name' => 'Zoë Ölçer', 'email' => 'zoe@example.com']], $list->participants);
        $this->assertSame([], $list->invalid);
    }

    public function test_quoted_comma_in_name(): void
    {
        $list = ParticipantList::parse('"Peeters, An",an@example.com');

        $this->assertSame([['name' => 'Peeters, An', 'email' => 'an@example.com']], $list->participants);
    }

    public function test_invalid_lines_reported_with_line_numbers(): void
    {
        $list = ParticipantList::parse("An;an@example.com\nBert\nCees;not-an-email\nDe;d@x.be;extra\n");

        $this->assertSame([
            ['line' => 2, 'text' => 'Bert'],
            ['line' => 3, 'text' => 'Cees;not-an-email'],
            ['line' => 4, 'text' => 'De;d@x.be;extra'],
        ], $list->invalid);
    }

    public function test_header_only_skipped_on_first_line(): void
    {
        $list = ParticipantList::parse("An;an@example.com\nNaam;E-mail");

        $this->assertSame([['line' => 2, 'text' => 'Naam;E-mail']], $list->invalid);
    }

    public function test_duplicates_collapsed(): void
    {
        $list = ParticipantList::parse("An;an@example.com\nAn;AN@example.com\nLien;an@example.com");

        // Siblings sharing an address both stay.
        $this->assertSame([
            ['name' => 'An', 'email' => 'an@example.com'],
            ['name' => 'Lien', 'email' => 'an@example.com'],
        ], $list->participants);
    }

    public function test_line_round_trips(): void
    {
        foreach ([
            ['name' => 'Peeters, An', 'email' => 'an@example.com'],
            ['name' => 'An "Ankie"; Peeters', 'email' => 'an@example.com'],
        ] as $participant) {
            $this->assertSame([$participant], ParticipantList::parse(ParticipantList::line($participant))->participants);
        }
    }
}
