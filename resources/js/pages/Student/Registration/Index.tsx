// Registrasi semester mahasiswa (Inertia React).
// Registrasi berstatus Cancelled tidak lagi menghalangi pengajuan baru.
import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarCheck, CalendarDays, CheckCircle2, ClipboardCheck, Clock3,
    Info, NotebookPen, Send, TriangleAlert, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type HistoryRow = {
    academicYear: string;
    semesterNo: number | null;
    registrationStatus: string;
    academicStatus: string;
    registrationDate: string;
    submittedAt: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    hasActiveYear: boolean;
    student: { name: string; nim: string | null; program: string; faculty: string; semester: number | null } | null;
    year: { id: number; name: string } | null;
    period: { canRegister: boolean; name: string | null; range: string | null };
    form: { semester_no: number | null; academic_status: string; notes: string };
    current: {
        id: number; status: string; academicStatus: string;
        submittedAt: string | null; approvedAt: string | null; canEdit: boolean;
    } | null;
    history: HistoryRow[];
    academicStatusOptions: string[];
    urls: { index: string; draft: string; submit: string; cancel: string; leaves: string };
};

const statusTone = (status: string): string => {
    if (status === 'Approved') return 'green';
    if (status === 'Draft') return 'amber';
    if (status === 'Rejected' || status === 'Cancelled') return 'red';
    if (status === 'Submitted') return '';
    return 'gray';
};

const statusLabel = (status: string): string => {
    const map: Record<string, string> = {
        Approved: 'Disetujui', Submitted: 'Diajukan', Draft: 'Draf',
        Rejected: 'Ditolak', Cancelled: 'Dibatalkan',
    };
    return map[status] ?? status;
};

export default function RegistrationIndex({
    shell, hasProfile, hasActiveYear, student, year, period, form: initialForm,
    current, history, academicStatusOptions, urls,
}: Props) {
    const [confirmCancel, setConfirmCancel] = useState(false);
    const form = useForm({
        semester_no: (initialForm.semester_no ?? '') as number | '',
        academic_status: initialForm.academic_status,
        notes: initialForm.notes,
    });

    const canEdit = current?.canEdit ?? true;
    const canCancel = current !== null && ['Draft', 'Submitted'].includes(current.status);
    const saveDraft = () => form.post(urls.draft, { preserveScroll: true });
    const submitRegistration = () => form.post(urls.submit, { preserveScroll: true });
    const cancelRegistration = () => {
        router.post(urls.cancel, {}, {
            preserveScroll: true,
            onFinish: () => setConfirmCancel(false),
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Registrasi Semester · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardCheck}
                        eyebrow="Registrasi semester"
                        title={student?.name ?? 'Registrasi Semester'}
                        description={student ? `${student.program} · ${student.faculty}` : 'Lengkapi registrasi semester pada tahun akademik aktif.'}
                        badges={student ? [
                            `NIM ${student.nim ?? '-'}`,
                            `Semester ${student.semester ?? '-'}`,
                            current ? `Status ${statusLabel(current.status)}` : 'Belum ada registrasi',
                            year?.name ?? 'Tahun akademik belum aktif',
                        ] : []}
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
                            <span><b>Tahun akademik aktif belum ditentukan.</b> Registrasi semester belum dapat dilakukan.</span>
                        </div>
                    )}

                    {hasProfile && hasActiveYear && student && (
                        <>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18, alignItems: 'start' }}>
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><NotebookPen size={16} /> Form Registrasi Semester</h2>
                                        <span className={`db-badge ${statusTone(current?.status ?? '')}`}>
                                            {current ? statusLabel(current.status) : 'Baru'}
                                        </span>
                                    </div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                        <div className={`db-note ${period.canRegister ? 'success' : 'warning'}`}>
                                            <Info size={15} />
                                            <span>
                                                Tahun akademik aktif <b>{year?.name}</b>.{' '}
                                                {period.canRegister
                                                    ? <>Registrasi dapat diajukan pada periode <b>{period.name}</b> ({period.range}).</>
                                                    : 'Registrasi masih bisa disimpan sebagai draf, tetapi pengajuan menunggu periode aktif.'}
                                            </span>
                                        </div>

                                        <div className="db-grid-2">
                                            <label style={{ display: 'grid', gap: 6 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Semester</span>
                                                <input
                                                    className="db-input"
                                                    type="number"
                                                    min={1}
                                                    max={14}
                                                    value={form.data.semester_no}
                                                    onChange={(event) => form.setData('semester_no', event.target.value === '' ? '' : Number(event.target.value))}
                                                    disabled={!canEdit}
                                                />
                                                {form.errors.semester_no && <span className="db-error">{form.errors.semester_no}</span>}
                                            </label>

                                            <label style={{ display: 'grid', gap: 6 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Status Akademik</span>
                                                <select
                                                    className="db-input"
                                                    value={form.data.academic_status}
                                                    onChange={(event) => form.setData('academic_status', event.target.value)}
                                                    disabled={!canEdit}
                                                >
                                                    {academicStatusOptions.map((status) => (
                                                        <option key={status} value={status}>{status}</option>
                                                    ))}
                                                </select>
                                                {form.errors.academic_status && <span className="db-error">{form.errors.academic_status}</span>}
                                            </label>
                                        </div>

                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Pengajuan cuti akademik dilakukan melalui{' '}
                                            <a href={urls.leaves} style={{ fontWeight: 700 }}>Layanan &gt; Cuti Akademik</a>{' '}
                                            agar dapat direview dengan lampiran dan riwayat.
                                        </p>

                                        <label style={{ display: 'grid', gap: 6 }}>
                                            <span className="db-hint" style={{ fontWeight: 700 }}>Catatan</span>
                                            <textarea
                                                className="db-input"
                                                rows={4}
                                                value={form.data.notes}
                                                onChange={(event) => form.setData('notes', event.target.value)}
                                                disabled={!canEdit}
                                                placeholder="Tambahkan catatan jika dibutuhkan"
                                            />
                                            {form.errors.notes && <span className="db-error">{form.errors.notes}</span>}
                                        </label>

                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                            <button
                                                type="button"
                                                className="db-btn ghost"
                                                onClick={saveDraft}
                                                disabled={!canEdit || form.processing}
                                            >
                                                <CalendarCheck size={15} /> Simpan Draf
                                            </button>
                                            <button
                                                type="button"
                                                className="db-btn primary"
                                                onClick={submitRegistration}
                                                disabled={!canEdit || !period.canRegister || form.processing}
                                            >
                                                <Send size={15} /> Ajukan Registrasi
                                            </button>
                                            {canCancel && (
                                                <button
                                                    type="button"
                                                    className="db-btn danger"
                                                    onClick={() => setConfirmCancel(true)}
                                                >
                                                    <XCircle size={15} /> Batalkan Pengajuan
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </section>

                                <div className="db-stack" style={{ gap: 18 }}>
                                    <section className="db-card">
                                        <div className="db-card-head">
                                            <h2 className="db-card-title"><CheckCircle2 size={16} /> Status Saat Ini</h2>
                                        </div>
                                        <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                            <div>
                                                <div className="db-hint">Registrasi</div>
                                                <span className={`db-badge ${statusTone(current?.status ?? '')}`}>
                                                    {current ? statusLabel(current.status) : '-'}
                                                </span>
                                            </div>
                                            <div>
                                                <div className="db-hint">Status Akademik</div>
                                                <b>{current?.academicStatus ?? '-'}</b>
                                            </div>
                                            <div>
                                                <div className="db-hint">Diajukan Pada</div>
                                                <b>{current?.submittedAt ?? '-'}</b>
                                            </div>
                                            <div>
                                                <div className="db-hint">Disetujui Pada</div>
                                                <b>{current?.approvedAt ?? '-'}</b>
                                            </div>
                                        </div>
                                    </section>

                                    <section className="db-card">
                                        <div className="db-card-head">
                                            <h2 className="db-card-title"><Clock3 size={16} /> Periode Aktif</h2>
                                        </div>
                                        <div className="db-card-body">
                                            {period.canRegister ? (
                                                <>
                                                    <div style={{ fontWeight: 700, color: 'var(--db-heading)' }}>{period.name}</div>
                                                    <div className="db-hint">{period.range}</div>
                                                </>
                                            ) : (
                                                <div className="db-hint">Periode registrasi belum aktif atau sudah berakhir.</div>
                                            )}
                                        </div>
                                    </section>
                                </div>
                            </div>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarDays size={16} /> Riwayat Registrasi</h2>
                                    <span className="db-badge gray">{history.length} data</span>
                                </div>
                                <div className="db-card-body">
                                    {history.length === 0 ? (
                                        <div className="db-empty-calm">
                                            <CalendarDays size={22} />
                                            <div><b>Belum ada riwayat registrasi.</b><small>Registrasi semester akan tampil di sini.</small></div>
                                        </div>
                                    ) : (
                                        <div className="db-table-wrap">
                                            <table className="db-table">
                                                <thead>
                                                    <tr>
                                                        <th>Tahun Akademik</th>
                                                        <th>Semester</th>
                                                        <th>Status Registrasi</th>
                                                        <th>Status Akademik</th>
                                                        <th>Tanggal</th>
                                                        <th>Diajukan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {history.map((row, index) => (
                                                        <tr key={`${row.academicYear}-${row.semesterNo}-${index}`}>
                                                            <td style={{ fontWeight: 600 }}>{row.academicYear}</td>
                                                            <td>{row.semesterNo ?? '-'}</td>
                                                            <td><span className={`db-badge ${statusTone(row.registrationStatus)}`}>{statusLabel(row.registrationStatus)}</span></td>
                                                            <td>{row.academicStatus}</td>
                                                            <td className="db-hint">{row.registrationDate}</td>
                                                            <td className="db-hint">{row.submittedAt}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                </div>
            </div>

            <ConfirmModal
                open={confirmCancel}
                title="Batalkan pengajuan registrasi?"
                message="Registrasi yang dibatalkan tidak dapat dilanjutkan, tetapi Anda dapat menyusun registrasi baru pada tahun akademik yang sama."
                confirmLabel="Ya, batalkan"
                onConfirm={cancelRegistration}
                onCancel={() => setConfirmCancel(false)}
            />
        </AdminShell>
    );
}
