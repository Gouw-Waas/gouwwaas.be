<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The scouting groups of the gouw, shown on the map on /districten and managed by admins.
     * ("groups" is a reserved word in MySQL 8, hence scout_groups.)
     */
    public function up(): void
    {
        Schema::create('scout_groups', function (Blueprint $table) {
            $table->id();
            // Key of a district in config/gouw.php, e.g. "klaas".
            $table->string('district', 50)->index();
            // Scouts en Gidsen Vlaanderen group number, e.g. O3101G.
            $table->string('number', 10)->unique();
            $table->string('name');
            // scouts | gidsen | gemengd | zeescouts
            $table->string('type', 20);
            $table->string('street');
            $table->string('postal_code', 10);
            $table->string('city');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        // Snapshot of November 2026: numbers, types, addresses and locations from the
        // Scouts en Gidsen Vlaanderen groepenzoeker, names and websites from the old list.
        $now = now();
        DB::table('scout_groups')->insert(array_map(fn (array $group) => $group + ['created_at' => $now, 'updated_at' => $now], [
            ['district' => 'klaas', 'number' => 'O3114G', 'name' => 'Jin Sint-Niklaas', 'type' => 'gemengd', 'street' => 'Apostelstraat 5', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1639472, 'longitude' => 4.1422881, 'website' => null],
            ['district' => 'klaas', 'number' => 'O3108G', 'name' => 'Kriko-m', 'type' => 'gemengd', 'street' => 'Industriepark-Noord 33', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1511138, 'longitude' => 4.161288, 'website' => 'https://www.kriko-m.be/'],
            ['district' => 'klaas', 'number' => 'O3101G', 'name' => 'Kristus Koning', 'type' => 'gemengd', 'street' => 'Puitvoetstraat 91', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1474605, 'longitude' => 4.1617993, 'website' => 'https://www.kriko-m.be/'],
            ['district' => 'klaas', 'number' => 'O3111G', 'name' => 'Mgr. Bermijn', 'type' => 'gemengd', 'street' => 'Dries 30', 'postal_code' => '9170', 'city' => 'Sint-Pauwels', 'latitude' => 51.193512, 'longitude' => 4.0964634, 'website' => 'https://www.bermijn.net/'],
            ['district' => 'klaas', 'number' => 'O3112G', 'name' => 'Sint-Johan', 'type' => 'gemengd', 'street' => 'Beeldstraat 2B', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1678888, 'longitude' => 4.1910651, 'website' => 'https://akabesintjohan.be/'],
            ['district' => 'klaas', 'number' => 'O3103G', 'name' => 'Sint-Jozef', 'type' => 'gemengd', 'street' => 'Tereken 33', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1550109, 'longitude' => 4.1475085, 'website' => 'https://www.tereken.be/'],
            ['district' => 'klaas', 'number' => 'O3104S', 'name' => 'Sint-Kristoffel', 'type' => 'scouts', 'street' => 'Heistraat 206', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1772003, 'longitude' => 4.1395339, 'website' => 'https://www.sint-kristoffel.be/'],
            ['district' => 'klaas', 'number' => 'O3109G', 'name' => 'Sint-Martinus', 'type' => 'gemengd', 'street' => 'Heihoekstraat 204', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1807548, 'longitude' => 4.1912714, 'website' => 'https://www.scoutsnieuwkerken.be/'],
            ['district' => 'klaas', 'number' => 'O3107G', 'name' => 'Sint-Paulus en Sint-Franciscus', 'type' => 'gemengd', 'street' => 'Iepenstraat 78', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1792235, 'longitude' => 4.1533826, 'website' => 'https://www.scoutingdolmen.be/'],
            ['district' => 'klaas', 'number' => 'O3105G', 'name' => 'Sint-Tarsicius', 'type' => 'gemengd', 'street' => 'Puitvoetstraat 91', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1474605, 'longitude' => 4.1617993, 'website' => 'https://www.sint-tarsicius.be/'],
            ['district' => 'klaas', 'number' => 'O3113M', 'name' => 'Sint-Vincentius', 'type' => 'gidsen', 'street' => 'Pijkedreef 100', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.1704998, 'longitude' => 4.1228556, 'website' => 'https://www.vincies.be/'],
            ['district' => 'klaas', 'number' => 'O3106G', 'name' => 'Zeescouts Reynaert', 'type' => 'zeescouts', 'street' => 'Beeldstraat 2', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'latitude' => 51.169429, 'longitude' => 4.196755, 'website' => 'https://dobo.be/'],
            ['district' => 'lelie', 'number' => 'O3312M', 'name' => 'Gidsen St.-Martinus', 'type' => 'gidsen', 'street' => 'Kallobaan 174', 'postal_code' => '9120', 'city' => 'Beveren-Waas', 'latitude' => 51.2217426, 'longitude' => 4.257387, 'website' => 'https://www.scoutsbeveren.be/'],
            ['district' => 'lelie', 'number' => 'O3304S', 'name' => 'Sint-Hieronymus', 'type' => 'scouts', 'street' => 'Cortewalledreef 57', 'postal_code' => '9120', 'city' => 'Beveren-Waas', 'latitude' => 51.2105498, 'longitude' => 4.2654869, 'website' => 'https://www.scoutsbeveren.be/'],
            ['district' => 'lelie', 'number' => 'O3306G', 'name' => 'Sint-Jan', 'type' => 'gemengd', 'street' => 'Sparrenlaan 9', 'postal_code' => '9140', 'city' => 'Temse', 'latitude' => 51.1265647, 'longitude' => 4.2755927, 'website' => 'https://www.scoutssteendorp.com/'],
            ['district' => 'lelie', 'number' => 'O3307S', 'name' => 'Sint-Jan Berchmans', 'type' => 'scouts', 'street' => 'Broekstraat 123', 'postal_code' => '9150', 'city' => 'Kruibeke', 'latitude' => 51.131978, 'longitude' => 4.298076, 'website' => 'https://scoutsrupelmonde.be/'],
            ['district' => 'lelie', 'number' => 'O3314G', 'name' => 'Sint-Johannes', 'type' => 'gemengd', 'street' => 'Tybaartstraat 1', 'postal_code' => '9170', 'city' => 'Sint-Gillis-Waas', 'latitude' => 51.2183205, 'longitude' => 4.1126925, 'website' => 'https://www.scoutssintjohannes.be/'],
            ['district' => 'lelie', 'number' => 'O3301G', 'name' => 'Sint-Michiel', 'type' => 'gemengd', 'street' => 'Oud Arenberg 88B', 'postal_code' => '9130', 'city' => 'Beveren', 'latitude' => 51.2878483, 'longitude' => 4.1869015, 'website' => 'https://www.scoutskieldrecht.be/'],
            ['district' => 'lelie', 'number' => 'O3310G', 'name' => 'Sint-Raphael', 'type' => 'gemengd', 'street' => 'Pastoor Steenssensstraat 110', 'postal_code' => '9120', 'city' => 'Beveren-Waas', 'latitude' => 51.2130786, 'longitude' => 4.2356279, 'website' => 'https://www.scoutssintraphael.be/'],
            ['district' => 'lelie', 'number' => 'O3305G', 'name' => 'Sint-Willibrordus', 'type' => 'gemengd', 'street' => 'Fonteinstraat 7', 'postal_code' => '9140', 'city' => 'Temse', 'latitude' => 51.1247542, 'longitude' => 4.225451, 'website' => 'https://scoutstemse.be/'],
            ['district' => 'durme', 'number' => 'O3210M', 'name' => 'Edelweiss', 'type' => 'gidsen', 'street' => 'Oude Heerweg 125', 'postal_code' => '9160', 'city' => 'Lokeren', 'latitude' => 51.1154198, 'longitude' => 4.0028274, 'website' => 'https://www.gidsenedelweiss.be/'],
            ['district' => 'durme', 'number' => 'O3205G', 'name' => 'O.L.V. Ter Heide', 'type' => 'gemengd', 'street' => 'Rivierstraat 39', 'postal_code' => '9250', 'city' => 'Waasmunster', 'latitude' => 51.1125172, 'longitude' => 4.0811131, 'website' => 'https://www.scoutswaasmunster.be/'],
            ['district' => 'durme', 'number' => 'O3202S', 'name' => 'Scouts Jan Berchmans', 'type' => 'scouts', 'street' => 'Veerstraat 14', 'postal_code' => '9160', 'city' => 'Lokeren', 'latitude' => 51.1104004, 'longitude' => 3.971502, 'website' => 'https://scoutsheirbrug.be/'],
            ['district' => 'durme', 'number' => 'O3214G', 'name' => 'Scouts Thomas', 'type' => 'gemengd', 'street' => 'Koebrugstraat 14', 'postal_code' => '9190', 'city' => 'Klein-Sinaai', 'latitude' => 51.1834821, 'longitude' => 3.9877283, 'website' => null],
            ['district' => 'durme', 'number' => 'O3204G', 'name' => 'Sint-Antonius', 'type' => 'gemengd', 'street' => 'Moerhofstraat 16', 'postal_code' => '9180', 'city' => 'Moerbeke-Waas', 'latitude' => 51.1761545, 'longitude' => 3.9468939, 'website' => 'https://www.scoutsmoerbekewaas.be/'],
            ['district' => 'durme', 'number' => 'O3207G', 'name' => 'Sint-Franciscus', 'type' => 'gemengd', 'street' => 'Wijnveld 251', 'postal_code' => '9112', 'city' => 'Sinaai-Waas', 'latitude' => 51.1445067, 'longitude' => 4.0654303, 'website' => 'https://scoutssinaai.be/'],
            ['district' => 'durme', 'number' => 'O3201S', 'name' => 'Sint-Laurentius', 'type' => 'scouts', 'street' => 'Oude Heerweg 125', 'postal_code' => '9160', 'city' => 'Lokeren', 'latitude' => 51.1154198, 'longitude' => 4.0028274, 'website' => 'https://www.scoutshoogland.be/'],
            ['district' => 'durme', 'number' => 'O3203G', 'name' => 'Sint-Pieter', 'type' => 'gemengd', 'street' => 'Zilverberkstraat 1', 'postal_code' => '9220', 'city' => 'Hamme (O.-Vl.)', 'latitude' => 51.0887197, 'longitude' => 4.1500679, 'website' => 'https://www.scoutshamme.be/'],
            ['district' => 'durme', 'number' => 'O3206G', 'name' => 'Sint-Rochus', 'type' => 'gemengd', 'street' => 'Veldstraat 2', 'postal_code' => '9250', 'city' => 'Waasmunster', 'latitude' => 51.1142605, 'longitude' => 4.1257086, 'website' => 'https://www.scoutssombeke.be/'],
            ['district' => 'land-van-aymon', 'number' => 'O3407G', 'name' => 'Justus De Harduyn', 'type' => 'gemengd', 'street' => 'Mevrouw Courtmansstraat 18', 'postal_code' => '9200', 'city' => 'Dendermonde', 'latitude' => 51.0096026, 'longitude' => 4.0593075, 'website' => 'https://www.scoutsoudegem.be/'],
            ['district' => 'land-van-aymon', 'number' => 'O3405G', 'name' => 'Scouts en Gidsen Buggenhout', 'type' => 'gemengd', 'street' => 'Provinciale baan 165', 'postal_code' => '9200', 'city' => 'Dendermonde', 'latitude' => 51.0316566, 'longitude' => 4.1918046, 'website' => 'https://www.scoutsbuggenhout.be/'],
            ['district' => 'land-van-aymon', 'number' => 'O3408G', 'name' => 'Sint-Ignatius', 'type' => 'gemengd', 'street' => 'Scoutsstraat 55', 'postal_code' => '9240', 'city' => 'Zele', 'latitude' => 51.0616019, 'longitude' => 4.0206401, 'website' => 'https://scoutingzele.be/'],
            ['district' => 'land-van-aymon', 'number' => 'O3411G', 'name' => 'Sint-Paulus Baden Pauwel', 'type' => 'gemengd', 'street' => 'Tragel 7', 'postal_code' => '9200', 'city' => 'Dendermonde', 'latitude' => 51.0272573, 'longitude' => 4.0773809, 'website' => 'https://www.scoutsdendermonde.be/'],
            ['district' => 'land-van-aymon', 'number' => 'O3404G', 'name' => 'Sint-Ursmarus', 'type' => 'gemengd', 'street' => 'Broekkantstraat 108', 'postal_code' => '9200', 'city' => 'Baasrode', 'latitude' => 51.0425093, 'longitude' => 4.1598312, 'website' => 'https://www.scoutsbaasrode.be/'],
            ['district' => 'land-van-aymon', 'number' => 'O3401G', 'name' => 'Tijl Uilenspiegel', 'type' => 'gemengd', 'street' => 'Lange Minnestraat 65', 'postal_code' => '9280', 'city' => 'Lebbeke', 'latitude' => 50.9841486, 'longitude' => 4.1444745, 'website' => 'https://www.scoutslebbeke.be/'],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_groups');
    }
};
