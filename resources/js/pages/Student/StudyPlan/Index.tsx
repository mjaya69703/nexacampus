// Kartu Rencana Studi (KRS) mahasiswa — pengisian, revisi, dan submit (Inertia React).
// Seluruh perubahan mengikuti periode KRS/revisi yang aktif dan batas 24 SKS.
import { Head, router } from '@inertiajs/react';
import {
    BookOpenCheck, CalendarDays, CheckCircle2, Clock3, GraduationCap,
    Info, ListChecks, Plus, RefreshCw, RotateCcw, Send, Trash2, TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../components/Shared/Crud/ConfirmModal';
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

type Registration = {
    id: number;
    semesterNo: number | null;
    status: string;
    academicStatus: string;
};

type Period = {
    open: boolean;
    revisionOpen: boolean;
    changeOpen: boolean;
    name: string | null;
    range: string | null;
    revisionName: string | null;
    revisionRange: string | null;
};

type Plan = {
    id: number | null;
    status: string;
    submittedAt: string | null;
    totalCredits: number;
    maxCredits: number;
    canModify: boolean;
    canSubmit: boolean;
    canRevise: boolean;
};

type SelectedCourse = {
    detailId: number;
    code: string;
    name: string;
    credits: number;
    semesterNo: number | null;
    label: string | null;
    deliveryMode: string | null;
    isRepeat: boolean;
    status: string;
    schedule: string | null;
};

type Offering = {
    id: number;
    code: string;
    name: string;
    credits: number;
    semesterNo: number | null;
    label: string | null;
    deliveryMode: string | null;
    isRetake: boolean;
    schedule: string | null;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    hasActiveYear: boolean;
    hasActiveRegistration: boolean;
    isRegistrationApproved: boolean;
    isAcademicallyActive: boolean;
    student: Student | null;
    year: { id: number; name: string } | null;
    registration: Registration | null;
    period: Period;
    plan: Plan;
    selectedCourses: SelectedCourse[];
    availableOfferings: Offering[];
    showRetake: boolean;
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

export default function StudyPlanIndex({
    shell, hasProfile, hasActiveYear, hasActiveRegistration, isRegistrationApproved,
    isAcademicallyActive, student, year, registration, period, plan,
    selectedCourses, availableOfferings, showRetake, urls,
}: Props) {
    const [busyOffering, setBusyOffering] = useState<number | null>(null);
    const [confirmRemove, setConfirmRemove] = useState<SelectedCourse | null>(null);
    const [confirmRevise, setConfirmRevise] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [revising, setRevising] = useState(false);

    const addCourse = (offeringId: number): void => {
        router.post(urls.courses, { course_offering_id: offeringId }, {
            preserveScroll: true,
            onStart: () => setBusyOffering(offeringId),
            onFinish: () => setBusyOffering(null),
        });
    };

    const removeCourse = (): void => {
        if (!confirmRemove) return;

        router.delete(`${urls.courses}/${confirmRemove.detailId}`, {
            preserveScroll: true,
            onFinish: () => setConfirmRemove(null),
        });
    };

    const submitPlan = (): void => {
        router.post(urls.submit, {}, {
            preserveScroll: true,
            onStart: () => setSubmitting(true),
            onFinish: () => setSubmitting(false),
        });
    };

    const revisePlan = (): void => {
        router.post(urls.revise, {}, {
            preserveScroll: true,
            onStart: () => setRevising(true),
            onFinish: () => {
                setRevising(false);
                setConfirmRevise(false);
            },
        });
    };

    const creditPercent = plan.maxCredits > 0
        ? Math.min(100, Math.round((plan.totalCredits / plan.maxCredits) * 100))
        : 0;

    return (
        <AdminShell shell={shell}>
            <Head title={`Kartu Rencana Studi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ListChecks}
                        eyebrow="Kartu Rencana Studi"
                        title={student?.name ?? 'Kartu Rencana Studi'}
                        description={student ? `${student.program} · ${student.faculty}` : 'Susun rencana studi pada tahun akademik aktif.'}
                        badges={student ? [
                            `NIM ${student.nim ?? '-'}`,
                            `Semester ${student.semester ?? '-'}`,
                            year?.name ?? 'Tahun akademik belum aktif',
                            `Status KRS ${statusLabel(plan.status)}`,
                        ] : []}
                        actions={(
                            <a className="db-btn light" href={urls.comparison}>
                                <GraduationCap size={15} /> Perbandingan KRS
                            </a>
                        )}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Hubungi admin akademik untuk sinkronisasi data mahasiswa.</span>
                        </div>
                    )}

                    {hasProfile && !hasActiveYear && (
                        <div className="db-note warning">
                            <TriangleAlert size={15} />
                            <span><b>Tahun akademik aktif belum ditentukan.</b> KRS belum dapat disusun.</span>
                        </div>
                    )}

                    {hasProfile && hasActiveYear && !hasActiveRegistration && (
                        <div className="db-note warning">
                            <TriangleAlert size={15} />
                            <span>
                                <b>Registrasi semester belum tersedia.</b> Isi registrasi semester terlebih dahulu sebelum menyusun KRS.{' '}
                                <a href={urls.registration} style={{ fontWeight: 700 }}>Buka Registrasi Semester</a>
                            </span>
                        </div>
                    )}

                    {hasProfile && hasActiveYear && hasActiveRegistration && !isRegistrationApproved && (
                        <div className="db-note warning">
                            <TriangleAlert size={15} />
                            <span>
                                <b>Registrasi semester belum disetujui.</b> KRS dapat disusun setelah registrasi berstatus Approved.
                            </span>
                        </div>
                    )}

                    {hasProfile && hasActiveYear && hasActiveRegistration && isRegistrationApproved && !isAcademicallyActive && (
                        <div className="db-note warning">
                            <TriangleAlert size={15} />
                            <span>
                                <b>Status akademik semester ini {registration?.academicStatus ?? '-'}.</b> KRS hanya dapat diproses bila status akademik Aktif.
                            </span>
                        </div>
                    )}

                    {hasProfile && hasActiveYear && hasActiveRegistration && isRegistrationApproved && isAcademicallyActive && (
                        <>
                            <section className="db-stats">
                                <div className="db-stat">
                                    <span className="db-stat-icon"><BookOpenCheck size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{plan.totalCredits}/{plan.maxCredits}</span>
                                        <span className="db-stat-label">SKS dipilih</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{selectedCourses.length}</span>
                                        <span className="db-stat-label">Mata kuliah</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon gold"><CalendarDays size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">Sem {registration?.semesterNo ?? '-'}</span>
                                        <span className="db-stat-label">Semester registrasi</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon red"><Clock3 size={20} /></span>
                                    <div>
                                        <span className={`db-badge ${statusTone(plan.status)}`}>{statusLabel(plan.status)}</span>
                                        <span className="db-stat-label">Status KRS</span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Clock3 size={16} /> Periode KRS</h2>
                                    <span className={`db-badge ${period.changeOpen ? 'green' : 'red'}`}>
                                        {period.changeOpen ? 'Aktif' : 'Belum aktif'}
                                    </span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {period.open && (
                                        <div className="db-note success">
                                            <CheckCircle2 size={15} />
                                            <span>
                                                <b>Pengisian KRS:</b> {period.name} <span className="db-hint">({period.range})</span>
                                            </span>
                                        </div>
                                    )}
                                    {period.revisionOpen && (
                                        <div className="db-note info">
                                            <Info size={15} />
                                            <span>
                                                <b>Revisi KRS:</b> {period.revisionName} <span className="db-hint">({period.revisionRange})</span>
                                            </span>
                                        </div>
                                    )}
                                    {!period.changeOpen && (
                                        <div className="db-note danger">
                                            <TriangleAlert size={15} />
                                            <span><b>Periode pengisian/revisi KRS belum aktif.</b> Perubahan KRS hanya bisa dilakukan saat periode dibuka.</span>
                                        </div>
                                    )}
                                    <div className="db-progress"><i style={{ width: `${creditPercent}%` }} /></div>
                                    <div className="db-hint">
                                        Total {plan.totalCredits} dari maksimal {plan.maxCredits} SKS. Mata kuliah semester di atas semester registrasi tidak diperbolehkan.
                                    </div>
                                </div>
                            </section>

                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: 18, alignItems: 'start' }}>
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><BookOpenCheck size={16} /> Mata Kuliah Tersedia</h2>
                                        <a
                                            className={`db-btn sm ${showRetake ? 'primary' : 'ghost'}`}
                                            href={showRetake ? urls.index : `${urls.index}?retake=1`}
                                        >
                                            <RefreshCw size={14} /> {showRetake ? 'Sembunyikan retake' : 'Tampilkan retake'}
                                        </a>
                                    </div>
                                    <div className="db-card-body">
                                        {availableOfferings.length === 0 ? (
                                            <div className="db-empty-calm">
                                                <BookOpenCheck size={22} />
                                                <div><b>Tidak ada kelas tersedia.</b><small>Semua kelas pada semester ini sudah dipilih atau belum dibuka.</small></div>
                                            </div>
                                        ) : (
                                            <div className="db-table-wrap">
                                                <table className="db-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Mata Kuliah</th>
                                                            <th>SKS</th>
                                                            <th>Smt</th>
                                                            <th>Kelas / Jadwal</th>
                                                            <th />
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {availableOfferings.map((offering) => (
                                                            <tr key={offering.id}>
                                                                <td>
                                                                    <div style={{ fontWeight: 600 }}>{offering.name}</div>
                                                                    <div className="db-hint">
                                                                        {offering.code}
                                                                        {offering.isRetake && <span className="db-badge amber" style={{ marginLeft: 8 }}>Retake</span>}
                                                                    </div>
                                                                </td>
                                                                <td>{offering.credits}</td>
                                                                <td>{offering.semesterNo ?? '-'}</td>
                                                                <td>
                                                                    <div>{offering.label ?? '-'}</div>
                                                                    <div className="db-hint">
                                                                        {offering.deliveryMode ?? '-'}
                                                                        {offering.schedule ? ` · ${offering.schedule}` : ''}
                                                                    </div>
                                                                </td>
                                                                <td style={{ textAlign: 'right' }}>
                                                                    <button
                                                                        type="button"
                                                                        className="db-btn sm primary"
                                                                        disabled={!plan.canModify || busyOffering !== null}
                                                                        onClick={() => addCourse(offering.id)}
                                                                    >
                                                                        <Plus size={14} /> {busyOffering === offering.id ? 'Proses…' : 'Ambil'}
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                </section>

                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><CheckCircle2 size={16} /> KRS Saya</h2>
                                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                            {plan.canRevise && (
                                                <button
                                                    type="button"
                                                    className="db-btn sm ghost"
                                                    disabled={revising}
                                                    onClick={() => setConfirmRevise(true)}
                                                >
                                                    <RotateCcw size={14} /> Revisi
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                className="db-btn sm primary"
                                                disabled={!plan.canSubmit || submitting}
                                                onClick={submitPlan}
                                            >
                                                <Send size={14} /> {submitting ? 'Mengirim…' : 'Submit KRS'}
                                            </button>
                                        </div>
                                    </div>
                                    <div className="db-card-body">
                                        {selectedCourses.length === 0 ? (
                                            <div className="db-empty-calm">
                                                <ListChecks size={22} />
                                                <div><b>Belum ada mata kuliah dipilih.</b><small>Pilih kelas dari daftar di samping untuk menyusun KRS.</small></div>
                                            </div>
                                        ) : (
                                            <div className="db-table-wrap">
                                                <table className="db-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Mata Kuliah</th>
                                                            <th>SKS</th>
                                                            <th>Kelas / Jadwal</th>
                                                            <th>Status</th>
                                                            <th />
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {selectedCourses.map((course) => (
                                                            <tr key={course.detailId}>
                                                                <td>
                                                                    <div style={{ fontWeight: 600 }}>{course.name}</div>
                                                                    <div className="db-hint">
                                                                        {course.code}
                                                                        {course.isRepeat && <span className="db-badge amber" style={{ marginLeft: 8 }}>Retake</span>}
                                                                    </div>
                                                                </td>
                                                                <td>{course.credits}</td>
                                                                <td>
                                                                    <div>{course.label ?? '-'}</div>
                                                                    <div className="db-hint">
                                                                        {course.schedule ?? course.deliveryMode ?? '-'}
                                                                    </div>
                                                                </td>
                                                                <td><span className={`db-badge ${statusTone(course.status)}`}>{statusLabel(course.status)}</span></td>
                                                                <td style={{ textAlign: 'right' }}>
                                                                    <button
                                                                        type="button"
                                                                        className="db-btn sm danger"
                                                                        disabled={!plan.canModify}
                                                                        onClick={() => setConfirmRemove(course)}
                                                                    >
                                                                        <Trash2 size={14} /> Hapus
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}

                                        {plan.submittedAt && (
                                            <div className="db-hint" style={{ marginTop: 12 }}>
                                                <Clock3 size={12} /> Diajukan pada {plan.submittedAt}
                                            </div>
                                        )}
                                    </div>
                                </section>
                            </div>
                        </>
                    )}
                </div>
            </div>

            <ConfirmModal
                open={confirmRemove !== null}
                title="Hapus mata kuliah dari KRS?"
                message={confirmRemove
                    ? `${confirmRemove.name} (${confirmRemove.credits} SKS) akan dihapus dari KRS Anda.`
                    : ''}
                confirmLabel="Ya, hapus"
                onConfirm={removeCourse}
                onCancel={() => setConfirmRemove(null)}
            />

            <ConfirmModal
                open={confirmRevise}
                title="Kembalikan KRS ke draf?"
                message="KRS yang sudah diajukan akan kembali ke Draft agar dapat direvisi. Anda perlu melakukan submit ulang setelah selesai."
                confirmLabel="Ya, revisi"
                danger={false}
                processing={revising}
                onConfirm={revisePlan}
                onCancel={() => setConfirmRevise(false)}
            />
        </AdminShell>
    );
}
