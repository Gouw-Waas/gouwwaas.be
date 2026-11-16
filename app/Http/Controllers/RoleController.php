<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Wie is wie": everyone can view it, admins edit the functions and their people.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $load = fn () => Role::with('members')->orderBy('position')->orderBy('id')->get();
        $roles = $load();

        if (Role::syncFixed($roles)) {
            $roles = $load();
        }

        // Functions without people are listed together at the bottom, under "Openstaande functies".
        [$filled, $vacant] = $roles->filter->isListed()->partition(fn (Role $role) => $role->members->isNotEmpty());
        $filled = $filled->groupBy('category');
        $categoryOrder = array_flip([Role::BUREAU, Role::ADJUNCT, Role::DISTRICT]);

        return view('wie-is-wie.index', [
            'bureau' => $filled->get(Role::BUREAU, collect()),
            'adjuncten' => $filled->get(Role::ADJUNCT, collect()),
            'districtRoles' => $filled->get(Role::DISTRICT, collect()),
            'vacant' => $vacant->sortBy(fn (Role $role) => [$categoryOrder[$role->category], $role->position, $role->id])->values(),
            'districts' => config('gouw.districts'),
        ]);
    }

    public function create(): View
    {
        return view('wie-is-wie.form', ['role' => new Role(['category' => Role::ADJUNCT])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = new Role(['category' => Role::ADJUNCT]);
        [$attributes, $members, $photos] = $this->validated($request, $role);

        $role = DB::transaction(function () use ($attributes, $members, $photos) {
            $role = Role::create($attributes + [
                'category' => Role::ADJUNCT,
                'position' => (int) Role::where('category', Role::ADJUNCT)->max('position') + 1,
            ]);
            $role->syncMembers($members, $photos);

            return $role;
        });

        return $this->backToPage($role, "\"{$role->title}\" is toegevoegd.");
    }

    public function edit(Role $role): View
    {
        return view('wie-is-wie.form', ['role' => $role->load('members')]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        [$attributes, $members, $photos] = $this->validated($request, $role);

        DB::transaction(function () use ($role, $attributes, $members, $photos) {
            $role->update($attributes);
            $role->syncMembers($members, $photos);
        });

        return $this->backToPage($role, "\"{$role->title}\" is bijgewerkt.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($role->isAdjunct(), 403, 'Alleen adjuncten kunnen verwijderd worden.');

        DB::transaction(fn () => $role->delete());

        return redirect()->route('wie-is-wie')->with('status', "\"{$role->title}\" is verwijderd.");
    }

    private function backToPage(Role $role, string $status): RedirectResponse
    {
        return redirect()->to(route('wie-is-wie').'#functie-'.$role->id)->with('status', $status);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int|string, array<string, mixed>>, 2: array<int|string, \Illuminate\Http\UploadedFile>}
     */
    private function validated(Request $request, Role $role): array
    {
        // "@gouw.waas" or "gouw.waas" is fine too.
        $instagram = trim((string) $request->input('instagram'));
        if ($instagram !== '' && preg_match('/^@?([A-Za-z0-9._]+)$/', $instagram, $matches)) {
            $request->merge(['instagram' => "https://www.instagram.com/{$matches[1]}/"]);
        }

        $validated = $request->validate([
            'title' => $role->isAdjunct() ? ['required', 'string', 'max:255'] : ['prohibited'],
            'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255'],
            'instagram' => ['nullable', 'url:http,https', 'max:255'],
            'facebook' => ['nullable', 'url:http,https', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'members' => ['nullable', 'array', 'max:30'],
            'members.*.id' => ['nullable', 'integer'],
            'members.*.first_name' => ['required', 'string', 'max:100'],
            'members.*.last_name' => ['required', 'string', 'max:100'],
            'members.*.totem' => ['nullable', 'string', 'max:100'],
            'members.*.title' => ['nullable', 'string', 'max:255'],
            'members.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'members.*.remove_photo' => ['nullable', 'boolean'],
        ], [
            'members.*.first_name.required' => 'Vul een voornaam in.',
            'members.*.last_name.required' => 'Vul een achternaam in.',
            'members.*.photo.image' => 'Kies een foto (jpg, png of webp).',
            'members.*.photo.mimes' => 'Kies een foto (jpg, png of webp).',
            'members.*.photo.max' => 'De foto mag maximaal 5 MB groot zijn.',
            'members.*.photo.uploaded' => 'De foto kon niet worden opgeladen. Probeer een kleinere foto.',
            'instagram.url' => 'Vul een Instagram-link of -gebruikersnaam in.',
        ], [
            'title' => 'naam van de functie',
            'description' => 'beschrijving',
            'email' => 'e-mailadres',
            'facebook' => 'Facebook-link',
            'website' => 'website',
        ]);

        $attributes = collect($validated)
            ->only(['description', 'email', 'instagram', 'facebook', 'website'])
            ->map(fn ($value) => filled($value) ? trim($value) : null)
            ->all();

        if ($role->isAdjunct()) {
            $attributes['title'] = trim($validated['title']);
        }

        $members = $validated['members'] ?? [];
        $photos = collect($members)
            ->keys()
            ->mapWithKeys(fn ($key) => [$key => $request->file("members.{$key}.photo")])
            ->filter()
            ->all();

        return [$attributes, $members, $photos];
    }
}
