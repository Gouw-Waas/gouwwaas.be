<?php

namespace App\Http\Controllers;

use App\Certificates\CertificateRenderer;
use App\Certificates\CertificateUnavailable;
use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Participation certificates (attesten) for activities with vormingsuren, for registrants
 * who are present and confirmed.
 */
class CertificateController extends Controller
{
    public function download(Activity $activity, Registration $registration, CertificateRenderer $renderer): Response|RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        abort_unless($activity->hasCertificates() && $registration->isPresent() && $registration->isConfirmed(), 404);

        try {
            $renderer->ensureAvailable($activity);
        } catch (CertificateUnavailable $e) {
            return back()->withErrors(['certificate' => $e->getMessage()]);
        }

        return response($renderer->pdf($registration), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$renderer->filename($registration).'"',
        ]);
    }
}
