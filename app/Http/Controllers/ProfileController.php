<?php

namespace App\Http\Controllers;

use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $isEmployer = $user->role === 'employer';

        if (! in_array($user->role, ['candidate', 'employer'], true)) {
            return response()->json(['error' => 'Fitur ini hanya untuk kandidat dan perusahaan.'], 403);
        }

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:100'],
            'headline' => ['nullable', 'string', 'max:120'],
        ];

        if ($isEmployer) {
            $rules += [
                'company_name' => ['required', 'string', 'min:2', 'max:120'],
                'industry' => ['nullable', 'string', 'max:100'],
                'company_size' => ['nullable', 'string', 'max:40'],
                'website' => ['nullable', 'url', 'max:200'],
                'companyLogo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=2400,max_height=2400'],
            ];
        } else {
            $rules['avatarPhoto'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=2400,max_height=2400'];
        }

        $data = $request->validate($rules);
        $imageField = $isEmployer ? 'companyLogo' : 'avatarPhoto';
        $imageColumn = $isEmployer ? 'company_logo_path' : 'avatar_path';

        if ($request->hasFile($imageField)) {
            $oldPath = $user->{$imageColumn};
            $data[$imageColumn] = $request->file($imageField)->store('profiles', 'public');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
        }
        unset($data[$imageField]);

        $user->fill($data)->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'location' => $user->location,
                'headline' => $user->headline,
                'companyName' => $user->company_name,
                'industry' => $user->industry,
                'companySize' => $user->company_size,
                'website' => $user->website,
                'photoUrl' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                'companyLogoUrl' => $user->company_logo_path ? Storage::disk('public')->url($user->company_logo_path) : null,
            ],
        ]);
    }

    public function saveCv(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'candidate') {
            return response()->json(['error' => 'CV online hanya untuk kandidat.'], 403);
        }

        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array', 'max:40'],
            'skills.*' => ['string', 'max:60'],
            'experiences' => ['nullable', 'array', 'max:20'],
            'experiences.*.title' => ['required', 'string', 'max:120'],
            'experiences.*.company' => ['required', 'string', 'max:120'],
            'experiences.*.location' => ['nullable', 'string', 'max:100'],
            'experiences.*.start' => ['nullable', 'string', 'max:20'],
            'experiences.*.end' => ['nullable', 'string', 'max:20'],
            'experiences.*.description' => ['nullable', 'string', 'max:1000'],
            'educations' => ['nullable', 'array', 'max:20'],
            'educations.*.school' => ['required', 'string', 'max:140'],
            'educations.*.major' => ['nullable', 'string', 'max:120'],
            'educations.*.start' => ['nullable', 'string', 'max:20'],
            'educations.*.end' => ['nullable', 'string', 'max:20'],
            'links' => ['nullable', 'array', 'max:10'],
            'links.*.label' => ['required', 'string', 'max:60'],
            'links.*.url' => ['required', 'url', 'max:300'],
        ]);

        $profile = CandidateProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->fill($data)->save();

        return response()->json([
            'message' => 'CV online berhasil disimpan.',
            'cv' => $this->cvData($profile),
        ]);
    }

    public function uploadCv(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'candidate') {
            return response()->json(['error' => 'CV online hanya untuk kandidat.'], 403);
        }

        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $profile = CandidateProfile::firstOrCreate(['user_id' => $user->id]);
        if ($profile->cv_path) {
            Storage::disk('public')->delete($profile->cv_path);
        }
        $profile->cv_path = $request->file('document')->store('cv', 'public');
        $profile->save();

        return response()->json([
            'message' => 'Dokumen CV berhasil diunggah.',
            'cvUrl' => Storage::disk('public')->url($profile->cv_path),
        ]);
    }

    public function deleteCv(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'candidate') {
            return response()->json(['error' => 'CV online hanya untuk kandidat.'], 403);
        }

        $profile = CandidateProfile::where('user_id', $user->id)->first();
        if ($profile && $profile->cv_path) {
            Storage::disk('public')->delete($profile->cv_path);
            $profile->cv_path = null;
            $profile->save();
        }

        return response()->json(['message' => 'Dokumen CV berhasil dihapus.']);
    }

    private function cvData(CandidateProfile $profile): array
    {
        return [
            'summary' => $profile->summary,
            'skills' => $profile->skills ?? [],
            'experiences' => $profile->experiences ?? [],
            'educations' => $profile->educations ?? [],
            'links' => $profile->links ?? [],
            'cvPath' => $profile->cv_path ? Storage::disk('public')->url($profile->cv_path) : null,
        ];
    }
}
