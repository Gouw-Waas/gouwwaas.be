<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Functions on /wie-is-wie ("wie is wie") and the people filling them.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // bureau | adjunct | district
            $table->string('category', 20);
            // Fixed functions (bureau, district) are matched to config/gouw.php by key; adjuncten have none.
            $table->string('key', 50)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['category', 'key']);
        });

        Schema::create('role_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('totem')->nullable();
            $table->string('title')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // The adjuncten that were on the page before it became editable.
        // The fixed bureau and district functions are created from config/gouw.php on first use.
        $now = now();
        $adjuncten = [
            'Technieken' => "Interesse in een vorming? Of advies nodig over een sjorconstructie? Je kan altijd bij onze AGC's technieken terecht!",
            'Integriteit' => null,
            'Diversiteit' => null,
            'Social media' => null,
            'Evenementen' => null,
            'Karree' => 'Omdat we als een van de weinige gouwen een gouwhuis hebben met een vaste evenementenwerking, is hier ook een apart team voor opgericht.',
            'Materiaal' => null,
            'Beheerszaken' => null,
            'Catering' => null,
        ];

        DB::table('roles')->insert(collect($adjuncten)->keys()->map(fn (string $title, int $position) => [
            'category' => 'adjunct',
            'title' => $title,
            'description' => $adjuncten[$title],
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('role_members');
        Schema::dropIfExists('roles');
    }
};
