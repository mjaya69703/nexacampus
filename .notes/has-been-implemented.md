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

**Catatan Evaluasi (Perlu Ditambahkan):**
- **Video links / embed YouTube** belum ada (hanya upload file). Tambah opsi input URL dan tampilan embed di halaman student learning.
- **Bookmark/Favorite** belum ada. Tambah toggle favorit per materi untuk mahasiswa.
- **PDF viewer in-browser** belum ada. Tambah viewer sederhana (iframe/PDF.js) untuk mobile-friendly.
- **Drag & drop upload** belum ada. Upgrade input upload ke drag & drop.
- **Versioning** belum ada. Simpan versi file/material (minimal log versi/riwayat update).

**Description:**
Sistem upload dan manajemen materi perkuliahan yang terintegrasi antara dosen dan mahasiswa.

**Features:**
- **Lecturer Side:**
  - Upload syllabus/RPS (Rencana Pembelajaran Semester)
  - Upload materi perkuliahan (PDF, PPT, DOC, video links)
  - Organize materials by meeting/session number
  - Categorize materials (Syllabus, Lecture Notes, Assignments, References)
  - Share resources with enrolled students
  - Download statistics tracking
  - Version control for updated materials
  - File size & type validation
  - Drag & drop upload interface

- **Student Side:**
  - View materials per course offering
  - Download syllabus/RPS
  - Download lecture notes (PDF, PPT, DOC)
  - Watch embedded videos (YouTube links)
  - Filter by category (Syllabus, Lecture Notes, Assignments, References)
  - Filter by meeting/session number
  - Download counter & last accessed tracking
  - Search materials by keyword
  - Bookmark/favorite important materials
  - Mobile-friendly PDF viewer

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

---

#### **Priority 2: Announcement System**
*Impact: Lecturer + Student | Module: Academic*

##### 2. Announcements System 📢
**Status:** 🚧 IN PROGRESS  
**Roles Affected:** Lecturer (create), Student (view)  
**Module Category:** Academic → New Submodule: `announcements`

**Description:**
Sistem pengumuman untuk komunikasi dosen-mahasiswa yang efektif dan terdokumentasi.

**Features:**
- **Lecturer Side:**
  - Create announcements per course offering
  - Rich text editor (bold, italic, lists, links)
  - Attach files/links to announcements
  - Schedule announcements (publish later)
  - Pin important announcements
  - Mark as read/unread tracking
  - Email notification integration (optional)
  - Announcement history/archive
  - Bulk send to multiple classes

- **Student Side:**
  - View announcements per enrolled course
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
  - Search announcements
  - Email notification for important announcements
  - Push notification (PWA)

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
- Simple CRUD with title, content (HTML), course_offering_id, published_at, is_pinned
- Use Trix or Quill editor for rich text
- Add "read_by_students" pivot table for tracking
- Optional: Integrate with Laravel notifications for email/push
- Reuse existing course_offerings relationship
- Implement priority levels (enum: normal, important, urgent)

**Database Changes Required:**
```php
// New table: announcements
Schema::create('announcements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // lecturer
    $table->string('title');
    $table->longText('content'); // HTML from rich text editor
    $table->string('priority')->default('normal'); // normal, important, urgent
    $table->boolean('is_pinned')->default(false);
    $table->boolean('is_published')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamp('scheduled_at')->nullable(); // for scheduled publishing
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['course_offering_id', 'is_published']);
    $table->index(['published_at', 'is_pinned']);
});

// Pivot table for tracking reads
Schema::create('announcement_reads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->timestamp('read_at');
    
    $table->unique(['announcement_id', 'student_id']);
});
```

**New Dependencies:**
- Trix Editor or Quill Editor (for rich text editing)
- Laravel Notifications (optional, for email/push)

**Files to Create/Modify:**
- Models: `app/Models/Academic/Announcement.php`, `app/Models/Academic/AnnouncementRead.php`
- Livewire Components:
  - `app/Livewire/Academic/AnnouncementTable.php` (lecturer)
  - `app/Livewire/Academic/StudentAnnouncementTable.php` (student)
- Views:
  - `resources/views/components/lecturer/announcements/` (index, create, edit)
  - `resources/views/components/student/announcements/` (index, view)
- Migration: `database/migrations/YYYY_MM_DD_create_announcements_table.php`

**Success Metrics:**
- >90% announcement read rate within 24 hours
- Average 2-3 announcements per course per week
- Reduced email inquiries about schedule changes

**Dependencies:**
- Requires: Existing course_offerings system ✅
- Blocks: None
- Related: Personalized Notifications (future enhancement)

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
