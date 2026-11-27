<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance: organisers mark who was there (also the basis for participation certificates).
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            // When the organiser marked this registrant present; null = not marked present.
            $table->timestamp('present_at')->nullable()->after('confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('present_at');
        });
    }
};
