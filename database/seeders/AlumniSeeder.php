<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniEventParticipant;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AlumniSeeder extends Seeder
{
    private ?User $admin = null;
    private ?Faculty $faculty = null;
    private ?StudyProgram $studyProgram = null;
    private ?AcademicYear $academicYear = null;

    public function run(): void
    {
        if (! $this->bootContext()) {
            return;
        }

        $alumniUser = $this->seedAlumniUser();
        $profiles = $this->seedAlumniProfiles($alumniUser);
        $employers = $this->seedEmployerPartners();
        $this->seedJobPostings($employers);
        $events = $this->seedAlumniEvents();
        $this->seedEventParticipants($events, $profiles);
        $this->seedTracerStudyCampaign($profiles);
    }

    private function bootContext(): bool
    {
        $this->admin = User::query()->where('email', 'superuser@example.com')->first();
        $this->faculty = Faculty::query()->where('code', 'FST')->first();
        $this->studyProgram = StudyProgram::query()->where('code', 'TI')->first();
        $this->academicYear = AcademicYear::query()->where('code', '2025G')->first();

        return (bool) ($this->admin && $this->faculty);
    }

    private function seedAlumniUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'alumni@example.com'],
            [
                'first_name' => 'Alumni',
                'last_name' => 'Demo',
                'photo' => 'default.jpg',
                'username' => 'alumni',
                'phone' => '0800000099',
                'code' => Str::random(6),
                'password' => Hash::make('alumni123'),
            ]
        );

        $user->syncRoles(['alumni']);

        return $user;
    }

    private function seedAlumniProfiles(User $alumniUser): array
    {
        $profiles = [];

        $sampleData = [
            [
                'nim' => 'ALM20200001',
                'full_name' => 'Budi Santoso',
                'graduation_year' => 2023,
                'graduation_date' => '2023-08-15',
                'email' => 'budi.santoso@example.com',
                'phone' => '081234567801',
                'employment_status' => 'working',
                'employer_name' => 'PT Teknologi Nusantara',
                'job_title' => 'Software Engineer',
                'job_industry' => 'Teknologi Informasi',
                'current_city' => 'Jakarta',
                'current_province' => 'DKI Jakarta',
                'gpa' => 3.65,
                'gender' => 'Laki-laki',
                'linkedin_url' => 'https://linkedin.com/in/budi-santoso',
                'user_id' => $alumniUser->id,
            ],
            [
                'nim' => 'ALM20200002',
                'full_name' => 'Siti Rahayu',
                'graduation_year' => 2023,
                'graduation_date' => '2023-08-15',
                'email' => 'siti.rahayu@example.com',
                'phone' => '081234567802',
                'employment_status' => 'entrepreneur',
                'employer_name' => 'Warung Digital Creative',
                'job_title' => 'Founder & CEO',
                'job_industry' => 'Ekonomi Kreatif',
                'current_city' => 'Bandung',
                'current_province' => 'Jawa Barat',
                'gpa' => 3.80,
                'gender' => 'Perempuan',
            ],
            [
                'nim' => 'ALM20190003',
                'full_name' => 'Ahmad Fauzi',
                'graduation_year' => 2022,
                'graduation_date' => '2022-08-20',
                'email' => 'ahmad.fauzi@example.com',
                'phone' => '081234567803',
                'employment_status' => 'working',
                'employer_name' => 'Bank Mandiri',
                'job_title' => 'Data Analyst',
                'job_industry' => 'Keuangan',
                'current_city' => 'Surabaya',
                'current_province' => 'Jawa Timur',
                'gpa' => 3.55,
                'gender' => 'Laki-laki',
            ],
            [
                'nim' => 'ALM20210004',
                'full_name' => 'Dewi Lestari',
                'graduation_year' => 2024,
                'graduation_date' => '2024-02-10',
                'email' => 'dewi.lestari@example.com',
                'phone' => '081234567804',
                'employment_status' => 'studying',
                'current_city' => 'Yogyakarta',
                'current_province' => 'DI Yogyakarta',
                'gpa' => 3.90,
                'gender' => 'Perempuan',
            ],
            [
                'nim' => 'ALM20210005',
                'full_name' => 'Rizky Pratama',
                'graduation_year' => 2024,
                'graduation_date' => '2024-02-10',
                'email' => 'rizky.pratama@example.com',
                'employment_status' => 'unemployed',
                'current_city' => 'Semarang',
                'current_province' => 'Jawa Tengah',
                'gpa' => 3.40,
                'gender' => 'Laki-laki',
            ],
        ];

        foreach ($sampleData as $data) {
            $profile = AlumniProfile::updateOrCreate(
                ['nim' => $data['nim']],
                array_merge($data, [
                    'study_program_id' => $this->studyProgram?->id,
                    'faculty_id' => $this->faculty->id,
                    'birth_date' => fake()->dateTimeBetween('1995-01-01', '2002-12-31')->format('Y-m-d'),
                    'address' => fake()->address(),
                    'is_active' => true,
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ])
            );
            $profiles[] = $profile;
        }

        return $profiles;
    }

    private function seedEmployerPartners(): array
    {
        $employers = [];

        $data = [
            [
                'name' => 'PT Teknologi Nusantara',
                'industry' => 'Teknologi Informasi',
                'website' => 'https://teknologinusantara.example.com',
                'contact_person' => 'HRD Departemen',
                'contact_email' => 'hrd@teknologinusantara.example.com',
                'contact_phone' => '021-5551234',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'description' => 'Perusahaan teknologi terkemuka yang fokus pada pengembangan solusi digital untuk enterprise.',
            ],
            [
                'name' => 'PT Finansial Digital Indonesia',
                'industry' => 'Fintech',
                'website' => 'https://finansialdigital.example.com',
                'contact_person' => 'Recruitment Team',
                'contact_email' => 'recruit@finansialdigital.example.com',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'description' => 'Startup fintech yang bergerak di bidang payment gateway dan digital banking.',
            ],
            [
                'name' => 'PT Konsultan Bisnis Asia',
                'industry' => 'Konsultasi',
                'website' => 'https://konsultanbisnisasia.example.com',
                'contact_email' => 'info@konsultanbisnisasia.example.com',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'description' => 'Firma konsultasi manajemen yang melayani perusahaan-perusahaan di Asia Tenggara.',
            ],
        ];

        foreach ($data as $item) {
            $employer = EmployerPartner::updateOrCreate(
                ['name' => $item['name']],
                array_merge($item, [
                    'address' => fake()->address(),
                    'is_active' => true,
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ])
            );
            $employers[] = $employer;
        }

        return $employers;
    }

    private function seedJobPostings(array $employers): void
    {
        $jobs = [
            [
                'employer_partner_id' => $employers[0]->id ?? null,
                'title' => 'Backend Developer (PHP/Laravel)',
                'company_name' => 'PT Teknologi Nusantara',
                'industry' => 'Teknologi Informasi',
                'description' => 'Kami mencari Backend Developer berpengalaman untuk bergabung dengan tim engineering kami. Tanggung jawab utama meliputi pengembangan API, optimasi database, dan implementasi microservices.',
                'requirements' => "- Minimal S1 Teknik Informatika atau setara\n- Pengalaman 2+ tahun dengan PHP/Laravel\n- Familiar dengan REST API, MySQL, Redis\n- Memahami konsep microservices dan Docker",
                'location' => 'Jakarta Selatan',
                'job_type' => 'full-time',
                'salary_range' => '10-15 juta',
                'apply_url' => 'https://teknologinusantara.example.com/careers',
                'contact_email' => 'hrd@teknologinusantara.example.com',
                'posted_date' => now()->subDays(5)->toDateString(),
                'deadline_date' => now()->addDays(25)->toDateString(),
            ],
            [
                'employer_partner_id' => $employers[1]->id ?? null,
                'title' => 'Data Analyst',
                'company_name' => 'PT Finansial Digital Indonesia',
                'industry' => 'Fintech',
                'description' => 'Bergabunglah dengan tim data kami untuk menganalisis transaksi digital dan menghasilkan insight bisnis yang berharga.',
                'requirements' => "- S1 Statistik, Matematika, atau Ilmu Komputer\n- Mahir Python, SQL, dan tools visualisasi data\n- Pengalaman dengan A/B testing dan statistical modeling",
                'location' => 'Jakarta',
                'job_type' => 'full-time',
                'salary_range' => '8-12 juta',
                'posted_date' => now()->subDays(3)->toDateString(),
                'deadline_date' => now()->addDays(30)->toDateString(),
            ],
            [
                'employer_partner_id' => $employers[0]->id ?? null,
                'title' => 'Frontend Developer Intern',
                'company_name' => 'PT Teknologi Nusantara',
                'industry' => 'Teknologi Informasi',
                'description' => 'Program magang 3 bulan untuk fresh graduate yang ingin mengasah kemampuan frontend development.',
                'requirements' => "- Mahasiswa semester akhir atau fresh graduate\n- Familiar dengan HTML, CSS, JavaScript, React\n- Portfolio project web development",
                'location' => 'Jakarta',
                'job_type' => 'internship',
                'salary_range' => '3-5 juta',
                'posted_date' => now()->subDays(1)->toDateString(),
                'deadline_date' => now()->addDays(20)->toDateString(),
            ],
            [
                'employer_partner_id' => $employers[2]->id ?? null,
                'title' => 'Business Consultant',
                'company_name' => 'PT Konsultan Bisnis Asia',
                'industry' => 'Konsultasi',
                'description' => 'Mencari lulusan baru yang tertarik dengan dunia konsultasi bisnis dan memiliki kemampuan analitis yang kuat.',
                'requirements' => "- S1 Manajemen, Ekonomi, atau Teknik Industri\n- IPK minimal 3.00\n- Kemampuan komunikasi dan presentasi yang baik",
                'location' => 'Surabaya',
                'job_type' => 'contract',
                'salary_range' => '6-9 juta',
                'posted_date' => now()->subDays(7)->toDateString(),
                'deadline_date' => now()->addDays(15)->toDateString(),
            ],
            [
                'title' => 'UI/UX Designer (Part-time)',
                'company_name' => 'StartupHub Indonesia',
                'industry' => 'Teknologi Informasi',
                'description' => 'Dibutuhkan UI/UX Designer part-time untuk mengerjakan desain aplikasi mobile.',
                'requirements' => "- Pengalaman dengan Figma dan Adobe XD\n- Portfolio desain UI/UX aplikasi mobile\n- Memahami prinsip user-centered design",
                'location' => 'Remote',
                'job_type' => 'part-time',
                'salary_range' => '4-6 juta',
                'contact_email' => 'hello@startuphub.example.com',
                'posted_date' => now()->subDays(2)->toDateString(),
                'deadline_date' => now()->addDays(18)->toDateString(),
            ],
        ];

        foreach ($jobs as $data) {
            JobPosting::updateOrCreate(
                ['title' => $data['title'], 'company_name' => $data['company_name']],
                array_merge($data, [
                    'is_active' => true,
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ])
            );
        }
    }

    private function seedAlumniEvents(): array
    {
        $events = [];

        $data = [
            [
                'title' => 'Webinar: Karir di Era Digital',
                'description' => 'Webinar tentang peluang karir di era transformasi digital. Narasumber dari berbagai perusahaan teknologi terkemuka akan berbagi pengalaman dan tips sukses.',
                'event_type' => 'webinar',
                'event_date' => now()->addDays(14)->setHour(10)->setMinute(0),
                'end_date' => now()->addDays(14)->setHour(12)->setMinute(0),
                'is_online' => true,
                'meeting_url' => 'https://zoom.example.com/j/alumni-webinar',
                'max_participants' => 200,
                'registration_deadline' => now()->addDays(13),
            ],
            [
                'title' => 'Career Fair 2026',
                'description' => 'Career fair tahunan yang mempertemukan alumni dengan puluhan perusahaan mitra. Bawa CV dan portfolio terbaik kamu!',
                'event_type' => 'career_fair',
                'event_date' => now()->addDays(30)->setHour(8)->setMinute(0),
                'end_date' => now()->addDays(30)->setHour(17)->setMinute(0),
                'location' => 'Gedung Serbaguna Kampus',
                'is_online' => false,
                'max_participants' => 500,
                'registration_deadline' => now()->addDays(28),
            ],
            [
                'title' => 'Alumni Networking Night',
                'description' => 'Malam networking untuk mempererat hubungan antar alumni dari berbagai angkatan. Acara santai dengan dress code casual.',
                'event_type' => 'networking',
                'event_date' => now()->addDays(45)->setHour(18)->setMinute(30),
                'end_date' => now()->addDays(45)->setHour(21)->setMinute(0),
                'location' => 'Rooftop Lounge Hotel Bintang',
                'is_online' => false,
                'max_participants' => 100,
                'registration_deadline' => now()->addDays(42),
            ],
        ];

        foreach ($data as $item) {
            $event = AlumniEvent::updateOrCreate(
                ['title' => $item['title']],
                array_merge($item, [
                    'is_published' => true,
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ])
            );
            $events[] = $event;
        }

        return $events;
    }

    private function seedEventParticipants(array $events, array $profiles): void
    {
        if (empty($events) || empty($profiles)) {
            return;
        }

        // Register first 2 alumni to the first event
        foreach (array_slice($profiles, 0, 2) as $profile) {
            AlumniEventParticipant::updateOrCreate(
                [
                    'alumni_event_id' => $events[0]->id,
                    'alumni_profile_id' => $profile->id,
                ],
                [
                    'registered_at' => now()->subDays(2),
                ]
            );
        }

        // Register first alumni to second event with attendance
        if (count($profiles) > 0 && count($events) > 1) {
            AlumniEventParticipant::updateOrCreate(
                [
                    'alumni_event_id' => $events[1]->id,
                    'alumni_profile_id' => $profiles[0]->id,
                ],
                [
                    'registered_at' => now()->subDays(5),
                    'attended_at' => now()->subDays(5),
                ]
            );
        }
    }

    private function seedTracerStudyCampaign(array $profiles): void
    {
        if (empty($profiles)) {
            return;
        }

        $questions = [
            [
                'id' => 'q1',
                'type' => 'radio',
                'text' => 'Apakah pekerjaan Anda saat ini sesuai dengan bidang studi?',
                'options' => ['Sangat Sesuai', 'Sesuai', 'Cukup Sesuai', 'Tidak Sesuai'],
            ],
            [
                'id' => 'q2',
                'type' => 'select',
                'text' => 'Berapa range gaji Anda saat ini?',
                'options' => ['< 3 juta', '3-5 juta', '5-8 juta', '8-12 juta', '> 12 juta'],
            ],
            [
                'id' => 'q3',
                'type' => 'radio',
                'text' => 'Seberapa puas Anda dengan pendidikan yang didapat di kampus?',
                'options' => ['Sangat Puas', 'Puas', 'Cukup Puas', 'Kurang Puas', 'Tidak Puas'],
            ],
            [
                'id' => 'q4',
                'type' => 'textarea',
                'text' => 'Saran dan masukan untuk perbaikan kurikulum kampus:',
                'options' => [],
            ],
            [
                'id' => 'q5',
                'type' => 'radio',
                'text' => 'Apakah Anda merekomendasikan kampus ini kepada calon mahasiswa?',
                'options' => ['Sangat Merekomendasikan', 'Merekomendasikan', 'Netral', 'Tidak Merekomendasikan'],
            ],
        ];

        $graduationYears = array_unique(array_map(fn ($p) => $p->graduation_year, $profiles));

        $campaign = TracerStudyCampaign::updateOrCreate(
            ['title' => 'Tracer Study Angkatan 2022-2024'],
            [
                'description' => 'Survei tracer study untuk alumni angkatan 2022-2024. Bantu kami meningkatkan kualitas pendidikan dengan mengisi survei ini.',
                'academic_year_id' => $this->academicYear?->id,
                'target_graduation_years' => $graduationYears,
                'target_study_program_ids' => $this->studyProgram ? [$this->studyProgram->id] : null,
                'start_date' => now()->subDays(10)->toDateString(),
                'end_date' => now()->addDays(50)->toDateString(),
                'questions' => $questions,
                'status' => 'active',
                'total_sent' => count($profiles),
                'total_responded' => 2,
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ]
        );

        // Submit responses from first 2 alumni
        $responseSamples = [
            [
                'answers' => ['q1' => 'Sangat Sesuai', 'q2' => '8-12 juta', 'q3' => 'Puas', 'q4' => 'Kurikulum sudah baik, perlu lebih banyak praktik industri.', 'q5' => 'Sangat Merekomendasikan'],
                'employment_status' => 'working',
                'employer_name' => 'PT Teknologi Nusantara',
                'job_title' => 'Software Engineer',
                'job_relevance' => 'relevant',
                'time_to_employment_months' => 3,
                'salary_range' => '8-12 juta',
            ],
            [
                'answers' => ['q1' => 'Sesuai', 'q2' => '5-8 juta', 'q3' => 'Sangat Puas', 'q4' => 'Tambahkan mata kuliah entrepreneurship.', 'q5' => 'Merekomendasikan'],
                'employment_status' => 'entrepreneur',
                'employer_name' => 'Warung Digital Creative',
                'job_title' => 'Founder & CEO',
                'job_relevance' => 'partially_relevant',
                'time_to_employment_months' => 1,
                'salary_range' => '5-8 juta',
            ],
        ];

        foreach (array_slice($profiles, 0, 2) as $i => $profile) {
            $sample = $responseSamples[$i] ?? $responseSamples[0];
            TracerStudyResponse::updateOrCreate(
                [
                    'tracer_study_campaign_id' => $campaign->id,
                    'alumni_profile_id' => $profile->id,
                ],
                array_merge($sample, [
                    'further_study' => false,
                    'submitted_at' => now()->subDays(5 - $i),
                ])
            );
        }
    }
}
