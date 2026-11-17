<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Text blocks on the home page that admins can add, edit and remove, starting with the two
 * blocks that were in the template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('position')->default(0);
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        DB::table('page_blocks')->insert([
            [
                'position' => 1,
                'title' => 'Gouw watte?',
                'body' => 'Gouw Waas is de verzameling van de districten Klaas, Lelie, Durme en Land van Aymon, die samen '
                    .'{aantal groepen} scoutsgroepen vertegenwoordigen verspreid over het ruime Waasland. Onze uitvalsbasis '
                    ."is in gouwhuis Karree in Sint-Niklaas.\n\n"
                    .'[Bekijk alle groepen per district](/districten)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position' => 2,
                'title' => 'En wat doen jullie dan?',
                'body' => 'De meeste leiding kent ons ongetwijfeld van **Herfstontmoeting**, waar onze ploeg voor jullie '
                    .'klaarstaat. Als gouw ondersteunen wij de districten (en daarmee ook indirect de groepen) in '
                    ."verschillende pedagogische en beleidsmatige thema's. Dit doen we door het aanbieden van vormingen "
                    ."en het vertegenwoordigen op enkele nationale vergaderingen.\n\n"
                    .'Daarnaast organiseren we ook **open kampwerkingen**, zodat elk kind of jongere uit de regio mee op '
                    .'kamp kan, ook als dat via een eigen groep wat moeilijker lukt.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
    }
};
