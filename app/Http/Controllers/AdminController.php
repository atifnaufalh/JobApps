<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AdminAuditLog;
use App\Models\Job;
use App\Models\User;
use App\Support\FirebaseCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', [$like]);
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin-dashboard', [
            'users' => $users,
            'search' => $search,
            'stats' => [
                'users' => User::count(),
                'candidates' => User::where('role', 'candidate')->count(),
                'employers' => User::where('role', 'employer')->count(),
                'jobs' => Job::count(),
                'applications' => Application::count(),
            ],
            'admin' => $request->user(),
            'activity' => AdminAuditLog::latest()->limit(8)->get(),
            'firebaseConfig' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'storageBucket' => config('services.firebase.storage_bucket'),
                'messagingSenderId' => config('services.firebase.messaging_sender_id'),
                'appId' => config('services.firebase.app_id'),
                'measurementId' => config('services.firebase.measurement_id'),
            ],
        ]);
    }

    public function health(): JsonResponse
    {
        $checks = [
            'database' => 'ok',
            'firebase' => 'ok',
            'email' => 'ok',
        ];

        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $checks['database'] = 'error';
        }

        try {
            app(FirebaseCredentials::class)->load();
        } catch (\InvalidArgumentException) {
            $checks['firebase'] = 'error';
        }
        if (! config('services.mailtarget.key') || ! config('mail.from.address')) {
            $checks['email'] = 'error';
        }

        $healthy = ! in_array('error', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'checkedAt' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    public function users(): JsonResponse
    {
        return response()->json([
            'users' => User::latest()->paginate(100, ['id', 'name', 'email', 'role', 'phone', 'location', 'headline', 'company_name', 'industry', 'company_size', 'website', 'avatar_path', 'company_logo_path', 'email_verified_at', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'role' => ['required', Rule::in(['candidate', 'employer'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:100'],
            'headline' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:200'],
        ]);

        if ($data['role'] === 'employer' && empty($data['company_name'])) {
            throw ValidationException::withMessages(['company_name' => 'Nama perusahaan wajib diisi untuk akun perusahaan.']);
        }
        $user = User::create([
            ...$data,
            'email' => Str::lower($data['email']),
            'firebase_uid' => (string) Str::uuid(),
            'password' => bcrypt(Str::random(48)),
        ]);
        AdminAuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'user.created',
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'metadata' => ['role' => $user->role],
        ]);

        return response()->json(['user' => $this->userData($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json(['error' => 'Akun admin dikelola melalui akses server dan tidak dapat diedit dari dashboard.'], 403);
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'required', Rule::in(['candidate', 'employer'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:100'],
            'headline' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:200'],
        ]);
        $role = $data['role'] ?? $user->role;
        if ($role === 'employer' && empty($data['company_name'] ?? $user->company_name)) {
            throw ValidationException::withMessages(['company_name' => 'Nama perusahaan wajib diisi untuk akun perusahaan.']);
        }
        $emailChanged = isset($data['email']) && Str::lower($data['email']) !== $user->email;
        if ($emailChanged) {
            $data['email'] = Str::lower($data['email']);
        }
        $changedFields = array_keys($data);
        $user->update($data);
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
            $changedFields[] = 'email_verified_at';
        }
        AdminAuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'user.updated',
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'metadata' => ['fields' => $changedFields],
        ]);

        return response()->json(['user' => $this->userData($user->refresh())]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->role === 'admin' || $user->is($request->user())) {
            return response()->json(['error' => 'Akun admin aktif tidak dapat dihapus dari dashboard.'], 403);
        }

        foreach ([$user->avatar_path, $user->company_logo_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }
        AdminAuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'user.deleted',
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'metadata' => ['role' => $user->role],
        ]);
        $user->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'location' => $user->location,
            'headline' => $user->headline,
            'companyName' => $user->company_name,
            'industry' => $user->industry,
            'companySize' => $user->company_size,
            'website' => $user->website,
            'emailVerified' => (bool) $user->email_verified_at,
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
