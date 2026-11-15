<?php

namespace Database\Seeders;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

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
    }
}
