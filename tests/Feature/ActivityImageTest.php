<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function photo(string $name = 'kamp.png'): UploadedFile
    {
        // A real 1x1 PNG, so the image rule's mime check passes without GD.
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Activity $activity, array $overrides = []): array
    {
        return [
            'title' => $activity->title,
            'description' => $activity->description,
            'location' => $activity->location,
            'starts_at' => $activity->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $activity->ends_at?->format('Y-m-d\TH:i'),
            'registration_deadline' => $activity->registration_deadline->format('Y-m-d\TH:i'),
            ...$overrides,
        ];
    }

    public function test_without_an_image_the_header_is_plain_and_the_logo_is_the_preview(): void
    {
        $activity = Activity::factory()->create();

        $this->get("/activiteiten/{$activity->slug}")
            ->assertOk()
            ->assertDontSee('page-header-image')
            ->assertSee('<meta property="og:image" content="'.asset('images/logo-600.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('<meta property="og:title" content="'.e($activity->title).' · Gouw Waas">', false);
    }

    public function test_organiser_adds_an_image_shown_behind_the_header_and_in_link_previews(): void
    {
        $activity = Activity::factory()->create();

        $this->actingAs($activity->user)
            ->put("/activiteiten/{$activity->slug}", $this->payload($activity, ['image' => $this->photo()]))
            ->assertRedirect("/activiteiten/{$activity->slug}");

        $activity->refresh();
        $this->assertNotNull($activity->image_path);
        Storage::disk('public')->assertExists($activity->image_path);

        // On the page without host, so it works on any host (CSP img-src 'self')...
        $url = $activity->imageUrl();
        $this->assertSame('/storage/'.$activity->image_path, $url);
        // ...and in full for link previews.
        $this->assertSame('http://localhost/storage/'.$activity->image_path, $activity->imageAbsoluteUrl());

        $this->get("/activiteiten/{$activity->slug}")
            ->assertSee('page-header-image')
            ->assertSee("--header-image: url('{$url}')", false)
            ->assertSee('<meta property="og:image" content="'.$activity->imageAbsoluteUrl().'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_the_image_works_when_app_url_is_another_host(): void
    {
        // Like a local server with APP_URL=http://localhost:8000 opened as http://127.0.0.1:8000,
        // or www vs no www in production: the page must not point at APP_URL's host.
        Storage::fake('public', ['url' => 'http://localhost:8000/storage']);
        $activity = Activity::factory()->create();
        $activity->updateImage($this->photo());
        $activity->save();

        $this->get("http://127.0.0.1:8000/activiteiten/{$activity->slug}")
            ->assertSee("--header-image: url('/storage/{$activity->image_path}')", false)
            ->assertSee('<meta property="og:image" content="http://127.0.0.1:8000/storage/'.$activity->image_path.'">', false)
            ->assertDontSee('localhost:8000/storage');
    }

    public function test_an_image_can_be_set_when_creating(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/activiteiten', [
            'title' => 'Herfstontmoeting',
            'description' => 'Tekst.',
            'location' => 'Gouwhuis Karree',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'registration_deadline' => now()->addWeeks(3)->format('Y-m-d\TH:i'),
            'image' => $this->photo(),
        ])->assertRedirect();

        $activity = Activity::sole();
        Storage::disk('public')->assertExists((string) $activity->image_path);
    }

    public function test_replacing_and_removing_the_image_deletes_the_old_file(): void
    {
        $activity = Activity::factory()->create();
        $this->actingAs($activity->user);

        $this->put("/activiteiten/{$activity->slug}", $this->payload($activity, ['image' => $this->photo()]));
        $first = (string) $activity->refresh()->image_path;

        $this->put("/activiteiten/{$activity->slug}", $this->payload($activity, ['image' => $this->photo('ander.png')]));
        $second = (string) $activity->refresh()->image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        // Saving without a new file keeps the image.
        $this->put("/activiteiten/{$activity->slug}", $this->payload($activity, ['title' => 'Nieuwe titel']));
        $this->assertSame($second, $activity->refresh()->image_path);

        $this->put("/activiteiten/{$activity->slug}", $this->payload($activity, ['remove_image' => '1']));
        $this->assertNull($activity->refresh()->image_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_only_images_are_accepted(): void
    {
        $activity = Activity::factory()->create();

        $this->actingAs($activity->user)
            ->put("/activiteiten/{$activity->slug}", $this->payload($activity, [
                'image' => UploadedFile::fake()->create('programma.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertNull($activity->refresh()->image_path);
    }

    public function test_the_image_is_deleted_with_the_activity(): void
    {
        $activity = Activity::factory()->create();
        $this->actingAs($activity->user)->put("/activiteiten/{$activity->slug}", $this->payload($activity, ['image' => $this->photo()]));
        $path = (string) $activity->refresh()->image_path;

        $this->actingAs($activity->user)->delete("/activiteiten/{$activity->slug}")->assertRedirect();

        $this->assertModelMissing($activity);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_archiving_deletes_the_image_too(): void
    {
        $activity = Activity::factory()->create([
            'starts_at' => now()->subYears(3),
            'ends_at' => now()->subYears(3)->addHours(3),
            'registration_deadline' => now()->subYears(3)->subWeek(),
        ]);
        $activity->updateImage($this->photo());
        $activity->save();
        $path = (string) $activity->image_path;

        Artisan::call('activities:archive');

        $this->assertModelMissing($activity);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_other_pages_use_the_logo_as_preview(): void
    {
        $this->get('/')
            ->assertSee('<meta property="og:image" content="'.asset('images/logo-600.png').'">', false)
            ->assertSee('<meta property="og:url" content="'.url('/').'">', false);
    }
}
