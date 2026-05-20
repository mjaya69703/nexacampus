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
- ✅ **Comments & Discussion System** - BARU DITAMBAHKAN (Phase 2). Threaded comments dengan reply, like, edit/delete, dan soft delete.
- ✅ **Material Likes** - BARU DITAMBAHKAN (Phase 2). Students/lecturers bisa like/unlike materi dengan counter real-time.
- ✅ **Comment Likes** - BARU DITAMBAHKAN (Phase 2). Like system terpisah untuk comments menggunakan `comment_likes` table.
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
  - ✅ View & moderate discussion threads (Phase 2)
  - ✅ Like/unlike course materials (Phase 2)
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
  - ✅ Post comments & replies to discussions (Phase 2)
  - ✅ Like/unlike course materials (Phase 2)
  - ✅ Like comments in discussion threads (Phase 2)
  - ✅ Edit/delete own comments with tracking (Phase 2)

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
- ✅ Models: 
  - `app/Models/Academic/CourseMaterial.php` (enhanced with likes/comments relationships)
  - `app/Models/Academic/CourseMaterialDownload.php`
  - `app/Models/Academic/CourseMaterialFile.php`
  - `app/Models/Academic/CourseMaterialBookmark.php`
  - `app/Models/Academic/CourseMaterialComment.php` (Phase 2 - threaded comments)
  - `app/Models/Academic/MaterialLike.php` (Phase 2 - material likes with global user_id)
  - `app/Models/Academic/CommentLike.php` (Phase 2 - comment likes)
- ✅ Livewire Components:
  - `app/Livewire/Academic/CourseMaterialTable.php` (lecturer)
  - `app/Livewire/Academic/StudentCourseMaterialTable.php` (student)
  - `resources/views/components/student/⚡course-material-comments.blade.php` (Phase 2)
  - `resources/views/components/lecturer/⚡course-material-comments.blade.php` (Phase 2)
- ✅ Views:
  - Lecturer: `⚡index.blade.php`, `⚡list.blade.php`, `⚡show.blade.php` (enhanced with discussion section)
  - Student: `⚡index.blade.php`, `⚡show.blade.php`, `⚡course-materials.blade.php` (enhanced with discussion section)
- ✅ Migrations:
  - Phase 1: `2026_05_05_193435_create_course_materials_table.php`
  - Phase 1: `2026_05_05_193438_create_course_material_downloads_table.php`
  - Phase 1: `2026_05_06_171729_make_course_materials_file_fields_nullable.php`
  - Phase 1: `2026_05_06_172237_create_course_material_files_table.php`
  - Phase 1: `2026_05_07_010212_create_course_material_bookmarks_table.php`
  - Phase 2: `2026_05_07_043429_create_course_material_comments_table.php`
  - Phase 2: `2026_05_07_043432_create_course_material_likes_table.php`
  - Phase 2: `2026_05_07_045418_add_likes_count_to_course_materials_table.php`
  - Phase 2: `2026_05_07_051755_create_course_material_likes_table.php` (renamed from comment_likes)
- ✅ Controller: `app/Http/Controllers/Lecturer/CourseMaterialController.php` (download handler)
- ✅ Routes: Added lecturer & student routes in `routes/web.php`
- ✅ Navigation: Integrated into lecturer course-offerings show page & student schedule page
- ✅ Model Enhancement: Added relationships to `CourseOffering` and `StudentProfile` models

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
**Status:** ✅ COMPLETED  
**Roles Affected:** Admin/Superuser, Lecturer (via permission), Student  
**Module Category:** Publication → Submodule: `announcements`

**Catatan Evaluasi (Status Implementasi):**
- ✅ **Multi-scope targeting** - SUDAH ADA. Support global, faculty, study_program, course_offering, lecturer, student via PHP Enums.
- ✅ **Permission-based access** - SUDAH ADA. Admin base owner, Lecturer via `announcement.*` permissions, Student read-only based on targeting.
- ✅ **Jodit Editor v4** - SUDAH ADA. Rich text editor untuk content announcement (consistent dengan course materials).
- ✅ **Auto-mark as read** - SUDAH ADA. Otomatis mark read saat user buka detail announcement.
- ✅ **Unread count badges** - SUDAH ADA. Badge counter di sidebar menu + dashboard widgets.
- ✅ **Inbox/Mine tabs (Lecturer)** - SUDAH ADA. Inbox shows all targeted announcements, Mine tab shows created by lecturer.
- ✅ **Custom pagination** - SUDAH ADA. Lecturer (10/20/50), Student (10/20) dengan custom UI (bukan PowerGrid).
- ✅ **Search & filter** - SUDAH ADA. Search by title/content, filter by priority & date range.
- ✅ **Single attachment (max 5MB)** - SUDAH ADA. Optional file attachment (PDF, DOC, XLS, images).
- ✅ **Purple theme consistency** - SUDAH ADA. Color scheme `#667eea`/`#764ba2` consistent dengan app design.
- ❌ **Email notifications** - BELUM ADA. Future enhancement untuk notify users saat announcement published.
- ❌ **Push notifications (PWA)** - BELUM ADA. Future enhancement untuk real-time alerts.
- ❌ **Read statistics dashboard** - BELUM ADA. Admin/lecturer belum bisa lihat detailed read analytics per announcement.

**Description:**
Sistem pengumuman untuk komunikasi dosen-mahasiswa yang efektif dan terdokumentasi dengan multi-scope targeting.

**Features:**
- **Admin/Superuser Side (Base Owner):**
  - ✅ Full CRUD untuk semua announcements
  - ✅ Manage announcements across all scopes (global, faculty, program, course, individual)
  - ✅ Create campus-wide announcements (global scope)
  - ✅ Assign permissions to lecturers
  - ✅ Schedule announcements for future publishing
  - ✅ Archive/delete any announcement
  - ❌ View detailed read statistics *(BELUM IMPLEMENTED)*

- **Lecturer Side (Via Permission):**
  - ✅ Create announcements for their assigned course offerings
  - ✅ Jodit rich text editor (bold, italic, lists, links, tables)
  - ✅ Attach single optional file (max 5MB: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG)
  - ✅ Schedule announcements (publish later)
  - ✅ Pin important announcements
  - ✅ Mark as read/unread tracking
  - ✅ Edit/delete own announcements only
  - ✅ Inbox/Mine tabs untuk organize announcements
  - ✅ Custom pagination (10/20/50 items per page)
  - ✅ Delete confirmation modal
  - ❌ View read statistics per announcement *(BELUM IMPLEMENTED)*

- **Student Side:**
  - ✅ View announcements targeted to them based on:
    - Global announcements
    - Faculty-level announcements
    - Study program announcements
    - Course offering announcements (enrolled courses)
    - Personal announcements (targeted specifically to student)
  - ✅ Announcement list dengan:
    - Title & content preview (rich text)
    - Posted date & time
    - Creator name (lecturer/admin)
    - Priority level (Normal/Important/Urgent) dengan visual badge
    - Read/unread status indicator
  - ✅ Auto-mark as read when viewing detail
  - ✅ Unread count badge in sidebar
  - ✅ Filter by priority and date range
  - ✅ Search by title and content
  - ✅ Sort by published date, priority, pinned status
  - ✅ Custom pagination (10/20 items per page)

**Why Important:**
- Komunikasi efektif & terdokumentasi
- Inform perubahan jadwal, deadline, dll
- Semua mahasiswa receive same information
- Reduce miscommunication
- Tidak ketinggalan info penting dari dosen

**Estimated Effort:** Low-Medium (1-2 days total)
- Lecturer side: 1 day
- Student side: 0.5 day
- Notification integration: 0.5 day (future)

**Technical Notes:**
- **Architecture:** Publication module dengan multi-scope targeting pattern
- **Target Types:** PHP Enum `AnnouncementTargetType` (global, faculty, study_program, course_offering, lecturer, student)
- **Priority Levels:** PHP Enum `AnnouncementPriority` (normal, important, urgent) dengan visual indicators
- **Permission System:** Lecturer access via permission registration (`announcement.viewAny`, `.view`, `.create`, `.update`, `.delete`, `.publish`)
- **Database:** `target_type` (string with enum casting) + `target_id` (nullable) untuk flexible targeting
- **Rich Text Editor:** Jodit Editor v4 (consistent dengan existing course materials pattern)
- **Read Tracking:** Pivot table `announcement_reads` track reads by user_id (not just student)
- **Auto-mark as Read:** Saat user membuka detail announcement, otomatis mark as read via `updateOrCreate`
- **Notifications:** Future enhancement (email/push notifications not in v1)
- **Authorization:** 
  - Admin/Superuser: Full access via permissions
  - Lecturer: Can manage announcements for their course offerings (scoped by permission + ownership)
  - Student: Read-only access based on targeting logic (no permission needed)
- **File Storage:** Laravel public disk, max 5MB, allowed types: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG
- **Soft Deletes:** Announcement model uses soft deletes, AnnouncementRead uses hard deletes (cascade)

**Database Changes Required:**
```php
// New table: announcements (Publication module)
Schema::create('announcements', function (Blueprint $table) {
    $table->id();
    
    // Multi-scope targeting
    $table->string('target_type'); // Cast to AnnouncementTargetType enum
    $table->unsignedBigInteger('target_id')->nullable(); // ID dari target (NULL jika global)
    
    // Creator info
    $table->foreignId('created_by')
        ->constrained('users')
        ->cascadeOnDelete(); // Admin or Lecturer
    
    // Content
    $table->string('title');
    $table->longText('content'); // HTML from Jodit editor
    
    // Metadata
    $table->string('priority')->default('normal'); // Cast to AnnouncementPriority enum
    $table->boolean('is_pinned')->default(false);
    $table->boolean('is_published')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamp('scheduled_at')->nullable(); // For scheduled publishing
    
    // Optional single attachment
    $table->string('attachment_path')->nullable();
    $table->string('attachment_name')->nullable();
    $table->string('attachment_type')->nullable(); // pdf, doc, docx, xls, xlsx, jpg, png
    $table->integer('attachment_size')->nullable(); // in bytes
    
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes for performance
    $table->index(['target_type', 'target_id']);
    $table->index(['is_published', 'published_at']);
    $table->index(['priority', 'is_published']);
});

// Pivot table for tracking reads (by user, not just student)
Schema::create('announcement_reads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('announcement_id')
        ->constrained('announcements')
        ->cascadeOnDelete();
    $table->foreignId('user_id')
        ->constrained('users')
        ->cascadeOnDelete(); // Track reads for all user types
    $table->timestamp('read_at');
    
    $table->unique(['announcement_id', 'user_id']); // Prevent duplicate reads
});
```

**Attachment Design Decision:**
- **Single optional attachment** per announcement (not multiple)
- **Rationale:** Announcements are for communication, not file repository
- **If need multiple files:** Upload to Course Materials, then share link in announcement
- **Supported types:** PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (max 5MB)
- **Storage:** Laravel public disk at `storage/app/public/announcements/{YYYY}/{MM}/`
- **Max size:** 5MB per attachment

**Files Created/Modified:**
- ✅ **Enums (first in project):** 
  - `app/Enums/AnnouncementTargetType.php` (global, faculty, study_program, course_offering, lecturer, student)
  - `app/Enums/AnnouncementPriority.php` (normal, important, urgent with badgeClass, icon, label helpers)
- ✅ **Models:** 
  - `app/Models/Publication/Announcement.php` (with soft deletes, activity logging, query scopes)
  - `app/Models/Publication/AnnouncementRead.php` (pivot table, no timestamps)
- ✅ **Livewire Components:**
  - `app/Livewire/Publication/AnnouncementTable.php` (PowerGrid table for admin)
  - Anonymous components dalam view files untuk forms/views (pattern NexaCampus)
- ✅ **Views:**
  - Admin (3 files): `⚡index.blade.php`, `⚡create.blade.php`, `⚡edit.blade.php`
  - Lecturer (4 files): `⚡index.blade.php` (Inbox/Mine tabs), `⚡show.blade.php`, `⚡create.blade.php`, `⚡edit.blade.php`
  - Student (2 files): `⚡index.blade.php` (paginated list), `⚡show.blade.php` (detail view)
- ✅ **Migrations:**
  - `2026_05_08_000001_create_announcements_table.php`
  - `2026_05_08_000002_create_announcement_reads_table.php`
- ✅ **Routes:** 10 routes added to `routes/web.php` (4 admin, 4 lecturer, 2 student)
- ✅ **Config:** 
  - `app/Support/SidebarMenu.php` - Added Publication groups for lecturer & student
  - `config/resources.php` - Added announcement resource entry
- ✅ **Dashboard Integration:**
  - Lecturer dashboard: Announcement widget (col-lg-4 sidebar) with unread badge + "Buat Pengumuman" shortcut
  - Student dashboard: Announcement widget (col-lg-4 third column) with unread badge

**Implementation Details:**
- Multi-scope targeting dengan PHP Enums (first use in project)
- Permission-based access control via Spatie Permission
- Jodit Editor v4 integration untuk rich text content
- Auto-mark as read saat user view detail announcement
- Unread count calculation untuk sidebar badges
- Custom pagination UI (non-PowerGrid) untuk lecturer & student
- Purple gradient theme (`#667eea`/`#764ba2`) consistent dengan app
- File upload validation (max 5MB, allowed types)
- Soft deletes untuk announcement, cascade delete untuk reads

**Success Metrics:**
- >90% announcement read rate within 24 hours
- Average 2-3 announcements per course per week
- Reduced email inquiries about schedule changes

**Dependencies:**
- Requires: Existing course_offerings system ✅
- Requires: Permission system for lecturer access ✅
- Blocks: None
- Related: Personalized Notifications (future enhancement)

**Recommended Future Enhancements:**
- 🔧 **Email Notifications** - Send email saat announcement published (important/urgent priority)
- 🔧 **Push Notifications (PWA)** - Real-time push notification untuk new announcements
- 🔧 **Read Statistics Dashboard** - Detailed analytics: who read, when, read rate per announcement
- 🔧 **Bulk Actions** - Delete/archive multiple announcements sekaligus
- 🔧 **Announcement Templates** - Pre-defined templates untuk common announcements
- 🔧 **Scheduled Reminders** - Auto-remind students yang belum baca announcement penting setelah X jam
- 🔧 **Export Announcements** - Export announcement list ke PDF/Excel untuk documentation
- 🔧 **Reaction System** - Students bisa react (👍❤️😮) ke announcements seperti social media

---

#### **Priority 3: Assessment & Grading Enhancement**
*Impact: Lecturer + Student + Admin | Module: Academic*

##### 3. Grade Book with Export 📊
**Status:** ✅ COMPLETED  
**Roles Affected:** Lecturer (export), Admin (reporting)  
**Module Category:** Academic → Enhancement to existing `student-grades`

**Catatan Evaluasi (Status Implementasi):**
- ✅ **Comprehensive grade overview** - SUDAH ADA. Dedicated page untuk lecturer dengan statistics dashboard.
- ✅ **Filter by course/semester/year** - SUDAH ADA. Filter dropdowns untuk course offering, academic year, semester.
- ✅ **Export to Excel (.xlsx)** - SUDAH ADA. PhpSpreadsheet integration untuk Excel export.
- ✅ **Export to PDF (formatted report)** - SUDAH ADA. Dompdf integration untuk PDF generation.
- ✅ **Real-time search** - SUDAH ADA. Search by student name atau NIM.
- ✅ **Grade badges** - SUDAH ADA. Visual indicator dengan color coding (A=green, B=blue, C=yellow, D/F=red).
- ✅ **Statistics dashboard** - SUDAH ADA. 6 stat cards (total students, average score, pass rate, etc.).
- ✅ **Role-based UI pattern** - SUDAH ADA. Lecturer pakai dedicated page, Admin pakai PowerGrid table.
- ❌ **Grade distribution charts** - BELUM ADA. Future enhancement untuk visualisasi bar/pie chart.
- ❌ **Bulk grade operations** - BELUM ADA. Future enhancement untuk curve grades, apply formula.
- ❌ **Print-friendly view** - BELUM ADA. Future enhancement untuk optimized print layout.

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

**Implementation Details & UI Pattern Decision:**

**UI Pattern Berdasarkan Role (IMPORTANT DECISION):**

1. **Lecturer Interface - Dedicated Grade Book Page:**
   - Route: `/lecturer/student-grades/grade-book`
   - Component: Anonymous Livewire (`⚡grade-book.blade.php`)
   - Features:
     - Hero section dengan purple/blue gradient (#667eea → #764ba2)
     - Statistics dashboard (total students, graded count, finalized count, average score, median, pass rate, std deviation)
     - Grade distribution analysis (A+, A, B+, B, C, D, E breakdown with percentages)
     - Filter dropdowns (course offering, academic year, semester)
     - Real-time search by student name/NIM/course
     - Responsive table dengan grade badges (color-coded)
     - Export buttons: CSV, Excel (.xlsx), PDF dengan statistics & distribution
   - Rationale: Lecturers prefer visual context, quick overview, teaching-focused interface
   - Theme: Modern card-based design consistent dengan lecturer dashboard

2. **Admin Interface - PowerGrid Table with Filter-Aware Export:**
   - Route: `/admin/academic/student-grades`
   - Component: Simple view (`⚡index.blade.php`) + PowerGrid table (`StudentGradeTable.php`)
   - Features:
     - **Simple card layout** dengan export buttons di header (CSV, Excel, PDF)
     - **PowerGrid built-in filtering**: Search, column filters, sorting, pagination
     - **Filter-aware export**: Export respects current PowerGrid filters
       - When user applies filters in PowerGrid → export only filtered data
       - Uses Livewire events to trigger export from within PowerGrid component
       - `getFilteredRows()` method extracts current filter state
     - Bulk operations (checkbox selection)
     - Column toggles (show/hide columns)
     - Permission check: `student-grade.viewAny` or `student-grade.view`
   - Rationale: Admins need powerful filtering + export that respects those filters
   - Theme: Clean, functional admin interface - no unnecessary visual fluff

**Technical Implementation:**
- Service Layer: `app/Support/GradeExportService.php` handles both roles
  - `rows(User $user, array $filters, bool $admin)`: Returns filtered collection
  - `statistics(Collection $rows)`: Calculate total_students, graded_count, average_score, median_score, highest_score, lowest_score, pass_rate, standard_deviation
  - `distribution(Collection $rows)`: Grade distribution (A+ through E) with counts and percentages
  - `streamCsv()`, `streamXlsx()`: Streaming exports dengan UTF-8 BOM support
  - Role detection via `$admin` parameter (not role checking inside service)
  - Admin mode: Gets ALL grades from database
  - Lecturer mode: Filters by CourseOfferingLecturer relationship
- Controllers: Separate controllers untuk lecturer dan admin
  - `app/Http/Controllers/Lecturer/GradeExportController.php` (csv, excel, pdf methods)
  - `app/Http/Controllers/Admin/Academic/GradeExportController.php` (csv, excel, pdf methods with permission checks)
- PDF Generation: Dompdf with DejaVu Sans font, landscape A4 paper
  - Template: `resources/views/exports/grade-book-pdf.blade.php`
  - Includes statistics table + detailed grade rows
- UTF-8 Encoding: BOM (Byte Order Mark) added to CSV exports
- Session-based filter passing: Livewire redirect dengan query parameters
- Database Indexes: Added indexes on `grade_status` and `graded_at` columns for performance
- ActivePermission: Used in controllers dan views untuk authorization

**Files Created/Modified:**
- ✅ Services: `app/Support/GradeExportService.php` (complete export service dengan statistics & distribution)
- ✅ Controllers:
  - `app/Http/Controllers/Lecturer/GradeExportController.php` (csv, excel, pdf methods)
  - `app/Http/Controllers/Admin/Academic/GradeExportController.php` (csv, excel, pdf with ActivePermission checks)
- ✅ Livewire Components:
  - New: `resources/views/components/lecturer/student-grades/⚡grade-book.blade.php` (anonymous component dengan statistics dashboard)
  - Enhanced: `app/Livewire/Academic/StudentGradeTable.php` (added filter-aware export methods with Livewire events)
- ✅ Views:
  - New: `resources/views/exports/grade-book-pdf.blade.php` (PDF template dengan statistics table)
  - Enhanced: `resources/views/components/lecturer/student-grades/⚡index.blade.php` (Grade Book navigation button)
- ✅ Routes: Added lecturer grade-book route + separate CSV/Excel/PDF routes untuk lecturer dan admin
- ✅ Migrations: `2026_05_10_000001_add_grade_book_indexes.php` (indexes on grade_status & graded_at)
- ✅ Dependencies: phpoffice/phpspreadsheet ^5.7, dompdf/dompdf ^3.1
- ✅ Documentation: Updated status to COMPLETED

**Success Metrics:**
- 100% of lecturers use export feature at end of semester
- <5 seconds export time for 100+ students
- Zero data discrepancies in exported reports
- >90% lecturer satisfaction with Grade Book UI
- >85% admin efficiency improvement with PowerGrid export

**Recommended Future Enhancements:**
- 🔧 **Grade Distribution Charts** - Bar chart & pie chart visualization menggunakan Chart.js
- 🔧 **Bulk Grade Operations** - Curve grades, apply formula, batch update
- 🔧 **Print-Friendly View** - Optimized CSS untuk print layout
- 🔧 **Advanced Analytics Dashboard** - Trend analysis, comparison across semesters
- 🔧 **Grade History Tracking** - Track perubahan nilai dengan timestamp
- 🔧 **Automated Report Generation** - Schedule weekly/monthly reports
- 🔧 **Department-Level Filtering** - Admin bisa filter by faculty/department
- 🔧 **Custom Grade Scales** - Support different grading systems per program
- 🔧 **Export Templates** - Pre-formatted templates untuk different report types
- 🔧 **Grade Validation Rules** - Auto-check untuk anomali (e.g., sudden grade drops)

**Dependencies:**
- Requires: Existing student_grades system ✅
- Requires: PhpSpreadsheet & Dompdf packages ✅
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
**Status:** 🚧 IN PROGRESS (Phase 5 implemented: invoice scheduling, automation, and email notifications; payment gateway deferred)
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
  - Overpayment handling via student credit balance, so verified excess payment can be tracked for refund or future use instead of silently disappearing
  - Finance can reject mismatched payment proof/amount during verification

- **Adjustments, Discounts & Credit Balance:**
  - Invoice adjustment flow for invoices that already have payment history
  - Adjustment types: correction, discount, scholarship, waiver, penalty, write-off
  - Admin/finance discount and waiver are direct actions, not approval-based for MVP
  - Optional discount/voucher code model can be added later, but Phase 4 uses direct finance/admin application first
  - Student credit balance records overpayment, refund, credit applied, and manual credit correction
  - Overpayment creates auditable credit balance transaction linked to the payment/invoice
  - Credit balance can support refund workflow later without changing original payment history
  - Adjustment records are auditable and should not rewrite original invoice item snapshots
  
- **Financial Reports:**
  - Payment summary per semester
  - Outstanding payments report
  - Revenue by study program
  - Payment trend analysis
  - Adjustment/discount/waiver report
  - Scholarship utilization report
  - Student credit balance and overpayment report
  - Export to Excel/PDF
  
- **Scholarship Management:**
  - Scholarship types & criteria
  - Student scholarship assignment
  - Percentage-based and fixed-amount scholarship support
  - Scholarship amount & duration
  - Renewal tracking
  - Scholarship can be auto-applied during invoice generation when the assignment matches academic year/semester
  - Existing invoices can receive scholarship through invoice adjustment, not by editing original invoice items directly

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
- Phase 4 tables: `invoice_adjustments`, `student_credit_balances`, `student_credit_transactions`, `scholarships`, `student_scholarships`
- Invoice generation logic
- Payment status tracking (Pending → Paid → Overdue)
- Integration dengan payment gateway (Midtrans/Xendit)
- PDF receipt generation dengan Dompdf
- Scheduled jobs untuk invoice generation
- Consider double-entry accounting for accuracy
- Implement role-based access (admin full access, student view-only)
- Phase 4 should treat invoice totals as item snapshot plus adjustment ledger, so paid invoices are not edited destructively
- Overpayment policy: verified amount beyond outstanding balance becomes student credit balance; finance can later refund or apply the credit
- Late penalty policy should be soft/configurable. Default MVP behavior is manual penalty adjustment only; automatic daily penalties are optional and disabled by default
- If automatic penalty is enabled later, invoice should snapshot the penalty rule (`none`, `manual`, `daily`) and create auditable penalty adjustments instead of silently mutating invoice items

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

// Future: auditable invoice adjustments after payment exists
Schema::create('invoice_adjustments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
    $table->string('adjustment_type'); // correction, discount, scholarship, waiver, penalty, write_off
    $table->decimal('amount', 12, 2); // signed amount: negative reduces invoice, positive increases invoice
    $table->nullableMorphs('source'); // optional origin: scholarship assignment, admin action, penalty policy, etc.
    $table->text('reason')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['student_invoice_id', 'adjustment_type']);
});

// Future: student credit balance summary
Schema::create('student_credit_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
    $table->decimal('balance', 12, 2)->default(0);
    $table->timestamps();

    $table->unique('student_profile_id');
});

// Future: auditable credit movement ledger
Schema::create('student_credit_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
    $table->foreignId('student_invoice_id')->nullable()->constrained('student_invoices')->nullOnDelete();
    $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
    $table->string('transaction_type'); // overpayment, refund, credit_applied, manual_adjustment
    $table->decimal('amount', 12, 2); // signed amount: positive adds credit, negative consumes/refunds credit
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['student_profile_id', 'transaction_type']);
});

// Scholarships
Schema::create('scholarships', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->enum('type', ['full', 'partial', 'merit', 'need_based']);
    $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
    $table->decimal('discount_percentage', 5, 2)->nullable();
    $table->decimal('fixed_amount', 12, 2)->nullable();
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
  - ✅ `app/Models/Financial/InvoiceAdjustment.php`
  - ✅ `app/Models/Financial/StudentCreditBalance.php`
  - ✅ `app/Models/Financial/StudentCreditTransaction.php`
  - ✅ `app/Models/Financial/Scholarship.php`
  - ✅ `app/Models/Financial/StudentScholarship.php`
- Services:
  - `app/Support/InvoiceGenerationService.php`
  - `app/Support/PaymentProcessingService.php`
  - ✅ `app/Support/Financial/InvoiceAdjustmentService.php`
  - ✅ `app/Support/Financial/StudentCreditService.php`
  - ✅ `app/Support/Financial/ScholarshipApplicationService.php`
  - ✅ `app/Support/Financial/FinancialReportExportService.php`
- Livewire Components:
  - `app/Livewire/Financial/TuitionFeeTable.php`
  - `app/Livewire/Financial/InvoiceTable.php`
  - `app/Livewire/Financial/PaymentTable.php`
  - ✅ `app/Livewire/Financial/ScholarshipTable.php`
  - ✅ `app/Livewire/Financial/StudentScholarshipTable.php`
  - ✅ `app/Livewire/Financial/InvoiceAdjustmentTable.php`
  - ✅ `app/Livewire/Financial/StudentCreditTable.php`
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

**Implementation Phases (Actual):**
1. **Phase 1 - Fee Structure & Invoice Core** ✅
   - Tuition fee templates per academic year + study program + semester.
   - Student invoices use `student_profile_id` as financial identity.
   - Invoice item snapshots, invoice number generation, admin invoice management, and student invoice read-only pages.

2. **Phase 2 - Payment Processing & Installments** ✅
   - Student manual payment proof upload.
   - Admin payment verification/rejection with inline proof preview.
   - Payment records, payment history, and receipt PDF.
   - Installment request simulation, admin approval/rejection, and installment schedule generation.
   - Approved installment invoices are paid through installment schedule while allowing multiple installment rows to be paid together.

3. **Phase 3 - Holds, Clearance & Relief Policy** ✅
   - Financial clearance policies seeded for conservative defaults: `tuition`, `registration`, and `exam`.
   - Admin clearance policy dashboard to create/edit/toggle rules without editing code or database manually.
   - Default blocking targets: tuition blocks registration/KRS, registration blocks KRS, exam blocks exam card.
   - 7-day grace period before blocking; overdue students receive global warning banner first.
   - `financial_holds` audit trail with active/released/waived statuses.
   - Admin financial hold management with release and temporary dispensation/waiver.
   - Student registration and KRS routes use `FinancialClearanceService` via middleware, keeping finance checks centralized.

4. **Phase 4 - Scholarships, Adjustments & Reporting** ✅
   - Scholarship master data and student scholarship assignment.
   - Percentage-based and fixed-amount scholarship support.
   - Apply scholarship automatically during invoice generation when assignment matches, or manually to existing invoices as an adjustment.
   - Invoice adjustment flow after payment exists: correction, discount, scholarship, waiver, penalty, write-off.
   - Direct admin/finance discount and waiver actions for MVP; voucher/code-based discounts are optional later.
   - Student credit balance for overpayment, refund tracking, and future credit application.
   - Verified overpayment becomes an auditable credit transaction instead of breaking invoice totals.
   - Late penalties are soft/configurable: manual penalty adjustment first, automatic daily penalties optional and disabled by default.
   - Financial reports: payment summary, outstanding report, revenue by study program, payment trend, adjustment/discount summary, scholarship utilization, and credit balance report.
   - Export financial reports to Excel/PDF.

5. **Phase 5 - Invoice Scheduling, Automation & Email Notifications** ✅
   - Admin financial dashboard with revenue, outstanding, pending payment, overdue invoice, hold, schedule, credit, and scholarship summary.
   - Admin invoice schedule dashboard for planned invoice publishing.
   - Scheduled tuition/custom invoice generation by active students, selected students, or a single student.
   - Schedule status tracking: pending, running, completed, failed, cancelled.
   - Scheduler command: `financial:run-invoice-schedules`.
   - Automated overdue refresh command: `financial:refresh-overdue`.
   - Automated financial hold evaluation command: `financial:evaluate-holds`.
   - Laravel Scheduler entries for invoice schedules, overdue refresh, and hold evaluation.
   - Financial email namespace: `app/Mail/Financial`.
   - Financial email templates under `resources/views/templates/email/financial`.
   - Email delivery for invoice issued, invoice overdue, payment verified/rejected, and installment approved/rejected.
   - Payment gateway integration moved to Future Recommendations.

**Future Recommendations:**
- Midtrans/Xendit payment gateway integration.
- Gateway callback/webhook handling with idempotency and signature verification.
- Student wallet/credit usage for paying future invoices.
- Notification center / in-app notification bell.

---

## 🧭 Draft Next Priorities (Priority 6-10)

Draft berikut adalah kandidat lanjutan setelah Priority 1-5 sudah dianggap clear. Sumbernya digabung dari `will-be-implemented.md`, status implementasi aktual di dokumen ini, dan dependency yang sudah tersedia dari Academic, Admission, dan Financial.

---

#### **Priority 6: Student Services & Administration**
*Impact: Admin + Students | Module: New*

##### 6. Student Services - Letters, Leave, Transfer & Graduation 📋
**Status:** 🚧 IN PROGRESS (Phase 1 implemented: Letter Request Center)  
**Roles Affected:** Admin/Student Affairs (manage), Students (request/track), Academic/Finance (clearance checks)  
**Module Category:** New Module → `student-services`

**Description:**
One-stop layanan administrasi mahasiswa untuk request surat, cuti akademik, pindah program, pengajuan kelulusan, dan complaint/feedback. Modul ini jadi jembatan antara kebutuhan administrasi mahasiswa dan approval internal kampus.

**Why Next:**
- Paling nyambung setelah Financial karena banyak layanan administrasi butuh financial clearance.
- Memberikan workflow nyata untuk mahasiswa selain akademik dan pembayaran.
- Cocok untuk kampus karena surat, cuti, transkrip, dan kelulusan adalah proses operasional harian.

**Core Features:**
- ✅ Letter/certificate request: active student letter, transcript request, internship recommendation, and custom/manual letter types.
- ✅ Online approval workflow with status tracking.
- ✅ PDF letter generation using existing Dompdf pattern.
- ✅ Manual upload fulfillment for custom/final letter files.
- ✅ Hybrid fulfillment mode so admin can choose generate PDF or upload final file.
- ✅ Correction workflow: admin can request correction and student can revise/resubmit without creating a new request.
- ✅ Optional digital signature/signatory placeholder through signer name and position.
- Leave of absence application with attachments and multi-step approval.
- Student transfer request with curriculum/credit mapping notes.
- Graduation application with eligibility checklist.
- Complaint/feedback submission with department assignment and SLA tracking.

**Integration Notes:**
- ✅ Use `student_profile_id` as student identity.
- ✅ Integrate `FinancialClearanceService` for transcript/graduation/letter restrictions.
- ✅ Use existing admin resource registry + PowerGrid pattern.
- ✅ Student side follows existing modern student page pattern.

**Estimated Effort:** High (6-7 days)

**Recommended Phases:**
1. **Phase 1 - Letter Request Center**
   - ✅ Letter type master data.
   - ✅ Fulfillment modes: auto_generate, manual_upload, hybrid.
   - ✅ Student request form with dynamic fields and optional attachment.
   - ✅ Admin review/approve/reject/correction workflow.
   - ✅ Student correction/resubmit flow for requests marked `revision_requested`.
   - ✅ Status history/audit trail.
   - ✅ PDF generation.
   - ✅ Admin final file upload for manual fulfillment.
   - ✅ Student download for issued letters.
   - ✅ Financial clearance check for selected letter types.
2. **Phase 2 - Leave & Transfer Workflow**
   - Leave application, attachment, approval history.
   - Return from leave status.
   - Internal transfer request and evaluation notes.
3. **Phase 3 - Graduation & Complaints**
   - Graduation application and requirement checklist.
   - Complaint category, assignment, response, and resolution tracking.

**Candidate Tables:**
- ✅ `service_letter_types`
- ✅ `service_letter_requests`
- `student_leave_applications`
- `student_transfer_requests`
- `graduation_applications`
- `student_complaints`
- ✅ `service_request_status_histories`

---

#### **Priority 7: Assignment Management**
*Impact: Lecturer + Students | Module: Academic Enhancement*

##### 7. Assignment Submission & Grading 📝
**Status:** 📝 DRAFT / NOT STARTED  
**Roles Affected:** Lecturer (create/grade), Students (submit/track), Admin (monitor)  
**Module Category:** Academic → New Submodule: `assignments`

**Description:**
Sistem pengumpulan tugas online yang terhubung dengan course offering, materi perkuliahan, dan grade book. Dosen membuat tugas, mahasiswa submit file/text, dosen memberi nilai dan feedback.

**Why Next:**
- Melengkapi e-learning yang sudah punya materi, diskusi, bookmark, dan download tracking.
- Nyambung langsung ke grade book/export yang sudah ada.
- Memberikan workflow akademik harian yang sangat sering dipakai.

**Core Features:**
- Assignment per course offering/session.
- Assignment detail: title, description, due date, max score, allowed file types, max file size, rubric/instructions.
- File submission and optional text submission.
- Multiple file support.
- Resubmission before deadline.
- Late submission policy.
- Submission status: not submitted, submitted, late, graded, returned.
- Lecturer grading with score and feedback.
- Student view for grade and feedback.
- Optional reminder email before deadline.

**Integration Notes:**
- Link to `course_offerings`, student enrollments/KRS, and existing grade book.
- Assignment score can later become grade component input.
- Reuse secure file preview/download patterns from Admission and Course Materials.
- Student UI should mirror Course Materials page style.

**Estimated Effort:** Medium-High (3-4 days)

**Recommended Phases:**
1. **Phase 1 - Assignment Core**
   - Lecturer create/edit/list assignments.
   - Student list/detail assignments.
   - Submission upload and deadline validation.
2. **Phase 2 - Grading & Feedback**
   - Lecturer submission review.
   - Score/feedback.
   - Student graded result view.
3. **Phase 3 - Grade Book Integration**
   - Optional mapping to grade components.
   - Export/report integration.

**Candidate Tables:**
- `assignments`
- `assignment_submissions`
- `assignment_submission_files`
- `assignment_grades`
- `assignment_status_histories`

---

#### **Priority 8: Student Progress Analytics & Academic Advising**
*Impact: Lecturer + Students + Academic Advisors | Module: Academic Enhancement*

##### 8. Progress Analytics & Advisor Dashboard 📈
**Status:** 📝 DRAFT / NOT STARTED  
**Roles Affected:** Lecturer/Academic Advisor (monitor), Students (view progress), Admin (oversight)  
**Module Category:** Academic → New Submodule: `academic-analytics`

**Description:**
Dashboard monitoring performa mahasiswa berbasis nilai, SKS, KRS, absensi, materi, dan status finansial. Modul ini juga menjadi fondasi paling sehat untuk fitur AI academic assistant di masa depan.

**Why Next:**
- Data akademik, nilai, materi, admission, dan financial sudah cukup kaya untuk dianalisis.
- Membantu dosen PA mendeteksi mahasiswa berisiko lebih awal.
- AI akan lebih berguna jika analytics dan action workflow-nya sudah rapi.

**Core Features:**
- Student progress tracker: SKS completed, remaining credits, IPS/IPK trend.
- Semester-by-semester academic performance.
- Failed/repeated course detection.
- Attendance risk indicator.
- Financial risk indicator from overdue/hold state.
- Advisor dashboard per assigned students.
- Recommendation list: meet advisor, retake course, resolve KRS/payment issue, improve attendance.
- Student-facing progress page with charts.
- Export progress summary to PDF.

**Integration Notes:**
- Read from transcript/grade snapshots, study plans, attendance, course offerings, and financial holds.
- Do not duplicate source-of-truth grades; calculate from existing academic records.
- Use ApexCharts already used in financial dashboard.
- Future AI should consume this analytics layer, not raw scattered queries.

**Estimated Effort:** Medium-High (4-5 days)

**Recommended Phases:**
1. **Phase 1 - Student Progress Tracker**
   - Student academic progress page.
   - GPA/SKS trend charts.
   - Remaining requirement summary.
2. **Phase 2 - Advisor Dashboard**
   - Advisor-student assignment.
   - Risk indicators and student list.
   - Advisor notes/recommendations.
3. **Phase 3 - AI-Ready Insight Layer**
   - Consolidated analytics service.
   - Explainable risk reasons.
   - Recommendation history.

**Candidate Tables:**
- `academic_advisor_assignments`
- `student_progress_snapshots`
- `student_advisor_notes`
- `student_recommendations`

---

#### **Priority 9: Lecturer HR & Administration**
*Impact: Admin + Lecturers | Module: New / Academic Support*

##### 9. Lecturer HR, Workload & Certification 👨‍🏫
**Status:** 📝 DRAFT / NOT STARTED  
**Roles Affected:** Admin/HR (manage), Lecturer (view/update profile), Academic (workload planning)  
**Module Category:** New Module → `lecturer-hr`

**Description:**
Modul administrasi dosen untuk biodata lengkap, riwayat pendidikan, workload mengajar, sertifikasi, pelatihan, evaluasi, dan cuti dosen.

**Why Next:**
- Course offering dan lecturer assignment sudah ada, sehingga workload bisa dihitung dari data real.
- Berguna untuk akreditasi dan manajemen SDM akademik.
- Menambah sisi operasional kampus selain mahasiswa.

**Core Features:**
- Lecturer profile enhancement: education, employment status, join date, signature/photo.
- Teaching load calculation by semester/SKS/course offering.
- Workload cap and overload warning.
- Certification and training records.
- Expiry reminder for certification/training.
- Lecturer leave request and substitute lecturer assignment.
- Performance evaluation summary from teaching, student evaluation, research/community service placeholders.

**Integration Notes:**
- Enhance existing lecturer profile instead of duplicating user identity.
- Use course offering lecturer assignments for workload calculation.
- Future integration with Lecture Evaluation and Research modules.

**Estimated Effort:** Medium-High (4-5 days)

**Recommended Phases:**
1. **Phase 1 - Lecturer Profile & Documents**
   - Extended biodata.
   - Education/certification/training records.
   - Signature/photo upload.
2. **Phase 2 - Teaching Workload**
   - Workload dashboard.
   - Semester filters.
   - Overload/underload indicators.
3. **Phase 3 - Leave & Evaluation**
   - Lecturer leave request.
   - Substitute assignment.
   - Performance summary foundation.

**Candidate Tables:**
- `lecturer_educations`
- `lecturer_certifications`
- `lecturer_trainings`
- `lecturer_workload_snapshots`
- `lecturer_leaves`
- `lecturer_performance_reviews`

---

#### **Priority 10: Alumni & Career Services**
*Impact: Admin + Alumni + Students | Module: New*

##### 10. Alumni Management, Tracer Study & Career Services 🎓
**Status:** 📝 DRAFT / NOT STARTED  
**Roles Affected:** Admin/Career Center (manage), Alumni (update/survey), Students (career access)  
**Module Category:** New Module → `alumni`

**Description:**
Modul alumni untuk database lulusan, tracer study, engagement alumni, career services, job board, dan laporan outcome lulusan untuk kebutuhan akreditasi.

**Why Next:**
- Natural follow-up setelah graduation workflow di Student Services.
- Tracer study dan outcome lulusan penting untuk akreditasi kampus.
- Bisa menjadi modul publik/engagement yang memperkuat value NexaCampus.

**Core Features:**
- Alumni profile database: graduation year, program, GPA, contact, location.
- Employment status tracking: working, entrepreneur, studying, unemployed.
- Tracer study survey distribution and response tracking.
- Job relevance, time to employment, salary range, employer feedback.
- Alumni events and engagement.
- Job posting board and internship/career opportunities.
- Reports by batch, study program, employment status, industry, and location.

**Integration Notes:**
- Alumni can be created from graduated student profiles.
- Keep privacy controls for alumni directory visibility.
- Email survey delivery can reuse mail patterns from Admission/Financial.
- Career/job board can later connect to Student Services and Internship module.

**Estimated Effort:** Medium (3-4 days)

**Recommended Phases:**
1. **Phase 1 - Alumni Database**
   - Alumni profile.
   - Conversion from graduated student.
   - Admin list/detail.
2. **Phase 2 - Tracer Study**
   - Survey campaign.
   - Alumni response form.
   - Analytics dashboard.
3. **Phase 3 - Career Services**
   - Job posting board.
   - Career events.
   - Employer/partner records.

**Candidate Tables:**
- `alumni_profiles`
- `tracer_study_campaigns`
- `tracer_study_responses`
- `alumni_events`
- `job_postings`
- `employer_partners`

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
- **Total Features In Progress:** 3
- **Roles Impacted:** Lecturer, Student, Admin
- **Modules Affected:** Academic (enhancement), Publication (new), PMB (new), Financial (new)
- **Estimated Total Effort:** ~18-23 days

### Completion Tracking
- ✅ Completed Features: 3 (Course Materials Management, Announcement System, Grade Book with Export)
- 🚧 In Progress: 2
- ⏸️ Planned: 40+
- ❌ Not Started: 40+

### Module Distribution
- **Academic:** 13 features (existing + enhancements)
- **Publication:** 1 feature (new module — announcements ✅)
- **PMB (Admission):** 1 feature (new module)
- **Financial:** 1 feature (new module)
- **Student Services:** 1 feature (planned)
- **Lecturer HR:** 1 feature (planned)
- **Alumni:** 1 feature (planned)
- **System:** 11 features (planned)
- **Other:** 12 features (planned)

---

## 🔄 Update History

- **2026-05-14 (Financial Scholarships, Adjustments & Reporting Implementation):**
  - ✅ **PHASE 4: Scholarships, Adjustments, Credit Balance & Reports** (Pending Commit)
    - Added invoice adjustment ledger for correction, discount, scholarship, waiver, penalty, and write-off.
    - Added scholarship master data and student scholarship assignment workflow.
    - Added automatic scholarship application when matching invoices are issued, plus manual scholarship apply from invoice detail.
    - Added student credit balance and credit transaction ledger for verified overpayment, refund, and manual credit correction.
    - Updated payment verification so verify-time overpayment becomes auditable student credit instead of breaking invoice totals.
    - Updated invoice totals to include adjustment ledger without editing original invoice item snapshots.
    - Added admin pages for scholarships, student scholarships, invoice adjustments, student credits, and financial reports.
    - Added CSV, Excel, and PDF export for financial invoice summary reports.
    - Kept late penalties soft/configurable via manual penalty adjustment first; automatic penalties remain optional for later automation.
    - Files changed: financial phase four migration, models, services, PowerGrid tables, admin financial views, report export controller/service, resource registry, and routes.

- **2026-05-14 (Financial Clearance Holds & Policy Dashboard Implementation):**
  - ✅ **PHASE 3: Financial Holds, Clearance Policy & Relief** (Pending Commit)
    - Added configurable financial clearance policy dashboard under Financial menu.
    - Added `financial_clearance_policies` and `financial_holds` tables with conservative default policies.
    - Added global student warning banner for overdue/blocked financial workflows.
    - Added centralized `FinancialClearanceService` and `financial_clearance` middleware for student route gating.
    - Added configurable hold target mapping in `config/financial.php` so new student menu targets can be mapped without changing route definitions.
    - Added admin Financial Holds management with release and temporary dispensation/waiver actions.
    - Integrated payment verification with hold re-evaluation and auto-release behavior.
    - Hardened installment/payment edge cases: rounded installment schedules, pending payment cap, and verify-time overpayment guard.
    - Fixed invoice edit item-type normalization for tuition-generated invoice items.
    - Files changed: financial clearance middleware/service/models, config/resources, student layout banner, financial admin views, invoice/payment services.

- **2026-05-14 (Financial Payment Processing & Installment Workflow):**
  - ✅ **PHASE 2: Payment Verification & Installments** (Commit: d773ced)
    - Student manual payment proof upload from invoice detail.
    - Admin payment verification/rejection workflow with inline proof preview.
    - Payment records, payment history, receipt PDF route/template, and payment admin table.
    - Student installment request flow with tenor simulation.
    - Admin installment request approval/rejection and installment schedule generation.
    - Payment allocation across approved installment rows.
    - Files changed: payment/installment models, services, migrations, admin/student financial views, routes, resource registry.

- **2026-05-13 (Financial Fee Structure & Invoice Lifecycle):**
  - ✅ **PHASE 1: Tuition Billing & Invoice Core** (Commit: f712a8b)
    - Financial module namespace with tuition fee, student invoice, and invoice item models.
    - Tuition fee template/rule management by academic year, study program, and semester.
    - Student invoice generation using `student_profile_id` as financial identity.
    - Custom invoice draft/issue lifecycle and edit-before-payment rule.
    - Admin tuition fee and student invoice management pages.
    - Student financial menu and invoice list/detail pages.
    - Documented financial phase plan and installment policy.

- **2026-05-13 (Admission Final Audit & Stabilization):**
  - ✅ **COMPLETED: Admission Module Stabilization** (Priority 4, Commit: 4c57412)
    - Final audit for Admission implementation after phase 1-3 delivery.
    - Stabilized related course material tests and migration consistency.
    - Confirmed admission lifecycle from public application through conversion is ready to proceed into financial integration.

- **2026-05-12 (Admission Conversion & Acceptance Letter):**
  - ✅ **PHASE 3: Student Conversion, NIM Rules & Acceptance Letter** (Commit: b6cce8b)
    - Flexible NIM generation rules and sequence counters.
    - Accepted applicant conversion to user/student profile/registration.
    - Acceptance letter PDF generation and welcome notification foundation.

- **2026-05-12 (Admission Selection Workflow):**
  - ✅ **PHASE 2: Exam, Ranking, Quota & Waitlist** (Commit: 4d18935)
    - Exam/interview scheduling and participant assignment.
    - Score input, ranking, quota usage, waitlist, and bulk decision workflow.
    - Selection dashboard for admin-side admission processing.

- **2026-05-11 (Admission Foundation & Applicant Portal):**
  - ✅ **PHASE 1: Admission Foundation & Portal** (Commit: e4c702f)
    - Admission periods with academic year binding.
    - Public application form, status check, and token-based applicant portal.
    - Document requirements, secure upload/preview, admin review, and status history.
    - Email notifications and public/admin UI polish.

- **2026-05-11 (Grade Book with Export Implementation):**
  - ✅ **COMPLETED: Grade Book with Export** (Priority 3)
    - Commit: 5263f87
    - Role-based UI pattern decision: Lecturer (dedicated page) vs Admin (PowerGrid table)
    - PhpSpreadsheet integration for Excel export (.xlsx format)
    - Dompdf integration for PDF generation with formatted reports
    - Dedicated Grade Book page for lecturers with statistics dashboard
      - Hero section with purple/blue gradient (#667eea → #764ba2)
      - 6 stat cards (total students, avg score, pass rate, highest, lowest, graded count)
      - Filter by course offering, academic year, semester
      - Real-time search by student name/NIM/course
      - Responsive table dengan color-coded grade badges
      - Export buttons: CSV, Excel, PDF (dengan statistics included in PDF)
    - Admin interface: PowerGrid table + export buttons in card header (NOT in PowerGrid header)
      - Direct route links untuk CSV/Excel/PDF exports
      - ActivePermission checks: `student-grade.viewAny` or `student-grade.view`
    - Service layer architecture: `GradeExportService` dengan comprehensive methods:
      - `rows(User $user, array $filters, bool $admin)`: Filtered collection based on role
      - `statistics(Collection $rows)`: 8 statistical metrics calculation
      - `distribution(Collection $rows)`: Grade distribution analysis
      - `streamCsv()`, `streamXlsx()`: Streaming exports dengan proper headers
      - Admin mode via `$admin` parameter (not role checking inside service)
    - Separate controllers untuk lecturer dan admin:
      - Lecturer: No permission check (implicit access via authentication)
      - Admin: ActivePermission checks in controller methods
    - PDF template: `resources/views/exports/grade-book-pdf.blade.php` dengan DejaVu Sans font
    - Database performance optimization dengan indexes on `grade_status` & `graded_at` columns
    - Files changed: ~15 files, +2,000+ insertions
    - Dependencies added: phpoffice/phpspreadsheet ^5.7, dompdf/dompdf ^3.1

- **2026-05-08 (Announcement System Implementation):**
  - ✅ **COMPLETED: Announcement System** (Priority 2)
    - Multi-scope targeting system with PHP Enums (first in project)
    - Permission-based access control via Spatie Permission
    - Jodit Editor v4 integration for rich text content
    - Auto-mark as read on detail view + unread count badges
    - Custom pagination UI (non-PowerGrid) for lecturer & student
    - Purple gradient theme (`#667eea`/`#764ba2`) consistent with app
    - Database: announcements, announcement_reads tables
    - UI: Admin index/create/edit + Lecturer index/show/create/edit + Student index/show views
    - Livewire components: AnnouncementTable (PowerGrid admin) + anonymous components
    - Navigation integration in sidebar menu + dashboard widgets
    - Permissions synced: announcement.viewAny, .view, .create, .update, .delete, .publish
    - Files changed: 22 files, +3,317 insertions

- **2026-05-07 (E-Learning Feature Implementation):**
  - ✅ **PHASE 1: Course Materials Management with Bookmarks** (Commit: 04acc3e)
    - Complete course materials management system for lecturers and students
    - Jodit rich text editor integration for material descriptions
    - Multiple file attachments support with category classification
    - PDF preview via iframe + YouTube video embed
    - Bookmark/favorite functionality per material
    - Download tracking with student profile association
    - Database: course_materials, course_material_files, course_material_downloads, course_material_bookmarks tables
    - UI: Lecturer index/list/show views + Student index/show/course-materials views
    - Livewire components: CourseMaterialTable, StudentCourseMaterialTable
    - Navigation integration in lecturer dashboard & student schedule pages
    - Files changed: 32 files, +4993 insertions, -11 deletions
  
  - ✅ **PHASE 2: Discussion System with Likes & Comments** (Commit: af4737f)
    - Threaded comments/discussion system with parent-child structure
    - Edit/delete own comments with tracking (is_edited, edited_at)
    - Soft delete for deleted comments (preserves reply context)
    - Dual-like system: material likes + comment likes (separate tables)
    - Real-time counter updates with visual feedback
    - Global user_id architecture (migrated from role-specific profiles)
    - Enhanced UI with modern gradient hero sections
    - Database: course_material_comments, comment_likes, course_material_likes tables
    - Models: CommentLike, CourseMaterialComment, MaterialLike
    - Livewire comment components for both student and lecturer
    - Files changed: 16 files, +2753 insertions, -296 deletions
  
  - 📊 **Total Impact:** 48 files modified, ~7,746 lines added, 6 new tables, 5 new models
  - ⚠️ **Issues Resolved:** Livewire multiple root elements, PSR-4 autoloading, namespace conflicts
  - 🔧 **Tech Stack:** Laravel 12.55.1, PHP 8.4.12, Livewire v4, Jodit Editor, Bootstrap 5
  - Co-authored-by: Copilot <copilot@github.com>
  
- **2026-05-05 (Initial Implementation Session):**
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
- Last updated by: AI Assistant (based on actual git commit history)

---

**Note:** This is a living document that will be updated as features progress through development phases.
