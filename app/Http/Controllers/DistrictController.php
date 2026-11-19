<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Role;
use App\Models\ScoutGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /districten: the map with the districts and their scouting groups.
 */
class DistrictController extends Controller
{
    public function index(): View
    {
        $groups = ScoutGroup::orderBy('name')->get()->groupBy('district');

        // The districtsploeg of each district, as on /wie-is-wie.
        $teams = Role::where('category', Role::DISTRICT)->with('members')->get()->keyBy('key');
        $records = District::all()->keyBy('key');

        $districts = collect(ScoutGroup::districts())->map(fn (array $district, string $key) => $district + [
            'key' => $key,
            'groups' => $groups->get($key, collect()),
            'team' => $teams->get($key),
            'logo' => $records->get($key)?->logoUrl(),
        ]);

        // What the map script needs; the sidebar panels themselves are rendered in the view.
        $mapData = [
            'districts' => $districts->map(fn (array $d) => [
                'key' => $d['key'],
                'name' => $d['name'],
                'color' => $d['color'],
                'boundary' => $d['boundary'],
            ])->values(),
            'groups' => $districts->flatMap(fn (array $d) => $d['groups'])
                ->filter->hasLocation()
                ->map(fn (ScoutGroup $group) => [
                    'id' => $group->anchor(),
                    'name' => $group->name,
                    'district' => $group->district,
                    'lat' => $group->latitude,
                    'lng' => $group->longitude,
                    'akabe' => $group->hasAkabe() ? $group->akabe : null,
                ])->values(),
        ];

        return view('districten.index', [
            'districts' => $districts,
            'akabeGroups' => $districts->flatMap(fn (array $d) => $d['groups'])->filter->hasAkabe()->sortBy('name')->values(),
            'groupCount' => $groups->flatten()->count(),
            'mapData' => $mapData,
        ]);
    }

    /**
     * Admins: the district's logo.
     */
    public function edit(string $district): View
    {
        return view('districten.district-form', ['district' => District::forKey($district)]);
    }

    public function update(Request $request, string $district): RedirectResponse
    {
        $district = District::forKey($district);

        $request->validate([
            // No SVG: an uploaded SVG can contain scripts.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.image' => 'Kies een afbeelding (jpg, png of webp).',
            'logo.mimes' => 'Kies een afbeelding (jpg, png of webp).',
            'logo.max' => 'Het logo mag maximaal 2 MB groot zijn.',
        ]);

        $district->updateLogo($request->file('logo'), $request->boolean('remove_logo'));
        $district->save();

        return redirect()->to(route('districten').'#district-'.$district->key)
            ->with('status', "District {$district->name()} is bijgewerkt.");
    }
}
