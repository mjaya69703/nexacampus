// Detail jadwal ujian — kelola peserta, kehadiran, dan skor seleksi.
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, Clock, MapPin, Trash2, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Participant = {
    id: number; applicationId: number; name: string | null; applicationNumber: string | null;
    studyProgram: string | null; attendanceStatus: string; notes: string | null;
    finalScore: string | number | null; scoreType: string; score: string | number | null;
    weight: string | number; scoreNotes: string | null;
    attendanceUrl: string; scoreUrl: string; removeUrl: string;
};

type Schedule = {
    id: number; title: string; examType: string; examTypeLabel: string; period: string | null;
    examDate: string | null; examTime: string | null; venue: string | null;
    meetingLink: string | null; quota: number; registeredCount: number;
    notes: string | null; isActive: boolean; participants: Participant[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    schedule: Schedule;
    eligibleApplications: { id: number; label: string }[];
    attendanceOptions: { id: string; label: string }[];
    urls: { index: string; edit: string; assign: string; assignAll: string };
};

function ParticipantCard({ participant, attendanceOptions, canUpdate, onRemove }: {
    participant: Participant;
    attendanceOptions: { id: string; label: string }[];
    canUpdate: boolean;
    onRemove: (participant: Participant) => void;
}) {
    const attendance = useForm({
        attendance_status: participant.attendanceStatus,
        notes: participant.notes ?? '',
    });

    const score = useForm({
        score_type: participant.scoreType,
        score: participant.score ?? '',
        weight: participant.weight,
        notes: participant.scoreNotes ?? '',
    });

    const saveAttendance = () => {
        attendance.patch(participant.attendanceUrl, { preserveScroll: true });
    };

    const saveScore = () => {
        score.post(participant.scoreUrl, { preserveScroll: true });
    };

    return (
        <div style={{ border: '1px solid var(--db-line)', borderRadius: 12, padding: 14 }}>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 12 }}>
                <div>
                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 14 }}>{participant.name ?? '-'}</b>
                    <small className="db-hint">
                        {participant.applicationNumber ?? '-'} {participant.studyProgram ? `• ${participant.studyProgram}` : ''}
                    </small>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span className={`db-badge ${participant.attendanceStatus === 'present' ? 'green' : participant.attendanceStatus === 'absent' ? 'red' : 'gray'}`}>
                        {attendanceOptions.find((option) => option.id === participant.attendanceStatus)?.label ?? participant.attendanceStatus}
                    </span>
                    <span className="db-badge gray">Skor akhir: {participant.finalScore ?? '-'}</span>
                    {canUpdate && (
                        <button className="db-btn danger sm" type="button" onClick={() => onRemove(participant)} title="Hapus peserta">
                            <Trash2 size={13} />
                        </button>
                    )}
                </div>
            </div>

            {canUpdate && (
                <div className="crud-grid">
                    <div>
                        <span className="crud-label">Kehadiran</span>
                        <div style={{ display: 'flex', gap: 8 }}>
                            <select
                                className="db-input"
                                value={attendance.data.attendance_status}
                                onChange={(e) => attendance.setData('attendance_status', e.target.value)}
                                aria-label="Status kehadiran"
                            >
                                {attendanceOptions.map((option) => (
                                    <option key={option.id} value={option.id}>{option.label}</option>
                                ))}
                            </select>
                            <button className="db-btn primary sm" type="button" onClick={saveAttendance} disabled={attendance.processing}>
                                Simpan
                            </button>
                        </div>
                        {attendance.errors.attendance_status && <div className="db-error">{attendance.errors.attendance_status}</div>}
                        <input
                            className="db-input"
                            style={{ marginTop: 8 }}
                            placeholder="Catatan kehadiran (opsional)"
                            value={attendance.data.notes}
                            onChange={(e) => attendance.setData('notes', e.target.value)}
                            aria-label="Catatan kehadiran"
                        />
                    </div>

                    <div>
                        <span className="crud-label">Komponen nilai</span>
                        <div style={{ display: 'grid', gridTemplateColumns: '1.4fr .8fr .8fr', gap: 8 }}>
                            <input
                                className="db-input"
                                placeholder="Jenis nilai"
                                value={score.data.score_type}
                                onChange={(e) => score.setData('score_type', e.target.value)}
                                aria-label="Jenis nilai"
                            />
                            <input
                                className="db-input"
                                type="number"
                                step="0.01"
                                min={0}
                                max={100}
                                placeholder="Skor"
                                value={score.data.score}
                                onChange={(e) => score.setData('score', e.target.value)}
                                aria-label="Skor"
                            />
                            <input
                                className="db-input"
                                type="number"
                                step="0.01"
                                min={0}
                                max={100}
                                placeholder="Bobot"
                                value={score.data.weight}
                                onChange={(e) => score.setData('weight', e.target.value)}
                                aria-label="Bobot"
                            />
                        </div>
                        {(score.errors.score_type || score.errors.score || score.errors.weight) && (
                            <div className="db-error">{score.errors.score_type ?? score.errors.score ?? score.errors.weight}</div>
                        )}
                        <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                            <input
                                className="db-input"
                                placeholder="Catatan penilaian (opsional)"
                                value={score.data.notes}
                                onChange={(e) => score.setData('notes', e.target.value)}
                                aria-label="Catatan penilaian"
                            />
                            <button className="db-btn primary sm" type="button" onClick={saveScore} disabled={score.processing}>
                                Simpan Nilai
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function AdmissionExamScheduleShow({ shell, can, schedule, eligibleApplications, attendanceOptions, urls }: Props) {
    const [removeTarget, setRemoveTarget] = useState<Participant | null>(null);
    const [removing, setRemoving] = useState(false);
    const assign = useForm({ application_id: '' });

    const submitAssign = () => {
        assign.post(urls.assign, { preserveScroll: true, onSuccess: () => assign.reset() });
    };

    const assignAll = () => {
        router.post(urls.assignAll, {}, { preserveScroll: true });
    };

    const confirmRemove = () => {
        if (!removeTarget) return;
        setRemoving(true);
        router.delete(removeTarget.removeUrl, {
            preserveScroll: true,
            onFinish: () => {
                setRemoving(false);
                setRemoveTarget(null);
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${schedule.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Admission"
                        title={schedule.title}
                        description={`${schedule.examTypeLabel} • ${schedule.period ?? 'Periode tidak diketahui'}`}
                        badges={[schedule.isActive ? 'Aktif' : 'Nonaktif', `${schedule.registeredCount} peserta terdaftar`]}
                        actions={(
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                {can.update && <a className="db-btn light" href={urls.edit}>Edit Jadwal</a>}
                                <a className="db-btn ghost" href={urls.index} style={{ color: '#fff', borderColor: 'rgba(255,255,255,.4)' }}>Kembali</a>
                            </div>
                        )}
                    />

                    <section className="db-stats" aria-label="Ringkasan jadwal">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Clock size={20} /></span>
                            <div><span className="db-stat-num" style={{ fontSize: 18 }}>{schedule.examDate ?? '-'}</span><span className="db-stat-label">{schedule.examTime ? `Pukul ${schedule.examTime}` : 'Waktu belum diisi'}</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Users size={20} /></span>
                            <div><span className="db-stat-num">{schedule.registeredCount}</span><span className="db-stat-label">Peserta terdaftar</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CheckCircle2 size={20} /></span>
                            <div><span className="db-stat-num">{schedule.quota > 0 ? schedule.quota : '∞'}</span><span className="db-stat-label">Kuota peserta</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><MapPin size={20} /></span>
                            <div><span className="db-stat-num" style={{ fontSize: 16 }}>{schedule.venue || 'Daring'}</span><span className="db-stat-label">Lokasi ujian</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Users size={16} /> Peserta Ujian</h2>
                        </div>
                        <div className="db-card-body">
                            {can.update && (
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'flex-end', marginBottom: 16 }}>
                                    <div style={{ flex: '1 1 320px' }}>
                                        <SelectField
                                            label="Tambah peserta dari gelombang ini"
                                            value={assign.data.application_id}
                                            onChange={(e) => assign.setData('application_id', e.target.value)}
                                            error={assign.errors.application_id}
                                        >
                                            <option value="">-- Pilih pendaftar --</option>
                                            {eligibleApplications.map((application) => (
                                                <option key={application.id} value={application.id}>{application.label}</option>
                                            ))}
                                        </SelectField>
                                    </div>
                                    <button className="db-btn primary" type="button" onClick={submitAssign} disabled={assign.processing || !assign.data.application_id}>
                                        <UserPlus size={14} /> Tambah
                                    </button>
                                    <button className="db-btn ghost" type="button" onClick={assignAll} title="Daftarkan semua pendaftar yang belum punya jadwal">
                                        Daftarkan Massal
                                    </button>
                                </div>
                            )}

                            {eligibleApplications.length === 0 && can.update && (
                                <p className="db-hint" style={{ marginBottom: 14 }}>
                                    Semua pendaftar pada gelombang ini sudah terdaftar, atau belum ada pendaftar berstatus submitted / under review / waitlisted.
                                </p>
                            )}

                            <div className="db-stack" style={{ gap: 10 }}>
                                {schedule.participants.length === 0 && (
                                    <p className="db-hint">Belum ada peserta yang didaftarkan pada jadwal ini.</p>
                                )}
                                {schedule.participants.map((participant) => (
                                    <ParticipantCard
                                        key={participant.id}
                                        participant={participant}
                                        attendanceOptions={attendanceOptions}
                                        canUpdate={can.update}
                                        onRemove={setRemoveTarget}
                                    />
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Informasi Jadwal</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div><span className="crud-label">Jenis seleksi</span><div>{schedule.examTypeLabel}</div></div>
                                <div><span className="crud-label">Periode PMB</span><div>{schedule.period ?? '-'}</div></div>
                                <div><span className="crud-label">Tanggal & waktu</span><div>{schedule.examDate ?? '-'} {schedule.examTime ? `• ${schedule.examTime}` : ''}</div></div>
                                <div><span className="crud-label">Lokasi</span><div>{schedule.venue || '-'}</div></div>
                                <div>
                                    <span className="crud-label">Tautan daring</span>
                                    <div>
                                        {schedule.meetingLink
                                            ? <a href={schedule.meetingLink} target="_blank" rel="noreferrer">{schedule.meetingLink}</a>
                                            : '-'}
                                    </div>
                                </div>
                                <div><span className="crud-label">Status</span><div>{schedule.isActive ? 'Aktif' : 'Nonaktif'}</div></div>
                                {schedule.notes && (
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <span className="crud-label">Catatan</span>
                                        <div style={{ whiteSpace: 'pre-wrap' }}>{schedule.notes}</div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={removeTarget !== null}
                    title="Hapus peserta dari jadwal?"
                    message={`${removeTarget?.name ?? 'Peserta'} akan dihapus dari jadwal ini. Nilai yang sudah tersimpan tetap ada pada pendaftar.`}
                    confirmLabel="Ya, hapus"
                    processing={removing}
                    onConfirm={confirmRemove}
                    onCancel={() => setRemoveTarget(null)}
                />
            </div>
        </AdminShell>
    );
}
