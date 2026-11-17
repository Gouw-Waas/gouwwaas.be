<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capacity per activity, and registrations added (or marked paid) by organisers.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Maximum number of participants; null = no limit.
            $table->unsignedInteger('capacity')->nullable()->after('price_cents');
        });

        Schema::table('registrations', function (Blueprint $table) {
            // online: through the registration form; manual: added by an organiser.
            $table->string('source', 10)->default('online')->after('activity_id');
            // Marked paid by an organiser (cash, transfer…): not through Mollie, so never refunded through Mollie.
            $table->boolean('paid_manually')->default(false)->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['source', 'paid_manually']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
