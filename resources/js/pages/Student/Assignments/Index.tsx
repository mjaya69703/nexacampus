// Tugas kuliah mahasiswa (Inertia React).
// Hanya tugas dari kelas yang aktif di KRS yang ditampilkan.
import { Head, router } from '@inertiajs/react';
import {
    ArrowRight, CalendarClock, CheckCircle2, ClipboardCheck, ClipboardList,
    Clock, FileCheck2, Inbox, RotateCcw, Search, SlidersHorizontal,
    TriangleAlert,
} from 'lucide-react';
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
    semester: number | null;
};

type AssignmentRow = {
    id: number;
    title: string;
    courseCode: string;
    courseName: string;
    classLabel: string | null;
    academicYear: string;
    dueAt: string | null;
    dueHuman: string | null;
    isPastDue: boolean;
    status: string;
    statusLabel: string;
    statusTone: string;
    canSubmit: boolean;
    score: number | null;
    maxScore: number;
    submittedAt: string | null;
    filesCount: number;
};

type Stats = {
    total: number;
    open: number;
    submitted: number;
    graded: number;
    returned: number;
    late: number;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    student: Student | null;
    filters: { status: string; search: string };
    stats: Stats;
    assignments: AssignmentRow[];
    urls: { index: string; show: string };
};

const FILTERS: Array<{ key: string; label: string }> = [
    { key: 'all', label: 'Semua' },
    { key: 'open', label: 'Belum Submit' },
    { key: 'submitted', label: 'Sudah Submit' },
    { key: 'graded', label: 'Dinilai' },
    { key: 'returned', label: 'Revisi' },
    { key: 'late', label: 'Telat/Missing' },
];

const withId = (url: string, id: number): string => url.replace('__ID__', String(id));

const toneClass = (tone: string): string => {
    switch (tone) {
        case 'green':
            return 'green';
        case 'red':
            return 'red';
        case 'amber':
            return 'amber';
        case 'brand':
            return '';
        default:
            return 'gray';
    }
};

export default function AssignmentsIndex({
    shell, hasProfile, student, filters, stats, assignments, urls,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (next: { status?: string; search?: string }): void => {
        const status = next.status ?? filters.status;
        const keyword = next.search ?? search;
        const params: Record<string, string> = {};

        if (status !== 'all') params.status = status;
        if (keyword.trim() !== '') params.search = keyword.trim();

        router.get(urls.index, params, { preserveScroll: true, preserveState: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Tugas Kuliah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardCheck}
                        eyebrow="Academic"
                        title="Tugas Kuliah"
                        description={student
                            ? `Pantau deadline, kirim submission, dan baca feedback dosen — ${student.program}.`
                            : 'Pantau deadline dan kirim submission tugas kuliah.'}
                        badges={student ? [
                            `NIM ${student.nim ?? '-'}`,
                            `Semester ${student.semester ?? '-'}`,
                            `${stats.total} tugas`,
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
                                    <span className="db-stat-icon"><ClipboardList size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.total}</span>
                                        <span className="db-stat-label">Total tugas</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon gold"><Clock size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.open}</span>
                                        <span className="db-stat-label">Belum submit</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon green"><FileCheck2 size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.graded}</span>
                                        <span className="db-stat-label">Dinilai</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon red"><CalendarClock size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.late}</span>
                                        <span className="db-stat-label">Telat / missing</span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><SlidersHorizontal size={16} /> Filter &amp; Pencarian</h2>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                        {FILTERS.map((item) => (
                                            <button
                                                key={item.key}
                                                type="button"
                                                className={`db-btn ${filters.status === item.key ? 'primary' : 'ghost'}`}
                                                onClick={() => apply({ status: item.key })}
                                            >
                                                {item.label}
                                                {item.key === 'all' && <span className="db-badge gray">{stats.total}</span>}
                                                {item.key === 'open' && <span className="db-badge gray">{stats.open}</span>}
                                                {item.key === 'graded' && <span className="db-badge gray">{stats.graded}</span>}
                                                {item.key === 'returned' && <span className="db-badge gray">{stats.returned}</span>}
                                                {item.key === 'late' && <span className="db-badge gray">{stats.late}</span>}
                                            </button>
                                        ))}
                                    </div>

                                    <div style={{ display: 'flex', gap: 8, marginTop: 14, flexWrap: 'wrap' }}>
                                        <span style={{ position: 'relative', display: 'block', flex: '1 1 260px' }}>
                                            <Search size={15} style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: 'var(--db-muted)' }} />
                                            <input
                                                type="text"
                                                className="db-input"
                                                style={{ paddingLeft: 34 }}
                                                placeholder="Cari judul tugas atau mata kuliah…"
                                                value={search}
                                                onChange={(event) => setSearch(event.target.value)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') apply({ search });
                                                }}
                                            />
                                        </span>
                                        <button type="button" className="db-btn primary" onClick={() => apply({ search })}>
                                            <Search size={15} /> Terapkan
                                        </button>
                                        {filters.search !== '' && (
                                            <button
                                                type="button"
                                                className="db-btn ghost"
                                                onClick={() => {
                                                    setSearch('');
                                                    apply({ search: '' });
                                                }}
                                            >
                                                Reset
                                            </button>
                                        )}
                                    </div>

                                    {filters.search !== '' && (
                                        <div className="db-hint" style={{ marginTop: 12 }}>
                                            Menampilkan hasil untuk “<b>{filters.search}</b>” · {assignments.length} tugas ditemukan.
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ClipboardList size={16} /> Daftar Tugas</h2>
                                    <span className="db-badge gray">{assignments.length} tugas</span>
                                </div>
                                <div className="db-card-body">
                                    {assignments.length === 0 ? (
                                        <EmptyState
                                            icon={Inbox}
                                            title="Belum ada tugas yang tersedia"
                                            description={filters.status !== 'all' || filters.search !== ''
                                                ? 'Tidak ada tugas yang cocok dengan filter saat ini.'
                                                : 'Tugas akan muncul setelah dosen mempublikasikannya untuk kelas yang kamu ambil.'}
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 12 }}>
                                            {assignments.map((assignment) => (
                                                <article key={assignment.id} className="db-card" style={{ margin: 0 }}>
                                                    <div className="db-card-body" style={{ display: 'flex', gap: 14, flexWrap: 'wrap', alignItems: 'center' }}>
                                                        <span className="db-stat-icon" style={{ width: 44, height: 44, flexShrink: 0 }}>
                                                            <ClipboardList size={20} />
                                                        </span>

                                                        <div style={{ flex: '1 1 280px', minWidth: 0 }}>
                                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                                                <h3 style={{ margin: 0, fontSize: 15, fontWeight: 700, color: 'var(--db-heading)' }}>{assignment.title}</h3>
                                                                <span className={`db-badge ${toneClass(assignment.statusTone)}`}>{assignment.statusLabel}</span>
                                                                {assignment.isPastDue && (
                                                                    <span className="db-badge red">Deadline lewat</span>
                                                                )}
                                                                {assignment.isPastDue && assignment.status === 'not_submitted' && assignment.canSubmit && (
                                                                    <span className="db-badge amber">Masih bisa dikumpulkan</span>
                                                                )}
                                                            </div>

                                                            <div className="db-hint" style={{ marginTop: 4 }}>
                                                                {assignment.courseCode} — {assignment.courseName}
                                                                {assignment.classLabel ? ` · Kelas ${assignment.classLabel}` : ''}
                                                                {` · ${assignment.academicYear}`}
                                                            </div>

                                                            <div className="db-hint" style={{ marginTop: 4 }}>
                                                                <Clock size={12} /> {assignment.dueAt ?? 'Tanpa deadline'}
                                                                {assignment.dueHuman ? ` (${assignment.dueHuman})` : ''}
                                                                {assignment.filesCount > 0 ? ` · ${assignment.filesCount} berkas terunggah` : ''}
                                                            </div>
                                                        </div>

                                                        <div style={{ minWidth: 150 }}>
                                                            {assignment.status === 'graded' && assignment.score !== null ? (
                                                                <>
                                                                    <div className="db-hint">Nilai</div>
                                                                    <div style={{ fontWeight: 700, color: 'var(--db-brand)', fontSize: 16 }}>
                                                                        {assignment.score.toFixed(2)} / {assignment.maxScore.toFixed(2)}
                                                                    </div>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <div className="db-hint">Submission</div>
                                                                    <div style={{ fontWeight: 600, color: 'var(--db-heading)', fontSize: 13 }}>
                                                                        {assignment.submittedAt ?? 'Belum submit'}
                                                                    </div>
                                                                </>
                                                            )}
                                                        </div>

                                                        <a className="db-btn primary" href={withId(urls.show, assignment.id)}>
                                                            Buka <ArrowRight size={15} />
                                                        </a>
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}

                    {hasProfile && stats.returned > 0 && (
                        <div className="db-note warning">
                            <RotateCcw size={15} />
                            <span>
                                <b>{stats.returned} tugas perlu revisi.</b> Baca catatan dosen lalu kirim ulang jawaban final sebelum deadline.
                            </span>
                        </div>
                    )}

                    {hasProfile && stats.submitted > 0 && (
                        <div className="db-note success">
                            <CheckCircle2 size={15} />
                            <span><b>{stats.submitted} tugas sudah dikumpulkan.</b> Pantau feedback dosen di halaman detail tugas.</span>
                        </div>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
