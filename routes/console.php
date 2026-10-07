<?php

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('jobagent:make-admin {email} {name}', function (string $email, string $name): int {
    $email = Str::lower(trim($email));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Masukkan alamat email admin yang valid.');
        return Command::FAILURE;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Email ini sudah digunakan. Buat admin utama dengan email terpisah.');
        return Command::FAILURE;
    }

    User::create([
        'name' => $name,
        'email' => $email,
        'firebase_uid' => (string) Str::uuid(),
        'role' => 'admin',
        'email_verified_at' => null,
        'password' => bcrypt(Str::random(48)),
    ]);

    $this->info("Akun admin JobAgent dibuat untuk {$email}. Buat akun Firebase Authentication dengan email yang sama, verifikasi emailnya, lalu masuk dari beranda.");
    return Command::SUCCESS;
})->purpose('Create a separate JobAgent administrator account');
