<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organisatie → Gegevens: the organisation's name, address and e-mail.
 */
class OrganisationController extends Controller
{
    public function edit(): View
    {
        return view('organisatie.gegevens', ['organisation' => Organisation::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $organisation = Organisation::current();
        $organisation->fill($validated)->save();

        return redirect()->route('organisatie.edit')->with('status', 'De gegevens van de organisatie zijn opgeslagen.');
    }
}
