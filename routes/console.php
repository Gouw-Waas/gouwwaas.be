<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('user:create {name} {email}', function (string $name, string $email) {
    $user = User::create([
        'name' => $name,
        'email' => $email,
        'password' => $this->secret('Password'),
    ]);

    $this->info("User {$user->email} created.");
})->purpose('Create a user that can log in to the website');
