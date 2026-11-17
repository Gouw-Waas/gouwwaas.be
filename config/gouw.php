<?php

/*
|--------------------------------------------------------------------------
| Gouw Waas: gouwbureau and districts
|--------------------------------------------------------------------------
*/

return [

    /*
    | The three fixed functions of the gouwbureau: key => [title, default description].
    | Admins edit the description, people and contact details on /wie-is-wie;
    | the titles and the order come from here.
    */
    'bureau' => [
        'voorzitter' => ['Gouwvoorzitter', 'Verantwoordelijk voor de coördinatie binnen de gouw.'],
        'commissaris' => ['Gouwcommissaris', 'De pedagogisch verantwoordelijke.'],
        'beheerder' => ['Gouwbeheerder', "Verantwoordelijk voor de financiën en andere beheersmatige thema's."],
    ],

    /*
    | Past activities are archived automatically this many months after they ended:
    | the activity and all its registrations are deleted permanently (no refunds, no
    | e-mails). Runs every night: php artisan activities:archive, from a cron job in the
    | hosting control panel (see README). Also mentioned in the privacy statement.
    */
    'archive_after_months' => 24,

    /*
    | The four districts. Each gets a fixed "District <name>" function (its
    | districtsploeg) on /wie-is-wie and a colour on the map on /districten.
    |
    | The scouting groups themselves are in the database (scout_groups table),
    | managed by admins on /districten.
    |
    | boundary: the district outline on the map, as a list of [latitude, longitude]
    | points (or a list of such lists for a district in several parts). null = no
    | outline yet; the district is then only reachable through the legend and list.
    */
    'districts' => [
        'klaas' => ['name' => 'Klaas', 'color' => '#e0761a', 'boundary' => null],
        'lelie' => ['name' => 'Lelie', 'color' => '#5c6a12', 'boundary' => null],
        'durme' => ['name' => 'Durme', 'color' => '#1f7a8c', 'boundary' => null],
        'land-van-aymon' => ['name' => 'Land van Aymon', 'color' => '#b23a48', 'boundary' => null],
    ],

];
