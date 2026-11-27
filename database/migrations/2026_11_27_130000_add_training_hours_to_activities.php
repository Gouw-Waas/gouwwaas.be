<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vormingsuren: printed on participation certificates. null = no certificates.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('training_hours', 4, 1)->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('training_hours');
        });
    }
};
