<?php

namespace Tests\Feature\Concerns;

use App\Certificates\Stamp;
use App\Models\Role;
use Illuminate\Support\Facades\Storage;

/**
 * A stamp on the (fake) private disk and a Gouwcommissaris role with two members.
 */
trait SetsUpCertificates
{
    /** A 1×1 PNG. */
    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    protected Role $commissaris;

    protected function setUpCertificates(): void
    {
        Storage::fake(Stamp::DISK);
        Storage::disk(Stamp::DISK)->put(Stamp::PATH, base64_decode(self::PNG));

        $this->commissaris = Role::create(['category' => Role::BUREAU, 'key' => 'commissaris', 'title' => 'Gouwcommissaris', 'position' => 2]);
        $this->commissaris->members()->create(['first_name' => 'Bert', 'last_name' => 'Claes', 'position' => 2]);
        $this->commissaris->members()->create(['first_name' => 'An', 'last_name' => 'Peeters', 'position' => 1]);
    }
}
