<?php

use App\Models\User;
use App\Support\FirebaseCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;

Artisan::command('jobagent:make-admin {email} {name}', function (string $email, string $name): int {
    $email = Str::lower(trim($email));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Masukkan alamat email admin yang valid.');
        return Command::FAILURE;
    }

    $password = $this->secret('Masukkan password admin (minimal 12 karakter)');
    $confirmation = $this->secret('Ulangi password admin');
    if (! is_string($password) || strlen($password) < 12 || $password !== $confirmation) {
        $this->error('Password tidak cocok atau kurang dari 12 karakter.');
        return Command::FAILURE;
    }

    $firebaseAuth = (new Factory)
        ->withServiceAccount(app(FirebaseCredentials::class)->load())
        ->createAuth();

    try {
        $firebaseUser = $firebaseAuth->getUserByEmail($email);
    } catch (\Kreait\Firebase\Exception\Auth\UserNotFound) {
        $firebaseUser = null;
    }

    if ($firebaseUser) {
        $firebaseUser = $firebaseAuth->updateUser($firebaseUser->uid, [
            'password' => $password,
            'displayName' => $name,
            'emailVerified' => true,
            'disabled' => false,
        ]);
    } else {
        $firebaseUser = $firebaseAuth->createUser([
            'email' => $email,
            'password' => $password,
            'displayName' => $name,
            'emailVerified' => true,
            'disabled' => false,
        ]);
    }

    DB::transaction(function () use ($email, $name, $firebaseUser): void {
        $user = User::where('email', $email)->lockForUpdate()->first();
        $user ??= new User(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'firebase_uid' => $firebaseUser->uid,
            'role' => 'admin',
            'email_verified_at' => now(),
        ])->save();
    });

    $this->info("Akun admin JobAgent siap digunakan untuk {$email}. Masuk dari halaman utama dengan email dan password yang baru dimasukkan.");
    return Command::SUCCESS;
})->purpose('Create or update a Firebase-backed JobAgent administrator account');
