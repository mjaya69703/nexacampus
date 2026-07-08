# NexaCampus - Feature Roadmap & Requirements

Dokumen ini berisi daftar fitur-fitur yang perlu diimplementasikan untuk pengembangan NexaCampus.

---

## Kebutuhan Dosen

Dokumen ini merangkum fitur-fitur yang dibutuhkan untuk role **Lecturer (Dosen)** dalam sistem NexaCampus.

### ✅ Fitur yang Sudah Ada (Completed)

1. **Dashboard Lecturer**
   - Hero section dengan profile info
   - Stats cards (Total Kelas, Mahasiswa, Sesi Absensi, Nilai Finalized/Published)
   - Quick Actions (Kelola Absensi, Input Nilai, Kelola Kelas)
   - Jadwal Mengajar Terdekat (sorted ascending by date & time)
   - Recent Classes overview

2. **Course Offerings Management**
   - List semua kelas yang diajar (card-based layout)
   - Filter & search functionality
   - Color-coded status badges (Open/Closed/Draft)
   - Capacity progress bar
   - Navigate to course detail page

3. **Course Detail Page**
   - Course info & statistics
   - Tab navigation: Students, Attendance, Grades
   - Modern UI with FontAwesome icons

4. **Student Management per Course**
   - View enrolled students (card-based)
   - Student avatar with initials
   - NIM, name, program info
   - KRS status indicator
   - Repeat student indicator

5. **Attendance Management**
   - Session list per course (card-based)
   - Create attendance sessions
   - Edit session topic & status (inline modal)
   - Status options: Draft, Opened, Closed
   - Pulse animation for "Opened" status
   - Rekap absensi per session (Present, Late, Excused, Sick, Absent counts)
   - Input attendance records (navigate to edit page)

6. **Grade Management**
   - Grade input per course offering
   - Student grade cards with avatars
   - Score display (Final Score, Letter Grade, Grade Point)
   - Status badges (Published/Finalized/Draft)
   - Color-coded grade letters (A=green, B=blue, C=yellow, D=red, E=gray)
   - Edit individual grades
   - View all student grades across courses


---

### ❌ Fitur yang Masih Dibutuhkan (To Be Implemented)

#### 🎯 PRIORITAS TINGGI (High Priority - Week 1-2)

##### 1. 🚧 IN PROGRESS - Course Materials / Bahan Ajar 📚
**Deskripsi:** Sistem upload dan manajemen materi perkuliahan

**Fitur Detail:**
- Upload syllabus/RPS (Rencana Pembelajaran Semester)
- Upload materi perkuliahan (PDF, PPT, DOC, video links)
- Organize materials by meeting/session number
- Categorize materials (Syllabus, Lecture Notes, Assignments, References, etc.)
- Share resources with enrolled students
- Download statistics tracking
- Version control for updated materials *(completed with automatic material version history)*
- File size & type validation
- Drag & drop upload interface *(completed for lecturer material upload/add-attachment surfaces)*

**Kenapa Penting:**
- Central repository untuk semua materi ajar
- Mahasiswa bisa akses materi kapan saja
- Dokumentasi pembelajaran terstruktur
- Support blended/hybrid learning

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Use Laravel file storage (public disk)
- Store metadata in database (file name, path, size, type, uploader, course_offering_id, meeting_no, category)
- Implement file validation (max size 50MB, allowed types: pdf, ppt, pptx, doc, docx, mp4, link)
- Add download counter & last accessed timestamp
- Consider using AWS S3 for production

---

##### 2. ✅ COMPLETED - Announcements / Pengumuman 📢
**Deskripsi:** Sistem pengumuman untuk komunikasi dosen-mahasiswa yang efektif dan terdokumentasi

**Module:** Publication (base ownership di Admin/Superuser)

**Roles & Permissions:**
- **Admin/Superuser:** Full CRUD semua announcements (base owner)
- **Lecturer:** Create/edit announcements untuk kelas mereka (via permission: `announcement.create`, `announcement.edit`)
- **Student:** View-only announcements untuk kelas yang diambil

**Fitur Detail:**
- **Admin Side:**
  - Manage all announcements across all courses
  - Create campus-wide/global announcements
  - Assign permissions to lecturers
  - View analytics & read statistics
  
- **Lecturer Side (Via Permission):**
  - Create announcements per assigned course offering
  - Rich text editor dengan Summernote
  - Attach files/links to announcements
  - Schedule announcements (publish later)
  - Pin important announcements
  - Mark as read/unread tracking
  - View read statistics
  - Edit/delete own announcements only
  
- **Student Side:**
  - View announcements for enrolled courses
  - Filter by course, date range, priority
  - Search announcements
  - Mark as read functionality
  - Email/push notifications (optional)

**Kenapa Penting:**
- Komunikasi efektif & terdokumentasi
- Inform perubahan jadwal, deadline, dll
- Semua mahasiswa receive same information
- Reduce miscommunication
- Tidak ketinggalan info penting dari dosen

**Estimated Effort:** Low-Medium (1-2 days)

**Technical Notes:**
- **Architecture:** Publication module dengan base ownership di Admin
- **Models:** `app/Models/Publication/Announcement.php`, `app/Models/Publication/AnnouncementRead.php`
- **Livewire:** `app/Livewire/Publication/AnnouncementTable.php`
- **Views:** `resources/views/components/admin/publication/announcements/`
- **Database:** Simple CRUD dengan title, content (HTML), course_offering_id (nullable for global), published_at, is_pinned
- **Attachment:** Optional single file attachment (PDF, DOC, images - max 10MB)
- **Rich Text Editor:** Use Summernote (consistent dengan existing pattern)
- **Read Tracking:** Pivot table `announcement_reads` untuk track student reads
- **Authorization:** Policy-based access control (Admin > Lecturer > Student)
- **Priority Levels:** Enum (normal, important, urgent) dengan visual indicators

**Future Scalability:**
Publication module designed untuk easy extension:
- News/Berita (campus-wide news)
- Events/Kegiatan (campus events dengan registration)
- Blog/Articles (knowledge sharing)
- Gallery/Media (photo/video library)

---

##### 3. 🚧 IN PROGRESS - Grade Book with Export 📊
**Deskripsi:** Rekap nilai lengkap dengan export functionality

**Fitur Detail:**
- Comprehensive grade overview across ALL courses
- Table view with sortable columns
- Filter by course, semester, academic year
- Export to Excel (.xlsx)
- Export to PDF (formatted report)
- Grade distribution charts (bar chart, pie chart)
- Calculate final grades automatically based on components
- Bulk grade operations (curve grades, apply formula)
- Print-friendly view
- Summary statistics (average, median, std deviation)

**Kenapa Penting:**
- End-of-semester reporting requirement
- Documentation untuk akreditasi
- Easy sharing with department/admin
- Data analysis for teaching improvement

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Use PhpSpreadsheet for Excel export
- Use Dompdf or TCPDF for PDF generation
- Implement Chart.js for visualizations
- Add calculation engine for weighted grades
- Cache grade calculations for performance

---

##### 4. ✅ COMPLETED - Teaching Schedule Calendar 📅
**Deskripsi:** Visual calendar view untuk jadwal mengajar

**Fitur Detail:**
- Monthly/weekly/daily calendar views
- All classes displayed in one view
- Color-coded by course
- Click event to navigate to course detail
- Show meeting times & room locations
- Sync with personal calendar (Google Calendar iCal export)
- Highlight today's classes
- Upcoming classes sidebar
- Filter by course/semester
- Print calendar view

**Kenapa Penting:**
- Better time management & planning
- Visual overview of teaching load
- Avoid scheduling conflicts
- Professional appearance

**Estimated Effort:** Medium (2 days)

**Technical Notes:**
- Use FullCalendar.js library
- Fetch events via AJAX from backend
- Generate iCal feed for external sync
- Responsive design for mobile viewing
- Cache calendar data for performance

---

#### 🎯 PRIORITAS SEDANG (Medium Priority - Week 3-4)

##### 5. Assignment Management 📝
**Deskripsi:** Sistem pembuatan & penilaian tugas

**Fitur Detail:**
- Create assignments per course
- Set title, description, deadline, max score
- Upload assignment instructions/files
- Allow student submissions (file upload/text)
- Track submission status (submitted/late/missing)
- Grade submissions with comments
- Rubric-based grading (optional)
- Bulk download all submissions
- Return graded assignments with feedback
- Plagiarism check integration (future)
- Extension request handling

**Kenapa Penting:**
- Core assessment method selain ujian
- Streamlined workflow untuk dosen
- Transparent grading process
- Digital submission tracking

**Estimated Effort:** High (4-5 days)

**Technical Notes:**
- Separate tables: assignments, assignment_submissions, assignment_grades
- File upload handling with validation
- Deadline checking logic (on-time vs late)
- Consider using queue jobs for bulk operations
- Add plagiarism detection API integration (Turnitin/etc)

---

##### 6. Student Progress Analytics 📈
**Deskripsi:** Analytics dashboard untuk monitoring performa mahasiswa

**Fitur Detail:**
- Individual student performance dashboard
- Attendance rate trends
- Grade trends over time
- Identify at-risk students (low attendance + low grades)
- Performance comparison across courses
- Early warning system alerts
- Engagement metrics (material downloads, announcement reads)
- Export analytics reports
- Visual charts & graphs
- Cohort analysis

**Kenapa Penting:**
- Proactive intervention untuk mahasiswa bermasalah
- Data-driven teaching decisions
- Support academic advising
- Accreditation documentation

**Estimated Effort:** Medium-High (3-4 days)

**Technical Notes:**
- Aggregate data from attendance, grades, activity logs
- Use Chart.js or ApexCharts for visualizations
- Implement threshold-based alerting
- Cache analytics calculations
- Consider background jobs for heavy computations

---

##### 7. Academic Advising Dashboard 👨‍🏫
**Deskripsi:** Dashboard khusus untuk dosen Pembimbing Akademik (PA)

**Fitur Detail:**
- View assigned advisees list
- Monitor advisee academic progress
- GPA/IPK tracking over semesters
- Credit completion status
- Course enrollment history
- Meeting notes/history log
- Action items & follow-ups
- Transcript preview
- Degree audit/progress toward graduation
- Recommendations tracking

**Kenapa Penting:**
- Structured academic advising process
- Track student development over time
- Documentation for advising meetings
- Early identification of academic issues

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Query AcademicAdvisorAssignment model
- Join with student grades, transcripts, study plans
- Add meeting_notes table for PA meetings
- Integrate with existing transcript system
- Role-based access (only assigned PA can view)

---

##### 8. ✅ COMPLETED - Course Evaluation Results ⭐
**Deskripsi:** View hasil evaluasi pengajaran dari mahasiswa

**Fitur Detail:**
- View anonymous student evaluations
- Rating breakdown (teaching quality, materials, fairness, etc.)
- Written comments from students (anonymized)
- Historical comparison across semesters
- Department/faculty benchmarking
- Export evaluation reports
- Response rate tracking
- Improvement action planning

**Kenapa Penting:**
- Self-improvement & professional development
- Accreditation requirements
- Promotion/tenure documentation
- Quality assurance

**Estimated Effort:** Low-Medium (1-2 days)

**Technical Notes:**
- Depends on existing course evaluation system
- Read-only view for lecturers
- Aggregate ratings & calculate averages
- Anonymize student comments
- Generate PDF reports

---

##### 9. Consultation Management 🕐
**Status:** ✅ Core implemented 2026-06-28. Remaining enhancements: calendar sync, email/in-app notifications, holiday/break exceptions.

**Deskripsi:** Manajemen jam konsultasi dosen

**Fitur Detail:**
- Set weekly consultation schedule
- Specify location (onsite/online)
- Allow students to book appointments
- Calendar integration
- Appointment confirmation/cancellation
- Waiting list for popular slots
- Meeting notes after consultation
- No-show tracking
- Recurring schedule setup
- Holiday/break exceptions

**Kenapa Penting:**
- Structured student consultation time
- Reduce ad-hoc interruptions
- Better time management
- Documentation of student interactions

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- New table: consultation_slots, consultation_appointments
- Integration with calendar system
- Email notifications for bookings
- Conflict detection logic
- Consider video conferencing integration (Zoom/Meet links)

---

##### 10. Rubric Builder 📋
**Deskripsi:** Builder untuk membuat rubrik penilaian

**Fitur Detail:**
- Create custom rubrics per assignment/exam
- Define criteria & performance levels
- Point allocation per criterion
- Descriptive labels for each level
- Reuse rubrics across courses
- Auto-calculate scores from rubric
- Rubric templates library
- Share rubrics with colleagues
- Student view of rubric before submission
- Rubric-based grading interface

**Kenapa Penting:**
- Fair & transparent grading
- Consistent assessment standards
- Clear expectations for students
- Reduce grading subjectivity

**Estimated Effort:** Medium-High (3-4 days)

**Technical Notes:**
- Tables: rubrics, rubric_criteria, rubric_levels, rubric_grades
- JSON structure for flexible rubric definitions
- Integrate with assignment grading workflow
- Template system with common rubric patterns
- Drag & drop builder interface

---

#### 🎯 PRIORITAS RENDAH (Low Priority - Future Enhancement)

##### 11. Messaging System 💬
**Deskripsi:** Internal messaging antara dosen-mahasiswa

**Fitur Detail:**
- Direct messages to individual students
- Group messages to entire class
- Inbox/outbox management
- Message threading/conversations
- File attachments
- Read receipts
- Notification preferences
- Archive/delete messages
- Search message history
- Spam/reporting system

**Estimated Effort:** High (4-5 days)

---

##### 12. Research & Publications Profile 🔬
**Deskripsi:** Tracking aktivitas penelitian & publikasi

**Fitur Detail:**
- Publication records (journals, conferences, books)
- Research projects tracking
- Grants & funding information
- Citation metrics
- Collaboration network
- Integration with ORCID/Google Scholar
- Annual research report generation
- Department research output summary

**Estimated Effort:** Medium (2-3 days)

---

##### 13. Attendance Analytics Dashboard 📊
**Deskripsi:** Deep analytics untuk data absensi

**Fitur Detail:**
- Attendance rate per course
- Trend analysis over semester
- Compare attendance across courses
- Correlation with grades
- Export detailed reports
- Heatmap visualization
- Predictive analytics (at-risk prediction)

**Estimated Effort:** Medium (2 days)

---

##### 14. COMPLETED - Grade Appeals Management
**Deskripsi:** Sistem handling komplain nilai

**Fitur Detail:**
- Student grade appeal submission
- Review workflow for lecturers
- Documentation of review process
- Approval/rejection with comments
- Escalation to department if needed
- Appeal history tracking
- Statistics on appeals

**Estimated Effort:** Medium (2-3 days)

**Implementation Status:** Core workflow completed (student submission, optional evidence attachments, lecturer review, decision comments, history, basic statistics, approved score correction). Department escalation remains a future extension.

---

##### 15. Syllabus Builder 📄
**Deskripsi:** Tool untuk membuat syllabus terstruktur

**Fitur Detail:**
- Template-based syllabus creation
- Learning outcomes mapping
- Weekly schedule planner
- Assessment plan builder
- Policy statements library
- Export to PDF/Word
- Version control
- Department approval workflow

**Estimated Effort:** High (4-5 days)

---

### 📋 Implementation Priority Matrix

| Fitur | Impact | Effort | Priority | Timeline |
|-------|--------|--------|----------|----------|
| Course Materials | High | Medium | **HIGH** | Week 1-2 |
| Announcements | High | Low | **HIGH** | Week 1-2 |
| Grade Book Export | High | Medium | **HIGH** | Week 1-2 |
| Teaching Calendar | Medium | Medium | **HIGH** | Week 1-2 |
| Assignment Mgmt | High | High | **MEDIUM** | Week 3-4 |
| Student Analytics | Medium | Medium | **MEDIUM** | Week 3-4 |
| Academic Advising | Medium | Medium | **MEDIUM** | Week 3-4 |
| Course Evaluations | Medium | Low | **MEDIUM** | Week 3-4 |
| Consultations | Low | Medium | **LOW** | Future |
| Rubric Builder | Medium | High | **LOW** | Future |
| Messaging | Low | High | **LOW** | Future |
| Research Profile | Low | Medium | **LOW** | Future |
| Attendance Analytics | Low | Medium | **LOW** | Future |
| Grade Appeals | Low | Medium | **LOW** | Future |
| Syllabus Builder | Medium | High | **LOW** | Future |

---

### 🚀 Recommended Implementation Phases

#### **Phase 1: Communication & Resources (Week 1-2)**
- Course Materials Upload
- Announcements System
- Grade Book with Export
- Teaching Schedule Calendar

**Goal:** Improve communication and resource sharing between lecturers and students.

#### **Phase 2: Assessment & Analytics (Week 3-4)**
- Assignment Management
- Student Progress Analytics
- Academic Advising Dashboard
- Course Evaluation Viewer

**Goal:** Enhance assessment capabilities and data-driven insights.

#### **Phase 3: Advanced Features (Month 2+)**
- Consultation Management
- Rubric Builder
- Messaging System
- Other low-priority features

**Goal:** Provide comprehensive tools for modern teaching practices.

---

### 💡 Quick Wins (Fast Implementation)

Jika butuh fitur yang **cepat diimplement** dengan **high impact**:

1. **Grade Export Button** - Add Excel/PDF export to existing student-grades page (1 day)
2. **Simple Announcements** - Basic CRUD without rich text (1 day)
3. **File Upload for Materials** - Simple upload without categorization (1-2 days)
4. **Calendar View** - Integrate FullCalendar with existing schedule data (2 days)

---

---

## Kebutuhan Admin (Administrator)

Dokumen ini merangkum fitur-fitur **CRUD bisnis** yang dibutuhkan untuk role **Admin (Administrator)** dalam sistem NexaCampus.

**Fokus:** Fitur yang impact langsung ke student, lecturer, financial, PMB - BUKAN fitur internal admin seperti analytics/monitoring.

### ✅ Fitur yang Sudah Ada (Completed)

#### **1. Dashboard Admin**
   - Stats cards (Users, Roles, Permissions, Menus count)
   - System warnings & health checks:
     - Roles without permissions
     - Menus without permission
     - Inactive menus
     - Orphan child menus
     - Invalid route menus
   - Recent activity logs (last 8 activities)
   - Last login timestamp
   - Superuser detection

#### **2. Access Management**
   - **Users Management**: CRUD users dengan PowerGrid table
   - **Roles Management**: CRUD roles dengan permission assignment
   - **Permissions Management**: CRUD permissions system-wide

#### **3. Academic Management**
   - **Academic Years**: Manage tahun akademik
   - **Faculties**: Manage fakultas
   - **Study Programs**: Manage program studi
   - **Courses**: Manage mata kuliah & prerequisites
   - **Curriculums**: Manage kurikulum & course mappings
   - **Student Registrations**: Manage pendaftaran mahasiswa baru
   - **Academic Periods**: Manage periode akademik (semester)
   - **Course Offerings**: Manage penawaran kelas per periode
   - **Study Plans**: Manage rencana studi mahasiswa (KRS admin view)
   - **Course Schedules**: Manage jadwal kuliah (waktu, ruang, dosen)
   - **Student Grades**: Manage nilai mahasiswa (admin override)
   - **Transcripts**: View & manage transkrip nilai
   - **Academic Advisor Assignments**: Assign dosen PA ke mahasiswa
   - **Attendance Sessions**: View detail sesi absensi (read-only show page)

#### **4. Campus Infrastructure**
   - **Buildings**: Manage gedung kampus
   - **Rooms**: Manage ruangan (dengan building relation)

#### **5. System Management**
   - **Settings**: System configuration settings
   - **Menus**: Manage sidebar navigation menus
   - **Activity Logs**: View system-wide activity audit trail

---

### ❌ Fitur yang Masih Dibutuhkan (To Be Implemented)

#### 🎯 PRIORITAS TINGGI (High Priority - Week 1-2)

##### 1. 🚧 IN PROGRESS - PMB (Penerimaan Mahasiswa Baru) Management 🎓
**Deskripsi:** Sistem pendaftaran & seleksi mahasiswa baru end-to-end

**Fitur Detail:**
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

**Kenapa Penting:**
- Core business process untuk kampus
- Streamline admission workflow
- Digital document management
- Transparent selection process
- Reduce manual paperwork

**Estimated Effort:** High (5-7 days)

**Technical Notes:**
- New tables: `pmb_applications`, `pmb_documents`, `pmb_exam_schedules`, `pmb_scores`
- File upload handling untuk documents
- Status workflow engine
- Integration dengan student registration system
- Email notifications untuk status updates
- Consider payment gateway integration untuk registration fee

---

##### 2. 🚧 IN PROGRESS - Financial Management - Tuition & Payments 💰
**Deskripsi:** Sistem manajemen keuangan mahasiswa (SPP, UKT, pembayaran)

**Fitur Detail:**
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

**Kenapa Penting:**
- Critical untuk cash flow management
- Automated billing reduce errors
- Transparency untuk mahasiswa
- Financial reporting requirements
- Reduce manual reconciliation

**Estimated Effort:** High (6-8 days)

**Technical Notes:**
- New tables: `tuition_fees`, `student_invoices`, `invoice_items`, `payments`, `scholarships`, `student_scholarships`
- Invoice generation logic
- Payment status tracking (Pending → Paid → Overdue)
- Integration dengan payment gateway (Midtrans/Xendit)
- PDF receipt generation dengan Dompdf
- Scheduled jobs untuk invoice generation
- Consider double-entry accounting for accuracy

---

##### 3. Student Services & Administration 📋
**Deskripsi:** Layanan administrasi mahasiswa non-akademik

**Fitur Detail:**
- **Surat Keterangan (Letters/Certificates):**
  - Active student letter (surat keterangan aktif kuliah)
  - Transcript request
  - Graduation letter
  - Internship recommendation letter
  - Custom letter templates
  - Online request & approval workflow
  - Digital signature support
  - Download/generated letters
  
- **Leave of Absence (Cuti Akademik):**
  - Leave application form
  - Reason & duration specification
  - Document attachment (medical certificate, etc.)
  - Approval workflow (PA → Department → Faculty)
  - Leave status tracking
  - Automatic semester skip
  - Return from leave process
  
- **Student Transfer:**
  - Internal transfer (change study program)
  - External transfer (from other university)
  - Credit transfer evaluation
  - Transfer approval workflow
  - Curriculum mapping
  
- **Graduation Management:**
  - Graduation eligibility check
  - Graduation application
  - Thesis/final project tracking
  - Graduation requirements verification
  - Graduation ceremony planning
  - Diploma generation
  
- **Student Complaints/Feedback:**
  - Complaint submission form
  - Category classification
  - Assignment to relevant department
  - Resolution tracking
  - Response time SLA
  - Satisfaction rating

**Kenapa Penting:**
- One-stop service untuk mahasiswa
- Reduce administrative bottlenecks
- Trackable & transparent processes
- Digital documentation
- Better student experience

**Estimated Effort:** High (6-7 days)

**Technical Notes:**
- New tables: `letter_requests`, `leave_applications`, `transfer_requests`, `graduation_applications`, `student_complaints`
- Workflow engine untuk approval processes
- PDF generation untuk letters/certificates
- Digital signature integration (optional)
- Email notifications untuk status updates
- Template system untuk different letter types
- Role-based approval routing

---

##### 4. Lecturer HR & Administration 👨‍🏫
**Deskripsi:** Manajemen data dosen & administrasi kepegawaian

**Fitur Detail:**
- **Lecturer Profile Management:**
  - Personal information (complete biodata)
  - Academic qualifications (S1/S2/S3 degrees)
  - Employment status (full-time, part-time, visiting)
  - Join date & employment history
  - Contact information
  - Photo & signature upload
  
- **Teaching Load Management:**
  - SKS (credit hours) allocation per semester
  - Maximum teaching load configuration
  - Current vs maximum load comparison
  - Teaching history per lecturer
  - Course assignment history
  - Workload balancing tools
  
- **Certification & Training:**
  - Professional certifications tracking
  - Training/workshop attendance
  - Seminar/conference participation
  - Continuing education records
  - Expiry date reminders
  
- **Performance Evaluation:**
  - Annual performance review
  - Student evaluation scores aggregation
  - Peer review results
  - Research output tracking
  - Community service records
  - Promotion eligibility check
  
- **Attendance & Leave:**
  - Lecturer attendance tracking
  - Leave requests (sick, annual, special)
  - Leave balance management
  - Substitute lecturer assignment
  - Class rescheduling due to leave

**Kenapa Penting:**
- Centralized lecturer database
- Workload monitoring & fairness
- Compliance dengan accreditation requirements
- Career development tracking
- Resource planning

**Estimated Effort:** Medium-High (4-5 days)

**Technical Notes:**
- Enhance existing `lecturer_profiles` table
- Add tables: `lecturer_certifications`, `lecturer_training`, `teaching_loads`, `lecturer_evaluations`, `lecturer_leaves`
- Integration dengan course offering assignments
- Calculate teaching load dari course schedules
- Dashboard untuk workload visualization
- Notification system untuk certification expiry

---

##### 5. Alumni Management 🎓
**Deskripsi:** Tracking & engagement dengan alumni

**Fitur Detail:**
- **Alumni Database:**
  - Graduate information (batch year, study program, GPA)
  - Current employment status
  - Company/organization info
  - Job title & position
  - Contact information (email, phone, LinkedIn)
  - Location/city/country
  
- **Tracer Study:**
  - Graduate survey distribution
  - Employment status tracking (working/entrepreneur/studying/unemployed)
  - Time to employment measurement
  - Salary range collection
  - Job relevance to study program
  - Employer satisfaction survey
  - Survey response analytics
  
- **Alumni Engagement:**
  - Alumni events management
  - Reunion planning
  - Networking directory
  - Job board for alumni
  - Mentorship program matching
  - Donation/fundraising tracking
  
- **Career Services:**
  - Job posting board
  - Internship opportunities
  - Career counseling requests
  - Resume/CV database (opt-in)
  - Employer partnership management
  
- **Statistics & Reports:**
  - Employment rate per batch/program
  - Average salary statistics
  - Industry distribution
  - Geographic distribution
  - Further education tracking
  - Export tracer study reports

**Kenapa Penting:**
- Accreditation requirement (tracer study)
- Alumni networking & engagement
- Career services enhancement
- University reputation building
- Fundraising opportunities
- Curriculum improvement feedback

**Estimated Effort:** Medium (3-4 days)

**Technical Notes:**
- New tables: `alumni_profiles`, `tracer_studies`, `alumni_events`, `job_postings`
- Auto-convert graduating students to alumni
- Survey builder dengan customizable questions
- Email campaign integration untuk surveys
- Privacy controls untuk data sharing
- Analytics dashboard untuk tracer study results
- Integration dengan job board platforms

---

##### 6. Extracurricular & Organization Management 🏆
**Deskripsi:** Manajemen kegiatan kemahasiswaan & organisasi

**Fitur Detail:**
- **Student Organizations:**
  - Organization registration (BEM, HIMA, clubs)
  - Organization profile & description
  - Member management (leader, members)
  - Activity calendar
  - Budget allocation tracking
  - Event proposals & approvals
  
- **Activities & Events:**
  - Event creation & promotion
  - Participant registration
  - Attendance tracking
  - Event documentation (photos, reports)
  - Budget realization tracking
  - Post-event evaluation
  
- **Achievements & Awards:**
  - Student achievement recording (competitions, awards)
  - Achievement categories (academic, sports, arts, etc.)
  - Competition level (local, national, international)
  - Certificate/document uploads
  - Achievement statistics per program
  - Hall of fame display
  
- **Points System:**
  - Extracurricular points allocation
  - Points per activity type
  - Student points accumulation
  - Graduation requirement check (min points)
  - Points leaderboard
  
- **Facility Booking:**
  - Venue/equipment booking system
  - Availability calendar
  - Booking approval workflow
  - Usage history tracking
  - Conflict detection

**Kenapa Penting:**
- Student development tracking
- Holistic education documentation
- Accreditation requirement (student activities)
- Campus life enhancement
- Leadership development

**Estimated Effort:** Medium (3-4 days)

**Technical Notes:**
- New tables: `student_organizations`, `organization_members`, `events`, `event_participants`, `student_achievements`, `extracurricular_points`, `facility_bookings`
- Point calculation engine
- Integration dengan student profiles
- Calendar view untuk events
- Image gallery untuk event documentation
- Leaderboard calculations

---

#### 🎯 PRIORITAS SEDANG (Medium Priority - Week 3-4)

##### 7. Library Management Integration 📚
**Deskripsi:** Integrasi atau module perpustakaan sederhana

**Fitur Detail:**
- Book catalog management
- Borrowing/return tracking
- Due date reminders
- Late return fines
- Book reservation system
- Reading history per student
- Popular books statistics
- Integration dengan existing library system (optional)

**Estimated Effort:** Medium (3-4 days)

---

##### 8. Hostel/Dormitory Management 🏠
**Deskripsi:** Manajemen asrama/kos kampus (jika ada)

**Fitur Detail:**
- Room inventory management
- Occupancy tracking
- Room assignment application
- Roommate matching
- Facility maintenance requests
- Payment tracking
- Vacancy monitoring
- Check-in/check-out process

**Estimated Effort:** Medium (3 days)

---

##### 9. Parking Management 🚗
**Deskripsi:** Manajemen parkir kampus (jika relevan)

**Fitur Detail:**
- Vehicle registration (students/staff)
- Parking permit issuance
- Parking slot allocation
- Violation tracking
- Payment management
- Statistics & reports

**Estimated Effort:** Low-Medium (2 days)

---

##### 10. Cafeteria/Canteen Management 🍽️
**Deskripsi:** Manajemen kantin/vendor makanan (opsional)

**Fitur Detail:**
- Vendor/tenant management
- Menu catalog
- Order management (pre-order system)
- Payment integration
- Sales reports
- Feedback/rating system

**Estimated Effort:** Medium (3 days)

---

#### 🎯 PRIORITAS RENDAH (Low Priority - Future Enhancement)

##### 11. Laboratory Management 🔬
**Deskripsi:** Manajemen laboratorium & praktikum

**Fitur Detail:**
- Lab inventory (equipment, chemicals)
- Lab session scheduling
- Safety training records
- Equipment borrowing
- Maintenance tracking
- Incident reporting

**Estimated Effort:** Medium (3-4 days)

---

##### 12. Internship/Praktek Kerja Lapangan Management 💼
**Deskripsi:** Manajemen PKL/magang mahasiswa

**Fitur Detail:**
- Company partner database
- Internship placement application
- Supervisor assignment (campus + company)
- Progress reporting
- Evaluation & grading
- Internship completion certificate
- Company feedback collection

**Estimated Effort:** Medium (3-4 days)

---

##### 13. Thesis/Final Project Management 📝
**Deskripsi:** Monitoring skripsi/tugas akhir

**Fitur Detail:**
- Topic proposal submission
- Supervisor assignment
- Proposal approval workflow
- Progress milestone tracking
- Revision history
- Scheduling defenses/seminars
- Final grade submission
- Plagiarism check integration
- Thesis repository/archive

**Estimated Effort:** High (4-5 days)

---

##### 14. Research & Community Service Management 🔬
**Deskripsi:** Tracking penelitian & pengabdian masyarakat dosen

**Fitur Detail:**
- Research proposal submission
- Funding/grant tracking
- Publication records
- Team member management
- Progress reporting
- Budget tracking
- Output documentation
- Community service project tracking

**Estimated Effort:** Medium (3-4 days)

---

##### 15. Partnership & MOU Management 🤝
**Deskripsi:** Manajemen kerjasama institusi

**Fitur Detail:**
- Partner institution database
- MOU/agreement tracking
- Agreement terms & duration
- Renewal reminders
- Collaboration activities log
- Contact person management
- Agreement document storage

**Estimated Effort:** Low-Medium (2-3 days)

---

#### 🎯 PRIORITAS SEDANG (Medium Priority - Week 3-4)

##### 5. Audit Trail Enhancement 🔎
**Deskripsi:** Enhanced audit logging dengan advanced filtering & search

**Fitur Detail:**
- Advanced filtering (by user, model, action, date range)
- Full-text search in changes
- Diff viewer (before/after comparison)
- Export audit logs (CSV/PDF)
- Real-time activity feed
- Suspicious activity detection
- Compliance reporting (GDPR, data privacy)
- Retention policy management
- Granular permission controls for audit access
- IP address & user agent tracking
- Session correlation
- Batch operation grouping

**Kenapa Penting:**
- Compliance & regulatory requirements
- Security incident investigation
- Accountability & transparency
- Debugging & troubleshooting
- Change history documentation

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Enhance existing Spatie Activity Log implementation
- Add custom properties untuk additional context
- Implement efficient indexing untuk fast searches
- Consider separate audit database untuk performance
- Add soft delete protection untuk critical records

---

##### 6. Role-Based Permission Matrix 🔐
**Deskripsi:** Visual matrix editor untuk manage permissions

**Fitur Detail:**
- Grid view: Roles (rows) x Permissions (columns)
- Toggle permissions on/off dengan single click
- Bulk permission operations (grant/revoke all)
- Permission templates (predefined role sets)
- Clone role functionality
- Permission dependency checking
- Conflict detection (contradictory permissions)
- Visual indicators for inherited permissions
- Export permission matrix (PDF/Excel)
- Role comparison tool
- Permission usage statistics
- Unused permission identification

**Kenapa Penting:**
- Simplify complex permission management
- Visual clarity untuk security auditing
- Reduce misconfiguration risks
- Faster role setup & maintenance

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Use interactive grid component (AG Grid atau similar)
- AJAX updates untuk instant feedback
- Transaction-based saves untuk consistency
- Cache permission checks untuk performance
- Add confirmation dialogs untuk bulk operations

---

##### 7. Data Import/Export Center 📥📤
**Deskripsi:** Centralized hub untuk semua operasi import/export data

**Fitur Detail:**
- **Import Modules:**
  - Student data (from registration system)
  - Lecturer profiles (from HR system)
  - Course catalog (from curriculum database)
  - Historical grades (legacy system migration)
  - Building/room inventory
  
- **Export Modules:**
  - Complete student database
  - Course offerings per semester
  - Grade transcripts (batch export)
  - Attendance records
  - User accounts & roles
  
- Format support: CSV, Excel, JSON, XML
- Mapping interface (map source fields to destination)
- Data validation & error preview
- Duplicate detection & handling
- Import history & rollback
- Scheduled exports (automated backups)
- API integration presets (SIS, HR systems)

**Kenapa Penting:**
- System integration dengan external systems
- Data migration dari legacy systems
- Backup & disaster recovery
- Interoperability dengan other platforms

**Estimated Effort:** High (4-5 days)

**Technical Notes:**
- Modular import/export handlers
- Queue-based processing untuk large files
- Temporary staging area untuk data validation
- Mapping configuration stored in database
- Support chunked processing untuk memory efficiency
- Add webhook notifications untuk completion

---

##### 8. Notification Center 🔔
**Deskripsi:** Centralized notification management system

**Fitur Detail:**
- System-wide announcement broadcasting
- Targeted notifications (by role, department, course)
- Notification templates library
- Multi-channel delivery (in-app, email, SMS, push)
- Notification scheduling
- Delivery status tracking
- Read/unread management
- Notification preferences per user
- Digest emails (daily/weekly summary)
- Emergency alert system (priority notifications)
- Notification analytics (open rates, engagement)
- Automated triggers (grade published, deadline approaching)

**Kenapa Penting:**
- Effective communication channel
- Timely information dissemination
- Reduced email clutter
- Better user engagement
- Emergency communication capability

**Estimated Effort:** Medium-High (3-4 days)

**Technical Notes:**
- Use Laravel Notifications system
- Database + broadcast channels
- Integration dengan email service (Mailgun/Sendgrid)
- SMS gateway integration (Twilio/local provider)
- Push notifications via PWA service worker ✅ core Web Push channel implemented; remaining work is notification center preferences, scheduling, analytics, and broader trigger coverage.
- Queue notifications untuk async delivery
- Template engine dengan variables

---

##### 9. Backup & Restore Manager 💾
**Deskripsi:** Automated backup system dengan restore capabilities

**Fitur Detail:**
- Automated daily/weekly backups
- Manual backup on-demand
- Database + file storage backup
- Incremental backups (space efficient)
- Encrypted backup files
- Cloud storage integration (AWS S3, Google Drive)
- Backup verification (integrity check)
- Point-in-time restore
- Selective restore (specific tables/files)
- Backup rotation policy (keep last X backups)
- Restore preview (what will be affected)
- Backup scheduling configuration
- Email notifications (success/failure)
- Backup size & duration tracking

**Kenapa Penting:**
- Disaster recovery preparedness
- Data loss prevention
- Compliance requirements
- Peace of mind untuk administrators
- Quick recovery from mistakes

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Use Laravel backup package (spatie/laravel-backup)
- Database dump dengan mysqldump/pg_dump
- File synchronization dengan rsync
- Encryption dengan OpenSSL
- Cloud storage SDK integration
- Cron scheduling untuk automation
- Test restore procedure regularly

---

##### 10. User Activity Insights 👥
**Deskripsi:** Detailed analytics tentang user behavior & engagement

**Fitur Detail:**
- Login frequency & patterns
- Feature usage statistics (most/least used)
- Peak usage times (hourly/daily heatmaps)
- Session duration analysis
- User journey tracking (common workflows)
- Inactive user identification
- Device & browser analytics
- Geographic access patterns (if applicable)
- Performance per user type (admin vs lecturer vs student)
- Adoption rate tracking (new features)
- Churn prediction (users becoming inactive)
- Engagement scoring system

**Kenapa Penting:**
- UX improvement insights
- Feature prioritization based on usage
- Identify training needs
- Capacity planning
- Security monitoring (unusual patterns)

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Track events dengan JavaScript + AJAX
- Store in dedicated analytics table
- Use session data + activity logs
- Privacy-compliant tracking (anonymize where needed)
- Dashboard dengan Chart.js visualizations
- Consider using analytics service (Mixpanel/Amplitude)

---

#### 🎯 PRIORITAS RENDAH (Low Priority - Future Enhancement)

##### 11. Multi-Campus Support 🏫
**Deskripsi:** Support untuk multiple campus locations

**Fitur Detail:**
- Campus entity management
- Campus-specific settings & configurations
- Cross-campus course offerings
- Campus-based user segmentation
- Inter-campus transfer management
- Campus-specific reporting
- Resource allocation per campus
- Campus comparison analytics

**Estimated Effort:** High (5-6 days)

---

##### 12. Advanced Search Engine 🔍
**Deskripsi:** Global search dengan Elasticsearch/intelligent search

**Fitur Detail:**
- Search across all entities (students, courses, grades, etc.)
- Fuzzy matching & typo tolerance
- Relevance-based ranking
- Advanced filters (date, type, status)
- Search suggestions/autocomplete
- Search history & recent searches
- Saved searches
- Boolean operators support
- Faceted search results
- Search analytics (popular queries)

**Estimated Effort:** High (4-5 days)

---

##### 13. Workflow Automation ⚙️
**Deskripsi:** Business process automation engine

**Fitur Detail:**
- Visual workflow builder (drag & drop)
- Trigger-action rules engine
- Conditional logic support
- Approval workflows (multi-level)
- Automated email/actions on events
- Workflow templates
- Execution history & debugging
- Custom script integration
- SLA tracking (time-based actions)
- Workflow analytics

**Estimated Effort:** Very High (7-10 days)

---

##### 14. API Management Portal 🔌
**Deskripsi:** RESTful API untuk third-party integrations

**Fitur Detail:**
- API endpoint documentation (Swagger/OpenAPI)
- API key management
- Rate limiting & throttling
- API usage analytics
- Webhook management
- OAuth2 authentication
- API versioning
- Sandbox environment
- SDK generation (PHP, JavaScript, Python)
- API testing console

**Estimated Effort:** High (5-6 days)

---

##### 15. Custom Field Builder 🏗️
**Deskripsi:** Dynamic field creation untuk entities

**Fitur Detail:**
- Add custom fields to any entity (students, courses, etc.)
- Field types: text, number, date, dropdown, checkbox, file
- Validation rules configuration
- Conditional field visibility
- Custom field groups/tabs
- Field permissions (who can view/edit)
- Export/include in reports
- Migration-safe (survives updates)
- Field templates library

**Estimated Effort:** High (5-6 days)

---

### 📋 Implementation Priority Matrix - Admin

| Fitur | Impact | Effort | Priority | Timeline |
|-------|--------|--------|----------|----------|
| PMB Management | High | High | **HIGH** | Week 1-2 |
| Financial/Tuition | High | High | **HIGH** | Week 1-2 |
| Student Services | High | High | **HIGH** | Week 1-2 |
| Lecturer HR | Medium | Medium | **HIGH** | Week 1-2 |
| Alumni Management | Medium | Medium | **MEDIUM** | Week 3-4 |
| Extracurricular | Medium | Medium | **MEDIUM** | Week 3-4 |
| Library Integration | Low | Medium | **LOW** | Future |
| Hostel Management | Low | Medium | **LOW** | Future |
| Parking Management | Low | Low | **LOW** | Future |
| Cafeteria System | Low | Medium | **LOW** | Future |
| Lab Management | Low | Medium | **LOW** | Future |
| Internship/PKL | Medium | Medium | **LOW** | Future |
| Thesis Management | Medium | High | **LOW** | Future |
| Research Tracking | Low | Medium | **LOW** | Future |
| Partnership/MOU | Low | Low | **LOW** | Future |

---

### 🚀 Recommended Implementation Phases - Admin

#### **Phase 1: Core Business Operations (Week 1-2)**
- PMB (Admission) Management
- Financial/Tuition Management
- Student Services & Administration
- Lecturer HR & Administration

**Goal:** Complete core CRUD operations yang langsung impact mahasiswa, dosen, dan operasional kampus.

#### **Phase 2: Student Life & Engagement (Week 3-4)**
- Alumni Management
- Extracurricular & Organization Management
- Library Integration (optional)
- Hostel Management (if applicable)

**Goal:** Enhance student experience dan tracking holistic development.

#### **Phase 3: Specialized Modules (Month 2+)**
- Internship/PKL Management
- Thesis/Final Project Management
- Laboratory Management
- Research & Community Service
- Partnership/MOU Tracking
- Other low-priority features

**Goal:** Add specialized modules sesuai kebutuhan spesifik kampus.

---

### 💡 Quick Wins for Admin (Fast Implementation)

Jika butuh fitur yang **cepat diimplement** dengan **high impact**:

1. **Simple Letter Generator** - Generate surat keterangan aktif kuliah (1-2 days)
2. **Basic Tuition Invoice** - Manual invoice creation per student (2 days)
3. **PMB Application Form** - Simple registration form tanpa complex workflow (2-3 days)
4. **Alumni Survey** - Basic tracer study form (1-2 days)
5. **Organization Directory** - List student organizations dengan members (1 day)

---

### 📝 Technical Considerations - Admin

#### **Database Changes Required:**
- `pmb_applications`, `pmb_documents`, `pmb_exam_schedules`, `pmb_scores` (PMB)
- `tuition_fees`, `student_invoices`, `invoice_items`, `payments`, `scholarships` (Financial)
- `letter_requests`, `leave_applications`, `transfer_requests`, `graduation_applications` (Student Services)
- `lecturer_certifications`, `teaching_loads`, `lecturer_evaluations`, `lecturer_leaves` (Lecturer HR)
- `alumni_profiles`, `tracer_studies`, `job_postings` (Alumni)
- `student_organizations`, `events`, `student_achievements`, `extracurricular_points` (Extracurricular)
- `internship_placements`, `thesis_supervisions`, `research_projects` (Future modules)

#### **New Dependencies:**
- PhpSpreadsheet (Excel exports untuk financial reports)
- Dompdf/TCPDF (PDF generation untuk invoices, letters, certificates)
- Laravel Queues (background jobs untuk invoice generation, email notifications)
- Payment gateway SDK (Midtrans/Xendit untuk online payments - optional)
- Chart.js/ApexCharts (visualizations untuk dashboards)
- Intervention Image (image processing untuk document uploads)

#### **Performance Considerations:**
- Database indexing untuk frequent queries (student lookups, payment status)
- Eager loading untuk prevent N+1 queries
- Pagination untuk large datasets (PMB applications, alumni lists)
- Caching untuk static data (tuition fee structures, organization lists)
- Queue jobs untuk heavy operations (bulk invoice generation, email campaigns)
- File storage optimization untuk document uploads
- Consider separate file server atau cloud storage untuk production

#### **Security Considerations:**
- Role-based access control (RBAC) untuk semua modules
- CSRF protection pada semua forms
- Input validation & sanitization (especially untuk PMB forms)
- SQL injection prevention (use Eloquent/query builder)
- XSS protection (escape output in views)
- File upload validation (type, size, malware scanning)
- Encryption untuk sensitive data (payment info, personal documents)
- Audit logging untuk all admin actions
- GDPR/data privacy compliance untuk student data
- Secure payment processing (PCI DSS compliance if handling cards)

---

### 🎯 Success Metrics - Admin

Setelah implementasi fitur-fitur admin di atas, success metrics yang diharapkan:

1. **PMB Efficiency:** 80% reduction in application processing time (dari manual ke digital)
2. **Payment Collection:** 95% on-time tuition payment rate dengan automated reminders
3. **Student Satisfaction:** >4.5/5.0 rating untuk administrative services
4. **Data Accuracy:** Zero discrepancies dalam financial records & student data
5. **Processing Time:** <24 hours untuk process letter requests & leave applications
6. **Alumni Engagement:** >60% tracer study response rate per batch
7. **Administrative Load:** 70% reduction in manual paperwork untuk admin staff
8. **Transparency:** 100% trackable application/request status untuk students

---

### 🔄 Update History - Admin Section

- **2026-05-08:** 
  - Announcement System marked as ✅ COMPLETED (moved to has-been-implemented.md)
  - Implemented with multi-scope targeting, PHP Enums, Jodit Editor, auto-mark as read
  - 22 files changed (~3,317 lines added), 10 routes, 6 permissions synced
- **2026-05-05:** 
  - Admin section revised dengan fokus ke fitur CRUD bisnis (bukan internal admin tools)
  - Added 15 business-critical features: PMB, Financial, Student Services, Lecturer HR, Alumni, dll
  - Removed internal admin features (analytics, monitoring, backup) - focus on user-facing features
  - Updated priority matrix sesuai business impact
- Last updated by: AI Assistant

---

## Kebutuhan Mahasiswa (Student)

Dokumen ini merangkum fitur-fitur yang dibutuhkan untuk role **Student (Mahasiswa)** dalam sistem NexaCampus.

### ✅ Fitur yang Sudah Ada (Completed)

#### **1. Dashboard Mahasiswa**
   - Hero section dengan profile info (NIM, Prodi, Fakultas, Status Akademik, Semester)
   - Stats cards:
     - Mata kuliah semester ini
     - SKS semester ini
     - Nilai published
     - Entry transkrip
     - IPK Semester & Kumulatif
   - Attendance overview (rate, attended, absent count)
   - Jadwal kuliah terdekat (upcoming schedules)
   - Recent grades preview
   - Registration status indicator
   - Study plan status indicator

#### **2. Registrasi Akademik (Herregistrasi)**
   - Form registrasi per tahun akademik aktif
   - Pilih status akademik (Aktif/Cuti/Non-aktif)
   - Input semester berjalan
   - Save draft functionality
   - Submit registration untuk approval
   - Cancel submission (jika masih draft/submitted)
   - View current registration status (Draft/Submitted/Approved/Rejected)
   - View registration history (semua registrasi sebelumnya)
   - Timestamp tracking (submitted_at, approved_at)
   - Academic period validation (hanya bisa daftar saat periode buka)

#### **3. Rencana Studi (KRS - Kartu Rencana Studi)**
   - View available course offerings per semester
   - Filter by semester number
   - Retake course indicator
   - Add/remove courses dari KRS
   - Real-time SKS counter
   - Max SKS validation (default 24 SKS)
   - Prerequisite checking
   - Schedule conflict detection
   - Submit KRS untuk approval
   - View KRS status (Draft/Submitted/Approved/Rejected)
   - View submitted timestamp
   - Lock editing setelah approved
   - Period validation (hanya bisa edit saat periode KRS buka)

#### **4. Jadwal Kuliah**
   - Weekly schedule view (grouped by day)
   - Course schedule cards dengan:
     - Course code & name
     - Class label
     - Time (start - end)
     - Room/location
     - Lecturer name(s)
   - Total courses & credits summary
   - Weekly session count
   - Attendance session stats (opened, attended)
   - Week label indicator
   - Navigate to attendance page per course
   - Requires: Approved registration + Approved study plan

#### **5. Absensi Per Mata Kuliah**
   - View attendance sessions per course offering
   - Session list dengan:
     - Meeting number
     - Topic/materi
     - Date & time
     - Status (Opened/Closed/Draft)
   - Input attendance record (saat session opened):
     - Self-checkin dengan geolocation (optional)
     - Select attendance status (Present/Late)
     - Add notes (optional)
   - View attendance history per course
   - Attendance rate calculation
   - Status badges dengan color coding
   - Real-time session status updates

#### **6. Nilai (Grades)**
   - View all published grades
   - Grade detail cards dengan:
     - Course code & name
     - Academic year & semester
     - Final score (0-100)
     - Letter grade (A/B/C/D/E)
     - Grade point (4.0 scale)
     - Result status (Pass/Fail)
   - Grade components breakdown:
     - Component name (UTS, UAS, Tugas, dll)
     - Weight percentage
     - Score per component
     - Notes (optional)
     - Total weight validation (should be 100%)
   - Summary statistics:
     - Total courses with grades
     - Published courses count
     - Average grade point
   - Only shows Published grades (not Draft/Finalized)

#### **7. Transkrip Akademik**
   - Complete transcript view
   - Transcript entries grouped by course
   - Each entry shows:
     - Course code & name
     - Academic year & semester
     - Credits (SKS)
     - Final score
     - Letter grade
     - Grade point
     - Result status (Lulus/Tidak Lulus)
   - Study results per semester:
     - Academic year
     - Semester number
     - Total courses taken
     - Credits taken & passed
     - Semester GPA (IPS)
     - Cumulative GPA (IPK)
     - Status (Active/Probation/etc.)
   - Summary statistics:
     - Total courses completed
     - Total credits earned
     - Passed credits
     - Current cumulative GPA
   - Sorted by course code for easy reference

---

### ❌ Fitur yang Masih Dibutuhkan (To Be Implemented)

#### 🎯 PRIORITAS TINGGI (High Priority - Week 1-2)

##### 1. 🚧 IN PROGRESS - Course Materials Access / Akses Bahan Ajar 📚
**Deskripsi:** Mahasiswa bisa download/view materi perkuliahan yang diupload dosen

**Fitur Detail:**
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

**Kenapa Penting:**
- Akses materi kapan saja untuk belajar
- Support blended learning
- Tidak perlu minta materi ke teman/dosen
- Dokumentasi pembelajaran terpusat

**Estimated Effort:** Low-Medium (1-2 days)

**Technical Notes:**
- Reuse existing course_materials table (dari lecturer feature)
- Implement download tracking
- Add material categories & metadata
- Consider using PDF.js for in-browser viewing

---

##### 2. 🚧 IN PROGRESS - Announcements View / Lihat Pengumuman 📢
**Deskripsi:** Mahasiswa bisa lihat pengumuman dari dosen per mata kuliah

**Fitur Detail:**
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

**Kenapa Penting:**
- Tidak ketinggalan info penting dari dosen
- Centralized communication channel
- Track which announcements sudah dibaca

**Estimated Effort:** Low (1 day)

**Technical Notes:**
- Reuse existing announcements table
- Create announcement_reads pivot table untuk track read status
- Add notification integration

---

##### 3. Assignment Submission / Pengumpulan Tugas 📝
**Deskripsi:** Sistem pengumpulan tugas online untuk mahasiswa

**Fitur Detail:**
- View assignments per course
- Assignment detail:
  - Title & description
  - Due date & time
  - Max score/points
  - Allowed file types
  - Max file size
  - Submission instructions/rubric
  - Late submission policy
- Upload submission:
  - File upload (PDF, DOC, ZIP, etc.)
  - Text submission (untuk essay/code)
  - Multiple file support
  - Resubmit before deadline
- Submission status:
  - Submitted on time
  - Submitted late (with penalty indicator)
  - Not submitted
  - Graded (with score & feedback)
- View grade & feedback from lecturer
- Download graded assignment (with comments)
- Countdown timer untuk deadline
- Email reminder before deadline

**Kenapa Penting:**
- Paperless submission system
- Timestamped submissions (bukti waktu kumpul)
- Easy access to feedback
- No more "file corrupt" excuses

**Estimated Effort:** Medium-High (3-4 days)

**Technical Notes:**
- Need new tables: assignments, assignment_submissions, assignment_grades
- File storage dengan unique naming
- Implement deadline validation
- Auto-mark late submissions
- Queue email reminders

---

##### 4. Academic Progress Tracker / Tracking Kemajuan Akademik 📊
**Deskripsi:** Visual dashboard untuk tracking progress akademik mahasiswa

**Fitur Detail:**
- Credit completion tracker:
  - Total credits required (e.g., 144 SKS)
  - Credits completed
  - Credits remaining
  - Progress bar visualization
- Semester-by-semester progress:
  - IPS per semester chart
  - IPK trend line
  - Credits taken vs passed
- Course completion status:
  - Required courses (completed/pending)
  - Elective courses (completed/pending)
  - Failed courses (need retake)
- Graduation prediction:
  - Estimated graduation date
  - Remaining requirements
  - Courses to take next semester
- GPA calculator:
  - Target IPK simulator
  - "What-if" scenarios
- Visual charts & graphs:
  - Pie chart (course categories)
  - Line chart (GPA trend)
  - Bar chart (credits per semester)

**Kenapa Penting:**
- Mahasiswa tahu posisi mereka
- Planning untuk semester depan
- Early warning jika ada masalah
- Motivation untuk maintain IPK

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- Use Chart.js atau ApexCharts
- Calculate based on transcript data
- Cache calculations for performance
- Add export to PDF functionality

---

##### 5. COMPLETED - Grade Appeal / Pengajuan Keberatan Nilai
**Deskripsi:** Sistem untuk mengajukan keberatan nilai ke dosen

**Fitur Detail:**
- Submit grade appeal per course:
  - Select course & grade component
  - Reason for appeal (dropdown + text)
  - Supporting evidence (file upload)
  - Expected outcome
- Track appeal status:
  - Submitted
  - Under Review
  - Approved (grade updated)
  - Rejected (with explanation)
- View appeal history
- Timeline tracking:
  - Submitted at
  - Reviewed at
  - Resolved at
- Notification when status changes
- Deadline enforcement (e.g., max 7 days after grade published)
- Lecturer response visible to student

**Kenapa Penting:**
- Formal channel untuk dispute nilai
- Transparency dalam grading
- Accountability untuk dosen
- Documentation of appeals

**Implementation Status:** Core student/lecturer workflow completed with optional evidence attachments. Notifications, deadline enforcement, and escalation can be added later if policy requires it.

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- New table: grade_appeals
- Add deadline validation logic
- Notification system integration
- Audit trail for grade changes

---

#### 🎯 PRIORITAS SEDANG (Medium Priority - Week 3-4)

##### 6. ✅ COMPLETED - Digital Student ID / Kartu Mahasiswa Digital 🪪
**Deskripsi:** Kartu mahasiswa digital dalam aplikasi

**Fitur Detail:**
- Digital ID card display:
  - Photo
  - Name & NIM
  - Program & Faculty
  - Academic year
  - QR code (untuk verifikasi)
- QR code scanner integration:
  - Check-in events
  - Library access
  - Exam verification
- Card validity status
- Expiration date indicator
- Download as image/PDF
- Share functionality

**Kenapa Penting:**
- Tidak perlu bawa kartu fisik
- Selalu tersedia di HP
- Modern & convenient

**Estimated Effort:** Low (1 day)

**Technical Notes:**
- Generate QR code dengan library (SimpleSoftwareIO/simple-qrcode)
- Store photo in storage
- Implement image generation untuk download

---

##### 7. ✅ COMPLETED - Study Plan Comparison / Perbandingan KRS 📋
**Deskripsi:** Compare KRS antar semester untuk planning

**Fitur Detail:**
- Side-by-side comparison:
  - Semester A vs Semester B
  - Courses overlap detection
  - Credits comparison
- Historical KRS view:
  - All previous KRS
  - Changes tracking
  - Approval status history
- Course load analysis:
  - Credits per semester chart
  - Difficulty distribution
  - Balance checker
- Recommendations:
  - Suggested courses for next semester
  - Prerequisites reminder
  - Schedule optimization tips

**Kenapa Penting:**
- Better academic planning
- Avoid scheduling conflicts
- Optimize course load

**Estimated Effort:** Medium (2 days)

---

##### 8. Lecture Evaluation / Evaluasi Dosen ⭐
**Deskripsi:** Sistem evaluasi pengajaran dosen oleh mahasiswa

**Fitur Detail:**
- Evaluate lecturers per course:
  - Rating scales (1-5):
    - Teaching quality
    - Clarity of explanation
    - Responsiveness
    - Fairness in grading
    - Overall satisfaction
  - Open-ended feedback (anonymous)
  - Strengths & weaknesses
- Evaluation period enforcement:
  - Only during specific period
  - One evaluation per course
  - Cannot submit twice
- View evaluation status:
  - Not yet evaluated
  - Completed
  - Period closed
- Anonymous submissions
- Aggregate results visible after grading period ends

**Kenapa Penting:**
- Feedback untuk improvement
- Requirement untuk akreditasi
- Quality assurance

**Estimated Effort:** Medium (2-3 days)

**Technical Notes:**
- New table: lecturer_evaluations
- Anonymize responses
- Aggregate statistics untuk dosen
- Period validation logic

---

##### 9. Personalized Notifications / Notifikasi Personal 🔔
**Deskripsi:** Smart notifications berdasarkan activity mahasiswa

**Fitur Detail:**
- Notification types:
  - New grade published
  - Assignment deadline reminder (3 days, 1 day, 1 hour before)
  - Announcement posted
  - Attendance session opened
  - KRS approval status change
  - Payment due date
  - Academic calendar reminders
- Notification preferences:
  - Enable/disable per type
  - Delivery method (in-app, email, push)
  - Quiet hours setting
- Notification center:
  - Unread count badge
  - Mark as read
  - Clear all
  - Filter by type
- Actionable notifications:
  - Click to go to relevant page
  - Quick actions (e.g., "Submit Assignment")

**Kenapa Penting:**
- Stay updated tanpa cek manual
- Never miss deadlines
- Better engagement

**Estimated Effort:** Medium (2-3 days)

---

##### 10. Document Request Center / Permohonan Surat 📄
**Deskripsi:** Online request untuk surat-surat administrasi

**Fitur Detail:**
- Request letter types:
  - Surat Keterangan Aktif Kuliah
  - Surat Keterangan Lulus
  - Transkrip Sementara
  - Surat Izin Penelitian
  - Surat Rekomendasi
  - dll
- Request form:
  - Purpose/reason
  - Recipient (if applicable)
  - Additional notes
  - Attachment upload (if needed)
- Track request status:
  - Submitted
  - Processing
  - Ready for pickup
  - Delivered (email/download)
  - Rejected (with reason)
- Download approved letters (PDF)
- Request history
- Processing time estimate
- Fee payment integration (if applicable)

**Kenapa Penting:**
- No need to visit office
- Track request status
- Faster processing
- Digital delivery option

**Estimated Effort:** Medium-High (3-4 days)

**Technical Notes:**
- New table: letter_requests
- PDF generation untuk approved letters
- Integration dengan admin approval workflow
- Payment gateway (if fees apply)

---

#### 🎯 PRIORITAS RENDAH (Low Priority - Future)

##### 11. Peer Study Group / Kelompok Belajar 👥
**Deskripsi:** Platform untuk membentuk kelompok belajar

**Fitur Detail:**
- Create/join study groups per course
- Member management
- Schedule study sessions
- Shared resources
- Discussion forum
- Task assignment within group

**Estimated Effort:** High (4-5 days)

---

##### 12. Career Services Portal / Layanan Karir 💼
**Deskripsi:** Job board & career resources

**Fitur Detail:**
- Job/internship postings
- Company profiles
- Application tracking
- Resume builder
- Interview preparation resources
- Alumni mentorship connection

**Estimated Effort:** High (4-5 days)

---

##### 13. Campus Map & Navigation / Peta Kampus 🗺️
**Deskripsi:** Interactive campus map

**Fitur Detail:**
- Building locations
- Room finder
- Class schedule integration (show where next class is)
- Navigation assistance
- Facility locations (library, cafeteria, parking)

**Estimated Effort:** Medium (2-3 days)

---

##### 14. Wellness & Mental Health Resources / Kesehatan Mental 🧘
**Deskripsi:** Resources untuk wellbeing mahasiswa

**Fitur Detail:**
- Counseling appointment booking
- Mental health assessments
- Stress management resources
- Crisis hotline
- Wellness workshops/events
- Anonymous support chat

**Estimated Effort:** Medium (2-3 days)

---

##### 15. Gamification & Achievements / Gamifikasi 🏆
**Deskripsi:** Gamification elements untuk engagement

**Fitur Detail:**
- Achievement badges:
  - Perfect attendance
  - Dean's list
  - Early bird (submit assignments early)
  - Helper (help peers in forum)
- Points system
- Leaderboards (optional, anonymous)
- Milestone celebrations
- Progress rewards

**Estimated Effort:** Medium-High (3-4 days)

---

### 📋 Implementation Priority Matrix - Student

| Fitur | Impact | Effort | Priority | Timeline |
|-------|--------|--------|----------|----------|
| Course Materials Access | High | Low | **HIGH** | Week 1-2 |
| Announcements View | High | Low | **HIGH** | Week 1-2 |
| Assignment Submission | High | Medium-High | **HIGH** | Week 1-2 |
| Academic Progress Tracker | High | Medium | **HIGH** | Week 1-2 |
| Grade Appeal | Medium | Medium | **HIGH** | Week 1-2 |
| Digital Student ID | Medium | Low | **MEDIUM** | Week 3-4 |
| Study Plan Comparison | Medium | Medium | **MEDIUM** | Week 3-4 |
| Lecture Evaluation | Medium | Medium | **MEDIUM** | Week 3-4 |
| Personalized Notifications | High | Medium | **MEDIUM** | Week 3-4 |
| Document Request Center | High | Medium-High | **MEDIUM** | Week 3-4 |
| Peer Study Group | Low | High | **LOW** | Future |
| Career Services | Medium | High | **LOW** | Future |
| Campus Map | Low | Medium | **LOW** | Future |
| Wellness Resources | Medium | Medium | **LOW** | Future |
| Gamification | Low | Medium-High | **LOW** | Future |

---

### 🚀 Recommended Implementation Phases - Student

**Phase 1: Core Learning Experience (Week 1-2)**
- Course Materials Access
- Announcements View
- Assignment Submission
- Academic Progress Tracker
- Grade Appeal

**Goal:** Complete core learning features yang langsung impact pengalaman belajar mahasiswa.

**Phase 2: Enhanced Services & Engagement (Week 3-4)**
- Digital Student ID
- Study Plan Comparison
- Lecture Evaluation
- Personalized Notifications
- Document Request Center

**Goal:** Enhance student services dan engagement dengan kampus.

**Phase 3: Advanced Features (Month 2+)**
- Peer Study Groups
- Career Services
- Campus Map
- Wellness Resources
- Gamification

**Goal:** Add advanced features untuk comprehensive student experience.

---

### 💡 Quick Wins for Student (Fast Implementation)

Fitur yang bisa diimplement cepat (1-2 hari):
1. **Course Materials Viewer** - Simple file list & download (1 day)
2. **Announcements Feed** - Basic announcement list (1 day)
3. **Digital ID Card** - Display card with QR code (1 day)
4. **GPA Calculator Widget** - Simple calculator tool (1-2 days)
5. **Grade Export** - Export grades to PDF/Excel (1-2 days)

---

### 📝 Technical Considerations - Student

#### **Database Tables Baru:**
- `course_material_downloads` (tracking)
- `announcement_reads` (pivot table)
- `assignments`, `assignment_submissions`, `assignment_grades`
- `grade_appeals`
- `letter_requests`
- `lecturer_evaluations`
- `study_groups`, `study_group_members`
- `job_postings`, `job_applications`
- `notifications`, `notification_preferences`
- `achievements`, `user_achievements`

#### **Dependencies Baru:**
- Chart.js atau ApexCharts (progress tracking visualizations)
- PDF.js (in-browser PDF viewing)
- SimpleSoftwareIO/simple-qrcode (QR code generation)
- Dompdf/TCPDF (document generation)
- Laravel Queues (background jobs untuk notifications)
- Laravel Echo + Pusher (real-time notifications)

#### **Performance Considerations:**
- Cache transcript calculations
- Lazy load course materials
- Paginate large lists (grades, announcements)
- Queue email notifications
- Optimize file downloads dengan CDN
- Implement infinite scroll untuk feeds

#### **Security Considerations:**
- Validate file uploads (type, size, malware scan)
- Enforce access control (only enrolled students can access materials)
- Anonymize evaluation responses
- Secure grade appeal process
- Protect personal data (GDPR compliance)
- Rate limiting untuk API endpoints

---

### 🎯 Success Metrics - Student

Setelah implementasi fitur-fitur di atas, success metrics yang diharapkan:

1. **Engagement:** >80% active students accessing course materials weekly
2. **Assignment Submission:** 95% on-time submission rate
3. **Communication:** >90% announcement read rate within 24 hours
4. **Satisfaction:** Student satisfaction score >4.5/5.0 untuk administrative services
5. **Progress Tracking:** 70% students regularly check academic progress dashboard
6. **Efficiency:** 60% reduction in administrative requests (surat, dll) via digital system
7. **Retention:** Improved retention rate melalui early warning system
8. **Graduation Rate:** Increased on-time graduation rate dengan better planning tools

---

### 🔄 Update History - Student Section

- **2026-05-08:** 
  - Announcement System marked as ✅ COMPLETED (moved to has-been-implemented.md)
  - Student view: targeted announcements, search/filter, custom pagination, unread badges
- **2026-05-05:** 
  - Initial student section created dengan comprehensive feature requirements
  - Added 7 completed features: Dashboard, Registration, Study Plan, Schedule, Attendance, Grades, Transcript
  - Added 15 needed features categorized by priority
  - Focus on user-facing CRUD yang impact langsung ke mahasiswa
  - Created implementation priority matrix & recommended phases
- Last updated by: AI Assistant

---

**Catatan:** Dokumen ini bersifat living document dan akan diupdate seiring perkembangan project.

---

### 📝 Technical Considerations

#### **Database Changes Required:**
- `course_materials` table
- `announcements` table
- `announcement_reads` pivot table
- `assignments` table
- `assignment_submissions` table
- `assignment_grades` table
- `consultation_slots` table
- `consultation_appointments` table
- `rubrics` table
- `meeting_notes` table (for PA)

#### **New Dependencies:**
- PhpSpreadsheet (Excel export)
- Dompdf or TCPDF (PDF generation)
- FullCalendar.js (calendar view)
- Chart.js or ApexCharts (analytics)
- Trix or Quill (rich text editor)

#### **Storage Requirements:**
- File uploads: ~50MB per user/month estimate
- Consider AWS S3 or similar for production
- Implement file cleanup for deleted courses

#### **Performance Considerations:**
- Cache grade calculations
- Lazy load calendar events
- Paginate large datasets
- Queue heavy operations (exports, analytics)
- Optimize database queries with eager loading

---

### 🎯 Success Metrics

Setelah implementasi fitur-fitur di atas, success metrics yang diharapkan:

1. **User Adoption:** >80% active lecturers using new features within 1 month
2. **Efficiency:** 50% reduction in time spent on administrative tasks
3. **Communication:** >90% announcement read rate by students
4. **Satisfaction:** Lecturer satisfaction score >4.5/5.0
5. **Engagement:** Increased student engagement with course materials (>70% download rate)

---

### 🔄 Update History

- **2026-05-08 (Revision):** 
  - Announcement System marked as ✅ COMPLETED across all roles (Lecturer, Student, Admin)
  - Moved detailed implementation notes to has-been-implemented.md
  - 22 files implemented (~3,317 lines), multi-scope targeting with PHP Enums
- **2026-05-05 (Revision):** 
  - Initial document created dengan comprehensive lecturer feature requirements
  - Admin section revised dengan fokus ke **fitur CRUD bisnis** (bukan internal admin tools)
  - Added complete Student section dengan 7 completed features & 15 needed features
  - Added 15 business-critical admin features: PMB, Financial, Student Services, Lecturer HR, Alumni, Extracurricular, dll
  - Removed internal admin features (analytics dashboard, monitoring, bulk ops) - focus on user-facing CRUD
  - Documented existing features untuk all three roles: Lecturer, Admin, & Student
  - Created implementation priority matrices & recommended phases for all roles
  - **Marked 7 features as 🚧 IN PROGRESS** (moved to has-been-implemented.md for active tracking):
    - Lecturer: Course Materials, Announcements, Grade Book Export
    - Admin: PMB Management, Financial/Tuition Management
    - Student: Course Materials Access, Announcements View
- Last updated by: AI Assistant

---

**Catatan:** Dokumen ini bersifat living document dan akan diupdate seiring perkembangan project.
