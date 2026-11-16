<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityField;
use App\Models\Registration;
use App\Payments\RegistrationCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function store(Request $request, Activity $activity, RegistrationCheckout $checkout): RedirectResponse
    {
        if (! $activity->isRegistrationOpen()) {
            return back()->withErrors(['registration' => 'De inschrijvingen voor deze activiteit zijn afgesloten.']);
        }

        $fields = $activity->fields;

        $rules = ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']];
        $attributes = ['name' => 'naam', 'email' => 'e-mailadres'];

        foreach ($fields as $field) {
            $rules["answers.{$field->id}"] = $field->rules();
            $attributes["answers.{$field->id}"] = '"'.$field->label.'"';
        }

        $validated = $request->validate($rules, [
            'answers.*.accepted' => 'Vink :attribute aan.',
            'answers.*.in' => 'Kies een geldige waarde voor :attribute.',
        ], $attributes);

        $registration = $activity->registrations()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'answers' => $fields->map(fn (ActivityField $field) => [
                'field_id' => $field->id,
                'label' => $field->label,
                'value' => $field->present($validated['answers'][$field->id] ?? null),
            ])->values()->all(),
            // The price now: changing it later doesn't change what this person pays.
            'amount_cents' => $activity->price_cents,
        ]);

        // Free: confirmed straight away. Paid: the registration's page is the checkout.
        if ($registration->isFree()) {
            $checkout->sendConfirmation($registration);
        }

        return redirect()->route('inschrijvingen.show', $registration);
    }

    public function index(Activity $activity): View
    {
        Gate::authorize('viewRegistrations', $activity);

        $activity->load('fields', 'registrations');

        return view('activiteiten.inschrijvingen', [
            'activity' => $activity,
            'columns' => $activity->answerColumns(),
        ]);
    }

    /**
     * The registrations as an Excel file (.xlsx). Text is written as text, never as a
     * formula, so answers like "=HYPERLINK(…)" from the public are harmless.
     */
    public function export(Activity $activity): StreamedResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $activity->load('fields', 'registrations');
        $columns = $activity->answerColumns();
        $filename = "inschrijvingen-{$activity->slug}.xlsx";

        return response()->streamDownload(function () use ($activity, $columns) {
            $headers = ['Ingeschreven op', 'Naam', 'E-mailadres', 'Betaling', 'Bedrag', ...$columns->pluck('label')];

            $options = new Options;
            $options->setColumnWidth(18, 1);
            $options->setColumnWidth(24, 2);
            $options->setColumnWidth(30, 3);
            $options->setColumnWidth(14, 4, 5);
            if ($columns->isNotEmpty()) {
                $options->setColumnWidthForRange(22, 6, count($headers));
            }

            $writer = new Writer($options);
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()
                ->setName('Inschrijvingen')
                ->setSheetView(new SheetView(freezeRow: 2)); // header stays visible while scrolling

            // Cell::fromValue() turns text starting with "=" into a formula: everything typed by
            // people (names, answers, field labels) is written as an explicit text cell instead.
            $text = fn (?string $value, ?Style $style = null) => new StringCell($value ?? '', $style);

            $header = new Style(fontBold: true, backgroundColor: 'F6F6E0');
            $writer->addRow(new Row(array_map(fn (string $label) => $text($label, $header), $headers)));

            $date = new Style(format: 'dd/mm/yyyy hh:mm');
            $euro = new Style(format: '"€" #,##0.00');

            foreach ($activity->registrations->sortBy('created_at') as $registration) {
                $writer->addRow(new Row([
                    new DateTimeCell($registration->created_at->toDateTimeImmutable(), $date),
                    $text($registration->name),
                    $text($registration->email),
                    $text($registration->paymentLabel()),
                    $registration->isFree() ? $text('') : new NumericCell($registration->amount_cents / 100, $euro),
                    ...$columns->map(fn (array $column) => $text($registration->answerFor($column['id'])))->all(),
                ]));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function destroy(Activity $activity, Registration $registration): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $registration->delete();

        return back()->with('status', "De inschrijving van {$registration->name} is verwijderd.");
    }
}
