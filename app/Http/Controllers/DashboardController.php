<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('home', ['login' => 'account']);
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        $cv = CandidateProfile::firstOrCreate(['user_id' => $user->id]);

        return view('dashboard', [
            'user' => $user,
            'cv' => $cv,
            'cvCompletion' => $this->cvCompletion($user, $cv),
            'firebaseConfig' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'appId' => config('services.firebase.app_id'),
            ],
            'avatarUrl' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'companyLogoUrl' => $user->company_logo_path ? Storage::disk('public')->url($user->company_logo_path) : null,
            'cvUrl' => $cv->cv_path ? Storage::disk('public')->url($cv->cv_path) : null,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['candidate', 'employer'], true)) {
            return response()->json(['error' => 'Dashboard ini hanya untuk kandidat dan perusahaan.'], 403);
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
            ->map(fn (Application $application): array => $this->applicationData($application, $user));

        $stats = $user->role === 'candidate'
            ? $this->candidateStats($user, $applications)
            : $this->employerStats($user, $applications);

        $jobs = [];
        if ($user->role === 'employer') {
            $jobs = Job::withCount('applications')
                ->where('employer_id', $user->id)
                ->latest()
                ->get()
                ->map(fn (Job $job): array => $this->jobData($job));
        }

        $cv = null;
        if ($user->role === 'candidate') {
            $cv = CandidateProfile::firstOrCreate(['user_id' => $user->id]);
        }

        return response()->json([
            'stats' => $stats,
            'applications' => $applications->values(),
            'jobs' => $jobs,
            'cvCompletion' => $cv ? $this->cvCompletion($user, $cv) : null,
        ]);
    }

    private function applicationData(Application $application, User $user): array
    {
        $job = $application->job;
        $data = [
            'id' => $application->id,
            'status' => $application->status,
            'jobTitle' => $job->title,
            'jobId' => $job->id,
            'appliedAt' => $application->created_at?->toIso8601String(),
            'updatedAt' => $application->updated_at?->toIso8601String(),
        ];

        if ($user->role === 'candidate') {
            return [
                ...$data,
                'companyName' => $job->employer?->company_name ?? 'Perusahaan JobAgent',
                'location' => $job->location,
                'type' => $job->type,
            ];
        }

        return [
            ...$data,
            'location' => $job->location,
            'candidate' => [
                'name' => $application->candidate->name,
                'email' => $application->candidate->email,
                'phone' => $application->candidate->phone,
                'location' => $application->candidate->location,
                'headline' => $application->candidate->headline,
            ],
        ];
    }

    private function candidateStats(User $user, $applications): array
    {
        $count = fn (string $status): int => $applications->where('status', $status)->count();

        return [
            ['key' => 'sent', 'label' => 'Lamaran dikirim', 'value' => $applications->count(), 'icon' => '↗', 'note' => 'Total lamaranmu'],
            ['key' => 'reviewing', 'label' => 'Sedang ditinjau', 'value' => $count('reviewing') + $count('submitted'), 'icon' => '◔', 'note' => 'Menunggu tim perusahaan'],
            ['key' => 'interview', 'label' => 'Wawancara', 'value' => $count('interview'), 'icon' => '◉', 'note' => 'Lanjut ke tahap berikutnya'],
            ['key' => 'hired', 'label' => 'Diterima', 'value' => $count('hired'), 'icon' => '✓', 'note' => 'Selamat!'],
        ];
    }

    private function employerStats(User $user, $applications): array
    {
        $jobs = Job::where('employer_id', $user->id);
        $activeJobs = (clone $jobs)->where('status', 'active')->count();
        $interviews = $applications->where('status', 'interview')->count();
        $newThisWeek = $applications->filter(
            fn (array $application) => $application['appliedAt'] !== null
                && \Illuminate\Support\Carbon::parse($application['appliedAt'])->gte(now()->subDays(7))
        )->count();

        return [
            ['key' => 'activeJobs', 'label' => 'Lowongan aktif', 'value' => $activeJobs, 'icon' => '▣', 'note' => 'Sedang menerima lamaran'],
            ['key' => 'totalJobs', 'label' => 'Total lowongan', 'value' => (clone $jobs)->count(), 'icon' => '▤', 'note' => 'Semua lowonganmu'],
            ['key' => 'applications', 'label' => 'Lamaran masuk', 'value' => $applications->count(), 'icon' => '♧', 'note' => $newThisWeek.' baru 7 hari terakhir'],
            ['key' => 'interview', 'label' => 'Wawancara', 'value' => $interviews, 'icon' => '◉', 'note' => 'Kandidat tahap lanjut'],
        ];
    }

    private function jobData(Job $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
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

    public function cvCompletion(User $user, CandidateProfile $cv): int
    {
        if ($user->role !== 'candidate') {
            return 0;
        }

        $score = 0;
        if ($user->avatar_path) {
            $score += 10;
        }
        if ($user->headline) {
            $score += 5;
        }
        if ($user->phone && $user->location) {
            $score += 10;
        }
        if (filled($cv->summary) && mb_strlen(trim($cv->summary)) >= 80) {
            $score += 15;
        }
        if (count($cv->skills ?? []) >= 3) {
            $score += 15;
        }
        if (count($cv->experiences ?? []) >= 1) {
            $score += 20;
        }
        if (count($cv->educations ?? []) >= 1) {
            $score += 15;
        }
        if (count($cv->links ?? []) >= 1) {
            $score += 5;
        }
        if ($cv->cv_path) {
            $score += 5;
        }

        return min($score, 100);
    }
}
