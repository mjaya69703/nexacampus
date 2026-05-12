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
- Service Layer: `app/Support/GradeBookExportService.php` handles lecturer grade book exports
  - `lecturerRows(User $user, array $filters)`: Returns filtered collection scoped to lecturer course offerings
  - `statistics(Collection $rows)`: Calculate total_students, graded_count, average_score, median_score, highest_score, lowest_score, pass_rate, standard_deviation
  - `distribution(Collection $rows)`: Grade distribution (A+ through E) with counts and percentages
  - `streamCsv()`, `streamXlsx()`, `pdf()`: Streaming exports dengan UTF-8 BOM support
- Lecturer Controller:
  - `app/Http/Controllers/Lecturer/GradeBookExportController.php` (csv, xlsx, pdf methods)
- Admin export:
  - Handled inside `app/Livewire/Academic/StudentGradeTable.php`
  - Uses PowerGrid filter/search/sort/selected-row state via `prepareToExport()`
  - CSV generated with native streamed response
  - XLSX generated with PhpSpreadsheet to avoid OpenSpout v5 exporter mismatch
- PDF Generation: Dompdf with DejaVu Sans font, landscape A4 paper
  - Template: `resources/views/exports/grade-book-pdf.blade.php`
  - Includes statistics table + detailed grade rows
- UTF-8 Encoding: BOM (Byte Order Mark) added to CSV exports
- Filter passing:
  - Lecturer export uses query parameters from current Livewire filter state
  - Admin export uses active PowerGrid state directly
- Database Indexes: Added idempotent indexes on `grade_status` and `graded_at` columns for performance

**Files Created/Modified:**
- ✅ Services: `app/Support/GradeBookExportService.php` (lecturer export service dengan statistics & distribution)
- ✅ Controllers:
  - `app/Http/Controllers/Lecturer/GradeBookExportController.php` (csv, xlsx, pdf methods)
- ✅ Livewire Components:
  - New: `resources/views/components/lecturer/student-grades/⚡grade-book.blade.php` (anonymous component dengan statistics dashboard)
  - Enhanced: `app/Livewire/Academic/StudentGradeTable.php` (PowerGrid filters + filter-aware CSV/XLSX export)
- ✅ Views:
  - New: `resources/views/exports/grade-book-pdf.blade.php` (PDF template dengan statistics table)
  - Enhanced: `resources/views/components/lecturer/student-grades/⚡index.blade.php` (Grade Book navigation button)
- ✅ Routes: Added lecturer grade-book route + CSV/XLSX/PDF routes untuk lecturer
- ✅ Migrations: `2026_05_11_000001_add_grade_book_indexes.php` (idempotent indexes on grade_status & graded_at)
- ✅ Dependencies: openspout/openspout ^5.0, phpoffice/phpspreadsheet ^5.7, dompdf/dompdf ^3.1
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

##### 4. Admission Management 🎓
**Status:** 🚧 IN PROGRESS (Phase 2 implemented, Phase 3 pending)  
**Roles Affected:** Admin (manage), Prospective Applicants (apply/track), Student (after conversion)  
**Module Category:** New Module → `admission`

**Description:**
Sistem pendaftaran, seleksi, dan konversi mahasiswa baru end-to-end dengan arsitektur fleksibel untuk berbagai pola admission kampus.

**Module Decision:**
- ✅ **Use `Admission`, not `PMB`** - Naming dibuat general English agar konsisten dan reusable.
- ✅ **New module, not Academic submodule** - Admission punya lifecycle pre-student sendiri: applicant → review → selection → accepted/waitlisted/rejected → converted to student.
- ✅ **No new applicant role for MVP** - Applicant Portal menggunakan public/token-based access, bukan role baru.
- ✅ **Create `student` role/user only after acceptance/conversion** - Applicant belum masuk role-based dashboard utama.
- ✅ **Flexible NIM generation required** - Format NIM harus rule-based karena tiap kampus bisa beda format dan basis sequence.

**Catatan Implementasi Phase 1 (2026-05-11):**
- ✅ **Admission Period Management** - SUDAH ADA. Admin bisa manage period/intake, open/close date, active/published flag, dan document requirements.
- ✅ **Academic Year Binding** - SUDAH ADA. Admission period terhubung ke master `academic_years` via `academic_year_id` agar sinkron dengan modul akademik lain.
- ✅ **Public Application Form** - SUDAH ADA. Applicant bisa submit form pendaftaran via `/admission/apply`.
- ✅ **Applicant Portal token-based** - SUDAH ADA. Applicant bisa cek status dan upload/update dokumen via secure token URL.
- ✅ **Status Check Page** - SUDAH ADA. Applicant bisa akses portal menggunakan application number + email via `/admission/status`.
- ✅ **Admin Application Review** - SUDAH ADA. Admin bisa melihat application list, detail applicant, verify/reject documents, update status, final score, dan review notes.
- ✅ **Status History/Audit Trail** - SUDAH ADA. Perubahan status terekam di `admission_status_histories`.
- ✅ **Document Requirements** - SUDAH ADA. Per period bisa set dokumen wajib/opsional, allowed extensions, max file size.
- ✅ **Secure document preview** - SUDAH ADA. Admin dan applicant portal pakai route preview internal, bukan direct storage URL, sehingga preview image/PDF tidak kena 403 public storage.
- ✅ **Upload security hardening** - SUDAH ADA. Upload dokumen dibatasi extension + MIME safe allowlist (`pdf`, `jpg`, `jpeg`, `png`, `webp`) dan sanitize konfigurasi allowed extensions.
- ✅ **Email notification via Mailpit SMTP** - SUDAH ADA. Submit application dan status update mengirim email memakai template di `resources/views/templates/email`.
- ✅ **Public/Admin UI polish** - SUDAH ADA. Public application/status/portal dan admin application detail dipoles mengikuti pola card/hero modern lecturer/student pages.
- ✅ **Exam/interview scheduling** - SUDAH ADA. Admin bisa manage schedule seleksi, assign participant, update attendance, dan input score.
- ✅ **Ranking/quota/waitlist automation** - SUDAH ADA. Selection dashboard menampilkan ranking by final score, quota usage, waitlist/accept/reject, dan bulk decision.
- ❌ **Student conversion + NIM rule engine** - BELUM ADA. Masuk Phase 3.

**Features:**
- **Admission Period & Intake Management:**
  - Manage admission periods/intakes (year, wave/batch, open/close dates)
  - Configure available faculties, study programs, class types, and quotas per period
  - Publish/unpublish admission period
  - Track application counts and quota usage

- **Online Application Form:**
  - Personal info (name, birth date, gender, address)
  - Contact info (phone, email, emergency contact)
  - Education background (high school, major, graduation year)
  - Configurable document uploads (ID card, high school certificate, photo, report cards, payment proof, etc.)
  - Program selection (faculty, study program, class type)
  - Payment confirmation upload/status (manual first, payment gateway optional later)
  - Draft/submitted state with validation before submission

- **Applicant Portal (Public/Token-Based):**
  - Track application status without adding a new `applicant` role
  - Access by application number + secure token/email verification link
  - View missing/verified documents
  - Upload/update documents when requested by admin
  - View exam/interview schedule and final decision
  
- **Application Review:**
  - Application list dengan status tracking (Submitted → Under Review → Accepted/Rejected/Waitlisted)
  - Document verification checklist
  - Status history/audit trail
  - Interview scheduling & notes
  - Test score input (entrance exam, TOEFL, etc.)
  - Bulk approval/rejection
  - Admin notes and internal review assignment
  
- **Selection Process:**
  - Entrance exam management (schedule, venue, participants)
  - Score calculation & ranking
  - Quota management per study program
  - Waitlist management
  - Automatic acceptance letter generation
  
- **Registration Completion:**
  - Convert accepted applicants to students
  - Auto-create user account
  - Generate student ID (NIM) using configurable generation rules
  - Initial enrollment setup
  - Welcome email/notification

- **Flexible NIM Generation Rules:**
  - Rule template with tokens such as `{year}`, `{period_code}`, `{faculty_code}`, `{program_code}`, `{class_type}`, `{sequence}`
  - Sequence scope options: global, per year, per admission period, per faculty, per study program, per class type
  - Configurable padding length and starting number
  - Preview/test generated NIM before activating rule
  - Collision check before conversion

**Why Important:**
- Core business process untuk kampus
- Streamline admission workflow
- Digital document management
- Transparent selection process
- Reduce manual paperwork
- 80% reduction in application processing time

**Estimated Effort:** High (8-12 days, recommended phased implementation)
- Phase 1 Foundation & Applicant Portal: 3-4 days
- Phase 2 Review, Exam, Selection & Quota: 3-4 days
- Phase 3 Conversion, NIM Rules & Acceptance Letter: 2-3 days
- Testing & refinement: 1 day

**Technical Notes:**
- New namespace: `Admission`
- New tables use `admission_*` prefix, not `pmb_*`
- File upload handling untuk documents
- Status workflow engine
- Integration dengan student registration system
- Email notifications untuk status updates
- Consider payment gateway integration untuk registration fee
- Admin access via existing admin/superuser role + permissions
- Applicant access via signed/tokenized public routes, not role-based dashboard
- Use PowerGrid for admin list/table pages, following existing admin pattern
- Use anonymous Livewire views for public applicant-facing pages, following existing project pattern

**Implementation Phases:**
1. **Phase 1 - Foundation & Portal**
   - ✅ Admission periods
   - ✅ Public application form
   - ✅ Secure applicant portal/status tracking
   - ✅ Document requirements and uploads
   - ✅ Admin application list/detail review
   - ✅ Status history

2. **Phase 2 - Selection Workflow**
   - ✅ Exam/interview schedules
   - ✅ Participant assignment
   - ✅ Score input
   - ✅ Ranking
   - ✅ Quota and waitlist management
   - ✅ Bulk status actions

3. **Phase 3 - Student Conversion**
   - Flexible NIM generation rules
   - Convert accepted applicant to user + student profile/registration
   - Acceptance letter PDF
   - Welcome notification/email

**Database Changes Required:**
```php
// Admission periods / intakes
Schema::create('admission_periods', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // e.g. Admission 2026 - Wave 1
    $table->string('code')->unique(); // e.g. ADM2026W1
    $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
    $table->year('academic_year');
    $table->unsignedTinyInteger('wave')->default(1);
    $table->date('opens_at');
    $table->date('closes_at');
    $table->boolean('is_active')->default(false);
    $table->boolean('is_published')->default(false);
    $table->timestamps();
    $table->softDeletes();
});

// Main application table
Schema::create('admission_applications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_period_id')->constrained('admission_periods')->cascadeOnDelete();
    $table->string('application_number')->unique(); // e.g., ADM-2026-0001
    $table->string('access_token')->unique(); // for public applicant portal
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
    $table->decimal('final_score', 6, 2)->nullable();
    $table->text('review_notes')->nullable();
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('converted_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['admission_period_id', 'status']);
    $table->index(['faculty_id', 'study_program_id']);
    $table->index(['status', 'created_at']);
});

// Configurable document requirements per period/program
Schema::create('admission_document_requirements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_period_id')->nullable()->constrained('admission_periods')->cascadeOnDelete();
    $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
    $table->string('document_type'); // id_card, certificate, photo, report_card, payment_proof
    $table->string('label');
    $table->boolean('is_required')->default(true);
    $table->string('allowed_extensions')->nullable(); // pdf,jpg,png
    $table->integer('max_size_kb')->nullable();
    $table->timestamps();
});

// Document uploads
Schema::create('admission_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
    $table->foreignId('document_requirement_id')->nullable()->constrained('admission_document_requirements')->nullOnDelete();
    $table->string('document_type'); // id_card, high_school_certificate, photo, report_card, payment_proof
    $table->string('file_path');
    $table->string('file_name');
    $table->integer('file_size');
    $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
    $table->text('verification_notes')->nullable();
    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
});

// Exam schedules
Schema::create('admission_exam_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_period_id')->constrained('admission_periods')->cascadeOnDelete();
    $table->string('exam_type'); // written_test, interview, practical
    $table->date('exam_date');
    $table->time('exam_time');
    $table->string('venue');
    $table->integer('quota');
    $table->integer('registered_count')->default(0);
    $table->timestamps();
});

// Exam participants
Schema::create('admission_exam_participants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_exam_schedule_id')->constrained('admission_exam_schedules')->cascadeOnDelete();
    $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
    $table->enum('attendance_status', ['registered', 'present', 'absent'])->default('registered');
    $table->timestamps();
    $table->unique(['admission_exam_schedule_id', 'admission_application_id']);
});

// Exam scores
Schema::create('admission_scores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
    $table->foreignId('admission_exam_schedule_id')->nullable()->constrained('admission_exam_schedules')->nullOnDelete();
    $table->string('score_type'); // entrance_exam, interview, toefl, portfolio
    $table->decimal('score', 5, 2);
    $table->decimal('weight', 5, 2)->default(100);
    $table->text('notes')->nullable();
    $table->foreignId('scored_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
});

// Quota per period/program/class type
Schema::create('admission_quotas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_period_id')->constrained('admission_periods')->cascadeOnDelete();
    $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
    $table->string('class_type')->nullable();
    $table->integer('quota');
    $table->integer('accepted_count')->default(0);
    $table->timestamps();
});

// Status history / audit trail
Schema::create('admission_status_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
    $table->string('from_status')->nullable();
    $table->string('to_status');
    $table->text('notes')->nullable();
    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});

// Flexible NIM generation rules
Schema::create('nim_generation_rules', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('pattern'); // e.g. {year}{program_code}{sequence}
    $table->string('sequence_scope')->default('study_program_year'); // global, year, period, faculty, study_program, class_type
    $table->unsignedTinyInteger('sequence_padding')->default(4);
    $table->unsignedInteger('sequence_start')->default(1);
    $table->boolean('is_active')->default(false);
    $table->timestamps();
});

Schema::create('nim_sequence_counters', function (Blueprint $table) {
    $table->id();
    $table->foreignId('nim_generation_rule_id')->constrained('nim_generation_rules')->cascadeOnDelete();
    $table->string('scope_key'); // resolved key based on sequence_scope
    $table->unsignedInteger('last_number')->default(0);
    $table->timestamps();
    $table->unique(['nim_generation_rule_id', 'scope_key']);
});
```

**New Dependencies:**
- Dompdf (already available from Grade Book) - for acceptance letter generation
- Laravel Queues - for email/status notifications
- Intervention Image (optional) - only if photo resizing/validation is needed
- Payment gateway SDK (optional later) - Midtrans/Xendit for registration fee

**Files to Create/Modify:**
- Models: 
  - ✅ `app/Models/Admission/AdmissionPeriod.php`
  - ✅ `app/Models/Admission/AdmissionApplication.php`
  - ✅ `app/Models/Admission/AdmissionDocumentRequirement.php`
  - ✅ `app/Models/Admission/AdmissionDocument.php`
  - ✅ `app/Models/Admission/AdmissionExamSchedule.php`
  - ✅ `app/Models/Admission/AdmissionExamParticipant.php`
  - ✅ `app/Models/Admission/AdmissionScore.php`
  - ✅ `app/Models/Admission/AdmissionQuota.php`
  - ✅ `app/Models/Admission/AdmissionStatusHistory.php`
  - `app/Models/Admission/NimGenerationRule.php`
  - `app/Models/Admission/NimSequenceCounter.php`
- Livewire Components:
  - ✅ `app/Livewire/Admission/ApplicationTable.php` (admin review)
  - ✅ `app/Livewire/Admission/AdmissionPeriodTable.php` (admin)
  - ✅ `app/Livewire/Admission/ExamScheduleTable.php` (admin)
  - ✅ `app/Livewire/Admission/QuotaTable.php` (admin)
  - `app/Livewire/Admission/NimGenerationRuleTable.php` (admin)
- Views:
  - ✅ `resources/views/components/admission/` (public registration, applicant portal, status tracking)
  - ✅ `resources/views/components/admin/admission/` (review dashboard, periods, applications, exam schedules, quotas, selection)
- Support/Services:
  - ✅ `app/Support/Admission/AdmissionNumberService.php`
  - ✅ `app/Support/Admission/AdmissionStatusService.php`
  - ✅ `app/Support/Admission/AdmissionSelectionService.php`
  - `app/Support/Admission/AdmissionConversionService.php`
  - `app/Support/Admission/NimGenerationService.php`
  - `app/Support/Admission/AdmissionDocumentService.php` (optional extraction if document logic grows)
- Migrations:
  - ✅ `2026_05_11_010000_create_admission_phase_one_tables.php`
  - ✅ `2026_05_11_020000_add_academic_year_id_to_admission_periods.php`
  - ✅ `2026_05_11_030000_create_admission_phase_two_tables.php`
  - ⏳ Phase 3 NIM generation tables
- Routes:
  - ✅ Admin routes via `config/resources.php` / resource registry pattern
  - ✅ Public routes: `/admission`, `/admission/apply`, `/admission/status`, tokenized applicant portal URL
  - ✅ Document preview routes for admin and tokenized applicant portal

**UI Pattern Decision:**
- **Admin Side:** PowerGrid-first for list pages, consistent with existing admin modules.
- **Applicant Side:** Public anonymous Livewire pages under `resources/views/components/admission/`, not `resources/views/components/applicant/`.
- **No applicant dashboard role in MVP:** Avoid adding sidebar/dashboard/role-selection complexity until truly needed.
- **Future role option:** Add `applicant` role only if applicants need authenticated dashboard, messaging, or long-running pre-student workflows.

**Success Metrics:**
- 80% reduction in application processing time
- 95% complete application submissions
- <24 hours average review time
- Zero lost applications/documents

**Dependencies:**
- Requires: Existing faculties & study_programs tables ✅
- Requires: Existing users/student profile/student registration structure for conversion ✅
- Blocks: Student profile creation automation (auto-convert on acceptance)
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
- Related: Admission (registration fee), Student Services (payment verification)

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
- **Modules Affected:** Academic (enhancement), Publication (new), Admission (new), Financial (new)
- **Estimated Total Effort:** ~21-28 days

### Completion Tracking
- ✅ Completed Features: 3 (Course Materials Management, Announcement System, Grade Book with Export)
- 🚧 In Progress: 2
- ⏸️ Planned: 40+
- ❌ Not Started: 40+

### Module Distribution
- **Academic:** 13 features (existing + enhancements)
- **Publication:** 1 feature (new module — announcements ✅)
- **Admission:** 1 feature (new module)
- **Financial:** 1 feature (new module)
- **Student Services:** 1 feature (planned)
- **Lecturer HR:** 1 feature (planned)
- **Alumni:** 1 feature (planned)
- **System:** 11 features (planned)
- **Other:** 12 features (planned)

---

## 🔄 Update History

- **2026-05-11 (Admission Phase 2 Implementation):**
  - 🚧 **PHASE 2 IMPLEMENTED: Admission Selection Workflow** (Priority 4)
    - Added Phase 2 admission tables:
      - `admission_exam_schedules`
      - `admission_exam_participants`
      - `admission_scores`
      - `admission_quotas`
    - Added models and relationships for exam schedules, participants, scores, and quotas
    - Added `AdmissionSelectionService` for final score recalculation, ranking query, quota matching, and accepted-count refresh
    - Added admin Admission Exam Schedule management with PowerGrid/resource registry pattern
      - Schedule create/edit/index/show
      - Participant assignment from applications in the same admission period
      - Attendance update: registered, present, absent
      - Score input with score type, score, weight, notes
      - Automatic `final_score` recalculation after score updates
    - Added admin Admission Quota management with PowerGrid/resource registry pattern
      - Scope by admission period, faculty, study program, and class type
      - Tracks accepted count and remaining quota
    - Added admin Selection dashboard:
      - Filter by period, study program, and class type
      - Ranking by final score
      - Quota usage display
      - Per-applicant accept/waitlist/reject decisions
      - Bulk status actions for selected applicants
    - Enhanced admin application table/detail:
      - Added final score, score count, and assigned session visibility
      - Added selection schedule and score summary on application detail
    - Enhanced applicant portal:
      - Applicant can view assigned exam/interview schedule
      - Applicant can view attendance status, score summary, and final score
    - Registered new resources/menus/permissions in `config/resources.php`:
      - `admission-exam-schedule`
      - `admission-quota`
      - `admission-selection`

- **2026-05-11 (Admission Phase 1 Implementation):**
  - 🚧 **PHASE 1 IMPLEMENTED: Admission Foundation & Applicant Portal** (Priority 4)
    - Created new `Admission` namespace and `admission_*` database tables for Phase 1
    - Added admin Admission Period management with configurable document requirements
    - Added Academic Year binding for Admission Period via `academic_year_id`
    - Added admin Admission Application review table/detail using PowerGrid/resource registry pattern
    - Added document verification workflow: pending, verified, rejected, verification notes
    - Added secure document preview routes for admin and tokenized applicant portal
    - Added status review workflow: submitted, under_review, accepted, rejected, waitlisted
    - Added public applicant flow:
      - `/admission/apply` public application form
      - `/admission/status` application lookup using application number + email
      - tokenized applicant portal URL for status tracking and document updates
    - Added polished public UI and admin detail UI following existing lecturer/student modern card pattern
    - Added Mailpit/local SMTP email flow:
      - application submitted email
      - admission status updated email
      - templates under `resources/views/templates/email`
    - Added upload security hardening:
      - extension + MIME validation for document uploads
      - safe allowlist: `pdf`, `jpg`, `jpeg`, `png`, `webp`
      - sanitized admin-configured `allowed_extensions`
    - Added support services:
      - `AdmissionNumberService` for application number + token generation
      - `AdmissionStatusService` for status changes and audit history
    - Synced permissions and menus from `config/resources.php`
    - Migrations completed:
      - `2026_05_11_010000_create_admission_phase_one_tables.php`
      - `2026_05_11_020000_add_academic_year_id_to_admission_periods.php`
    - Verification completed:
      - `php -l` for new PHP files: OK
      - `php artisan route:list --name=admission`: OK
      - `php artisan route:list --name=admission.documents`: OK
      - `php artisan view:cache`: OK
      - `php artisan migrate --force`: OK
      - `php artisan migrate:status --pending`: no pending migrations

- **2026-05-11 (Admission Module Planning Refinement):**
  - 🚧 **REFINED: Admission Management** (Priority 4)
    - Renamed module direction from `pmb` to `admission` for general English naming consistency
    - Confirmed Admission as a new module, not an Academic submodule
    - Confirmed MVP should not add a new `applicant` role
    - Applicant Portal will use public/token-based access for status tracking and document updates
    - Accepted applicants are converted into real `student` users only during registration completion
    - Added flexible NIM generation rule engine requirement:
      - Template tokens: `{year}`, `{period_code}`, `{faculty_code}`, `{program_code}`, `{class_type}`, `{sequence}`
      - Sequence scopes: global, year, admission period, faculty, study program, class type
      - Padding, starting number, preview, and collision checks
    - Expanded technical scope:
      - Admission periods/intakes
      - Configurable document requirements
      - Status history/audit trail
      - Exam participants and score weighting
      - Quota/waitlist management
      - Conversion service and acceptance letter generation
    - UI decision:
      - Admin side follows PowerGrid/resource registry pattern
      - Applicant side uses `resources/views/components/admission/` public pages
      - No `resources/views/components/applicant/` role folder for MVP

- **2026-05-09 (Grade Book with Export Implementation):**
  - ✅ **COMPLETED: Grade Book with Export** (Priority 3)
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
    - Admin interface: PowerGrid table with integrated filter-aware export
      - Export buttons live in PowerGrid header/dropdown
      - Export respects current PowerGrid search, filters, sorting, visible columns, and selected rows
      - Added academic filters: academic year, study program, course offering, class, semester, letter grade, lifecycle, result status
      - CSV/XLSX export handled in `StudentGradeTable.php` using PowerGrid `prepareToExport()` + native stream/PhpSpreadsheet
    - Service layer architecture: `GradeBookExportService` untuk lecturer grade book:
      - `lecturerRows(User $user, array $filters)`: Filtered collection scoped to lecturer course offerings
      - `statistics(Collection $rows)`: 8 statistical metrics calculation
      - `distribution(Collection $rows)`: Grade distribution analysis
      - `streamCsv()`, `streamXlsx()`, `pdf()`: Streaming exports dengan proper headers
    - Lecturer export controller:
      - `app/Http/Controllers/Lecturer/GradeBookExportController.php`
      - Export query parameters mirror active Livewire filters
    - PDF template: `resources/views/exports/grade-book-pdf.blade.php` dengan DejaVu Sans font
    - Database performance optimization dengan idempotent indexes on `grade_status` & `graded_at` columns
    - Post-implementation fixes completed:
      - Lecturer export dropdown clipping fixed
      - Lecturer filter options and filtered exports verified
      - Admin PowerGrid selectable filter options fixed
      - Admin CSV/XLSX 500 error fixed by bypassing incompatible OpenSpout v5 PowerGrid exporter
    - Verification completed:
      - CSV export smoke test: OK
      - XLSX export smoke test: OK
      - `php -l`, `php artisan view:cache`, `composer validate --strict`: OK
      - `php artisan migrate:status --pending`: no pending migrations
    - Dependencies added: openspout/openspout ^5.0, phpoffice/phpspreadsheet ^5.7, dompdf/dompdf ^3.1

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
  - Added module categorization (Academic, Admission, Financial)
  - Created planning queue for future features
- Last updated by: AI Assistant (based on actual git commit history)

---

**Note:** This is a living document that will be updated as features progress through development phases.
