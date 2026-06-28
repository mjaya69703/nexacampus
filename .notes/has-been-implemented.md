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
**Status:** 🚧 IN PROGRESS (Phase 3A implemented: Letter Request Center, Leave, Transfer, and Yudisium Workflow)  
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
- ✅ Leave of absence application with attachments and multi-step approval.
- ✅ Student transfer request with curriculum/credit mapping notes.
- ✅ Graduation/yudisium application with eligibility checklist.
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
2. **Phase 2A - Leave Application Workflow**
   - ✅ Leave application with academic year, semester, duration, reason, and attachment.
   - ✅ Admin review/approve/reject/correction workflow.
   - ✅ Student correction/resubmit flow.
   - ✅ Approval history/status audit trail.
   - ✅ Admin action to activate leave after approval.
   - ✅ Return from leave action to restore active student status.
   - ✅ Student semester registration no longer acts as the leave request entry point; students are routed to Student Services leave workflow.
   - ✅ Leave activation creates/updates semester registration snapshot with `academic_status = Cuti` without increasing `student_profiles.current_semester`.
   - ✅ Admin registration approval does not increase `current_semester` for `academic_status = Cuti`.
   - ✅ KRS, schedule, and attendance student pages require approved registration with `academic_status = Aktif`, so approved leave snapshots do not unlock academic activity.
   - ✅ Optional leave fee during approval; if fee is set, system issues a `leave` invoice and activation is blocked until paid.
3. **Phase 2B - Internal Transfer Workflow**
   - ✅ Internal transfer request and evaluation notes.
   - ✅ From/to study program tracking.
   - ✅ Transfer type rules: pindah prodi only lists programs in the same faculty, pindah fakultas requires choosing a program in a different faculty, and pindah kelas only changes `student_profiles.class_type` while keeping the same study program.
   - ✅ Admin review/approve/reject/correction workflow.
   - ✅ Student correction/resubmit flow.
   - ✅ Optional transfer fee during approval; if fee is set, system issues a `transfer` invoice and apply transfer is blocked until paid.
   - ✅ Manual apply transfer action after approval.
   - ✅ Curriculum/credit mapping notes as MVP, full mapping engine later.
   - Transfer application/conversion updates `student_profiles.study_program_id` for prodi/faculty transfer, updates `student_profiles.class_type` for class transfer, and can update `current_semester` from admin recommended semester.
   - Future: full curriculum/credit conversion engine to map old curriculum, accepted credits/SKS, equivalent courses, remaining courses, transcript treatment, GPA/IPK impact, and recommended semester automatically.
   - Future: post-transfer academic cleanup hooks for advisor reassignment, study plan reset/revalidation, course offering eligibility, scholarship/tuition reassessment, and transfer decision PDF/SK generation.
   - Future: multi-step approval after department head/dean/finance roles are formalized.
4. **Phase 3A - Graduation / Yudisium Workflow**
   - ✅ Student yudisium application with graduation batch, thesis/final project title, notes, and attachment.
   - ✅ Yudisium application window is selected from admin-managed `academic_periods` with type `Yudisium`, but official yudisium metadata is stored in Student Services graduation batches.
   - ✅ Admin-managed graduation/yudisium batch stores official yudisium date, optional SK number/date, status, scope, and links to the academic period window.
   - ✅ Student chooses an open yudisium batch; batch visibility respects academic period date window and optional study-program scope.
   - ✅ Admin-managed graduation/yudisium policy dashboard, with global fallback and optional study-program-specific policy.
   - ✅ Eligibility gate uses only supported system data: active status, current semester, passed SKS, latest GPA, incomplete transcript entries, graduation financial hold, and open yudisium period.
   - ✅ Unsupported operational requirements such as final project completion, library/lab clearance, and document completeness remain manual admin checklist until their supporting modules exist.
   - ✅ Financial clearance block for `graduation` hold before submit.
   - ✅ Eligibility snapshot from student profile, study program, passed credits, latest GPA, and financial hold state.
   - ✅ Admin review/under review/approve/reject/correction workflow.
   - ✅ Student correction/resubmit flow before finalization.
   - ✅ Autosaved admin checklist for transcript, final project, library/lab clearance, and document completeness, including checked by, checked at, and notes per item.
   - ✅ Admin-managed yudisium document requirements with global or study-program-specific scope.
   - ✅ Student yudisium document upload per requirement with file type/size validation.
   - ✅ Admin document preview and per-document verification/rejection notes.
   - ✅ Yudisium approval requires required documents to be verified.
   - ✅ Student detail page shows document verification status and auto-refreshes review progress.
   - ✅ Workflow guidance on admin and student yudisium detail pages so staff/student can see the next required action clearly.
   - ✅ Finalize graduation action uses the official yudisium date from the selected batch, changes `student_profiles.academic_status` to `Lulus`, sets `graduation_date`, and deactivates the student profile.
   - ✅ Bulk finalize approved applications from a graduation batch so many students can share the same official yudisium date without manual per-student date entry.
   - ✅ Finalized applications are treated as final and cannot be revised by normal workflow.
5. **Phase 3B - Complaints**
   - ✅ Supporting Organization module foundation: work unit master data and work unit members.
   - ✅ Work unit membership stores lightweight position (`member`, `coordinator`, `head`) without replacing the existing role/permission system.
   - ✅ Work units are designed as cross-module operational scope for complaint assignment, approval routing, notifications, and staff dashboards.
   - ✅ Complaint category master data with default work unit routing and default SLA hours.
   - ✅ Student complaint/ticket creation with priority, Jodit rich-text description, upload progress, and multiple attachments.
   - ✅ Admin complaint queue with work-unit/user assignment, status actions, response thread, multi-attachment support, status history, auto refresh, and practical PowerGrid filters.
   - ✅ Student and admin complaint detail pages use lightweight Livewire polling on the opened ticket only, so admin/student replies appear without manual refresh.
   - ✅ Admin complaint access is scoped for non-admin staff to tickets assigned to their user or active work units.
   - ✅ Complaint SLA/status guidance, overdue/sisa SLA labels, and quick claim action for staff.
   - ✅ Complaint attachments are served through secure role/student scoped download routes instead of raw public URLs.
6. **Phase 4 - Student Services Operations Dashboard & Notifications**
   - ✅ Admin queue dashboard for pending letters, leave, transfer, yudisium, complaints, correction-needed, ready-to-approve, and ready-to-finalize items.
   - ✅ Email/status notifications for request submitted, approved, rejected, correction requested, leave activated, transfer applied, graduation finalized, and complaint replies/status changes.
   - ✅ Cross-feature operational overview for staff so daily work can start from one Student Services dashboard instead of separate resource menus.

**Candidate Tables:**
- ✅ `service_letter_types`
- ✅ `service_letter_requests`
- ✅ `student_leave_applications`
- ✅ `student_leave_status_histories`
- ✅ `student_transfer_requests`
- ✅ `student_transfer_status_histories`
- ✅ `graduation_batches`
- ✅ `graduation_applications`
- ✅ `graduation_document_requirements`
- ✅ `graduation_documents`
- ✅ `graduation_status_histories`
- ✅ `graduation_policies`
- ✅ `work_units`
- ✅ `work_unit_user`
- ✅ `student_complaint_categories`
- ✅ `student_complaints`
- ✅ `student_complaint_messages`
- ✅ `student_complaint_attachments`
- ✅ `student_complaint_status_histories`
- ✅ `service_request_status_histories`

---

#### **Priority 7: Assignment Management**
*Impact: Lecturer + Students | Module: Academic Enhancement*

##### 7. Assignment Submission & Grading 📝
**Status:** ✅ IMPLEMENTED (Phase 1-3: Assignment Core, Submission, Review, Grade Book Sync, Reports)  
**Roles Affected:** Lecturer (create/grade), Students (submit/track), Admin (monitor)  
**Module Category:** Academic → New Submodule: `assignments`

**Description:**
Sistem pengumpulan tugas online yang terhubung dengan course offering, materi perkuliahan, dan grade book. Dosen membuat tugas, mahasiswa submit file/text, dosen memberi nilai dan feedback.

**Why Next:**
- Melengkapi e-learning yang sudah punya materi, diskusi, bookmark, dan download tracking.
- Nyambung langsung ke grade book/export yang sudah ada.
- Memberikan workflow akademik harian yang sangat sering dipakai.

**Core Features:**
- ✅ Assignment per course offering/session.
- ✅ Assignment detail: title, rich description/rubric, deadline, max score, allowed file types, max file size, and lecturer instruction attachments.
- ✅ File submission and optional text submission.
- ✅ Multiple file support.
- ✅ Resubmission before deadline and for returned revisions.
- ✅ Late submission policy with configurable late acceptance.
- ✅ Submission status: not submitted, submitted, late, graded, returned/missing.
- ✅ Lecturer grading with score and feedback.
- ✅ Student view for grade, feedback, returned status, and revision notes.
- ✅ Assignment analytics/export for lecturer review.
- ✅ Reminder email before deadline for students who have not submitted yet.

**Integration Notes:**
- Link to `course_offerings`, student enrollments/KRS, and existing grade book.
- Assignment score can be synced into existing grade book components.
- Reuse secure file preview/download patterns from Admission and Course Materials.
- Student UI should mirror Course Materials page style.

**Estimated Effort:** High (5-6 days)

**Recommended Phases:**
1. **Phase 1 - Assignment Core**
   - ✅ Lecturer create/edit/list assignments per course offering.
   - ✅ Assignment instruction attachments with secure preview for lecturer/student.
   - ✅ Student list/detail assignments from enrolled course offerings.
   - ✅ Submission upload, text answer, multiple files, and deadline validation.
2. **Phase 2 - Grading & Feedback**
   - ✅ Lecturer submission review dashboard per assignment.
   - ✅ Score/feedback.
   - ✅ Return-for-revision workflow.
   - ✅ Student graded result and feedback view.
3. **Phase 3 - Grade Book Integration**
   - ✅ Assignment score sync to grade book components with configurable component weight.
   - ✅ Assignment analytics summary on lecturer review page.
   - ✅ CSV/XLSX/PDF assignment report export.
   - ✅ Email reminder before deadline.

**Candidate Tables:**
- ✅ `assignments`
- ✅ `assignment_files`
- ✅ `assignment_submissions`
- ✅ `assignment_submission_files`
- ✅ `assignment_grades`
- ✅ `assignment_status_histories`

---

#### **Priority 8: Student Progress Analytics & Academic Advising**
*Impact: Lecturer + Students + Academic Advisors | Module: Academic Enhancement*

##### 8. Progress Analytics & Advisor Dashboard 📈
**Status:** ✅ IMPLEMENTED (Phase 1-3 implemented: Advisor Foundation, Student Progress, Advisor Follow-up)  
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
1. **Phase 1 - Advisor Assignment Foundation**
   - ✅ Harden existing Dosen Wali assignment workflow.
   - ✅ Searchable student/lecturer picker and bulk assignment flow.
   - ✅ Active assignment conflict validation, including open-ended/general assignments.
   - ✅ Advisor resolver service for active advisor, assigned students, and conditional lecturer menu.
2. **Phase 2 - Student Progress Tracker**
   - ✅ Student academic progress page.
   - ✅ GPA/SKS trend charts.
   - ✅ Remaining requirement summary.
   - ✅ Student-facing active advisor information.
   - ✅ Attendance, financial, KRS, and academic risk indicators.
   - ✅ Student-facing recommendations based on current risk reasons.
3. **Phase 3 - Advisor Dashboard & Recommendations**
   - ✅ Lecturer advisor dashboard for assigned students only.
   - ✅ Risk indicators and student list.
   - ✅ Advisor notes/recommendations and follow-up history.
   - ✅ Dedicated lecturer advising detail page per student, replacing modal-style note entry.
   - ✅ Student-visible advisor notes when marked visible by lecturer.
   - ✅ Consolidated student analytics service with explainable risk reasons.

**Candidate Tables:**
- `academic_advisor_assignments`
- `student_progress_snapshots`
- `student_advisor_notes`
- `student_recommendations`

---

#### **Priority 9: Kepegawaian, Struktur Organisasi & Scope Jabatan**
*Impact: Admin + Staff/Tendik + Lecturer + Academic Leadership | Module: Organization / Kepegawaian*

##### 9. Kepegawaian, Position Scope & Operational Structure
**Status:** ✅ COMPLETED (Phase 1-7 plus BKD/EDOM hardening, academic leader completion, attendance modernization, and modular seeders)
**Roles Affected:** Admin/Superuser (manage), Staff/Tendik (operate), Kaprodi/Dekan/Kepala Unit via scoped `academic-leader` access, Lecturer, Student  
**Module Category:** Existing Module Enhancement -> `organization`

**Description:**
Fondasi kepegawaian untuk menghubungkan user dengan profil pegawai, jabatan operasional, dan scope data seperti fakultas, program studi, atau unit kerja. Kaprodi, Dekan, Tendik, Kepala Unit, dan Staff Unit tidak menjadi `active_role` baru; mereka bekerja melalui admin/staff portal dengan role/permission + position scope.

**Why Next:**
- `work_units` dan `work_unit_user` sudah ada sebagai dasar assignment tiket, approval routing, notifikasi, dan pembatasan data staff.
- Student Services complaints sudah memakai work unit untuk assignment, SLA, dan scoped access.
- Modul berikutnya seperti approval engine, cuti pegawai, absensi pegawai, dan Kaprodi/Dekan dashboard butuh fondasi siapa mengelola area apa.
- Menghindari modul `lecturer-hr` yang terlalu sempit dan janggal karena HR/Kepegawaian seharusnya mencakup pegawai lintas role.

**Core Features:**
- Employee profile foundation untuk dosen, tendik, staff, admin, kontrak, dan tamu.
- Organizational position master data: Rektor, Wakil Rektor, Dekan, Wakil Dekan, Kaprodi, Sekprodi, Kepala Unit, Staff Unit, Tendik, Dosen.
- Position assignment dengan scope `faculty_id`, `study_program_id`, atau `work_unit_id`.
- Position scope resolver untuk membaca fakultas/prodi/unit yang dikelola user.
- Work-unit-scoped assignment tetap mensinkronkan membership ke `work_unit_user` agar complaint access existing tetap jalan.
- Approval template foundation untuk alur approval berurutan.
- Approval request engine dengan step `pending/current/approved/rejected/skipped`.
- Approver routing berbasis role, permission, jabatan organisasi, atau unit kerja.
- Approval action history untuk audit approve/reject/cancel.
- Admin UI untuk Template Approval dan Approval queue/detail.
- Employee attendance sources and records for manual/admin attendance input.
- Employee attendance office locations with GPS radius validation.
- Employee attendance self-service camera/upload evidence with client-side WebP conversion.
- Employee leave types, balances, requests, and approval-engine-backed approval status sync.
- Admin UI untuk Absensi Pegawai, Lokasi Absensi, Saldo Cuti Pegawai, Jenis Cuti Pegawai, dan Cuti Pegawai.
- Employee self-service UI untuk Absensi Saya dan Cuti Saya, visible untuk user yang punya Employee Profile aktif.

**Integration Notes:**
- Reuse existing `Organization` namespace and `work_units` module instead of creating a parallel HR namespace.
- Keep roles/permissions as feature access; use position assignments for data scope.
- Existing Student Services workflows are not migrated into the approval engine in Phase 1/2/3.
- Phase 3 integrates employee leave/cuti pegawai into the approval engine without changing student leave workflows.
- Phase 3 includes employee self-service pages shared across active roles when the user has an active Employee Profile.
- Lecturer workload and lecturer performance review are now implemented as child modules on top of the kepegawaian foundation.
- BKD/EDOM V1 means the feature is production-usable for internal campus workflow: data model, admin management, lecturer/student self-service, scoped academic-leader oversight, approval integration, demo seeder, routes, view cache, and focused feature tests are already in place.
- V1 is intentionally not a full regulatory/export suite. Formal BKD/SKP export, official PDF forms, multi-layer assessor chains beyond the current approval template, richer rubric weighting, semester trend analytics, faculty/program benchmarking, and external attendance/academic data imports remain suitable V2 enhancements.
- Dekan/Kaprodi are not auth roles. Access stays through coarse `academic-leader` role plus active `employee_position_assignments` scope resolved by `PositionScopeResolver`.

**Estimated Effort:** High (phased)

**Recommended Phases:**
1. **Phase 1 - Organization, Employee & Position Scope**
   - Employee profiles.
   - Organizational positions.
   - Employee position assignments with faculty/program/unit scope.
   - Position scope resolver.
   - Work unit membership sync for active work-unit assignments.
2. **Phase 2 - Approval Engine**
   - Completed reusable lightweight approval templates and sequential approval requests.
   - Completed admin Template Approval and Approval request screens.
   - Completed approver resolution for role, permission, organizational position, and work unit.
   - Employee leave integration is deferred to Phase 3 to keep Phase 2 focused on the engine foundation.
   - Keep existing Student Services request workflows unchanged until a dedicated migration/refactor phase.
3. **Phase 3 - Employee Attendance & Leave**
   - Completed employee attendance sources and attendance records.
   - Completed manual admin attendance input with check-in/check-out and work-minute calculation.
   - Completed office attendance locations with configurable GPS radius.
   - Completed self-service attendance camera/upload evidence, upload progress, WebP client-side conversion, GPS capture, and radius validation.
   - Completed employee leave types, balances, leave requests, and approval engine integration.
   - Completed admin leave balance grant/adjustment screen.
   - Completed approval callback sync from approval request final status into employee leave request status and balance usage.
   - Completed admin screens for Absensi Pegawai, Jenis Cuti Pegawai, and Cuti Pegawai.
   - Completed employee self-service screens for Absensi Saya and Cuti Saya.
   - Teaching attendance remains an optional source placeholder for later lecturer workload integration.
4. **Phase 4 - User Certification / Training Records** ✅
   - ✅ User-based development records for certifications, training, workshops, seminars, awards, licenses.
   - ✅ Works for lecturers, staff, admins, and students globally instead of being lecturer-only.
   - ✅ Migrations and Data Models properly bind to `user_id`.
   - ✅ Attachments framework integrated for certificate and document uploads.
   - ✅ Admin create/edit, private attachment preview, verification action, and user self-service upload with progress state are implemented.
5. **Phase 4.5 - Massive System-Wide Seeder Overhaul** ✅
   - ✅ Added `SystemWideDemoSeeder` as an idempotent cross-module demo-data aggregator.
   - ✅ Ties together Academic, Student Services, Financial, Admission, and Organization modules.
   - ✅ Generates realistic student histories for invoices, payments, service letters, leave, graduation, complaints, and admission.
   - ✅ Generates lecturer/staff/admin organization histories with attendance, leave, and verified/unverified user development records.
6. **Phase 5 - Tridharma Support** ✅
   - ✅ User-owned Tridharma records for research, community service, and publication/output tracking.
   - ✅ Optional lecturer/employee profile context while keeping `user_id` as the owner source of truth.
   - ✅ Proposal approval uses the existing approval engine with `TRIDHARMA_PROPOSAL` template and model callbacks.
   - ✅ Admin management covers create/edit/show, verification, completion/archive, members, milestones, budgets, outputs, and private attachments.
   - ✅ Lecturer/employee self-service can create draft, submit approval, upload evidence with progress, and add milestones/outputs after approval.
   - ✅ System-wide demo seeder now includes Tridharma approval-ready records with realistic team, budget, milestone, output, and evidence data.
7. **Phase 6 - Lecturer Workload / BKD** ✅
   - ✅ BKD period and SKS rule management under Organization / Kepegawaian.
   - ✅ Lecturer self-service can generate BKD draft from teaching assignments, structural positions, and verified Tridharma records.
   - ✅ BKD submission uses the existing approval engine with `LECTURER_WORKLOAD_REVIEW` template and model callbacks.
   - ✅ Admin can review BKD submissions, inspect source breakdown, and request revision.
   - ✅ Academic leader can monitor scoped BKD data through faculty/study-program position scope.
8. **Phase 7 - Performance Evaluation / EDOM** ✅
   - ✅ EDOM periods and question bank under Organization / Kepegawaian.
   - ✅ Student EDOM survey is limited to enrolled course offerings and one response per student/course/lecturer.
   - ✅ EDOM aggregation keeps student identity hidden from lecturer, admin, and academic-leader result views.
   - ✅ Lecturer performance review combines EDOM score, teaching attendance compliance, employee attendance compliance, and approved BKD total.
   - ✅ Academic leader read-only oversight uses scoped `academic-leader` portal instead of Dekan/Kaprodi auth-role proliferation.

**BKD/EDOM V1 Implementation Evidence:**
- New BKD tables: `lecturer_workload_periods`, `lecturer_workload_rules`, `lecturer_workload_submissions`, and `lecturer_workload_items`.
- New EDOM/performance tables: `edom_periods`, `edom_questions`, `edom_responses`, `edom_answers`, and `lecturer_performance_reviews`.
- New role surface: `/academic-leader` prefix with `academic-leader.*` route names, role selector metadata, sidebar menu, and `User::getPrefixAttribute()` mapping.
- Admin Organization resources follow the Organization/PowerGrid pattern for BKD periods, BKD rules, BKD submissions, EDOM periods, EDOM questions, and lecturer performance reviews.
- Lecturer self-service uses custom workspace-style BKD pages for generate draft, inspect breakdown, submit, and revision flow.
- Student EDOM uses card/form survey screens instead of admin tables, with one response per student/course/lecturer and anonymous aggregate output.
- Academic leader oversight is custom read-only UI under `resources/views/components/academic-leader/**` and is bounded by faculty/program scope.
- Demo seeding includes role/permission/resource setup, default BKD rules, active BKD/EDOM periods, default EDOM questions, and `LECTURER_WORKLOAD_REVIEW` approval template.
- Verification completed: migration succeeded, resource/menu sync succeeded, Organization demo seeder ran twice idempotently, route checks passed for `workload`, `edom`, and `academic-leader`, `php artisan view:cache` passed, and `tests/Feature/WorkloadEdomPhaseSixSevenTest.php` passed.

**BKD/EDOM V2 Candidates (Not Part of Current Completion Claim):**
- Official BKD/SKP export package with signed PDF/Excel forms and institution-specific format mapping.
- More granular assessor workflow such as Kaprodi -> Dekan -> Kepegawaian/Rektorat when required by campus policy.
- Configurable scoring rubric for performance review weights, minimum response thresholds, and question categories.
- Multi-semester lecturer performance trend dashboard, faculty/program benchmarking, and anomaly detection.
- External data import/sync for attendance, LMS activity, national BKD systems, or other academic sources.
- Deeper lecturer drilldown pages for academic leaders beyond the current read-only scoped overview.

**BKD/EDOM V2 Hardening Implemented:**
- Academic leader drilldown page for scoped lecturer detail, including latest BKD, performance trend, program benchmark, and faculty benchmark.
- BKD export support for admin and academic leader in CSV, XLSX, and PDF formats, with academic leader exports scoped by active faculty/program position assignments.
- Configurable lecturer performance rubric under Organization / Kepegawaian, covering EDOM weight, teaching compliance weight, employee attendance weight, BKD weight, minimum EDOM responses, and target BKD SKS.
- EDOM performance calculation now uses the active rubric and stores the applied rubric/components in the performance review snapshot.
- BKD approval template seeded as a scoped multi-step chain: Kaprodi review, Dekan review, then Kepegawaian Akademik finalization.
- Approval engine position checks now match approver position scope against the lecturer profile scope when processing BKD approvals.
- Verification completed: migration status confirmed, permissions/menu sync completed, Organization demo seeder ran twice idempotently, route checks passed for academic-leader, workload export, and rubrics, `php artisan view:cache` passed, and `tests/Feature/WorkloadEdomPhaseSixSevenTest.php` passed with 7 tests.

**Academic Attendance Modernization Implemented:**
- Dosen dapat membuka absensi QR dari halaman sesi kelas. Saat dibuka, sesi menjadi `Opened`, menyimpan secret QR, dan menampilkan QR dinamis.
- QR absensi berubah setiap 2 detik berdasarkan secret sesi dan slot waktu, tanpa menulis token baru ke database setiap pergantian.
- Saat dosen menutup absensi, sesi menjadi `Closed`, `closed_at` terisi, dan semua scan mahasiswa berikutnya ditolak oleh service backend.
- Mahasiswa melakukan absensi mandiri lewat scan QR. Scan valid otomatis mencatat status `Present` dengan source `student_qr`, timestamp scan, token slot, IP/user agent, dan metadata lokasi bila browser mengirim GPS.
- Mahasiswa tidak bisa memilih `Izin`, `Sakit`, `Terlambat`, atau `Alpha` dari jalur mandiri. Status non-hadir dan koreksi tetap lewat halaman manual dosen.
- Halaman jadwal/detail absensi mahasiswa sekarang menampilkan aksi `Scan Absensi` saat sesi dibuka oleh dosen, dan `Menunggu Dosen` saat sesi belum dibuka atau sudah ditutup.
- Verification completed: QR attendance migration ran successfully, route check passed for attendance routes, `php artisan view:cache` passed, and `tests/Feature/AcademicAttendanceQrServiceTest.php` passed with 4 tests.

**Academic Leader Completion Pack Implemented:**
- Sidebar academic leader sekarang memuat Dashboard, Dosen, Kelas & Kehadiran, BKD Dosen, EDOM & Performa, dan Laporan.
- Halaman Dosen Dalam Scope menampilkan daftar dosen sesuai faculty/study-program position scope, ringkasan kelas aktif, status BKD terakhir, skor performa, dan link drilldown dosen.
- Halaman Kelas & Kehadiran menampilkan monitoring kelas aktif, dosen pengampu, mahasiswa, sesi/pertemuan, sesi yang masih berjalan, sesi lama belum ditutup, persentase kehadiran, dan label risiko.
- Dashboard academic leader sekarang punya shortcut operasional dan alert akademik untuk kelas tanpa dosen, kelas tanpa jadwal aktif, sesi belum ditutup, pertemuan belum lengkap, kehadiran rendah, BKD yang perlu tindak lanjut, dan performa dosen rendah.
- Halaman Laporan menyediakan export scoped CSV, XLSX, dan PDF untuk dosen, kelas/kehadiran, dan alert akademik.
- Service oversight baru memusatkan query scoped academic leader agar dashboard, list, alert, dan export memakai batas akses yang sama.
- UI status sesi dosen dirapikan ke label operasional `Dijadwalkan`, `Berjalan`, `Ditutup`, dan `Dibatalkan`.
- Verification completed: route checks passed for `academic-leader`, `academic-leader/classes`, and `academic-leader/reports`; `php artisan view:cache` passed; `AcademicLeaderOversightService` dashboard sanity check passed for `superuser@example.com`; `tests/Feature/AcademicAttendanceQrServiceTest.php` passed with 4 tests; and `tests/Feature/WorkloadEdomPhaseSixSevenTest.php` passed with 7 tests.

**Seeder Modularization Implemented:**
- Removed ambiguous mixed seeders: `SystemWideDemoSeeder` and `OrganizationDemoSeeder`.
- Added module-scoped seeders: `OrganizationSeeder`, `AdmissionSeeder`, `FinancialSeeder`, and `StudentServiceSeeder`.
- `AcademicSeeder` now owns academic data and seeds richer faculty, program, lecturer, student, class, KRS, attendance, grade, material, assignment, and advising data.
- `DatabaseSeeder` now runs module seeders in dependency order: Settings, User, Menu, Academic, Organization, Admission, Financial, StudentService.
- Verification completed: all new seeders passed `php -l`, each module seeder ran successfully, and full `php artisan db:seed` ran twice idempotently.

**Candidate Tables:**
- `employee_profiles`
- `organizational_positions`
- `employee_position_assignments`
- `approval_templates`
- `approval_template_steps`
- `approval_requests`
- `approval_steps`
- `approval_actions`
- `employee_attendance_records`
- `employee_attendance_sources`
- `attendance_sessions`
- `attendance_records`
- `employee_leave_types`
- `employee_leave_requests`
- `employee_leave_balances`
- `employee_leave_attachments`
- `user_development_records`
- `user_development_attachments`
- `lecturer_workload_periods`
- `lecturer_workload_rules`
- `lecturer_workload_submissions`
- `lecturer_workload_items`
- `edom_periods`
- `edom_questions`
- `edom_responses`
- `edom_answers`
- `lecturer_performance_reviews`
- `lecturer_performance_rubrics`

---

#### **Priority 10: Alumni & Career Services**
*Impact: Admin + Alumni + Students | Module: New*

##### 10. Alumni Management, Tracer Study & Career Services 🎓
**Status:** ✅ COMPLETED (Full)  
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

**Estimated Effort:** Medium (3-4 days) — Delivered in one-shot implementation.

**Recommended Phases:**
1. **Phase 1 - Alumni Database** ✅
   - Alumni profile.
   - Conversion from graduated student.
   - Admin list/detail.
2. **Phase 2 - Tracer Study** ✅
   - Survey campaign.
   - Alumni response form.
   - Analytics dashboard.
3. **Phase 3 - Career Services** ✅
   - Job posting board.
   - Career events.
   - Employer/partner records.

**Candidate Tables:**
- `alumni_profiles` ✅
- `tracer_study_campaigns` ✅
- `tracer_study_responses` ✅
- `alumni_events` ✅
- `alumni_event_participants` ✅
- `job_postings` ✅
- `employer_partners` ✅

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

- [x] **Digital Student ID** - QR code card with signed verification page
- [ ] **Peer Study Group** - Collaboration platform
- [x] **Career Services Portal** - Job board (delivered as part of Alumni module)
- [ ] **Campus Map & Navigation** - Interactive map
- [ ] **Wellness & Mental Health Resources** - Counseling
- [ ] **Gamification & Achievements** - Badges & points

---

## 📊 Implementation Statistics

### Current Sprint (Week 1-2)
- **Total Features In Progress:** 2
- **Roles Impacted:** Lecturer, Student, Admin, Alumni
- **Modules Affected:** Academic (enhancement), Publication (new), PMB (new), Financial (new), Alumni (new)
- **Estimated Total Effort:** ~21-27 days

### Completion Tracking
- ✅ Completed Features: 4 (Course Materials Management, Announcement System, Grade Book with Export, Alumni Module)
- 🚧 In Progress: 1
- ⏸️ Planned: 39+
- ❌ Not Started: 39+

### Module Distribution
- **Academic:** 13 features (existing + enhancements)
- **Publication:** 1 feature (new module — announcements ✅)
- **PMB (Admission):** 1 feature (new module)
- **Financial:** 1 feature (new module)
- **Student Services:** 1 feature (planned)
- **Lecturer HR:** 1 feature (planned)
- **Alumni:** 1 feature (✅ completed — database, tracer study, career services)
- **System:** 11 features (planned)
- **Other:** 12 features (planned)

---

## 🔄 Update History

- **2026-06-11 (Alumni Module — Full End-to-End Implementation):**
  - ✅ **COMPLETED: Alumni & Career Services** (Priority 10)
    - Created `create_alumni_tables` migration with 7 tables: `alumni_profiles`, `employer_partners`, `job_postings`, `alumni_events`, `alumni_event_participants`, `tracer_study_campaigns`, `tracer_study_responses`.
    - Created 7 Eloquent models under `app/Models/Alumni/` with SoftDeletes, LogsActivity, and proper relationships.
    - Created 5 enums: `EmploymentStatus`, `EventType`, `JobType`, `CampaignStatus`, `JobRelevance`.
    - Added `alumni` role with full permission set and demo user `alumni@example.com`.
    - Registered 6 resource entries in `config/resources.php` under Alumni group.
    - Added admin and alumni routes in `routes/web.php` (Livewire + controller routes).
    - Created 6 PowerGrid admin tables under `app/Livewire/Alumni/` with filters.
    - Created 21 admin CRUD views (anonymous Livewire components) under `resources/views/components/admin/alumni/`.
    - Created 3 service classes: `AlumniConversionService`, `TracerStudyService`, `TracerStudyExportService`.
    - Created 11 alumni-facing anonymous Livewire components: dashboard, profile (view/edit), job board (list/detail), events (list/detail/register), tracer study (list/detail/fill).
    - Created 2 controllers: `AlumniConversionController`, `TracerStudyAnalyticsController`.
    - Created `AlumniSeeder` with sample data (5 alumni profiles, 3 employers, 5 jobs, 3 events, 1 tracer study campaign with responses).
    - Created `AlumniModuleTest.php` with 22 Pest PHP tests (enums, models, services, routes — all passing).
    - Files changed: migration, models, enums, services, controllers, PowerGrid tables, admin views, alumni views, seeder, tests, routes, resource registry, User model, UserSeeder.

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
