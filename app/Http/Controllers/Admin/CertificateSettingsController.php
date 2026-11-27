<?php

namespace App\Http\Controllers\Admin;

use App\Certificates\CertificateRenderer;
use App\Certificates\Stamp;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Beheer → Attest: the stamp on participation certificates, who signs them, and a preview.
 */
class CertificateSettingsController extends Controller
{
    public function edit(CertificateRenderer $renderer): View
    {
        return view('attesten.instellingen', [
            'stamp' => Stamp::dataUri(),
            'signers' => $renderer->signers(),
            'problems' => $renderer->problems(),
            'organisation' => config('gouw.organisation'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $message = 'Laad een PNG-afbeelding op van maximaal 2 MB.';
        $request->validate(
            ['stamp' => ['required', 'image', 'mimes:png', 'max:2048']],
            ['stamp.required' => $message, 'stamp.image' => $message, 'stamp.mimes' => $message, 'stamp.max' => $message],
        );

        Stamp::store($request->file('stamp'));

        return redirect()->route('attest.instellingen')->with('status', 'De stempel is opgeslagen.');
    }

    public function preview(CertificateRenderer $renderer): Response|RedirectResponse
    {
        if ($problem = $renderer->problems()[0] ?? null) {
            return back()->withErrors(['certificate' => $problem]);
        }

        return response($renderer->samplePdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="attest-voorbeeld.pdf"',
        ]);
    }
}
