// Perbandingan KRS antar semester — melihat mata kuliah yang sama,
// ditambahkan, dan dihapus antara dua rencana studi (Inertia React).
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeftRight, BookOpenCheck, CalendarDays, GraduationCap, Info,
    ListChecks, MinusCircle, PlusCircle, TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
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

type PlanOption = { id: number; label: string; status: string };

type Course = {
    courseId: number | null;
    code: string;
    name: string;
    credits: number;
    semester: number | string;
    status: string;
    isRequired: boolean;
    isRepeat: boolean;
    label: string;
};

type Summary = {
    id: number | null;
    title: string;
    semester: number | string;
    status: string;
    submittedAt: string;
    approvedAt: string;
    totalCourses: number;
    totalCredits: number;
    requiredCount: number;
    repeatCount: number;
    courses: Course[];
};

type Comparison = {
    sameCount: number;
    addedCount: number;
    removedCount: number;
    creditDelta: number;
    same: Course[];
    added: Course[];
    removed: Course[];
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    student: Student | null;
    plans: PlanOption[];
    left: Summary | null;
    right: Summary | null;
    comparison: Comparison | null;
    urls: { index: string; comparison: string; registration: string; courses: string; submit: string; revise: string };
};

const statusTone = (status: string): string => {
    if (status === 'Approved' || status === 'Taken') return 'green';
    if (status === 'Submitted' || status === 'Draft') return 'amber';
    if (status === 'Rejected' || status === 'Cancelled' || status === 'Dropped') return 'red';
    return 'gray';
};

const statusLabel = (status: string): string => {
    const map: Record<string, string> = {
        Approved: 'Disetujui',
        Submitted: 'Diajukan',
        Draft: 'Draf',
        Rejected: 'Ditolak',
        Cancelled: 'Dibatalkan',
        Taken: 'Diambil',
        Dropped: 'Dibatalkan',
    };
    return map[status] ?? status;
};

function PlanSummaryCard({ title, plan }: { title: string; plan: Summary }) {
    return (
        <section className="db-card">
            <div className="db-card-head">
                <h2 className="db-card-title"><CalendarDays size={16} /> {title}</h2>
                <span className={`db-badge ${statusTone(plan.status)}`}>{statusLabel(plan.status)}</span>
            </div>
            <div className="db-card-body" style={{ display: 'grid', gap: 6 }}>
                <b style={{ color: 'var(--db-heading)' }}>{plan.title}</b>
                <span className="db-hint">Semester {plan.semester}</span>
                <div className="db-summary" style={{ margin: 0, padding: 0 }}>
                    <div><b>{plan.totalCourses}</b><small>Mata kuliah</small></div>
                    <div><b>{plan.totalCredits}</b><small>SKS</small></div>
                    <div><b>{plan.requiredCount}</b><small>Wajib</small></div>
                    <div><b>{plan.repeatCount}</b><small>Retake</small></div>
                </div>
                <span className="db-hint">Diajukan: {plan.submittedAt || '-'}</span>
                <span className="db-hint">Disetujui: {plan.approvedAt || '-'}</span>
            </div>
        </section>
    );
}

function CourseTable({
    title, icon, courses, badge, emptyText,
}: {
    title: string;
    icon: ReactNode;
    courses: Course[];
    badge: { label: string; tone: string };
    emptyText: string;
}) {
    return (
        <section className="db-card">
            <div className="db-card-head">
                <h2 className="db-card-title">{icon} {title}</h2>
                <span className={`db-badge ${badge.tone}`}>{badge.label}</span>
            </div>
            <div className="db-card-body">
                {courses.length === 0 ? (
                    <div className="db-empty-calm">
                        <Info size={22} />
                        <div><b>{emptyText}</b><small>Tidak ada mata kuliah pada kategori ini.</small></div>
                    </div>
                ) : (
                    <div className="db-table-wrap">
                        <table className="db-table">
                            <thead>
                                <tr>
                                    <th>Mata Kuliah</th>
                                    <th>SKS</th>
                                    <th>Semester</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {courses.map((course) => (
                                    <tr key={`${title}-${course.courseId ?? course.code}`}>
                                        <td>
                                            <div style={{ fontWeight: 600 }}>{course.name}</div>
                                            <div className="db-hint">
                                                {course.code} · {course.label}
                                                {course.isRequired ? ' · Wajib' : ' · Pilihan'}
                                                {course.isRepeat ? ' · Retake' : ''}
                                            </div>
                                        </td>
                                        <td>{course.credits}</td>
                                        <td>{course.semester}</td>
                                        <td><span className={`db-badge ${statusTone(course.status)}`}>{statusLabel(course.status)}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
}

export default function StudyPlanComparison({
    shell, hasProfile, student, plans, left, right, comparison, urls,
}: Props) {
    const changePlan = (side: 'left' | 'right', value: string): void => {
        const id = Number(value);
        const params: Record<string, number> = {};
        const nextLeft = side === 'left' ? id : left?.id ?? 0;
        const nextRight = side === 'right' ? id : right?.id ?? 0;

        if (nextLeft) params.left = nextLeft;
        if (nextRight) params.right = nextRight;

        router.get(urls.comparison, params, { preserveScroll: true, preserveState: true });
    };

    const creditDelta = comparison?.creditDelta ?? 0;
    const deltaLabel = creditDelta > 0 ? `+${creditDelta}` : `${creditDelta}`;

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbandingan KRS · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ArrowLeftRight}
                        eyebrow="Perbandingan KRS"
                        title={student?.name ?? 'Perbandingan KRS'}
                        description={student ? `${student.program} · ${student.faculty}` : 'Bandingkan rencana studi antar semester.'}
                        badges={student ? [
                            `NIM ${student.nim ?? '-'}`,
                            `Semester ${student.semester ?? '-'}`,
                            `${plans.length} rencana studi`,
                        ] : []}
                        actions={(
                            <a className="db-btn light" href={urls.index}>
                                <ListChecks size={15} /> Kembali ke KRS
                            </a>
                        )}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Hubungi admin akademik untuk sinkronisasi data mahasiswa.</span>
                        </div>
                    )}

                    {hasProfile && plans.length === 0 && (
                        <div className="db-note warning">
                            <Info size={15} />
                            <span><b>Belum ada rencana studi.</b> Susun KRS terlebih dahulu pada menu Kartu Rencana Studi.</span>
                        </div>
                    )}

                    {hasProfile && plans.length > 0 && comparison && left && right && (
                        <>
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ArrowLeftRight size={16} /> Pilih Rencana Studi</h2>
                                    <span className="db-badge gray">Kiri = sebelumnya · Kanan = pembanding</span>
                                </div>
                                <div className="db-card-body">
                                    <div className="db-grid-2">
                                        <label style={{ display: 'grid', gap: 6 }}>
                                            <span className="db-hint" style={{ fontWeight: 700 }}>Rencana Studi Kiri</span>
                                            <select
                                                className="db-input"
                                                value={left.id ?? ''}
                                                onChange={(event) => changePlan('left', event.target.value)}
                                            >
                                                {left.id === null && <option value="">-</option>}
                                                {plans.map((plan) => (
                                                    <option key={`left-${plan.id}`} value={plan.id}>{plan.label} ({statusLabel(plan.status)})</option>
                                                ))}
                                            </select>
                                        </label>

                                        <label style={{ display: 'grid', gap: 6 }}>
                                            <span className="db-hint" style={{ fontWeight: 700 }}>Rencana Studi Kanan</span>
                                            <select
                                                className="db-input"
                                                value={right.id ?? ''}
                                                onChange={(event) => changePlan('right', event.target.value)}
                                            >
                                                {right.id === null && <option value="">-</option>}
                                                {plans.map((plan) => (
                                                    <option key={`right-${plan.id}`} value={plan.id}>{plan.label} ({statusLabel(plan.status)})</option>
                                                ))}
                                            </select>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <section className="db-stats">
                                <div className="db-stat">
                                    <span className="db-stat-icon green"><BookOpenCheck size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{comparison.sameCount}</span>
                                        <span className="db-stat-label">Mata kuliah sama</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon gold"><PlusCircle size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{comparison.addedCount}</span>
                                        <span className="db-stat-label">Ditambahkan</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon red"><MinusCircle size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{comparison.removedCount}</span>
                                        <span className="db-stat-label">Dihapus</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon"><GraduationCap size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{deltaLabel}</span>
                                        <span className="db-stat-label">Selisih SKS</span>
                                    </div>
                                </div>
                            </section>

                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18, alignItems: 'start' }}>
                                <PlanSummaryCard title="Rencana Kiri" plan={left} />
                                <PlanSummaryCard title="Rencana Kanan" plan={right} />
                            </div>

                            <div className="db-note info">
                                <Info size={15} />
                                <span>
                                    Perbandingan dihitung dari mata kuliah aktif (Draft/Taken). Selisih SKS{' '}
                                    <b>{deltaLabel}</b> dihitung dari rencana kanan dikurangi rencana kiri.
                                </span>
                            </div>

                            <CourseTable
                                title="Mata Kuliah Sama"
                                icon={<BookOpenCheck size={16} />}
                                courses={comparison.same}
                                badge={{ label: `${comparison.sameCount} mata kuliah`, tone: 'green' }}
                                emptyText="Tidak ada mata kuliah yang sama."
                            />

                            <CourseTable
                                title="Mata Kuliah Ditambahkan"
                                icon={<PlusCircle size={16} />}
                                courses={comparison.added}
                                badge={{ label: `${comparison.addedCount} mata kuliah`, tone: 'amber' }}
                                emptyText="Tidak ada mata kuliah baru."
                            />

                            <CourseTable
                                title="Mata Kuliah Dihapus"
                                icon={<MinusCircle size={16} />}
                                courses={comparison.removed}
                                badge={{ label: `${comparison.removedCount} mata kuliah`, tone: 'red' }}
                                emptyText="Tidak ada mata kuliah yang dihapus."
                            />
                        </>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
