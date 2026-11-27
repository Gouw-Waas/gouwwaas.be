<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\CertificateSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\ManualRegistrationController;
use App\Http\Controllers\PageBlockController;
use App\Http\Controllers\ParticipantMailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScoutGroupController;
use App\Models\PageBlock;
use App\Models\ScoutGroup;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => view('home', [
    'groupCount' => ScoutGroup::count(),
    'blocks' => PageBlock::ordered()->get(),
]))->name('home');
Route::get('/districten', [DistrictController::class, 'index'])->name('districten');
Route::get('/wie-is-wie', [RoleController::class, 'index'])->name('wie-is-wie');
Route::get('/activiteiten', [ActivityController::class, 'index'])->name('activiteiten.index');
Route::view('/privacy', 'privacy')->name('privacy');

Route::middleware('auth')->group(function () {
    // Before /activiteiten/{activity}, so "nieuw" isn't read as an activity slug.
    Route::get('/activiteiten/nieuw', [ActivityController::class, 'create'])->name('activiteiten.create');
    Route::post('/activiteiten', [ActivityController::class, 'store'])->name('activiteiten.store');
    Route::get('/activiteiten/{activity}/bewerken', [ActivityController::class, 'edit'])->name('activiteiten.edit');
    Route::put('/activiteiten/{activity}', [ActivityController::class, 'update'])->name('activiteiten.update');
    Route::delete('/activiteiten/{activity}', [ActivityController::class, 'destroy'])->name('activiteiten.destroy');

    Route::get('/activiteiten/{activity}/inschrijvingen', [RegistrationController::class, 'index'])->name('activiteiten.inschrijvingen');
    Route::get('/activiteiten/{activity}/inschrijvingen.xlsx', [RegistrationController::class, 'export'])->name('activiteiten.inschrijvingen.export');
    // Organisers adding someone themselves; "nieuw" before the {registration:id} routes.
    Route::get('/activiteiten/{activity}/inschrijvingen/nieuw', [ManualRegistrationController::class, 'create'])->name('activiteiten.inschrijvingen.create');
    Route::post('/activiteiten/{activity}/inschrijvingen', [ManualRegistrationController::class, 'store'])->name('activiteiten.inschrijvingen.store');
    Route::get('/activiteiten/{activity}/deelnemers-mailen', [ParticipantMailController::class, 'create'])->name('activiteiten.mail');
    Route::post('/activiteiten/{activity}/deelnemers-mailen', [ParticipantMailController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('activiteiten.mail.send');
    // By id here (organisers only); the public registration page uses the secret token.
    Route::post('/activiteiten/{activity}/inschrijvingen/{registration:id}/betaald', [ManualRegistrationController::class, 'markPaid'])
        ->scopeBindings()
        ->name('activiteiten.inschrijvingen.paid');
    // Attendance (who was there), on the registrations overview.
    Route::post('/activiteiten/{activity}/inschrijvingen/{registration:id}/aanwezig', [AttendanceController::class, 'update'])
        ->scopeBindings()
        ->name('activiteiten.aanwezigheid');
    Route::post('/activiteiten/{activity}/aanwezig', [AttendanceController::class, 'markAll'])->name('activiteiten.aanwezigheid.alle');
    // Participation certificates (activities with vormingsuren).
    Route::get('/activiteiten/{activity}/inschrijvingen/{registration:id}/attest.pdf', [CertificateController::class, 'download'])
        ->scopeBindings()
        ->name('activiteiten.attest');
    Route::post('/activiteiten/{activity}/attesten', [CertificateController::class, 'send'])->name('activiteiten.attesten.versturen');
    Route::delete('/activiteiten/{activity}/inschrijvingen/{registration:id}', [RegistrationController::class, 'destroy'])
        ->scopeBindings()
        ->name('activiteiten.inschrijvingen.destroy');
});

Route::get('/activiteiten/{activity}', [ActivityController::class, 'show'])->name('activiteiten.show');
Route::get('/activiteiten/{activity}/agenda.ics', [ActivityController::class, 'calendar'])->name('activiteiten.agenda');
Route::post('/activiteiten/{activity}/inschrijven', [RegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('activiteiten.inschrijven');

// A registration's own page (by its secret token): checkout, return from Mollie, confirmation.
Route::get('/inschrijvingen/{registration}', [CheckoutController::class, 'show'])->name('inschrijvingen.show');
Route::post('/inschrijvingen/{registration}/betalen', [CheckoutController::class, 'pay'])
    ->middleware('throttle:10,1')
    ->name('inschrijvingen.betalen');

// Mollie reports payment changes here (no CSRF token: see bootstrap/app.php).
Route::post('/mollie/webhook', [CheckoutController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->name('mollie.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/wachtwoord-vergeten', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/wachtwoord-vergeten', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/wachtwoord-herstellen/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/wachtwoord-herstellen', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/profiel', [ProfileController::class, 'show'])->name('profiel');
    Route::put('/profiel/wachtwoord', [ProfileController::class, 'updatePassword'])->name('profiel.wachtwoord');

    Route::middleware('can:admin')->group(function () {
        Route::resource('gebruikers', UserController::class)
            ->except('show')
            ->parameters(['gebruikers' => 'gebruiker']);
        Route::post('/gebruikers/{gebruiker}/wachtwoord-reset', [UserController::class, 'forcePasswordReset'])
            ->name('gebruikers.wachtwoord-reset');

        // Past activities: delete permanently, without refunds or e-mails.
        Route::post('/activiteiten/{activity}/archiveren', [ActivityController::class, 'archive'])->name('activiteiten.archive');

        // District logos and scouting groups on the map (/districten).
        Route::get('/districten/{district}/bewerken', [DistrictController::class, 'edit'])->name('districten.edit');
        Route::put('/districten/{district}', [DistrictController::class, 'update'])->name('districten.update');
        Route::get('/districten/groepen/nieuw', [ScoutGroupController::class, 'create'])->name('groepen.create');
        Route::post('/districten/groepen', [ScoutGroupController::class, 'store'])->name('groepen.store');
        Route::get('/districten/groepen/{scoutGroup}/bewerken', [ScoutGroupController::class, 'edit'])->name('groepen.edit');
        Route::put('/districten/groepen/{scoutGroup}', [ScoutGroupController::class, 'update'])->name('groepen.update');
        Route::delete('/districten/groepen/{scoutGroup}', [ScoutGroupController::class, 'destroy'])->name('groepen.destroy');

        // Text blocks on the home page ("Gouw watte?", "En wat doen jullie dan?"…).
        Route::get('/blokken/nieuw', [PageBlockController::class, 'create'])->name('blokken.create');
        Route::post('/blokken', [PageBlockController::class, 'store'])->name('blokken.store');
        Route::get('/blokken/{block}/bewerken', [PageBlockController::class, 'edit'])->name('blokken.edit');
        Route::put('/blokken/{block}', [PageBlockController::class, 'update'])->name('blokken.update');
        Route::delete('/blokken/{block}', [PageBlockController::class, 'destroy'])->name('blokken.destroy');

        // Participation certificates: stamp, signers and a preview.
        Route::get('/beheer/attest', [CertificateSettingsController::class, 'edit'])->name('attest.instellingen');
        Route::put('/beheer/attest', [CertificateSettingsController::class, 'update'])->name('attest.instellingen.update');
        Route::get('/beheer/attest/voorbeeld.pdf', [CertificateSettingsController::class, 'preview'])->name('attest.voorbeeld');

        // Wie is wie: only adjuncten can be added or removed (checked in the controller).
        Route::get('/wie-is-wie/adjuncten/nieuw', [RoleController::class, 'create'])->name('wie-is-wie.create');
        Route::post('/wie-is-wie', [RoleController::class, 'store'])->name('wie-is-wie.store');
        Route::get('/wie-is-wie/{role}/bewerken', [RoleController::class, 'edit'])->name('wie-is-wie.edit');
        Route::put('/wie-is-wie/{role}', [RoleController::class, 'update'])->name('wie-is-wie.update');
        Route::delete('/wie-is-wie/{role}', [RoleController::class, 'destroy'])->name('wie-is-wie.destroy');
    });
});
