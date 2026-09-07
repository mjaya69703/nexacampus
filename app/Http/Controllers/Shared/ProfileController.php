<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->load([
            'roles',
            'studentProfile.studyProgram.faculty',
            'studentProfile.entryAcademicYear',
            'lecturerProfile.faculty',
            'lecturerProfile.studyProgram',
            'developmentRecords.attachments',
        ]);

        $activeRole = session('active_role') ?: $user->roles->first()?->name;

        $student = $user->studentProfile ? [
            'nim' => $user->studentProfile->nim,
            'programName' => $user->studentProfile->studyProgram?->name,
            'facultyName' => $user->studentProfile->studyProgram?->faculty?->name,
            'entryYear' => $user->studentProfile->entry_year,
            'entryAcademicYear' => $user->studentProfile->entryAcademicYear?->name,
            'currentSemester' => $user->studentProfile->current_semester,
            'academicStatus' => $user->studentProfile->academic_status,
            'classType' => $user->studentProfile->class_type,
            'entryDate' => $user->studentProfile->entry_date?->format('Y-m-d'),
            'entryDateLabel' => $user->studentProfile->entry_date?->format('d M Y'),
            'graduationDate' => $user->studentProfile->graduation_date?->format('Y-m-d'),
            'isActive' => (bool) $user->studentProfile->is_active,
        ] : null;

        $lecturer = $user->lecturerProfile ? [
            'nidn' => $user->lecturerProfile->nidn,
            'nidk' => $user->lecturerProfile->nidk,
            'nip' => $user->lecturerProfile->nip,
            'facultyName' => $user->lecturerProfile->faculty?->name,
            'programName' => $user->lecturerProfile->studyProgram?->name,
            'employmentStatus' => $user->lecturerProfile->employment_status,
            'joinDate' => $user->lecturerProfile->join_date?->format('Y-m-d'),
            'joinDateLabel' => $user->lecturerProfile->join_date?->format('d M Y'),
            'isActive' => (bool) $user->lecturerProfile->is_active,
        ] : null;

        $developments = $user->developmentRecords
            ->sortByDesc('start_date')
            ->map(fn ($record) => [
                'id' => $record->id,
                'type' => $record->type,
                'typeLabel' => $this->developmentTypeLabel($record->type),
                'title' => $record->title,
                'organizer' => $record->organizer,
                'credentialNumber' => $record->credential_number,
                'startDate' => $record->start_date?->format('Y-m-d'),
                'startDateLabel' => $record->start_date?->format('d M Y'),
                'endDateLabel' => $record->end_date?->format('d M Y'),
                'expiresLabel' => $record->expires_at?->format('d M Y') ?? 'Seumur hidup',
                'isVerified' => (bool) $record->is_verified,
                'attachments' => $record->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'fileName' => $attachment->file_name,
                    'previewUrl' => route('profile.development-attachments.preview', $attachment),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $profile = [
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'instagram' => $user->instagram,
            'facebook' => $user->facebook,
            'linkedin' => $user->linkedin,
            'identityNumber' => $user->identity_number,
            'religion' => $user->religion,
            'bloodType' => $user->blood_type,
            'citizenship' => $user->citizenship,
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'placeOfBirth' => $user->place_of_birth,
            'dateOfBirth' => $user->date_of_birth
                ? (string) \Illuminate\Support\Carbon::parse($user->date_of_birth)->format('Y-m-d')
                : null,
            'dateOfBirthLabel' => $user->date_of_birth
                ? \Illuminate\Support\Carbon::parse($user->date_of_birth)->format('d M Y')
                : null,
            'photoUrl' => $user->photo,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'activeRole' => $activeRole,
            'activeRoleLabel' => $activeRole ? ucwords(str_replace('-', ' ', $activeRole)) : '-',
            'memberSince' => $user->created_at?->format('M Y'),
            'lastLogin' => $user->last_login_at?->format('d M Y H:i'),
        ];

        $completeness = $this->completeness($user);

        return Inertia::render('Shared/Profile', [
            'shell' => ShellProps::make($user, 'System', 'User Profile'),
            'profile' => $profile,
            'student' => $student,
            'lecturer' => $lecturer,
            'developments' => $developments,
            'completeness' => $completeness,
            'stats' => [
                'certifications' => collect($developments)->count(),
                'verified' => collect($developments)->where('isVerified', true)->count(),
                'pending' => collect($developments)->where('isVerified', false)->count(),
            ],
            'options' => [
                'religions' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'],
                'bloodTypes' => ['A', 'B', 'AB', 'O'],
                'citizenships' => ['WNI', 'WNA'],
                'genders' => ['Laki-laki', 'Perempuan'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'phone' => 'required|string|max:20|unique:users,phone,'.$user->id,
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'identity_number' => 'nullable|string|max:255|unique:users,identity_number,'.$user->id,
            'religion' => 'nullable|in:Islam,Kristen,Katolik,Hindu,Buddha,Khonghucu',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'citizenship' => 'nullable|in:WNI,WNA',
            'gender' => 'nullable|in:Laki-laki,Perempuan',
            'height' => 'nullable|integer|min:1|max:300',
            'weight' => 'nullable|integer|min:1|max:500',
            'place_of_birth' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
        ]);

        DB::transaction(function () use ($request, $user, $validated) {
            if ($request->hasFile('photo')) {
                $oldPhoto = $user->getRawOriginal('photo');
                $filename = 'profile_'.$user->id.'_'.time().'.'.$request->file('photo')->getClientOriginalExtension();
                $request->file('photo')->storeAs('images/profile', $filename, 'public');
                $user->photo = $filename;

                if ($oldPhoto && $oldPhoto !== 'default.jpg' && Storage::disk('public')->exists('images/profile/'.$oldPhoto)) {
                    Storage::disk('public')->delete('images/profile/'.$oldPhoto);
                }
            }

            $user->fill(collect($validated)->except('photo')->all());
            $user->save();
        });

        return redirect()->route('home.profile-index')->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string|current_password',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return redirect()->route('home.profile-index')->with('success', 'Kata sandi berhasil diubah.');
    }

    public function certificate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:certification,training,workshop,seminar,award,license',
            'title' => 'required|string|max:255',
            'organizer' => 'required|string|max:255',
            'credential_number' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'expires_at' => 'nullable|date|after:start_date',
            'description' => 'nullable|string',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $record = $request->user()->developmentRecords()->create([
                'type' => $validated['type'],
                'title' => $validated['title'],
                'organizer' => $validated['organizer'],
                'credential_number' => empty($validated['credential_number']) ? null : $validated['credential_number'],
                'start_date' => $validated['start_date'],
                'end_date' => empty($validated['end_date']) ? null : $validated['end_date'],
                'expires_at' => empty($validated['expires_at']) ? null : $validated['expires_at'],
                'description' => empty($validated['description']) ? null : $validated['description'],
                'is_verified' => false,
            ]);

            $file = $request->file('document');
            $filename = 'cert_'.$request->user()->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('private/user-developments', $filename);

            $record->attachments()->create([
                'document_type' => 'certificate',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
        });

        return redirect()->route('home.profile-index')->with('success', 'Sertifikasi berhasil diajukan dan menunggu verifikasi.');
    }

    private function developmentTypeLabel(?string $type): string
    {
        return match ($type) {
            'certification' => 'Sertifikasi',
            'training' => 'Pelatihan',
            'workshop' => 'Workshop',
            'seminar' => 'Seminar',
            'award' => 'Penghargaan',
            'license' => 'Lisensi',
            default => ucfirst((string) $type),
        };
    }

    /**
     * @return array{percent: int, missing: string[], filled: int, total: int}
     */
    private function completeness(\App\Models\User $user): array
    {
        $checks = [
            'Nama lengkap' => filled($user->first_name) && filled($user->last_name),
            'Kontak (email & telepon)' => filled($user->email) && filled($user->phone),
            'Foto profil' => ($raw = $user->getRawOriginal('photo')) && $raw !== 'default.jpg',
            'Data kelahiran' => filled($user->place_of_birth) && filled($user->date_of_birth),
            'Identitas & agama' => filled($user->identity_number) && filled($user->religion),
            'Media sosial' => filled($user->instagram) || filled($user->facebook) || filled($user->linkedin),
        ];

        $filled = collect($checks)->filter()->count();
        $total = count($checks);

        return [
            'percent' => (int) round($filled / max($total, 1) * 100),
            'missing' => collect($checks)->reject(fn ($ok) => $ok)->keys()->values()->all(),
            'filled' => $filled,
            'total' => $total,
        ];
    }
}
