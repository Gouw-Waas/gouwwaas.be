<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organisation's name and address, edited by admins on /organisatie. One row; until an
 * admin saves the page there is none and the defaults in config/gouw.php are used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('full_name');
            $table->string('building')->nullable();
            $table->string('street');
            $table->string('city');
            $table->string('email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation');
    }
};
