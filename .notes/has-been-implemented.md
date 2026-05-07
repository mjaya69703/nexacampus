# NexaCampus - Implementation Progress Tracker

Dokumen ini berisi tracking progress implementasi fitur-fitur NexaCampus yang sedang dan telah dikerjakan.

---

## 🚧 Currently In Progress (Week 1-2 Priority)

Fitur-fitur berikut sedang dalam tahap perencanaan/implementasi dengan prioritas tinggi.

### 📊 Global Priority Order (Cross-Role)

Urutan pengerjaan berdasarkan business impact dan dependencies antar role:

---

#### **Priority 1: Communication & Resource Foundation** 
*Impact: Lecturer + Student | Module: Academic*

##### 1. Course Materials Management 📚
**Status:** ✅ COMPLETED  
**Roles Affected:** Lecturer (upload), Student (access)  
**Module Category:** Academic → New Submodule: `course-materials`

**Catatan Evaluasi (Status Implementasi):**
- ✅ **Video links / embed YouTube** - SUDAH ADA. Dosen bisa input URL YouTube, student melihat embedded video player di learning page.
- ✅ **Bookmark/Favorite** - SUDAH ADA. Toggle bookmark per materi untuk mahasiswa, filter "Bookmarked Only", counter di dashboard.
- ✅ **PDF viewer in-browser** - SUDAH ADA. PDF ditampilkan via iframe embed di halaman student learning.
- ❌ **Drag & drop upload** - BELUM ADA. Upload masih menggunakan input file standar Bootstrap.
- ❌ **Versioning** - BELUM ADA. Belum ada sistem tracking versi/riwayat update material.

**Description:**
Sistem upload dan manajemen materi perkuliahan yang terintegrasi antara dosen dan mahasiswa.

**Features:**
- **Lecturer Side:**
  - ✅ Upload syllabus/RPS (Rencana Pembelajaran Semester)
  - ✅ Upload materi perkuliahan (PDF, PPT, DOC, video links)
  - ✅ Organize materials by meeting/session number
  - ✅ Categorize materials (Syllabus, Lecture Notes, Assignments, References)
  - ✅ Share resources with enrolled students
  - ✅ Download statistics tracking
  - ❌ Version control for updated materials *(BELUM IMPLEMENTED)*
  - ✅ File size & type validation
  - ❌ Drag & drop upload interface *(BELUM IMPLEMENTED)*

- **Student Side:**
  - ✅ View materials per course offering
  - ✅ Download syllabus/RPS
  - ✅ Download lecture notes (PDF, PPT, DOC)
  - ✅ Watch embedded videos (YouTube links)
  - ✅ Filter by category (Syllabus, Lecture Notes, Assignments, References)
  - ✅ Filter by meeting/session number
  - ✅ Download counter & last accessed tracking
  - ✅ Search materials by keyword
  - ✅ Bookmark/favorite important materials
  - ✅ Mobile-friendly PDF viewer (iframe embed)

**Why Important:**
- Central repository untuk semua materi ajar
- Mahasiswa bisa akses materi kapan saja
- Dokumentasi pembelajaran terstruktur
- Support blended/hybrid learning
- Reduce dependency on physical materials

**Estimated Effort:** Medium (2-3 days total)
- Lecturer side: 1.5 days
- Student side: 1 day
- Integration & testing: 0.5 days

**Technical Notes:**
- Use Laravel file storage (public disk)
- Store metadata in database (file name, path, size, type, uploader, course_offering_id, meeting_no, category)
- Implement file validation (max size 50MB, allowed types: pdf, ppt, pptx, doc, docx, mp4, link)
- Add download counter & last accessed timestamp
- Consider using AWS S3 for production
- Reuse existing course_offerings relationship
- Implement proper authorization (only enrolled students can access)

**Database Changes Required:**
```php
// New table: course_materials
Schema::create('course_materials', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
    $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete(); // lecturer
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('file_path'); // storage path
    $table->string('file_name'); // original filename
    $table->string('file_type'); // pdf, ppt, doc, video_link, etc.
    $table->integer('file_size')->nullable(); // in bytes
    $table->string('category')->default('lecture_notes'); // syllabus, lecture_notes, assignments, references
    $table->integer('meeting_number')->nullable(); // which session/meeting
    $table->boolean('is_published')->default(true);
    $table->integer('download_count')->default(0);
    $table->timestamp('last_accessed_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// Pivot table for tracking downloads
Schema::create('course_material_downloads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_material_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->timestamp('downloaded_at');
    $table->string('ip_address')->nullable();
});
```

**New Dependencies:**
- Intervention Image (for image thumbnails if needed)
- PDF.js (optional, for in-browser PDF viewing on student side)

**Files Created/Modified:**
- ✅ Models: `app/Models/Academic/CourseMaterial.php`, `app/Models/Academic/CourseMaterialDownload.php`
- ✅ Livewire Components:
  - `app/Livewire/Academic/CourseMaterialTable.php` (lecturer)
  - `app/Livewire/Academic/StudentCourseMaterialTable.php` (student)
- ✅ Views:
  - `resources/views/components/lecturer/course-materials/⚡index.blade.php` (with upload & edit modals)
  - `resources/views/components/student/course-materials/⚡index.blade.php` (with filters & download)
- ✅ Migrations: `database/migrations/2026_05_05_193435_create_course_materials_table.php`
- ✅ Migrations: `database/migrations/2026_05_05_193438_create_course_material_downloads_table.php`
- ✅ Controller: `app/Http/Controllers/Lecturer/CourseMaterialController.php` (download handler)
- ✅ Routes: Added lecturer & student routes in `routes/web.php`
- ✅ Navigation: Integrated into lecturer course-offerings show page & student schedule page
- ✅ Model Enhancement: Added `courseMaterials()` relationship to `CourseOffering` model

**Implementation Details:**
- File upload with validation (max 50MB, PDF/PPT/DOC/MP4 formats)
- Category filtering (Syllabus, Lecture Notes, Assignments, References)
- Meeting number filtering and organization
- Download tracking with IP address logging
- Published/Draft status for materials
- Bootstrap modals for upload and edit functionality
- FontAwesome icons throughout the interface
- Responsive design for mobile access
- Proper authorization checks (only enrolled students can access)

**Success Metrics:**
- >70% of lecturers upload at least 1 material per week
- >80% of students access course materials regularly
- Average 5+ downloads per material

**Dependencies:**
- Requires: Existing course_offerings system ✅
- Blocks: None
- Related: Assignment Management (future - can attach materials to assignments)

**Additional Features Implemented (After Initial Release):**
- ✅ **Comments & Discussion System** - Threaded comments dengan reply, like, edit/delete, mentions (@username), pagination, dan moderation
- ✅ **Material Likes** - Students bisa like/unlike materi dengan counter real-time
- ✅ **Advanced Comment Features:**
  - Edit/Delete own comments (dengan tracking `is_edited`, `edited_at`)
  - Soft delete untuk preserve context replies
  - Like system terpisah untuk comments (`comment_likes` table)
  - Mention parsing (@username) untuk notify users
  - Load more pagination untuk performance
  - Moderation capabilities via soft delete

**Recommended Future Enhancements:**
- 🔧 **Drag & Drop Upload** - Upgrade file upload interface ke drag & drop (gunakan Dropzone.js atau FilePond)
- 🔧 **Version Control** - Track versi material dengan history (table `course_material_versions`)
- 🔧 **Material Analytics Dashboard** - Statistik download per mahasiswa, engagement metrics
- 🔧 **Bulk Upload** - Upload multiple files sekaligus dengan progress bar
- 🔧 **Material Preview Thumbnails** - Generate thumbnail untuk PDF/PPT preview
- 🔧 **Download Expiry** - Set expiry date untuk material tertentu (misal: quiz materials)
- 🔧 **Access Logs** - Track siapa yang akses material kapan (audit trail lengkap)
- 🔧 **Material Tags** - Tambah tagging system untuk better organization
- 🔧 **Related Materials** - Suggest related materials based on category/meeting
- 🔧 **Offline Access** - Cache materials untuk offline viewing (PWA feature)

---

#### **Priority 2: Announcement System**
*Impact: Admin/Superuser + Lecturer + Student | Module: Publication*

##### 2. Announcements System 📢
**Status:** 🚧 IN PROGRESS  
**Roles Affected:** 
- **Admin/Superuser:** Manage all announcements (base ownership)
- **Lecturer:** Create & edit announcements for their courses (via permission)
- **Student:** View announcements for enrolled courses  
**Module Category:** Publication → Submodule: `announcements`

**Architecture Decision:**
- Announcements berbasis di **Admin/Publication module** sebagai base ownership
- Lecturer mendapat akses melalui **permission system** (didaftarkan ke role lecturer)
- Views berada di `resources/views/components/admin/publication/announcements/`
- Models di `app/Models/Publication/Announcement.php`
- Livewire components di `app/Livewire/Publication/AnnouncementTable.php`
- Scalable untuk future publication types (News, Events, Blog)

**Description:**
Sistem pengumuman untuk komunikasi dosen-mahasiswa yang efektif dan terdokumentasi.

**Features:**
- **Admin/Superuser Side (Base Owner):**
  - Full CRUD untuk semua announcements
  - Manage announcements across all courses
  - Create campus-wide announcements (global)
  - Assign permissions to lecturers
  - View analytics & read statistics
  - Archive/delete any announcement

- **Lecturer Side (Via Permission):**
  - Create announcements for their assigned course offerings
  - Rich text editor (bold, italic, lists, links)
  - Attach files/links to announcements
  - Schedule announcements (publish later)
  - Pin important announcements
  - Mark as read/unread tracking
  - View read statistics for their announcements
  - Edit/delete own announcements only
  - Filter by course offering

- **Student Side:**
  - View announcements for enrolled courses
  - Announcement list dengan:
    - Title
    - Content (rich text)
    - Posted date & time
    - Lecturer name
    - Priority level (Normal/Important/Urgent)
    - Read/unread status
  - Mark as read functionality
  - Filter by course
  - Filter by date range
  - Filter by priority
  - Search announcements
  - Email notification for important announcements (optional)
  - Push notification (PWA - optional)

**Why Important:**
- Komunikasi efektif & terdokumentasi
- Inform perubahan jadwal, deadline, dll
- Semua mahasiswa receive same information
- Reduce miscommunication
- Tidak ketinggalan info penting dari dosen

**Estimated Effort:** Low-Medium (1-2 days total)
- Lecturer side: 1 day
- Student side: 0.5 day
- Notification integration: 0.5 day

**Technical Notes:**
- **Architecture:** Publication module dengan base ownership di Admin/Superuser
- **Permission System:** Lecturer access via permission registration (e.g., `announcement.create`, `announcement.edit`)
- **Database:** Simple CRUD dengan title, content (HTML), course_offering_id (nullable for global), published_at, is_pinned
- **Rich Text Editor:** Use Summernote (consistent dengan existing pattern)
- **Read Tracking:** Pivot table `announcement_reads` untuk track student reads
- **Notifications:** Optional integration dengan Laravel notifications untuk email/push
- **Authorization:** 
  - Admin: Full access to all announcements
  - Lecturer: Can only manage announcements for their course offerings
  - Student: Read-only access to announcements for enrolled courses
- **Priority Levels:** Enum (normal, important, urgent) dengan visual indicators

**Database Changes Required:**
```php
// New table: announcements (Publication module)
Schema::create('announcements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_offering_id')
        ->nullable()  // Nullable for campus-wide/global announcements
        ->constrained('course_offerings')
        ->cascadeOnDelete();
    $table->foreignId('created_by')
        ->constrained('users')
        ->cascadeOnDelete(); // Admin or Lecturer
    $table->string('title');
    $table->longText('content'); // HTML from Summernote editor
    $table->string('priority')->default('normal'); // normal, important, urgent
    $table->boolean('is_pinned')->default(false);
    $table->boolean('is_published')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamp('scheduled_at')->nullable(); // For scheduled publishing
    
    // Optional single attachment (simple approach)
    $table->string('attachment_path')->nullable();
    $table->string('attachment_name')->nullable();
    $table->string('attachment_type')->nullable(); // pdf, doc, docx, xls, xlsx, jpg, png, etc.
    $table->integer('attachment_size')->nullable(); // in bytes
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['course_offering_id', 'is_published']);
    $table->index(['published_at', 'is_pinned']);
    $table->index(['priority', 'is_published']);
});

// Pivot table for tracking reads
Schema::create('announcement_reads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('announcement_id')
        ->constrained('announcements')
        ->cascadeOnDelete();
    $table->foreignId('student_id')
        ->constrained('users')
        ->cascadeOnDelete();
    $table->timestamp('read_at');
    
    $table->unique(['announcement_id', 'student_id']); // Prevent duplicate reads
});
```

**Attachment Design Decision:**
- **Single optional attachment** per announcement (not multiple)
- **Rationale:** Announcements are for communication, not file repository
- **If need multiple files:** Upload to Course Materials, then share link in announcement
- **Supported types:** PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (max 10MB)
- **Storage:** Laravel public disk (same as course materials)

**Files to Create/Modify:**

**Models:**
- `app/Models/Publication/Announcement.php`
- `app/Models/Publication/AnnouncementRead.php`

**Livewire Components:**
- `app/Livewire/Publication/AnnouncementTable.php` (Admin/Lecturer - PowerGrid table)
- `app/Livewire/Publication/AnnouncementCreate.php` (Admin/Lecturer - Create form)
- `app/Livewire/Publication/AnnouncementEdit.php` (Admin/Lecturer - Edit form)
- `app/Livewire/Publication/StudentAnnouncementTable.php` (Student - View only)

**Views:**
- Admin/Lecturer Views:
  - `resources/views/components/admin/publication/announcements/⚡index.blade.php`
  - `resources/views/components/admin/publication/announcements/⚡create.blade.php`
  - `resources/views/components/admin/publication/announcements/⚡edit.blade.php`
- Student Views:
  - `resources/views/components/student/publication/announcements/⚡index.blade.php`
  - `resources/views/components/student/publication/announcements/⚡show.blade.php`

**Migrations:**
- `database/migrations/YYYY_MM_DD_create_announcements_table.php`
- `database/migrations/YYYY_MM_DD_create_announcement_reads_table.php`

**Permissions (to be registered):**
- `announcement.view` - View announcements
- `announcement.create` - Create new announcements
- `announcement.edit` - Edit existing announcements
- `announcement.delete` - Delete announcements
- `announcement.publish` - Publish/schedule announcements

**Routes:**
```php
// Admin Routes (base ownership)
Route::middleware('active_role:superuser')->prefix('admin')->as('admin.')->group(function () {
    Route::livewire('/publication/announcements', 'publication.announcement-table')->name('announcements.index');
    Route::livewire('/publication/announcements/create', 'publication.announcement-create')->name('announcements.create');
    Route::livewire('/publication/announcements/{id}/edit', 'publication.announcement-edit')->name('announcements.edit');
});

// Lecturer Routes (via permission)
Route::middleware(['active_role:lecturer', 'can:announcement.view'])->prefix('lecturer')->as('lecturer.')->group(function () {
    Route::livewire('/announcements', 'publication.announcement-table')->name('announcements.index');
    Route::livewire('/announcements/create', 'publication.announcement-create')->name('announcements.create');
    Route::livewire('/announcements/{id}/edit', 'publication.announcement-edit')->name('announcements.edit');
});

// Student Routes (read-only)
Route::middleware('active_role:student')->prefix('student')->as('student.')->group(function () {
    Route::livewire('/announcements', 'publication.student-announcement-table')->name('announcements.index');
    Route::livewire('/announcements/{id}', 'publication.announcement-show')->name('announcements.show');
});
```

**Sidebar Menu Integration:**
```php
// In app/Support/SidebarMenu.php

'lecturer' => collect([
    static::makeLink('lecturer-course-offerings', 'Kelas Saya', 'lecturer.course-offerings.index', 'fas fa-book'),
    static::makeGroup('lecturer-publication', 'Publikasi', 'fas fa-bullhorn', [
        static::makeChildLink('lecturer.announcements.index', 'Pengumuman'),
        // Future: static::makeChildLink('lecturer.news.index', 'Berita'),
        // Future: static::makeChildLink('lecturer.events.index', 'Kegiatan'),
    ]),
    static::makeLink('lecturer-course-materials', 'Materi', 'lecturer.course-materials.list', 'fas fa-book-open'),
    static::makeLink('lecturer-student-grades', 'Nilai', 'lecturer.student-grades.index', 'fas fa-chart-bar'),
]),

'student' => collect([
    static::makeLink('student-registration', 'Registrasi', 'student.registration.index', 'fas fa-clipboard-list'),
    static::makeLink('student-study-plan', 'KRS', 'student.study-plan.index', 'fas fa-file-alt'),
    static::makeLink('student-schedule', 'Jadwal', 'student.schedule.index', 'fas fa-calendar'),
    static::makeGroup('student-publication', 'Publikasi', 'fas fa-bullhorn', [
        static::makeChildLink('student.announcements.index', 'Pengumuman'),
        // Future: static::makeChildLink('student.news.index', 'Berita'),
        // Future: static::makeChildLink('student.events.index', 'Kegiatan'),
    ]),
    static::makeLink('student-materials', 'Materi', 'student.course-materials.index', 'fas fa-book-open'),
    static::makeLink('student-grades', 'Nilai', 'student.grades.index', 'fas fa-chart-bar'),
    static::makeLink('student-transcript', 'Transkrip', 'student.transcript.index', 'fas fa-file-invoice'),
]),
```

**Success Metrics:**
- >90% announcement read rate within 24 hours
- Average 2-3 announcements per course per week
- Reduced email inquiries about schedule changes

**Dependencies:**
- Requires: Existing course_offerings system ✅
- Requires: Permission system for lecturer access ✅
- Blocks: None
- Related: Personalized Notifications (future enhancement)

**Implementation Details:**

**1. Authorization Logic:**
```php
// In Announcement model or Policy
class AnnouncementPolicy
{
    public function view(User $user, Announcement $announcement): bool
    {
        // Admin can view all
        if ($user->hasRole('superuser')) {
            return true;
        }
        
        // Lecturer can view if they teach the course
        if ($user->hasRole('lecturer')) {
            return $announcement->courseOffering->lecturers->contains($user->lecturerProfile);
        }
        
        // Student can view if enrolled in the course
        if ($user->hasRole('student')) {
            return $announcement->courseOffering->students->contains($user->studentProfile);
        }
        
        return false;
    }
    
    public function create(User $user): bool
    {
        // Admin or Lecturer with permission
        return $user->hasRole('superuser') || 
               ($user->hasRole('lecturer') && $user->can('announcement.create'));
    }
    
    public function update(User $user, Announcement $announcement): bool
    {
        // Admin can update any
        if ($user->hasRole('superuser')) {
            return true;
        }
        
        // Lecturer can only update their own announcements
        return $user->hasRole('lecturer') && 
               $announcement->created_by === $user->id &&
               $user->can('announcement.edit');
    }
}
```

**2. Query Scopes for Filtering:**
```php
// In Announcement model
public function scopePublished($query)
{
    return $query->where('is_published', true)
                 ->whereNotNull('published_at')
                 ->where('published_at', '<=', now());
}

public function scopeForCourse($query, $courseOfferingId)
{
    return $query->where('course_offering_id', $courseOfferingId);
}

public function scopeGlobal($query)
{
    return $query->whereNull('course_offering_id');
}

public function scopePinned($query)
{
    return $query->where('is_pinned', true);
}

public function scopeByPriority($query, $priority)
{
    return $query->where('priority', $priority);
}
```

**3. Read Tracking Implementation:**
```php
// Mark announcement as read
public function markAsRead(int $announcementId): void
{
    $user = auth()->user();
    
    AnnouncementRead::updateOrCreate(
        [
            'announcement_id' => $announcementId,
            'student_id' => $user->id,
        ],
        ['read_at' => now()]
    );
}

// Get unread count for student
public function getUnreadCount(): int
{
    $user = auth()->user();
    
    return Announcement::published()
        ->whereHas('courseOffering.students', function ($query) use ($user) {
            $query->where('student_profile_id', $user->studentProfile->id);
        })
        ->whereDoesntHave('reads', function ($query) use ($user) {
            $query->where('student_id', $user->id);
        })
        ->count();
}
```

**Future Scalability - Publication Module Expansion:**

The Publication module architecture is designed to be easily extensible:

```
app/Models/Publication/
├── Announcement.php          ← Current
├── AnnouncementRead.php      ← Current
├── News.php                  ← Future: Campus news
├── NewsCategory.php          ← Future: News categories
├── Event.php                 ← Future: Campus events
├── EventRegistration.php     ← Future: Event RSVP
├── BlogPost.php              ← Future: Articles/blog
└── BlogComment.php           ← Future: Blog comments

app/Livewire/Publication/
├── AnnouncementTable.php     ← Current
├── AnnouncementCreate.php    ← Current
├── AnnouncementEdit.php      ← Current
├── StudentAnnouncementTable.php ← Current
├── NewsTable.php             ← Future
├── NewsCreate.php            ← Future
├── EventTable.php            ← Future
├── EventCreate.php           ← Future
└── BlogTable.php             ← Future

resources/views/components/admin/publication/
├── announcements/            ← Current
├── news/                     ← Future
├── events/                   ← Future
└── blog/                     ← Future
```

**Benefits of This Architecture:**
1. ✅ **Centralized ownership** - All publication content managed from admin
2. ✅ **Permission-based access** - Flexible role assignments
3. ✅ **Consistent patterns** - Same structure for all publication types
4. ✅ **Easy to extend** - Add new publication types without refactoring
5. ✅ **Clear separation** - Publication vs Academic concerns
6. ✅ **Scalable permissions** - Granular control per publication type

---

#### **Priority 3: Assessment & Grading Enhancement**
*Impact: Lecturer + Student + Admin | Module: Academic*

##### 3. Grade Book with Export 📊
**Status:** 🚧 IN PROGRESS  
**Roles Affected:** Lecturer (export), Admin (reporting)  
**Module Category:** Academic → Enhancement to existing `student-grades`

**Description:**
Rekap nilai lengkap dengan export functionality untuk reporting dan dokumentasi.

**Features:**
- Comprehensive grade overview across ALL courses (lecturer view)
- Table view with sortable columns
- Filter by course, semester, academic year
- Export to Excel (.xlsx)
- Export to PDF (formatted report)
- Grade distribution charts (bar chart, pie chart)
- Calculate final grades automatically based on components
- Bulk grade operations (curve grades, apply formula)
- Print-friendly view
- Summary statistics (average, median, std deviation)
- Department-level reporting (admin view)

**Why Important:**
- End-of-semester reporting requirement
- Documentation untuk akreditasi
- Easy sharing with department/admin
- Data analysis for teaching improvement
- Compliance dengan standar akademik

**Estimated Effort:** Medium (2-3 days)
- Export functionality: 1.5 days
- Charts & analytics: 1 day
- Testing & optimization: 0.5 days

**Technical Notes:**
- Use PhpSpreadsheet for Excel export
- Use Dompdf or TCPDF for PDF generation
- Implement Chart.js for visualizations
- Add calculation engine for weighted grades
- Cache grade calculations for performance
- Reuse existing student_grades and student_grade_components tables
- Implement permission-based access (lecturer sees own courses, admin sees all)

**Database Changes Required:**
```php
// No new tables needed - enhance existing queries
// Add indexes for better performance
Schema::table('student_grades', function (Blueprint $table) {
    $table->index(['course_offering_id', 'grade_status']);
    $table->index(['academic_year_id', 'semester']);
});
```

**New Dependencies:**
- PhpSpreadsheet (^1.29) - for Excel export
- Dompdf (^2.0) or TCPDF - for PDF generation
- Chart.js (^4.0) - for visualizations (frontend)

**Files to Create/Modify:**
- Services: `app/Support/GradeExportService.php`
- Livewire Components:
  - Enhance: `app/Livewire/Academic/StudentGradeTable.php` (add export actions)
  - New: `app/Livewire/Academic/GradeBookAnalytics.php` (charts & stats)
- Views:
  - Enhance: `resources/views/components/lecturer/student-grades/index.blade.php` (add export buttons)
  - New: `resources/views/components/lecturer/student-grades/analytics.blade.php`
- Config: Add to `composer.json` dependencies

**Success Metrics:**
- 100% of lecturers use export feature at end of semester
- <5 seconds export time for 100+ students
- Zero data discrepancies in exported reports

**Dependencies:**
- Requires: Existing student_grades system ✅
- Blocks: None
- Related: Assignment Management (future - will add assignment grades to gradebook)

---

#### **Priority 4: Core Business Operations (Admin)**
*Impact: Admin + Prospective Students | Module: New*

##### 4. PMB (Penerimaan Mahasiswa Baru) Management 🎓
**Status:** 🚧 IN PROGRESS  
**Roles Affected:** Admin (manage), Prospective Students (apply)  
**Module Category:** New Module → `pmb` (Admission)

**Description:**
Sistem pendaftaran & seleksi mahasiswa baru end-to-end untuk streamline admission workflow.

**Features:**
- **Online Registration Form:**
  - Personal info (name, birth date, gender, address)
  - Contact info (phone, email, emergency contact)
  - Education background (high school, major, graduation year)
  - Document uploads (ID card, high school certificate, photo, report cards)
  - Program selection (faculty, study program, class type)
  - Payment confirmation upload
  
- **Application Review:**
  - Application list dengan status tracking (Submitted → Under Review → Accepted/Rejected)
  - Document verification checklist
  - Interview scheduling & notes
  - Test score input (entrance exam, TOEFL, etc.)
  - Bulk approval/rejection
  
- **Selection Process:**
  - Entrance exam management (schedule, venue, participants)
  - Score calculation & ranking
  - Quota management per study program
  - Waitlist management
  - Automatic acceptance letter generation
  
- **Registration Completion:**
  - Convert accepted applicants to students
  - Auto-create user account
  - Generate student ID (NIM)
  - Initial enrollment setup
  - Welcome email/notification

**Why Important:**
- Core business process untuk kampus
- Streamline admission workflow
- Digital document management
- Transparent selection process
- Reduce manual paperwork
- 80% reduction in application processing time

**Estimated Effort:** High (5-7 days)
- Application form & upload: 2 days
- Review workflow: 2 days
- Selection & conversion: 2 days
- Testing & refinement: 1 day

**Technical Notes:**
- New tables: `pmb_applications`, `pmb_documents`, `pmb_exam_schedules`, `pmb_scores`
- File upload handling untuk documents
- Status workflow engine
- Integration dengan student registration system
- Email notifications untuk status updates
- Consider payment gateway integration untuk registration fee
- Implement role-based access (admin only for review)

**Database Changes Required:**
```php
// Main application table
Schema::create('pmb_applications', function (Blueprint $table) {
    $table->id();
    $table->string('application_number')->unique(); // e.g., PMB-2026-0001
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // linked after acceptance
    $table->string('full_name');
    $table->string('email');
    $table->string('phone');
    $table->date('birth_date');
    $table->enum('gender', ['male', 'female']);
    $table->text('address');
    $table->string('emergency_contact_name');
    $table->string('emergency_contact_phone');
    $table->string('high_school_name');
    $table->string('high_school_major');
    $table->year('high_school_graduation_year');
    $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
    $table->string('class_type')->nullable(); // regular, evening, weekend
    $table->enum('status', ['draft', 'submitted', 'under_review', 'accepted', 'rejected', 'waitlisted'])->default('draft');
    $table->decimal('entrance_exam_score', 5, 2)->nullable();
    $table->decimal('toefl_score', 5, 2)->nullable();
    $table->text('review_notes')->nullable();
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamp('accepted_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['status', 'created_at']);
    $table->index(['faculty_id', 'study_program_id']);
});

// Document uploads
Schema::create('pmb_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('application_id')->constrained()->cascadeOnDelete();
    $table->string('document_type'); // id_card, high_school_certificate, photo, report_card, payment_proof
    $table->string('file_path');
    $table->string('file_name');
    $table->integer('file_size');
    $table->boolean('is_verified')->default(false);
    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
});

// Exam schedules
Schema::create('pmb_exam_schedules', function (Blueprint $table) {
    $table->id();
    $table->string('exam_type'); // written_test, interview, practical
    $table->date('exam_date');
    $table->time('exam_time');
    $table->string('venue');
    $table->integer('quota');
    $table->integer('registered_count')->default(0);
    $table->timestamps();
});

// Exam scores
Schema::create('pmb_scores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('application_id')->constrained()->cascadeOnDelete();
    $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
    $table->decimal('score', 5, 2);
    $table->text('notes')->nullable();
    $table->foreignId('scored_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
});
```

**New Dependencies:**
- Intervention Image (for photo processing)
- Laravel Queues (for email notifications)
- Dompdf (for acceptance letter generation)
- Payment gateway SDK (optional - Midtrans/Xendit)

**Files to Create/Modify:**
- Models: 
  - `app/Models/Pmb/PmbApplication.php`
  - `app/Models/Pmb/PmbDocument.php`
  - `app/Models/Pmb/PmbExamSchedule.php`
  - `app/Models/Pmb/PmbScore.php`
- Livewire Components:
  - `app/Livewire/Pmb/ApplicationForm.php` (public registration)
  - `app/Livewire/Pmb/ApplicationTable.php` (admin review)
  - `app/Livewire/Pmb/ExamScheduleTable.php` (admin)
- Views:
  - `resources/views/components/pmb/` (registration form, status tracking)
  - `resources/views/components/admin/pmb/` (review dashboard, exam management)
- Migrations: 4 migration files for PMB tables
- Routes: Add public routes for registration form

**Success Metrics:**
- 80% reduction in application processing time
- 95% complete application submissions
- <24 hours average review time
- Zero lost applications/documents

**Dependencies:**
- Requires: Existing faculties & study_programs tables ✅
- Blocks: Student profile creation (auto-convert on acceptance)
- Related: Financial Management (registration fee payment)

---

#### **Priority 5: Financial Management (Admin)**
*Impact: Admin + Students | Module: New*

##### 5. Financial Management - Tuition & Payments 💰
**Status:** 🚧 IN PROGRESS  
**Roles Affected:** Admin (manage billing), Students (view/pay)  
**Module Category:** New Module → `financial`

**Description:**
Sistem manajemen keuangan mahasiswa (SPP, UKT, pembayaran) untuk automated billing dan transparent financial tracking.

**Features:**
- **Tuition Fee Structure:**
  - Set tuition rates per study program/year
  - Semester-based fee configuration
  - Additional fees (lab fee, library fee, activity fee)
  - Discount/scholarship rules
  - Late payment penalties
  
- **Student Billing:**
  - Auto-generate invoices per semester
  - Itemized billing breakdown
  - Payment deadline tracking
  - Outstanding balance monitoring
  - Payment history per student
  
- **Payment Processing:**
  - Manual payment recording (cash/bank transfer)
  - Payment gateway integration (optional)
  - Payment verification & approval
  - Receipt generation (PDF)
  - Bulk payment processing
  
- **Financial Reports:**
  - Payment summary per semester
  - Outstanding payments report
  - Revenue by study program
  - Payment trend analysis
  - Export to Excel/PDF
  
- **Scholarship Management:**
  - Scholarship types & criteria
  - Student scholarship assignment
  - Scholarship amount & duration
  - Renewal tracking

**Why Important:**
- Critical untuk cash flow management
- Automated billing reduce errors
- Transparency untuk mahasiswa
- Financial reporting requirements
- Reduce manual reconciliation
- 95% on-time tuition payment rate target

**Estimated Effort:** High (6-8 days)
- Fee structure & invoicing: 2.5 days
- Payment processing: 2 days
- Reports & exports: 1.5 days
- Scholarship management: 1 day
- Testing & integration: 1 day

**Technical Notes:**
- New tables: `tuition_fees`, `student_invoices`, `invoice_items`, `payments`, `scholarships`, `student_scholarships`
- Invoice generation logic
- Payment status tracking (Pending → Paid → Overdue)
- Integration dengan payment gateway (Midtrans/Xendit)
- PDF receipt generation dengan Dompdf
- Scheduled jobs untuk invoice generation
- Consider double-entry accounting for accuracy
- Implement role-based access (admin full access, student view-only)

**Database Changes Required:**
```php
// Tuition fee structure
Schema::create('tuition_fees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
    $table->foreignId('study_program_id')->constrained()->cascadeOnDelete();
    $table->integer('semester'); // 1-8
    $table->decimal('base_fee', 12, 2); // SPP/UKT
    $table->decimal('lab_fee', 12, 2)->default(0);
    $table->decimal('library_fee', 12, 2)->default(0);
    $table->decimal('activity_fee', 12, 2)->default(0);
    $table->decimal('late_penalty_per_day', 12, 2)->default(0);
    $table->date('payment_deadline');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->unique(['academic_year_id', 'study_program_id', 'semester']);
});

// Student invoices
Schema::create('student_invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique(); // e.g., INV-2026-001-0001
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
    $table->integer('semester');
    $table->decimal('total_amount', 12, 2);
    $table->decimal('paid_amount', 12, 2)->default(0);
    $table->decimal('outstanding_amount', 12, 2)->storedAs('total_amount - paid_amount');
    $table->enum('status', ['pending', 'partially_paid', 'paid', 'overdue'])->default('pending');
    $table->date('due_date');
    $table->timestamp('paid_at')->nullable();
    $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['student_id', 'status']);
    $table->index(['status', 'due_date']);
});

// Invoice line items
Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->string('description'); // e.g., "SPP Semester 5"
    $table->decimal('amount', 12, 2);
    $table->timestamps();
});

// Payments
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->string('payment_number')->unique(); // e.g., PAY-2026-0001
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->decimal('amount', 12, 2);
    $table->enum('payment_method', ['cash', 'bank_transfer', 'credit_card', 'e_wallet']);
    $table->string('transaction_reference')->nullable(); // bank transfer ID, etc.
    $table->enum('status', ['pending', 'verified', 'failed'])->default('pending');
    $table->timestamp('paid_at');
    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('verified_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index(['student_id', 'paid_at']);
});

// Scholarships
Schema::create('scholarships', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->enum('type', ['full', 'partial', 'merit', 'need_based']);
    $table->decimal('discount_percentage', 5, 2)->nullable(); // for partial
    $table->decimal('fixed_amount', 12, 2)->nullable(); // for fixed amount
    $table->integer('duration_semesters')->default(1);
    $table->text('requirements')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Student scholarship assignments
Schema::create('student_scholarships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('scholarship_id')->constrained()->cascadeOnDelete();
    $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
    $table->integer('semester');
    $table->date('start_date');
    $table->date('end_date');
    $table->enum('status', ['active', 'completed', 'revoked'])->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->unique(['student_id', 'scholarship_id', 'academic_year_id', 'semester']);
});
```

**New Dependencies:**
- PhpSpreadsheet (^1.29) - for Excel reports
- Dompdf (^2.0) - for PDF receipts
- Laravel Queues - for invoice generation jobs
- Payment gateway SDK (optional - Midtrans/Xendit)

**Files to Create/Modify:**
- Models:
  - `app/Models/Financial/TuitionFee.php`
  - `app/Models/Financial/StudentInvoice.php`
  - `app/Models/Financial/InvoiceItem.php`
  - `app/Models/Financial/Payment.php`
  - `app/Models/Financial/Scholarship.php`
  - `app/Models/Financial/StudentScholarship.php`
- Services:
  - `app/Support/InvoiceGenerationService.php`
  - `app/Support/PaymentProcessingService.php`
- Livewire Components:
  - `app/Livewire/Financial/TuitionFeeTable.php`
  - `app/Livewire/Financial/InvoiceTable.php`
  - `app/Livewire/Financial/PaymentTable.php`
  - `app/Livewire/Financial/ScholarshipTable.php`
  - `app/Livewire/Financial/StudentInvoiceView.php` (student side)
- Views:
  - `resources/views/components/admin/financial/` (all admin tables)
  - `resources/views/components/student/financial/` (invoice view, payment history)
- Commands:
  - `app/Console/Commands/GenerateSemesterInvoices.php` (scheduled job)
- Migrations: 6 migration files

**Success Metrics:**
- 95% on-time tuition payment rate
- Zero discrepancies in financial records
- <2 seconds invoice generation per student
- 100% automated invoice delivery

**Dependencies:**
- Requires: Existing academic_years, study_programs, users tables ✅
- Blocks: None
- Related: PMB (registration fee), Student Services (payment verification)

---

## 📋 Planning Queue (Not Started Yet)

Fitur-fitur berikut sudah diidentifikasi namun belum masuk tahap implementasi aktif.

### Academic Module Enhancements

- [ ] **Teaching Schedule Calendar** (Lecturer) - FullCalendar integration
- [ ] **Assignment Management** (Lecturer + Student) - Online submission & grading
- [ ] **Student Progress Analytics** (Lecturer) - Performance dashboard
- [ ] **Academic Advising Dashboard** (Lecturer) - PA features
- [ ] **Course Evaluation Results** (Lecturer) - View student feedback
- [ ] **Office Hours Management** (Lecturer) - Appointment booking
- [ ] **Rubric Builder** (Lecturer) - Custom grading rubrics
- [ ] **Academic Progress Tracker** (Student) - Visual GPA/SKS tracking
- [ ] **Grade Appeal** (Student) - Formal appeal process
- [ ] **Study Plan Comparison** (Student) - KRS comparison tool
- [ ] **Lecture Evaluation** (Student) - Evaluate lecturers
- [ ] **Personalized Notifications** (Student) - Smart alerts
- [ ] **Document Request Center** (Student) - Online surat requests

### New Business Modules (Admin)

- [ ] **Student Services & Administration** - Letters, leave, transfers, graduation
- [ ] **Lecturer HR & Administration** - Profiles, workload, certifications
- [ ] **Alumni Management** - Tracer study, job board
- [ ] **Extracurricular & Organization Management** - Student activities
- [ ] **Library Management Integration** - Book borrowing system
- [ ] **Hostel/Dormitory Management** - Room allocation
- [ ] **Internship/PKL Management** - Placement tracking
- [ ] **Thesis/Final Project Management** - Skripsi monitoring
- [ ] **Research & Community Service** - Dosen research tracking
- [ ] **Partnership & MOU Management** - Institutional cooperation

### System Enhancements

- [ ] **Audit Trail Enhancement** - Advanced filtering & search
- [ ] **Role-Based Permission Matrix** - Visual permission editor
- [ ] **Data Import/Export Center** - Centralized hub
- [ ] **Notification Center** - Multi-channel delivery
- [ ] **Backup & Restore Manager** - Automated backups
- [ ] **User Activity Insights** - Behavior analytics
- [ ] **Multi-Campus Support** - Multiple locations
- [ ] **Advanced Search Engine** - Elasticsearch integration
- [ ] **Workflow Automation** - Business process engine
- [ ] **API Management Portal** - RESTful API
- [ ] **Custom Field Builder** - Dynamic fields

### Student Experience Features

- [ ] **Digital Student ID** - QR code card
- [ ] **Peer Study Group** - Collaboration platform
- [ ] **Career Services Portal** - Job board
- [ ] **Campus Map & Navigation** - Interactive map
- [ ] **Wellness & Mental Health Resources** - Counseling
- [ ] **Gamification & Achievements** - Badges & points

---

## 📊 Implementation Statistics

### Current Sprint (Week 1-2)
- **Total Features In Progress:** 5
- **Roles Impacted:** Lecturer, Student, Admin
- **Modules Affected:** Academic (enhancement), PMB (new), Financial (new)
- **Estimated Total Effort:** ~18-23 days

### Completion Tracking
- ✅ Completed Features: 1 (Course Materials Management)
- 🚧 In Progress: 4
- ⏸️ Planned: 40+
- ❌ Not Started: 40+

### Module Distribution
- **Academic:** 13 features (existing + enhancements)
- **PMB (Admission):** 1 feature (new module)
- **Financial:** 1 feature (new module)
- **Student Services:** 1 feature (planned)
- **Lecturer HR:** 1 feature (planned)
- **Alumni:** 1 feature (planned)
- **System:** 11 features (planned)
- **Other:** 12 features (planned)

---

## 🔄 Update History

- **2026-05-05 (Implementation Session):**
  - ✅ **COMPLETED: Course Materials Management** (Priority 1)
    - Created database migrations (course_materials & course_material_downloads tables)
    - Implemented Eloquent models with relationships and activity logging
    - Built lecturer Livewire component with file upload functionality
    - Built student Livewire component with download tracking and filters
    - Created responsive Blade views with Bootstrap modals
    - Added download controller with authorization checks
    - Integrated navigation into lecturer course-offerings & student schedule pages
    - All routes registered and tested
    - No syntax errors, ready for production use
  - Initial implementation tracker created
  - Added 5 high-priority features currently in progress
  - Organized by global priority order across roles
  - Included detailed technical specifications for each feature
  - Added module categorization (Academic, PMB, Financial)
  - Created planning queue for future features
- Last updated by: AI Assistant

---

**Note:** This is a living document that will be updated as features progress through development phases.
