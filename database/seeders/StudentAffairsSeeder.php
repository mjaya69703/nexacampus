<?php

namespace Database\Seeders;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\FacilityBookingStatus;
use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Enums\PointEntryCategory;
use App\Enums\StudentEventCategory;
use App\Enums\StudentEventStatus;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentEventRegistration;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Models\StudentAffairs\StudentPointEntry;
use App\Models\User;
use App\Support\StudentAffairs\StudentPointService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StudentAffairsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', 'superuser')->first();

        $fst = Faculty::query()->where('code', 'FST')->first();
        $informatika = StudyProgram::query()->where('code', 'TI')->first();
        $sistemInformasi = StudyProgram::query()->where('code', 'SI')->first();

        $advisor = User::query()->where('username', 'ari.pranata')->first();
        $advisorSi = User::query()->where('username', 'raka.mahendra')->first();

        $organizations = [
            [
                'name' => 'Badan Eksekutif Mahasiswa NexaCampus',
                'type' => OrganizationType::BEM->value,
                'faculty_id' => null,
                'study_program_id' => null,
                'advisor_id' => $advisor?->id,
                'description' => 'Lembaga eksekutif mahasiswa tingkat universitas yang menjalankan advokasi, pelayanan, dan program kerja lintas fakultas.',
                'founded_at' => '2018-09-01',
                'contact_email' => 'bem@nexacampus.ac.id',
                'contact_phone' => '081200000101',
                'instagram_url' => 'https://instagram.com/bemnexacampus',
            ],
            [
                'name' => 'Dewan Perwakilan Mahasiswa NexaCampus',
                'type' => OrganizationType::DPM->value,
                'faculty_id' => null,
                'study_program_id' => null,
                'advisor_id' => $advisor?->id,
                'description' => 'Lembaga legislatif mahasiswa yang mengawasi kinerja eksekutif dan menyalurkan aspirasi mahasiswa.',
                'founded_at' => '2018-09-01',
                'contact_email' => 'dpm@nexacampus.ac.id',
                'instagram_url' => 'https://instagram.com/dpmnexacampus',
            ],
            [
                'name' => 'Himpunan Mahasiswa Teknik Informatika',
                'type' => OrganizationType::HIMA->value,
                'faculty_id' => $fst?->id,
                'study_program_id' => $informatika?->id,
                'advisor_id' => $advisor?->id,
                'description' => 'Wadah pengembangan keilmuan, kompetisi, dan jejaring mahasiswa Teknik Informatika.',
                'founded_at' => '2019-09-01',
                'contact_email' => 'hima.ti@nexacampus.ac.id',
                'instagram_url' => 'https://instagram.com/himati.nexacampus',
            ],
            [
                'name' => 'Himpunan Mahasiswa Sistem Informasi',
                'type' => OrganizationType::HIMA->value,
                'faculty_id' => $fst?->id,
                'study_program_id' => $sistemInformasi?->id,
                'advisor_id' => $advisorSi?->id,
                'description' => 'Komunitas mahasiswa Sistem Informasi untuk pengembangan analisis bisnis dan transformasi digital.',
                'founded_at' => '2019-09-01',
                'contact_email' => 'hima.si@nexacampus.ac.id',
            ],
            [
                'name' => 'Unit Kegiatan Mahasiswa Olahraga',
                'type' => OrganizationType::UKM->value,
                'faculty_id' => null,
                'study_program_id' => null,
                'advisor_id' => $advisor?->id,
                'description' => 'Pembinaan dan penyaluran bakat olahraga mahasiswa: futsal, basket, voli, badminton, hingga e-sports.',
                'founded_at' => '2018-09-01',
                'instagram_url' => 'https://instagram.com/ukmolahraga.nexacampus',
            ],
            [
                'name' => 'Unit Kegiatan Mahasiswa Seni & Budaya',
                'type' => OrganizationType::UKM->value,
                'faculty_id' => null,
                'study_program_id' => null,
                'advisor_id' => $advisorSi?->id,
                'description' => 'Rumah bagi mahasiswa yang bergerak di bidang musik, tari, teater, dan paduan suara.',
                'founded_at' => '2018-09-01',
                'instagram_url' => 'https://instagram.com/ukmseni.nexacampus',
            ],
            [
                'name' => 'Unit Kegiatan Mahasiswa Penalaran',
                'type' => OrganizationType::UKM->value,
                'faculty_id' => null,
                'study_program_id' => null,
                'advisor_id' => $advisor?->id,
                'description' => 'Wadah riset, karya tulis ilmiah, debat, dan kompetisi penalaran mahasiswa.',
                'founded_at' => '2018-09-01',
            ],
            [
                'name' => 'Komunitas Startup NexaCampus',
                'type' => OrganizationType::COMMUNITY->value,
                'faculty_id' => null,
                'study_program_id' => null,
                'advisor_id' => $advisorSi?->id,
                'description' => 'Komunitas kewirausahaan digital untuk mahasiswa yang ingin membangun produk dan bisnis berbasis teknologi.',
                'founded_at' => '2023-02-14',
                'instagram_url' => 'https://instagram.com/startup.nexacampus',
            ],
        ];

        $organizationModels = collect($organizations)->mapWithKeys(function (array $data) use ($admin) {
            $organization = StudentOrganization::updateOrCreate(
                ['name' => $data['name']],
                [
                    ...$data,
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );

            return [$organization->name => $organization];
        });

        $members = [
            ['organization' => 'Himpunan Mahasiswa Teknik Informatika', 'username' => 'rania.putri', 'position' => MemberPosition::CHAIRMAN, 'period' => '2026/2027', 'joined_at' => '2026-08-01'],
            ['organization' => 'Himpunan Mahasiswa Teknik Informatika', 'username' => 'nadia.senior', 'position' => MemberPosition::SECRETARY, 'period' => '2026/2027', 'joined_at' => '2026-08-01'],
            ['organization' => 'Unit Kegiatan Mahasiswa Penalaran', 'username' => 'nadia.senior', 'position' => MemberPosition::COORDINATOR, 'period' => '2026/2027', 'joined_at' => '2026-08-05'],
            ['organization' => 'Unit Kegiatan Mahasiswa Olahraga', 'username' => 'dimas.saputra', 'position' => MemberPosition::CHAIRMAN, 'period' => '2026/2027', 'joined_at' => '2026-08-03'],
            ['organization' => 'Unit Kegiatan Mahasiswa Seni & Budaya', 'username' => 'citra.anjani', 'position' => MemberPosition::MEMBER, 'period' => '2026/2027', 'joined_at' => '2026-08-10'],
            ['organization' => 'Badan Eksekutif Mahasiswa NexaCampus', 'username' => 'rania.putri', 'position' => MemberPosition::COORDINATOR, 'period' => '2026/2027', 'joined_at' => '2026-08-01'],
        ];

        foreach ($members as $row) {
            $organization = $organizationModels->get($row['organization']);
            $user = User::query()->where('username', $row['username'])->first();

            if (! $organization || ! $user) {
                continue;
            }

            StudentOrganizationMember::updateOrCreate(
                [
                    'student_organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'period' => $row['period'],
                ],
                [
                    'position' => $row['position']->value,
                    'joined_at' => $row['joined_at'],
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );
        }

        $achievements = [
            [
                'username' => 'rania.putri',
                'organization' => 'Himpunan Mahasiswa Teknik Informatika',
                'title' => 'Juara 1 Hackathon Nasional NexaTech 2026',
                'category' => AchievementCategory::ACADEMIC,
                'level' => AchievementLevel::NATIONAL,
                'rank' => AchievementRank::WINNER,
                'achieved_at' => Carbon::now()->subMonths(2)->toDateString(),
                'organizer' => 'Kementerian Pendidikan Tinggi',
                'description' => 'Membangun platform analitik pembelajaran adaptif bersama tim mahasiswa Teknik Informatika.',
                'status' => AchievementStatus::VERIFIED,
                'is_featured' => true,
            ],
            [
                'username' => 'nadia.senior',
                'organization' => 'Unit Kegiatan Mahasiswa Penalaran',
                'title' => 'Juara 2 Kompetisi Data Science Nasional',
                'category' => AchievementCategory::RESEARCH,
                'level' => AchievementLevel::NATIONAL,
                'rank' => AchievementRank::RUNNER_UP,
                'achieved_at' => Carbon::now()->subMonths(4)->toDateString(),
                'organizer' => 'Asosiasi Data Science Indonesia',
                'description' => 'Menganalisis pola retensi mahasiswa menggunakan model prediktif berbasis machine learning.',
                'status' => AchievementStatus::VERIFIED,
                'is_featured' => true,
            ],
            [
                'username' => 'rania.putri',
                'organization' => 'Himpunan Mahasiswa Teknik Informatika',
                'title' => 'Finalis Lomba Desain Antarmuka Aplikasi Pendidikan',
                'category' => AchievementCategory::ARTS,
                'level' => AchievementLevel::REGIONAL,
                'rank' => AchievementRank::FINALIST,
                'achieved_at' => Carbon::now()->subMonths(6)->toDateString(),
                'organizer' => 'Forum Kreatif Kampus Jawa Barat',
                'description' => 'Merancang pengalaman belajar inklusif untuk mahasiswa dengan kebutuhan khusus.',
                'status' => AchievementStatus::VERIFIED,
                'is_featured' => false,
            ],
            [
                'username' => 'citra.anjani',
                'organization' => 'Unit Kegiatan Mahasiswa Seni & Budaya',
                'title' => 'Juara 3 Festival Paduan Suara Mahasiswa',
                'category' => AchievementCategory::ARTS,
                'level' => AchievementLevel::REGIONAL,
                'rank' => AchievementRank::THIRD_PLACE,
                'achieved_at' => Carbon::now()->subMonths(8)->toDateString(),
                'organizer' => 'Dinas Pendidikan Provinsi',
                'description' => 'Membawakan komposisi musik tradisional Nusantara dalam aransemen paduan suara modern.',
                'status' => AchievementStatus::VERIFIED,
                'is_featured' => false,
            ],
            [
                'username' => 'dimas.saputra',
                'organization' => 'Unit Kegiatan Mahasiswa Olahraga',
                'title' => 'Juara 1 Turnamen Futsal Antar Universitas',
                'category' => AchievementCategory::SPORTS,
                'level' => AchievementLevel::LOCAL,
                'rank' => AchievementRank::WINNER,
                'achieved_at' => Carbon::now()->subMonths(1)->toDateString(),
                'organizer' => 'Badan Olahraga Mahasiswa',
                'description' => 'Membawa tim futsal NexaCampus meraih juara pertama setelah pertandingan final yang ketat.',
                'status' => AchievementStatus::PENDING,
                'is_featured' => false,
            ],
            [
                'username' => 'rania.putri',
                'organization' => null,
                'title' => 'Peserta Konferensi Internasional Teknologi Pendidikan',
                'category' => AchievementCategory::RESEARCH,
                'level' => AchievementLevel::INTERNATIONAL,
                'rank' => AchievementRank::PARTICIPANT,
                'achieved_at' => Carbon::now()->subMonths(10)->toDateString(),
                'organizer' => 'Global Learning Technology Summit',
                'description' => 'Mempresentasikan poster penelitian tentang personalisasi pembelajaran berbasis data.',
                'status' => AchievementStatus::VERIFIED,
                'is_featured' => true,
            ],
        ];

        foreach ($achievements as $row) {
            $user = User::query()->where('username', $row['username'])->first();

            if (! $user) {
                continue;
            }

            $organization = $row['organization'] ? $organizationModels->get($row['organization']) : null;
            $isVerified = $row['status'] === AchievementStatus::VERIFIED;

            StudentAchievement::updateOrCreate(
                ['title' => $row['title']],
                [
                    'user_id' => $user->id,
                    'student_organization_id' => $organization?->id,
                    'category' => $row['category']->value,
                    'level' => $row['level']->value,
                    'rank' => $row['rank']->value,
                    'achieved_at' => $row['achieved_at'],
                    'organizer' => $row['organizer'],
                    'description' => $row['description'],
                    'status' => $row['status']->value,
                    'is_featured' => $isVerified && $row['is_featured'],
                    'verified_by' => $isVerified ? $admin?->id : null,
                    'verified_at' => $isVerified ? Carbon::now()->subDays(3) : null,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );
        }

        $events = [
            [
                'title' => 'Seminar Nasional Kepemimpinan Mahasiswa 2026',
                'category' => StudentEventCategory::SEMINAR,
                'organization' => 'Badan Eksekutif Mahasiswa NexaCampus',
                'organizer' => 'BEM NexaCampus',
                'location' => 'Aula Gedung B',
                'description' => 'Seminar kepemimpinan mahasiswa dengan pembicara nasional, membahas advokasi, kolaborasi lintas ormawa, dan pengembangan kapasitas pengurus.',
                'starts_at' => Carbon::now()->addDays(10)->setTime(9, 0),
                'ends_at' => Carbon::now()->addDays(10)->setTime(15, 0),
                'registration_deadline' => Carbon::now()->addDays(7)->setTime(23, 59),
                'quota' => 150,
                'points' => 15,
                'status' => StudentEventStatus::PUBLISHED,
                'is_featured' => true,
            ],
            [
                'title' => 'Workshop UI/UX untuk Organisasi',
                'category' => StudentEventCategory::WORKSHOP,
                'organization' => 'Himpunan Mahasiswa Teknik Informatika',
                'organizer' => 'HIMA Teknik Informatika',
                'location' => 'Laboratorium Komputer 203',
                'description' => 'Pelatihan praktis merancang poster, feed media sosial, dan antarmuka aplikasi organisasi menggunakan prinsip desain yang inklusif.',
                'starts_at' => Carbon::now()->addDays(14)->setTime(13, 0),
                'ends_at' => Carbon::now()->addDays(14)->setTime(16, 30),
                'registration_deadline' => Carbon::now()->addDays(12)->setTime(23, 59),
                'quota' => 40,
                'points' => 10,
                'status' => StudentEventStatus::PUBLISHED,
                'is_featured' => false,
            ],
            [
                'title' => 'Turnamen Futsal Antar Ormawa',
                'category' => StudentEventCategory::SPORTS,
                'organization' => 'Unit Kegiatan Mahasiswa Olahraga',
                'organizer' => 'UKM Olahraga',
                'location' => 'Lapangan Futsal Kampus',
                'description' => 'Turnamen futsal antar organisasi mahasiswa untuk mempererat kebersamaan dan menyalurkan bakat olahraga.',
                'starts_at' => Carbon::now()->addDays(21)->setTime(8, 0),
                'ends_at' => Carbon::now()->addDays(22)->setTime(17, 0),
                'registration_deadline' => Carbon::now()->addDays(18)->setTime(23, 59),
                'quota' => 80,
                'points' => 12,
                'status' => StudentEventStatus::PUBLISHED,
                'is_featured' => false,
            ],
            [
                'title' => 'Festival Seni Mahasiswa',
                'category' => StudentEventCategory::ARTS,
                'organization' => 'Unit Kegiatan Mahasiswa Seni & Budaya',
                'organizer' => 'UKM Seni & Budaya',
                'location' => 'Aula Gedung A',
                'description' => 'Panggung seni tahunan yang menampilkan musik, tari, teater, dan paduan suara mahasiswa.',
                'starts_at' => Carbon::now()->addDays(30)->setTime(10, 0),
                'ends_at' => Carbon::now()->addDays(30)->setTime(21, 0),
                'registration_deadline' => Carbon::now()->addDays(27)->setTime(23, 59),
                'quota' => 200,
                'points' => 20,
                'status' => StudentEventStatus::DRAFT,
                'is_featured' => false,
            ],
            [
                'title' => 'Bakti Sosial Mahasiswa',
                'category' => StudentEventCategory::SOCIAL,
                'organization' => 'Badan Eksekutif Mahasiswa NexaCampus',
                'organizer' => 'BEM NexaCampus',
                'location' => 'Desa Mitra Kampus',
                'description' => 'Kegiatan pengabdian masyarakat berupa penyuluhan literasi digital dan donor darah bersama warga sekitar.',
                'starts_at' => Carbon::now()->subDays(20)->setTime(8, 0),
                'ends_at' => Carbon::now()->subDays(20)->setTime(14, 0),
                'registration_deadline' => Carbon::now()->subDays(23)->setTime(23, 59),
                'quota' => 100,
                'points' => 15,
                'status' => StudentEventStatus::COMPLETED,
                'is_featured' => true,
            ],
            [
                'title' => 'Kompetisi Hackathon Internal NexaCampus',
                'category' => StudentEventCategory::COMPETITION,
                'organization' => 'Unit Kegiatan Mahasiswa Penalaran',
                'organizer' => 'UKM Penalaran',
                'location' => 'Laboratorium Komputer 203',
                'description' => 'Kompetisi pengembangan solusi digital selama 24 jam untuk menjawab permasalahan kampus.',
                'starts_at' => Carbon::now()->addDays(45)->setTime(8, 0),
                'ends_at' => Carbon::now()->addDays(46)->setTime(16, 0),
                'registration_deadline' => Carbon::now()->addDays(40)->setTime(23, 59),
                'quota' => 60,
                'points' => 25,
                'status' => StudentEventStatus::PUBLISHED,
                'is_featured' => true,
            ],
        ];

        $eventModels = collect($events)->mapWithKeys(function (array $data) use ($organizationModels, $admin) {
            $organization = $organizationModels->get($data['organization']);

            $payload = collect($data)
                ->except(['organization'])
                ->merge([
                    'category' => $data['category']->value,
                    'status' => $data['status']->value,
                    'student_organization_id' => $organization?->id,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ])
                ->all();

            $event = StudentEvent::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                $payload,
            );

            return [$event->title => $event];
        });

        $registrations = [
            ['event' => 'Seminar Nasional Kepemimpinan Mahasiswa 2026', 'username' => 'rania.putri', 'status' => EventRegistrationStatus::REGISTERED, 'registered_at' => Carbon::now()->subDays(3)],
            ['event' => 'Seminar Nasional Kepemimpinan Mahasiswa 2026', 'username' => 'nadia.senior', 'status' => EventRegistrationStatus::REGISTERED, 'registered_at' => Carbon::now()->subDays(2)],
            ['event' => 'Seminar Nasional Kepemimpinan Mahasiswa 2026', 'username' => 'dimas.saputra', 'status' => EventRegistrationStatus::REGISTERED, 'registered_at' => Carbon::now()->subDay()],
            ['event' => 'Workshop UI/UX untuk Organisasi', 'username' => 'rania.putri', 'status' => EventRegistrationStatus::REGISTERED, 'registered_at' => Carbon::now()->subDay()],
            ['event' => 'Workshop UI/UX untuk Organisasi', 'username' => 'citra.anjani', 'status' => EventRegistrationStatus::REGISTERED, 'registered_at' => Carbon::now()->subHours(12)],
            ['event' => 'Kompetisi Hackathon Internal NexaCampus', 'username' => 'rania.putri', 'status' => EventRegistrationStatus::REGISTERED, 'registered_at' => Carbon::now()->subHours(6)],
            ['event' => 'Bakti Sosial Mahasiswa', 'username' => 'rania.putri', 'status' => EventRegistrationStatus::ATTENDED, 'registered_at' => Carbon::now()->subDays(25), 'attended_at' => Carbon::now()->subDays(20)->setTime(9, 0)],
            ['event' => 'Bakti Sosial Mahasiswa', 'username' => 'nadia.senior', 'status' => EventRegistrationStatus::ATTENDED, 'registered_at' => Carbon::now()->subDays(24), 'attended_at' => Carbon::now()->subDays(20)->setTime(9, 0)],
            ['event' => 'Bakti Sosial Mahasiswa', 'username' => 'citra.anjani', 'status' => EventRegistrationStatus::ATTENDED, 'registered_at' => Carbon::now()->subDays(23), 'attended_at' => Carbon::now()->subDays(20)->setTime(9, 0)],
        ];

        $pointService = app(StudentPointService::class);

        foreach ($registrations as $row) {
            $event = $eventModels->get($row['event']);
            $user = User::query()->where('username', $row['username'])->first();

            if (! $event || ! $user) {
                continue;
            }

            $registration = StudentEventRegistration::updateOrCreate(
                ['student_event_id' => $event->id, 'user_id' => $user->id],
                [
                    'status' => $row['status']->value,
                    'registered_at' => $row['registered_at'],
                    'attended_at' => $row['attended_at'] ?? null,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );

            if ($row['status'] === EventRegistrationStatus::ATTENDED) {
                $pointService->awardEventAttendance($registration->refresh());
            }
        }

        $manualPoints = [
            ['username' => 'rania.putri', 'points' => 20, 'category' => PointEntryCategory::ORGANIZATION, 'description' => 'Keaktifan sebagai koordinator BEM NexaCampus'],
            ['username' => 'nadia.senior', 'points' => 15, 'category' => PointEntryCategory::ORGANIZATION, 'description' => 'Keaktifan sebagai koordinator UKM Penalaran'],
            ['username' => 'dimas.saputra', 'points' => 10, 'category' => PointEntryCategory::ORGANIZATION, 'description' => 'Keaktifan sebagai ketua UKM Olahraga'],
            ['username' => 'citra.anjani', 'points' => 8, 'category' => PointEntryCategory::ORGANIZATION, 'description' => 'Kontribusi anggota UKM Seni & Budaya'],
        ];

        foreach ($manualPoints as $row) {
            $user = User::query()->where('username', $row['username'])->first();

            if (! $user) {
                continue;
            }

            StudentPointEntry::updateOrCreate(
                ['user_id' => $user->id, 'description' => $row['description']],
                [
                    'points' => $row['points'],
                    'category' => $row['category']->value,
                    'awarded_at' => Carbon::now()->subDays(5)->toDateString(),
                    'awarded_by' => $admin?->id,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );
        }

        $bookings = [
            [
                'reservation_number' => 'RES-SEED-001',
                'room' => 'A-101',
                'organization' => 'Himpunan Mahasiswa Teknik Informatika',
                'username' => 'rania.putri',
                'title' => 'Rapat Kerja Pengurus HIMA TI',
                'purpose' => 'Menyusun program kerja dan pembagian tugas pengurus periode 2026/2027.',
                'date' => Carbon::now()->addDay()->toDateString(),
                'start' => '09:00',
                'end' => '12:00',
                'attendees' => 30,
                'status' => FacilityBookingStatus::PENDING,
            ],
            [
                'reservation_number' => 'RES-SEED-002',
                'room' => 'B-401',
                'organization' => 'Badan Eksekutif Mahasiswa NexaCampus',
                'username' => 'rania.putri',
                'title' => 'Gladi Bersih Seminar Kepemimpinan',
                'purpose' => 'Pengecekan teknis dan simulasi rundown sebelum seminar nasional.',
                'date' => Carbon::now()->addDays(9)->toDateString(),
                'start' => '13:00',
                'end' => '16:00',
                'attendees' => 45,
                'status' => FacilityBookingStatus::APPROVED,
            ],
            [
                'reservation_number' => 'RES-SEED-003',
                'room' => 'B-301',
                'organization' => 'Unit Kegiatan Mahasiswa Olahraga',
                'username' => 'dimas.saputra',
                'title' => 'Rapat Persiapan Turnamen Futsal',
                'purpose' => 'Koordinasi panitia, jadwal pertandingan, dan kebutuhan logistik.',
                'date' => Carbon::now()->addDays(3)->toDateString(),
                'start' => '16:00',
                'end' => '18:00',
                'attendees' => 25,
                'status' => FacilityBookingStatus::APPROVED,
            ],
            [
                'reservation_number' => 'RES-SEED-004',
                'room' => 'B-401',
                'organization' => 'Unit Kegiatan Mahasiswa Seni & Budaya',
                'username' => 'citra.anjani',
                'title' => 'Gladi Bersih Festival Seni',
                'purpose' => 'Latihan panggung dan pengecekan tata suara sebelum festival.',
                'date' => Carbon::now()->addDays(25)->toDateString(),
                'start' => '13:00',
                'end' => '17:00',
                'attendees' => 40,
                'status' => FacilityBookingStatus::PENDING,
            ],
        ];

        foreach ($bookings as $row) {
            $room = Room::query()->where('code', $row['room'])->first();
            $organization = $organizationModels->get($row['organization']);
            $user = User::query()->where('username', $row['username'])->first();

            if (! $room || ! $organization || ! $user) {
                continue;
            }

            $isApproved = $row['status'] === FacilityBookingStatus::APPROVED;

            RoomReservation::updateOrCreate(
                ['reservation_number' => $row['reservation_number']],
                [
                    'room_id' => $room->id,
                    'user_id' => $user->id,
                    'student_organization_id' => $organization->id,
                    'title' => $row['title'],
                    'purpose' => $row['purpose'],
                    'reservation_date' => $row['date'],
                    'start_time' => $row['start'],
                    'end_time' => $row['end'],
                    'attendee_count' => $row['attendees'],
                    'status' => $row['status']->value,
                    'approved_by' => $isApproved ? $admin?->id : null,
                    'approved_at' => $isApproved ? Carbon::now()->subDay() : null,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );
        }
    }
}
