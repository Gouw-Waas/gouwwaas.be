<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Online payments (Mollie) and confirmation e-mails for registrations.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            // In the address of the registration's own page (checkout / confirmation), instead
            // of the guessable id, so nobody can look at other people's registrations.
            $table->string('token', 64)->nullable()->unique()->after('id');
            // The price at the moment of registering; 0 = free.
            $table->unsignedInteger('amount_cents')->default(0)->after('answers');
            // Mollie's status of the latest payment: open, pending, authorized, paid, failed,
            // canceled or expired. null = no payment started (yet), or a free activity.
            $table->string('payment_status', 20)->nullable()->after('amount_cents');
            $table->string('mollie_payment_id', 50)->nullable()->index()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('mollie_payment_id');
            $table->timestamp('confirmation_sent_at')->nullable()->after('paid_at');
        });

        // Registrations from before: free, confirmed, no mail to send anymore.
        DB::table('registrations')->whereNull('token')->orderBy('id')->each(function (object $registration) {
            DB::table('registrations')->where('id', $registration->id)->update([
                'token' => Str::random(40),
                'confirmation_sent_at' => $registration->created_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropIndex(['mollie_payment_id']);
            $table->dropColumn(['token', 'amount_cents', 'payment_status', 'mollie_payment_id', 'paid_at', 'confirmation_sent_at']);
        });
    }
};
