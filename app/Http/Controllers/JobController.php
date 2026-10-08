<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user && ! $request->boolean('public')) {
            return redirect()->route($user->role === 'admin' ? 'admin.dashboard' : 'dashboard');
        }

        $jobs = Job::with('employer:id,company_name,company_logo_path')
            ->where('status', 'active')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Job $job): array => $this->jobData($job));

        return view('home', [
            'jobs' => $jobs,
            'firebaseConfig' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'storageBucket' => config('services.firebase.storage_bucket'),
                'messagingSenderId' => config('services.firebase.messaging_sender_id'),
                'appId' => config('services.firebase.app_id'),
                'measurementId' => config('services.firebase.measurement_id'),
            ],
            'currentUser' => $this->userData(auth()->user()),
        ]);
    }

    public function indexJson(): JsonResponse
    {
        $jobs = Job::with('employer:id,company_name,company_logo_path')
            ->where('status', 'active')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Job $job): array => $this->jobData($job));

        return response()->json(['jobs' => $jobs]);
    }

    public function applicationsPage(Request $request)
    {
        if (! $request->user()) {
            return redirect()->route('home', ['login' => 'account']);
        }

        if ($request->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return view('applications', [
            'user' => $request->user(),
            'firebaseConfig' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'appId' => config('services.firebase.app_id'),
            ],
        ]);
    }

    public function applications(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['candidate', 'employer'], true)) {
            return response()->json(['error' => 'Halaman ini hanya untuk kandidat dan perusahaan.'], 403);
        }

        $applications = Application::query()
            ->with(['job.employer', 'candidate'])
            ->when(
                $user->role === 'candidate',
                fn ($query) => $query->where('candidate_id', $user->id),
                fn ($query) => $query->whereHas('job', fn ($jobs) => $jobs->where('employer_id', $user->id)),
            )
            ->latest()
            ->get()
            ->map(function (Application $application) use ($user): array {
                $job = $application->job;
                $data = [
                    'id' => $application->id,
                    'status' => $application->status,
                    'jobTitle' => $job->title,
                    'appliedAt' => $application->created_at?->toIso8601String(),
                    'updatedAt' => $application->updated_at?->toIso8601String(),
                ];

                if ($user->role === 'candidate') {
                    return [
                        ...$data,
                        'companyName' => $job->employer?->company_name ?? 'Perusahaan JobAgent',
                        'location' => $job->location,
                    ];
                }

                return [
                    ...$data,
                    'candidate' => [
                        'name' => $application->candidate->name,
                        'email' => $application->candidate->email,
                        'phone' => $application->candidate->phone,
                        'location' => $application->candidate->location,
                        'headline' => $application->candidate->headline,
                    ],
                ];
            });

        return response()->json(['applications' => $applications]);
    }

    public function updateApplicationStatus(Request $request, Application $application): JsonResponse
    {
        if ($request->user()->role !== 'employer') {
            return response()->json(['error' => 'Hanya perusahaan yang dapat memperbarui status lamaran.'], 403);
        }

        if ($application->job()->where('employer_id', $request->user()->id)->doesntExist()) {
            return response()->json(['error' => 'Lamaran tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'reviewing', 'interview', 'rejected', 'hired'])],
        ]);
        $application->update($data);

        return response()->json(['message' => 'Status lamaran berhasil diperbarui.']);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'employer') {
            return response()->json(['error' => 'Fitur ini khusus perusahaan.'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'location' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote'])],
            'salary' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
        ]);

        $job = $request->user()->jobs()->create($data);

        return response()->json(['job' => $this->jobData($job->load('employer:id,company_name,company_logo_path'))], 201);
    }

    public function updateJob(Request $request, Job $job): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'employer' || $job->employer_id !== $user->id) {
            return response()->json(['error' => 'Lowongan tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'location' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote'])],
            'salary' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'closed'])],
        ]);

        $job->update($data);

        return response()->json([
            'message' => 'Lowongan berhasil diperbarui.',
            'job' => $this->jobData($job->loadCount('applications')),
        ]);
    }

    public function destroyJob(Request $request, Job $job): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'employer' || $job->employer_id !== $user->id) {
            return response()->json(['error' => 'Lowongan tidak ditemukan.'], 404);
        }

        $job->delete();

        return response()->json(['message' => 'Lowongan berhasil dihapus.']);
    }

    public function apply(Request $request, Job $job): JsonResponse
    {
        if ($request->user()->role !== 'candidate') {
            return response()->json(['error' => 'Hanya pencari kerja yang dapat melamar.'], 403);
        }
        if ($job->status !== 'active') {
            return response()->json(['error' => 'Lowongan sudah tidak tersedia.'], 404);
        }
        if (Application::where('job_id', $job->id)->where('candidate_id', $request->user()->id)->exists()) {
            return response()->json(['error' => 'Anda sudah melamar lowongan ini.'], 409);
        }

        Application::create([
            'job_id' => $job->id,
            'candidate_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Lamaran berhasil dikirim.'], 201);
    }

    private function jobData(Job $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'companyName' => $job->employer?->company_name ?? 'Perusahaan JobAgent',
            'companyLogo' => $job->employer?->company_logo_path ? Storage::disk('public')->url($job->employer->company_logo_path) : null,
            'location' => $job->location,
            'type' => $job->type,
            'salary' => $job->salary,
            'category' => $job->category,
            'description' => $job->description,
            'status' => $job->status,
            'applicationsCount' => $job->applications_count ?? 0,
            'createdAt' => $job->created_at?->toIso8601String(),
        ];
    }

    private function userData($user): ?array
    {
        if (! $user) {
            return null;
        }

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
