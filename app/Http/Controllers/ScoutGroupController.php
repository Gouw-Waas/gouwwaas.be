<?php

namespace App\Http\Controllers;

use App\Models\ScoutGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admins add, edit and remove the scouting groups shown on /districten.
 */
class ScoutGroupController extends Controller
{
    public function create(Request $request): View
    {
        $district = $request->query('district');

        return view('districten.form', ['group' => new ScoutGroup([
            'district' => array_key_exists($district, ScoutGroup::districts()) ? $district : null,
            'type' => 'gemengd',
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = new ScoutGroup($this->validated($request));
        $group->updateLogo($request->file('logo'));
        $group->save();

        return $this->backToMap($group, "\"{$group->name}\" is toegevoegd.");
    }

    public function edit(ScoutGroup $scoutGroup): View
    {
        return view('districten.form', ['group' => $scoutGroup]);
    }

    public function update(Request $request, ScoutGroup $scoutGroup): RedirectResponse
    {
        $scoutGroup->fill($this->validated($request, $scoutGroup));
        $scoutGroup->updateLogo($request->file('logo'), $request->boolean('remove_logo'));
        $scoutGroup->save();

        return $this->backToMap($scoutGroup, "\"{$scoutGroup->name}\" is bijgewerkt.");
    }

    public function destroy(ScoutGroup $scoutGroup): RedirectResponse
    {
        $scoutGroup->delete();

        return redirect()->route('districten')->with('status', "\"{$scoutGroup->name}\" is verwijderd.");
    }

    private function backToMap(ScoutGroup $group, string $status): RedirectResponse
    {
        return redirect()->to(route('districten').'#'.$group->anchor())->with('status', $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ScoutGroup $group = null): array
    {
        $request->merge([
            'number' => strtoupper(trim((string) $request->input('number'))),
            'postal_code' => trim((string) $request->input('postal_code')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'number' => ['required', 'regex:/^[A-Z]\d{4}[A-Z]$/', Rule::unique('scout_groups', 'number')->ignore($group)],
            'type' => ['required', Rule::in(array_keys(ScoutGroup::TYPES))],
            'district' => ['required', Rule::in(array_keys(ScoutGroup::districts()))],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^\d{4}$/'],
            'city' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:49.4,51.6'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:2.5,6.5'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            // No SVG: an uploaded SVG can contain scripts.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.image' => 'Kies een afbeelding (jpg, png of webp).',
            'logo.mimes' => 'Kies een afbeelding (jpg, png of webp).',
            'logo.max' => 'Het logo mag maximaal 2 MB groot zijn.',
            'number.regex' => 'Een groepsnummer ziet eruit als O3101G.',
            'postal_code.regex' => 'Een postcode heeft 4 cijfers.',
            'latitude.between' => 'Deze locatie ligt niet in België.',
            'longitude.between' => 'Deze locatie ligt niet in België.',
        ], [
            'name' => 'naam',
            'number' => 'groepsnummer',
            'street' => 'straat en nummer',
            'postal_code' => 'postcode',
            'city' => 'gemeente',
            'latitude' => 'locatie',
            'longitude' => 'locatie',
        ]);

        $validated['website'] = filled($validated['website'] ?? null) ? $validated['website'] : null;

        return Arr::except($validated, ['logo', 'remove_logo']);
    }
}
