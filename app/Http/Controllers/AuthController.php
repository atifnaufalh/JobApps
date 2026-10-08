<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\FirebaseCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Kreait\Firebase\Factory;
use Throwable;

class AuthController extends Controller
{
    public function passwordResetPage(Request $request)
    {
        return response()->view('password-reset', [
            'resetComplete' => $request->query('status') === 'complete',
        ])->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function firebaseSession(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['error' => 'Sesi masuk tidak ditemukan. Silakan coba lagi.'], 401);
        }

        try {
            $verifiedToken = (new Factory)
                ->withServiceAccount(app(FirebaseCredentials::class)->load())
                ->createAuth()
                ->verifyIdToken($token);
        } catch (InvalidArgumentException) {
            Log::error('Firebase credentials are unavailable for ID token verification.');

            return response()->json(['error' => 'Sistem masuk belum dikonfigurasi di server. Coba lagi nanti.'], 503);
        } catch (Throwable $exception) {
            Log::warning('Firebase ID token verification failed.', ['exception' => $exception::class]);

            return response()->json(['error' => 'Sesi tidak valid atau sudah kedaluwarsa. Silakan masuk kembali.'], 401);
        }

        $claims = $verifiedToken->claims();
        $email = $claims->get('email');
        $firebaseUid = $claims->get('sub');
        $emailVerified = $claims->get('email_verified') === true;
        if (! is_string($email) || ! is_string($firebaseUid) || $email === '') {
            return response()->json(['error' => 'Sesi masuk tidak memiliki email yang valid.'], 401);
        }
        $email = Str::lower($email);

        $data = $request->validate([
            'mode' => ['required', Rule::in(['login', 'register'])],
            'role' => ['required_if:mode,register', 'nullable', Rule::in(['candidate', 'employer'])],
        ]);
        $profile = [];
        if ($data['mode'] === 'register') {
            $input = $request->input('profile', []);
            if (is_string($input)) {
                try {
                    $input = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    return response()->json(['error' => 'Data profil tidak valid.'], 422);
                }
            }
            if (! is_array($input)) {
                return response()->json(['error' => 'Data profil tidak valid.'], 422);
            }
            $input = array_map(fn ($value) => $value === '' ? null : $value, $input);
            $rules = $data['role'] === 'candidate'
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
            $profile = validator($input, $rules)->validate();
            $imageField = $data['role'] === 'candidate' ? 'profilePhoto' : 'companyLogo';
            $request->validate([
                $imageField => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=2400,max_height=2400'],
            ]);
        }

        $isAdmin = $data['mode'] === 'login' && $claims->get('admin') === true;
        $claimName = $claims->get('name');
        $claimName = is_string($claimName) && $claimName !== '' ? $claimName : null;
        if (! $isAdmin && $data['mode'] === 'login' && ! User::where('email', $email)->exists()) {
            $isAdmin = $this->firebaseHasAdminClaim($firebaseUid);
        }

        if ($data['mode'] === 'login' && ! $emailVerified) {
            return response()->json(['error' => 'Verifikasi email Anda sebelum masuk.'], 403);
        }

        $user = DB::transaction(function () use ($data, $email, $firebaseUid, $emailVerified, $profile, $request, $isAdmin, $claimName): ?User {
            $user = User::where('email', $email)->lockForUpdate()->first();
            $uidOwner = User::where('firebase_uid', $firebaseUid)->first();
            if ($uidOwner && (! $user || $uidOwner->id !== $user->id)) {
                return null;
            }

            if ($user && ! $emailVerified) {
                return $user;
            }

            if (! $user && $data['mode'] === 'login' && ! $isAdmin) {
                return null;
            }

            if (! $user) {
                $role = $isAdmin ? 'admin' : $data['role'];
                $user = User::create([
                    'name' => $isAdmin
                        ? ($claimName ?: Str::before($email, '@'))
                        : ($role === 'candidate' ? $profile['fullName'] : $profile['contactName']),
                    'email' => $email,
                    'firebase_uid' => $firebaseUid,
                    'role' => $role,
                    'phone' => $profile['phone'] ?? null,
                    'location' => $profile['location'] ?? null,
                    'headline' => $profile['headline'] ?? null,
                    'company_name' => $profile['companyName'] ?? null,
                    'industry' => $profile['industry'] ?? null,
                    'company_size' => $profile['companySize'] ?? null,
                    'website' => $profile['website'] ?? null,
                    'email_verified_at' => $emailVerified ? now() : null,
                    'password' => Hash::make(Str::random(48)),
                ]);

                $imageField = $role === 'candidate' ? 'profilePhoto' : 'companyLogo';
                if ($request->hasFile($imageField)) {
                    $imagePath = $request->file($imageField)->store('profiles', 'public');
                    $imageColumn = $role === 'candidate' ? 'avatar_path' : 'company_logo_path';
                    $user->forceFill([$imageColumn => $imagePath])->save();
                }
            }

            $changes = [];
            if ($isAdmin && $user->role !== 'admin') {
                $changes['role'] = 'admin';
            }
            if ($user->firebase_uid !== $firebaseUid) {
                $changes['firebase_uid'] = $firebaseUid;
            }
            if ($emailVerified && ! $user->email_verified_at) {
                $changes['email_verified_at'] = now();
            }
            if ($changes) {
                $user->forceFill($changes)->save();
            }

            return $user;
        });

        if (! $user) {
            return response()->json([
                'error' => $data['mode'] === 'register'
                    ? 'Email ini sudah terhubung ke akun lain.'
                    : 'Akun JobAgent untuk email ini belum terdaftar.',
            ], $data['mode'] === 'register' ? 409 : 404);
        }

        if (! $emailVerified) {
            return response()->json([
                'message' => 'Akun dibuat. Periksa email Anda untuk tautan verifikasi sebelum masuk.',
                'verificationRequired' => true,
            ], 202);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Berhasil masuk ke JobAgent.',
            'user' => $this->userData($user),
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

    private function firebaseHasAdminClaim(string $firebaseUid): bool
    {
        try {
            $claims = (new Factory)
                ->withServiceAccount(app(FirebaseCredentials::class)->load())
                ->createAuth()
                ->getUser($firebaseUid)
                ->customClaims();

            return ($claims['admin'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
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
}
