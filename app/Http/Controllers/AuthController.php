<?php

namespace App\Http\Controllers;

use App\Models\EmailOtp;
use App\Models\User;
use App\Support\FirebaseCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\Rule;
use Kreait\Firebase\Factory;
use Throwable;

class AuthController extends Controller
{
    public function requestOtp(Request $request): JsonResponse
    {
        $expiredOtps = EmailOtp::where('expires_at', '<', now())->get();
        foreach ($expiredOtps as $expiredOtp) {
            if (isset($expiredOtp->profile['_privateImage'])) {
                Storage::disk('local')->delete($expiredOtp->profile['_privateImage']);
            }
        }
        EmailOtp::where('expires_at', '<', now())->delete();

        $base = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'mode' => ['required', Rule::in(['login', 'register'])],
            'role' => ['required', Rule::in(['candidate', 'employer', 'admin'])],
        ]);
        $email = Str::lower($base['email']);
        $profile = null;

        if ($base['mode'] === 'register') {
            if ($base['role'] === 'admin') {
                return response()->json(['error' => 'Akun admin hanya dapat dibuat melalui perintah server.'], 403);
            }
            $profileRules = $base['role'] === 'candidate'
                ? [
                    'fullName' => ['required', 'string', 'min:2', 'max:100'],
                    'phone' => ['nullable', 'string', 'max:30'],
                    'location' => ['nullable', 'string', 'max:100'],
                    'headline' => ['nullable', 'string', 'max:120'],
                ]
                : [
                    'contactName' => ['required', 'string', 'min:2', 'max:100'],
                    'companyName' => ['required', 'string', 'min:2', 'max:120'],
                    'industry' => ['nullable', 'string', 'max:100'],
                    'companySize' => ['nullable', 'string', 'max:40'],
                    'website' => ['nullable', 'url', 'max:200'],
                ];
            $profileInput = $request->input('profile', []);
            if (is_string($profileInput)) {
                try {
                    $profileInput = json_decode($profileInput, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    return response()->json(['error' => 'Data profil tidak valid.'], 422);
                }
            }
            if (! is_array($profileInput)) {
                return response()->json(['error' => 'Data profil tidak valid.'], 422);
            }
            $profileInput = array_map(
                fn ($value) => $value === '' ? null : $value,
                $profileInput,
            );
            $profile = validator($profileInput, $profileRules)->validate();
            $imageField = $base['role'] === 'candidate' ? 'profilePhoto' : 'companyLogo';
            $request->validate([
                $imageField => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=2400,max_height=2400'],
            ]);
            if ($request->hasFile($imageField)) {
                $profile['_privateImage'] = Storage::disk('local')->putFile('otp-private', $request->file($imageField));
            }
        }

        $user = User::where('email', $email)->first();
        $canSend = $base['mode'] === 'register'
            ? $user === null
            : $user !== null && $user->role === $base['role'];

        if (! $canSend) {
            if (isset($profile['_privateImage'])) {
                Storage::disk('local')->delete($profile['_privateImage']);
            }
            return response()->json([
                'message' => 'Jika email dapat digunakan, kode verifikasi akan segera dikirim.',
            ], 202);
        }

        $previous = EmailOtp::where('email', $email)->first();
        if ($previous && $previous->last_sent_at->diffInSeconds(now()) < 60) {
            if (isset($profile['_privateImage'])) {
                Storage::disk('local')->delete($profile['_privateImage']);
            }
            return response()->json(['error' => 'Tunggu sebentar sebelum meminta kode baru.'], 429);
        }

        $code = (string) random_int(100000, 999999);
        $secret = (string) config('services.otp.secret');
        if (strlen($secret) < 32) {
            if (isset($profile['_privateImage'])) {
                Storage::disk('local')->delete($profile['_privateImage']);
            }
            return response()->json(['error' => 'Layanan verifikasi belum dikonfigurasi.'], 503);
        }

        $previousPrivateImage = $previous?->profile['_privateImage'] ?? null;
        EmailOtp::updateOrCreate(
            ['email' => $email],
            [
                'code_hash' => hash_hmac('sha256', $email.':'.$code, $secret),
                'mode' => $base['mode'],
                'role' => $base['role'],
                'profile' => $profile,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
                'last_sent_at' => now(),
            ],
        );
        if ($previousPrivateImage && $previousPrivateImage !== ($profile['_privateImage'] ?? null)) {
            Storage::disk('local')->delete($previousPrivateImage);
        }

        try {
            $response = Http::connectTimeout(5)->timeout(10)->withToken((string) config('services.mailtarget.key'))
                ->acceptJson()
                ->post(config('services.mailtarget.endpoint'), [
                'from' => [
                    'email' => config('mail.from.address'),
                    'name' => config('mail.from.name'),
                ],
                'to' => [['email' => $email, 'name' => '']],
                'subject' => $code.' adalah kode verifikasi JobAgent',
                'bodyText' => "Kode verifikasi JobAgent Anda: {$code}. Kode berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun. Jika Anda tidak meminta kode ini, abaikan email ini.",
                'bodyHtml' => '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Kode verifikasi JobAgent</title></head><body style="margin:0;padding:30px;background:#f1f5f0;font-family:Arial,sans-serif;color:#152d29"><div style="max-width:520px;margin:auto;padding:28px;background:#fff;border-radius:14px"><p>Halo,</p><p>Gunakan kode berikut untuk masuk ke JobAgent:</p><h1 style="letter-spacing:8px;color:#087f6e">'.$code.'</h1><p>Kode berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun.</p><p>Jika Anda tidak meminta kode ini, abaikan email ini.</p></div></body></html>',
            ]);
        } catch (ConnectionException $exception) {
            $savedOtp = EmailOtp::where('email', $email)->first();
            if ($savedOtp && isset($savedOtp->profile['_privateImage'])) {
                Storage::disk('local')->delete($savedOtp->profile['_privateImage']);
            }
            EmailOtp::where('email', $email)->delete();
            Log::error('Mailtarget connection failed while sending a JobAgent OTP.', ['exception' => $exception::class]);

            return response()->json(['error' => 'Layanan email sedang tidak merespons. Coba kembali sebentar lagi.'], 502);
        }

        if (! $response->successful()) {
            $savedOtp = EmailOtp::where('email', $email)->first();
            if ($savedOtp && isset($savedOtp->profile['_privateImage'])) {
                Storage::disk('local')->delete($savedOtp->profile['_privateImage']);
            }
            EmailOtp::where('email', $email)->delete();
            Log::error('Mailtarget rejected a JobAgent OTP email.', ['status' => $response->status()]);

            return response()->json(['error' => 'Email OTP gagal dikirim. Periksa konfigurasi email.'], 502);
        }

        return response()->json([
            'message' => 'Jika email dapat digunakan, kode verifikasi akan segera dikirim.',
        ], 202);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'code' => ['required', 'digits:6'],
            'role' => ['required', Rule::in(['candidate', 'employer', 'admin'])],
        ]);
        $email = Str::lower($data['email']);
        $result = DB::transaction(function () use ($data, $email): array {
            $otp = EmailOtp::where('email', $email)->lockForUpdate()->first();
            if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= 5 || $otp->role !== $data['role']) {
                if ($otp && ($otp->expires_at->isPast() || $otp->attempts >= 5)) {
                    if (isset($otp->profile['_privateImage'])) {
                        Storage::disk('local')->delete($otp->profile['_privateImage']);
                    }
                    $otp->delete();
                }

                return ['error' => 'Kode tidak valid atau kedaluwarsa.', 'status' => 400];
            }

            $expected = hash_hmac(
                'sha256',
                $email.':'.$data['code'],
                (string) config('services.otp.secret'),
            );
            if (! hash_equals($otp->code_hash, $expected)) {
                $otp->increment('attempts');
                if ($otp->attempts >= 5) {
                    if (isset($otp->profile['_privateImage'])) {
                        Storage::disk('local')->delete($otp->profile['_privateImage']);
                    }
                    $otp->delete();
                }

                return ['error' => 'Kode tidak valid atau kedaluwarsa.', 'status' => 400];
            }

            $user = User::where('email', $email)->lockForUpdate()->first();
            if ($otp->mode === 'register' && $user) {
                if (isset($otp->profile['_privateImage'])) {
                    Storage::disk('local')->delete($otp->profile['_privateImage']);
                }
                $otp->delete();

                return ['error' => 'Akun sudah terdaftar. Silakan masuk.', 'status' => 409];
            }
            if ($otp->mode === 'login' && (! $user || $user->role !== $otp->role)) {
                if (isset($otp->profile['_privateImage'])) {
                    Storage::disk('local')->delete($otp->profile['_privateImage']);
                }
                $otp->delete();

                return ['error' => 'Akun tidak ditemukan untuk jenis pengguna ini.', 'status' => 404];
            }

            if (! $user) {
                $profile = $otp->profile ?? [];
                $user = User::create([
                    'name' => $otp->role === 'candidate' ? $profile['fullName'] : $profile['contactName'],
                    'email' => $email,
                    'firebase_uid' => (string) Str::uuid(),
                    'role' => $otp->role,
                    'phone' => $profile['phone'] ?? null,
                    'location' => $profile['location'] ?? null,
                    'headline' => $profile['headline'] ?? null,
                    'company_name' => $profile['companyName'] ?? null,
                    'industry' => $profile['industry'] ?? null,
                    'company_size' => $profile['companySize'] ?? null,
                    'website' => $profile['website'] ?? null,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(48)),
                ]);
            }

            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $privateImage = $otp->profile['_privateImage'] ?? null;
            if ($privateImage && Storage::disk('local')->exists($privateImage)) {
                $publicPath = 'profiles/'.Str::uuid().'.'.$this->imageExtension($privateImage);
                Storage::disk('public')->put($publicPath, Storage::disk('local')->get($privateImage));
                Storage::disk('local')->delete($privateImage);
                $imageColumn = $otp->role === 'candidate' ? 'avatar_path' : 'company_logo_path';
                $user->forceFill([$imageColumn => $publicPath])->save();
            }

            $otp->delete();

            return ['user' => $user];
        });

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status']);
        }

        try {
            $firebaseAuth = (new Factory)
                ->withServiceAccount(app(FirebaseCredentials::class)->load())
                ->createAuth();
            $customToken = $firebaseAuth->createCustomToken(
                $result['user']->firebase_uid,
                ['role' => $result['user']->role],
            )->toString();
        } catch (Throwable $exception) {
            Log::error('Unable to issue Firebase custom auth token.', ['exception' => $exception::class]);

            return response()->json(['error' => 'Verifikasi berhasil, tetapi sesi Firebase gagal dibuat. Periksa konfigurasi Firebase server.'], 503);
        }

        Auth::login($result['user']);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Berhasil masuk ke JobAgent.',
            'firebaseCustomToken' => $customToken,
            'user' => $this->userData($result['user']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Anda sudah keluar dari JobAgent.']);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'companyName' => $user->company_name,
            'photoUrl' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'companyLogoUrl' => $user->company_logo_path ? Storage::disk('public')->url($user->company_logo_path) : null,
        ];
    }

    private function imageExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
    }
}
