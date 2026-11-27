<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Registration;
use App\Payments\CancellationException;
use App\Payments\PaymentException;
use App\Payments\RegistrationCancellation;
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

        if ($activity->isFull()) {
            return back()->withInput()->withErrors(['registration' => 'Deze activiteit is volzet.']);
        }

        $registration = $activity->registrations()->create([
            ...$activity->validateRegistration($request),
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
            $headers = ['Ingeschreven op', 'Naam', 'E-mailadres', 'Betaling', 'Bedrag', 'Aanwezig', ...$columns->pluck('label')];

            $options = new Options;
            $options->setColumnWidth(18, 1);
            $options->setColumnWidth(24, 2);
            $options->setColumnWidth(30, 3);
            $options->setColumnWidth(14, 4, 5);
            $options->setColumnWidth(10, 6);
            if ($columns->isNotEmpty()) {
                $options->setColumnWidthForRange(22, 7, count($headers));
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
                    $text($registration->isPresent() ? 'Ja' : ''),
                    ...$columns->map(fn (array $column) => $text($registration->answerFor($column['id'])))->all(),
                ]));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Cancel a registration: paid ones are refunded (in full, or minus administrative costs),
     * and everyone who was registered gets an e-mail.
     */
    public function destroy(Request $request, Activity $activity, Registration $registration, RegistrationCancellation $cancellation): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $adminCost = 0;

        if ($registration->paidOnline()) {
            $request->merge(['admin_cost' => str_replace(',', '.', trim((string) $request->input('admin_cost', '0')))]);
            $validated = $request->validate([
                'refund' => ['required', 'in:full,minus_costs'],
                'admin_cost' => ['required_if:refund,minus_costs', 'nullable', 'numeric', 'min:0', 'decimal:0,2'],
            ], [
                'refund.required' => 'Kies hoe er terugbetaald wordt.',
                'admin_cost.numeric' => 'Vul de administratiekosten in als bedrag, bv. 1 of 1,50.',
                'admin_cost.decimal' => 'Vul de administratiekosten in als bedrag, bv. 1 of 1,50.',
            ]);

            if ($validated['refund'] === 'minus_costs') {
                $adminCost = (int) round((float) $validated['admin_cost'] * 100);

                if ($adminCost >= $registration->amount_cents) {
                    return back()->withErrors(['cancel' => 'De administratiekosten moeten lager zijn dan het betaalde bedrag ('.$registration->amountLabel().').']);
                }
            }
        }

        try {
            $refunded = $cancellation->cancel($registration, $adminCost);
        } catch (CancellationException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        } catch (PaymentException $e) {
            report($e);

            return back()->withErrors(['cancel' => "De terugbetaling aan {$registration->name} lukte niet, dus de inschrijving is niet geannuleerd. Kijk in het Mollie-dashboard of er genoeg saldo is en probeer opnieuw."]);
        }

        $status = "De inschrijving van {$registration->name} is geannuleerd.";
        if ($refunded) {
            $status .= ' De terugbetaling van € '.number_format($refunded / 100, 2, ',', '.').' wordt verwerkt.';
        }

        return back()->with('status', $status);
    }
}
