<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akabe (scouting for children and young people with a disability), separate from the type:
     * a whole group can be Akabe, or a group can have an Akabe branch.
     */
    public function up(): void
    {
        Schema::table('scout_groups', function (Blueprint $table) {
            // null | groep | tak
            $table->string('akabe', 10)->nullable()->after('type');
            // e.g. "Akabetak De Durpers, 8-25 jaar"
            $table->string('akabe_note')->nullable()->after('akabe');
        });
    }

    public function down(): void
    {
        Schema::table('scout_groups', function (Blueprint $table) {
            $table->dropColumn(['akabe', 'akabe_note']);
        });
    }
};
