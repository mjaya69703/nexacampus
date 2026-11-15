// Materi perkuliahan mahasiswa (Inertia React).
// Daftar materi dari kelas yang benar-benar diambil pada KRS aktif.
import { Head, router } from '@inertiajs/react';
import {
    BookMarked, Bookmark, BookmarkCheck, BookOpen, Download, FileArchive,
    FileImage, FileText, FileVideo, FolderOpen, Link2, Search, SlidersHorizontal,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Student = {
    name: string;
    nim: string | null;
    program: string;
    faculty: string;
    semester: number | null;
};

type CourseOption = {
    id: number;
    courseCode: string;
    courseName: string;
    label: string | null;
    academicYear: string | null;
};

type FileRow = {
    id: number | null;
    fileName: string;
    fileType: string | null;
    sizeLabel: string;
    downloadUrl: string | null;
};

type Material = {
    id: number;
    offeringId: number;
    title: string;
    description: string;
    category: string | null;
    categoryLabel: string;
    meetingNumber: number | null;
    uploadedAt: string;
    courseCode: string;
    courseName: string;
    classLabel: string | null;
    fileCount: number;
    primaryFileType: string | null;
    bookmarked: boolean;
    liked: boolean;
    likesCount: number;
    downloadCount: number;
    files: FileRow[];
    legacyFile: FileRow | null;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    student: Student | null;
    courses: CourseOption[];
    filters: {
        offeringId: number | null;
        search: string;
        bookmarkedOnly: boolean;
        lockedOffering: boolean;
    };
    stats: { total: number; bookmarked: number; courses: number; withFiles: number };
    materials: Material[];
    urls: {
        index: string;
        offering: string;
        download: string;
        show: string;
        bookmark: string;
        like: string;
        commentStore: string;
        commentUpdate: string;
        commentDelete: string;
        commentLike: string;
    };
};

const withId = (url: string, id: number): string => url.replace('__ID__', String(id));

const fileIcon = (type: string | null): LucideIcon => {
    switch ((type ?? '').toLowerCase()) {
        case 'pdf':
            return FileText;
        case 'doc':
        case 'docx':
            return FileText;
        case 'ppt':
        case 'pptx':
            return FileText;
        case 'xls':
        case 'xlsx':
        case 'csv':
            return FileArchive;
        case 'mp4':
        case 'mov':
        case 'avi':
            return FileVideo;
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return FileImage;
        case 'link':
            return Link2;
        default:
            return FolderOpen;
    }
};

export default function MaterialsIndex({
    shell, hasProfile, student, courses, filters, stats, materials, urls,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (next: { offering?: number | null; search?: string; bookmarked?: boolean }): void => {
        const offering = next.offering !== undefined ? next.offering : filters.offeringId;
        const keyword = next.search !== undefined ? next.search : search;
        const bookmarked = next.bookmarked !== undefined ? next.bookmarked : filters.bookmarkedOnly;

        const params: Record<string, string | number> = {};

        if (offering) params.offering = offering;
        if (keyword.trim() !== '') params.search = keyword.trim();
        if (bookmarked) params.bookmarked = 1;

        router.get(urls.index, params, { preserveScroll: true, preserveState: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Materi Perkuliahan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Learning"
                        title="Materi Perkuliahan"
                        description={student
                            ? `Materi dari kelas yang kamu ambil — ${student.program}.`
                            : 'Akses materi dari kelas yang kamu enroll.'}
                        badges={student ? [
                            `NIM ${student.nim ?? '-'}`,
                            `Semester ${student.semester ?? '-'}`,
                            `${stats.total} materi`,
                        ] : []}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Hubungi admin akademik untuk sinkronisasi data mahasiswa.</span>
                        </div>
                    )}

                    {hasProfile && (
                        <>
                            <section className="db-stats">
                                <div className="db-stat">
                                    <span className="db-stat-icon"><FolderOpen size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.total}</span>
                                        <span className="db-stat-label">Total materi</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon gold"><BookMarked size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.bookmarked}</span>
                                        <span className="db-stat-label">Bookmarked</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon green"><BookOpen size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.courses}</span>
                                        <span className="db-stat-label">Kelas</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon red"><FileText size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.withFiles}</span>
                                        <span className="db-stat-label">Dengan lampiran</span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><SlidersHorizontal size={16} /> Filter &amp; Pencarian</h2>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))', gap: 14 }}>
                                        <label style={{ display: 'grid', gap: 6 }}>
                                            <span className="db-hint" style={{ fontWeight: 700 }}>Filter kelas</span>
                                            <select
                                                className="db-input"
                                                value={filters.offeringId ?? ''}
                                                disabled={filters.lockedOffering}
                                                onChange={(event) => applyFilters({ offering: event.target.value ? Number(event.target.value) : null })}
                                            >
                                                <option value="">Semua kelas</option>
                                                {courses.map((course) => (
                                                    <option key={course.id} value={course.id}>
                                                        {course.courseCode} — {course.courseName}{course.label ? ` (${course.label})` : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>

                                        <label style={{ display: 'grid', gap: 6 }}>
                                            <span className="db-hint" style={{ fontWeight: 700 }}>Cari materi</span>
                                            <span style={{ position: 'relative', display: 'block' }}>
                                                <Search size={15} style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: 'var(--db-muted)' }} />
                                                <input
                                                    type="text"
                                                    className="db-input"
                                                    style={{ paddingLeft: 34 }}
                                                    placeholder="Judul atau deskripsi materi…"
                                                    value={search}
                                                    onChange={(event) => setSearch(event.target.value)}
                                                    onKeyDown={(event) => {
                                                        if (event.key === 'Enter') applyFilters({ search });
                                                    }}
                                                />
                                            </span>
                                        </label>

                                        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8, flexWrap: 'wrap' }}>
                                            <button
                                                type="button"
                                                className="db-btn primary"
                                                onClick={() => applyFilters({ search })}
                                            >
                                                <Search size={15} /> Terapkan
                                            </button>
                                            <button
                                                type="button"
                                                className={`db-btn ${filters.bookmarkedOnly ? 'primary' : 'ghost'}`}
                                                onClick={() => applyFilters({ bookmarked: !filters.bookmarkedOnly })}
                                            >
                                                {filters.bookmarkedOnly ? <BookmarkCheck size={15} /> : <Bookmark size={15} />}
                                                {filters.bookmarkedOnly ? 'Semua materi' : 'Hanya bookmark'}
                                            </button>
                                        </div>
                                    </div>

                                    {filters.search !== '' && (
                                        <div className="db-hint" style={{ marginTop: 12 }}>
                                            Menampilkan hasil untuk “<b>{filters.search}</b>” · {materials.length} materi ditemukan.
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><BookOpen size={16} /> Daftar Materi</h2>
                                    <span className="db-badge gray">{materials.length} materi</span>
                                </div>
                                <div className="db-card-body">
                                    {materials.length === 0 ? (
                                        <EmptyState
                                            icon={FolderOpen}
                                            title={filters.bookmarkedOnly ? 'Belum ada materi yang di-bookmark' : 'Belum ada materi tersedia'}
                                            description={filters.bookmarkedOnly
                                                ? 'Klik tombol Bookmark pada materi untuk menambahkannya ke daftar ini.'
                                                : 'Materi akan muncul setelah dosen mengunggah dan mempublikasikannya.'}
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 14 }}>
                                            {materials.map((material) => {
                                                const Icon = fileIcon(material.primaryFileType);

                                                return (
                                                    <article key={material.id} className="db-card" style={{ margin: 0 }}>
                                                        <div className="db-card-body" style={{ display: 'flex', gap: 14, flexWrap: 'wrap', alignItems: 'flex-start' }}>
                                                            <span className="db-stat-icon" style={{ width: 48, height: 48, flexShrink: 0 }}>
                                                                <Icon size={22} />
                                                            </span>

                                                            <div style={{ flex: '1 1 280px', minWidth: 0 }}>
                                                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                                                    <h3 style={{ margin: 0, fontSize: 15, fontWeight: 700, color: 'var(--db-heading)' }}>{material.title}</h3>
                                                                    {material.bookmarked && (
                                                                        <span className="db-badge gold"><Bookmark size={11} /> Bookmark</span>
                                                                    )}
                                                                    {material.meetingNumber && (
                                                                        <span className="db-badge gray">Pertemuan {material.meetingNumber}</span>
                                                                    )}
                                                                </div>

                                                                <div className="db-hint" style={{ marginTop: 4 }}>
                                                                    {material.courseCode} — {material.courseName}
                                                                    {material.classLabel ? ` · Kelas ${material.classLabel}` : ''}
                                                                    {` · ${material.categoryLabel}`}
                                                                </div>

                                                                {material.description && (
                                                                    <p style={{ margin: '8px 0 0', fontSize: 13, color: 'var(--db-muted)' }}>{material.description}</p>
                                                                )}

                                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 10 }}>
                                                                    {material.files.map((file) => (
                                                                        <a
                                                                            key={file.id ?? file.fileName}
                                                                            className="db-btn sm ghost"
                                                                            href={file.downloadUrl ?? '#'}
                                                                            style={{ pointerEvents: file.downloadUrl ? 'auto' : 'none' }}
                                                                        >
                                                                            <Download size={13} /> {file.fileName}
                                                                            <span className="db-hint">({file.sizeLabel})</span>
                                                                        </a>
                                                                    ))}
                                                                    {material.legacyFile && (
                                                                        <a className="db-btn sm ghost" href={material.legacyFile.downloadUrl ?? '#'}>
                                                                            <Download size={13} /> {material.legacyFile.fileName}
                                                                            <span className="db-hint">({material.legacyFile.sizeLabel})</span>
                                                                        </a>
                                                                    )}
                                                                    {material.fileCount === 0 && (
                                                                        <span className="db-hint">Tanpa lampiran</span>
                                                                    )}
                                                                </div>

                                                                <div className="db-hint" style={{ marginTop: 8 }}>
                                                                    Diunggah {material.uploadedAt} · {material.downloadCount}x diunduh
                                                                </div>
                                                            </div>

                                                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 140 }}>
                                                                <a className="db-btn primary" href={withId(urls.show, material.id)}>
                                                                    <BookOpen size={15} /> Buka materi
                                                                </a>
                                                                <button
                                                                    type="button"
                                                                    className={`db-btn ${material.bookmarked ? 'ghost' : 'light'}`}
                                                                    onClick={() => router.post(withId(urls.bookmark, material.id), {}, { preserveScroll: true })}
                                                                >
                                                                    {material.bookmarked ? <BookmarkCheck size={15} /> : <Bookmark size={15} />}
                                                                    {material.bookmarked ? 'Hapus bookmark' : 'Bookmark'}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </article>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
