<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logos for scouting groups and districts, shown in the sidebar of the map on /districten.
     */
    public function up(): void
    {
        Schema::table('scout_groups', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('website');
        });

        // The districts themselves (name, colour, outline) are in config/gouw.php; this table
        // holds what admins change on the website, keyed by the same district key.
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');

        Schema::table('scout_groups', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
