<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Support\OrganisationLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organisatie → Gegevens: the organisation's name, address, e-mail and logo.
 */
class OrganisationController extends Controller
{
    public function edit(): View
    {
        return view('organisatie.gegevens', [
            'organisation' => Organisation::current(),
            'logo' => OrganisationLogo::dataUri(),
            'logoUploaded' => OrganisationLogo::isUploaded(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $logoMessage = 'Laad een PNG-afbeelding op van maximaal 2 MB.';
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'building' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png', 'max:2048'],
            'remove_logo' => ['boolean'],
        ], ['logo.image' => $logoMessage, 'logo.mimes' => $logoMessage, 'logo.max' => $logoMessage]);

        $organisation = Organisation::current();
        $organisation->fill($validated)->save();

        if ($request->hasFile('logo')) {
            OrganisationLogo::store($request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            OrganisationLogo::delete();
        }

        return redirect()->route('organisatie.edit')->with('status', 'De gegevens van de organisatie zijn opgeslagen.');
    }
}
