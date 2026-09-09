// Workspace penawaran kelas — detail, dosen, jadwal, sesi dalam satu halaman.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, CalendarDays, ClipboardList, Pencil, Plus, Trash2, Users,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Lecturer = {
    id: number; name: string; role: string; isActive: boolean; notes: string | null;
    updateUrl: string; deleteUrl: string;
};

type SessionItem = {
    id: number; meetingNo: number | null; date: string | null; time: string;
    topic: string | null; status: string; records: number; url: string;
};

type EnrolledStudent = { nim: string | null; name: string; program: string };

type Props = {
    shell: ShellProps;
    can: { update: boolean; manageLecturers: boolean; manageSchedules: boolean; generate: boolean };
    offering: {
        id: number; course: string; label: string | null; code: string | null;
        year: string | null; program: string | null; curriculum: string | null;
        semester: number | null; capacity: number | null; credits: number | null;
        mode: string | null; status: string | null; statusTone: string;
        meetings: number | null; startDate: string | null; endDate: string | null;
        notes: string | null;
    };
    lecturers: Lecturer[];
    schedules: {
        id: number; day: string | null; time: string; room: string; lecturer: string;
        type: string | null; isActive: boolean; editUrl: string;
    }[];
    sessions: SessionItem[];
    students: EnrolledStudent[];
    generate: {
        canGenerate: boolean; activeSchedules: number;
        existingSessions: number; sessionsWithRecords: number; hasOpened: boolean;
    };
    urls: {
        index: string; edit: string; lecturerStore: string;
        scheduleCreate: string; generate: string; searchLecturers: string;
    };
};

type Tab = 'detail' | 'lecturers' | 'schedules' | 'sessions' | 'students';
const LECTURER_ROLES = ['Coordinator', 'Primary', 'Secondary', 'Assistant'];

export default function CourseOfferingWorkspace({ shell, can, offering, lecturers, schedules, sessions, students, generate, urls }: Props) {
    const [tab, setTab] = useState<Tab>('detail');
    const [addLecturer, setAddLecturer] = useState<AsyncOption | null>(null);
    const [addRole, setAddRole] = useState('Primary');
    const [editingLecturer, setEditingLecturer] = useState<Lecturer | null>(null);
    const [deleteLecturer, setDeleteLecturer] = useState<Lecturer | null>(null);
    const [generateOpen, setGenerateOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const lecturerForm = useForm({ role: 'Primary', sort_order: 0, notes: '', is_active: true });

    const submitAddLecturer = () => {
        if (!addLecturer) return;
        setProcessing(true);
        router.post(urls.lecturerStore, {
            lecturer_profile_id: Number(addLecturer.id),
            role: addRole,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setAddLecturer(null);
            },
        });
    };

    const openEditLecturer = (lecturer: Lecturer) => {
        lecturerForm.setData({
            role: lecturer.role,
            sort_order: 0,
            notes: lecturer.notes ?? '',
            is_active: lecturer.isActive,
        });
        lecturerForm.clearErrors();
        setEditingLecturer(lecturer);
    };

    const submitEditLecturer = () => {
        if (!editingLecturer) return;
        lecturerForm.put(editingLecturer.updateUrl, {
            preserveScroll: true,
            onSuccess: () => setEditingLecturer(null),
        });
    };

    const confirmDeleteLecturer = () => {
        if (!deleteLecturer) return;
        setProcessing(true);
        router.delete(deleteLecturer.deleteUrl, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setDeleteLecturer(null);
            },
        });
    };

    const confirmGenerate = () => {
        setProcessing(true);
        router.post(urls.generate, { confirm: 'yes' }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setGenerateOpen(false);
            },
        });
    };

    const tabs: { key: Tab; label: string; count?: number }[] = [
        { key: 'detail', label: 'Detail' },
        { key: 'lecturers', label: 'Dosen', count: lecturers.length },
        { key: 'schedules', label: 'Jadwal', count: schedules.length },
        { key: 'sessions', label: 'Sesi', count: sessions.length },
        { key: 'students', label: 'Mahasiswa', count: students.length },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Kelas ${offering.course} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik · Workspace kelas"
                        title={`${offering.course}${offering.label ? ` · ${offering.label}` : ''}`}
                        description={`${offering.year ?? '-'} · ${offering.program ?? '-'}${offering.semester ? ` · Smt ${offering.semester}` : ''}`}
                        badges={[
                            offering.status ?? '-',
                            `${offering.capacity ?? '-'} kursi`,
                            `${offering.credits ?? '-'} SKS`,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar</a>
                                {can.update && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Edit</a>}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <div className="crud-tabs" role="tablist" aria-label="Bagian workspace">
                                {tabs.map((t) => (
                                    <button
                                        key={t.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={tab === t.key}
                                        className={`crud-tab${tab === t.key ? ' active' : ''}`}
                                        onClick={() => setTab(t.key)}
                                    >
                                        {t.label}
                                        {typeof t.count === 'number' && <span className="db-badge gray">{t.count}</span>}
                                    </button>
                                ))}
                            </div>
                            <span className={`db-badge ${offering.statusTone}`}>{offering.status}</span>
                        </div>
                        <div className="db-card-body">
                            {tab === 'detail' && (
                                <div className="db-card-body tight" style={{ padding: 0 }}>
                                    {[
                                        ['Kode kelas', offering.code ?? '-'],
                                        ['Kurikulum acuan', offering.curriculum ?? '-'],
                                        ['Mode', offering.mode ?? '-'],
                                        ['Total pertemuan', offering.meetings !== null ? String(offering.meetings) : '-'],
                                        ['Rentang kelas', `${offering.startDate ?? '-'} → ${offering.endDate ?? '-'}`],
                                        ['Catatan', offering.notes ?? '-'],
                                    ].map(([label, value]) => (
                                        <div className="db-summary" key={label}>
                                            <div>
                                                <b>{label}</b>
                                                <small>{value}</small>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {tab === 'lecturers' && (
                                <div style={{ display: 'grid', gap: 14 }}>
                                    {can.manageLecturers && (
                                        <div className="crud-grid">
                                            <div style={{ gridColumn: '1 / -1' }}>
                                                <AsyncSelect
                                                    label="Tambah dosen"
                                                    fetchUrl={urls.searchLecturers}
                                                    value={addLecturer}
                                                    onChange={setAddLecturer}
                                                    placeholder="Ketik NIDN/nama dosen…"
                                                />
                                            </div>
                                            <SelectField
                                                label="Peran"
                                                value={addRole}
                                                onChange={(e) => setAddRole(e.target.value)}
                                            >
                                                {LECTURER_ROLES.map((role) => <option key={role} value={role}>{role}</option>)}
                                            </SelectField>
                                            <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                                                <button className="db-btn primary sm" type="button" disabled={!addLecturer || processing} onClick={submitAddLecturer}>
                                                    <Plus size={14} /> Tambahkan
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                    {lecturers.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada dosen. Jadwal membutuhkan dosen terdaftar di kelas ini.</p>
                                    )}
                                    {lecturers.map((lecturer) => (
                                        <div className="db-summary" key={lecturer.id}>
                                            <span className="db-summary-icon"><Users size={17} /></span>
                                            <div>
                                                <b>{lecturer.name}</b>
                                                <small>{lecturer.role}{lecturer.notes ? ` · ${lecturer.notes}` : ''}</small>
                                            </div>
                                            <span className={`db-badge ${lecturer.isActive ? 'green' : 'gray'}`}>{lecturer.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                            {can.manageLecturers && (
                                                <div className="crud-row-actions">
                                                    <button className="db-btn ghost sm" type="button" onClick={() => openEditLecturer(lecturer)} title="Ubah peran">
                                                        <Pencil size={13} />
                                                    </button>
                                                    <button className="db-btn danger sm" type="button" onClick={() => setDeleteLecturer(lecturer)} title="Keluarkan">
                                                        <Trash2 size={13} />
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {tab === 'schedules' && (
                                <div style={{ display: 'grid', gap: 4 }}>
                                    {can.manageSchedules && (
                                        <div style={{ marginBottom: 8 }}>
                                            <a className="db-btn primary sm" href={`${urls.scheduleCreate}?offering=${offering.id}&return=workspace`}>
                                                <Plus size={14} /> Tambah jadwal
                                            </a>
                                        </div>
                                    )}
                                    {schedules.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada jadwal. Generate sesi membutuhkan minimal satu jadwal aktif.</p>
                                    )}
                                    {schedules.map((schedule) => (
                                        <div className="db-summary" key={schedule.id}>
                                            <div>
                                                <b>{schedule.day} · {schedule.time}</b>
                                                <small>{schedule.room} · {schedule.lecturer} · {schedule.type ?? '-'}</small>
                                            </div>
                                            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                                <span className={`db-badge ${schedule.isActive ? 'green' : 'gray'}`}>{schedule.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                                {can.manageSchedules && (
                                                    <a className="db-btn ghost sm" href={schedule.editUrl} title="Ubah jadwal">
                                                        <Pencil size={13} />
                                                    </a>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {tab === 'sessions' && (
                                <div style={{ display: 'grid', gap: 4 }}>
                                    {can.generate && (
                                        <div style={{ marginBottom: 8 }}>
                                            <button
                                                className="db-btn primary sm"
                                                type="button"
                                                disabled={!generate.canGenerate}
                                                title={generate.canGenerate ? 'Generate sesi pertemuan' : 'Lengkapi total pertemuan + rentang tanggal dulu'}
                                                onClick={() => setGenerateOpen(true)}
                                            >
                                                <CalendarDays size={14} /> Generate sesi
                                            </button>
                                        </div>
                                    )}
                                    {sessions.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada sesi. Generate dari jadwal aktif kelas ini.</p>
                                    )}
                                    {sessions.map((session) => (
                                        <a key={session.id} href={session.url} className="db-summary" style={{ textDecoration: 'none' }}>
                                            <div>
                                                <b>Pertemuan {session.meetingNo ?? '-'} · {session.date ?? '-'}</b>
                                                <small>{session.time}{session.topic ? ` · ${session.topic}` : ''} · {session.records} catatan</small>
                                            </div>
                                            <span className="db-badge">{session.status}</span>
                                        </a>
                                    ))}
                                </div>
                            )}

                            {tab === 'students' && (
                                <div style={{ display: 'grid', gap: 4 }}>
                                    <p className="db-hint" style={{ margin: 0 }}>
                                        Terisi {students.length}{offering.capacity ? ` dari ${offering.capacity} kursi` : ''} (KRS Approved berstatus Taken).
                                    </p>
                                    {students.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada mahasiswa terdaftar di kelas ini.</p>
                                    )}
                                    {students.map((student, i) => (
                                        <div className="db-summary" key={`${student.nim ?? 'x'}-${i}`}>
                                            <div>
                                                <b>{student.name}</b>
                                                <small>{student.nim ?? '-'} · {student.program}</small>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                {editingLecturer && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setEditingLecturer(null)} role="dialog" aria-modal="true" aria-label="Ubah peran dosen">
                            <div className="db-modal" style={{ width: 'min(26rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>Peran — {editingLecturer.name}</h3>
                                <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                                    <SelectField
                                        label="Peran"
                                        value={lecturerForm.data.role}
                                        onChange={(e) => lecturerForm.setData('role', e.target.value)}
                                        error={(lecturerForm.errors as Record<string, string>).role}
                                    >
                                        {LECTURER_ROLES.map((role) => <option key={role} value={role}>{role}</option>)}
                                    </SelectField>
                                    <SwitchField
                                        label="Aktif di kelas ini"
                                        checked={lecturerForm.data.is_active}
                                        onChange={(v) => lecturerForm.setData('is_active', v)}
                                        error={(lecturerForm.errors as Record<string, string>).is_active}
                                    />
                                    <TextareaField
                                        label="Catatan"
                                        value={lecturerForm.data.notes}
                                        onChange={(e) => lecturerForm.setData('notes', e.target.value)}
                                        error={(lecturerForm.errors as Record<string, string>).notes}
                                    />
                                </div>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setEditingLecturer(null)}>Batal</button>
                                    <button className="db-btn primary" type="button" onClick={submitEditLecturer} disabled={lecturerForm.processing}>
                                        {lecturerForm.processing ? 'Menyimpan…' : 'Simpan'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <ConfirmModal
                    open={deleteLecturer !== null}
                    title="Keluarkan dosen?"
                    message={deleteLecturer ? `${deleteLecturer.name} dikeluarkan dari kelas ini.` : ''}
                    confirmLabel="Ya, keluarkan"
                    danger
                    processing={processing}
                    onConfirm={confirmDeleteLecturer}
                    onCancel={() => setDeleteLecturer(null)}
                />

                {generateOpen && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setGenerateOpen(false)} role="dialog" aria-modal="true" aria-label="Generate sesi">
                            <div className="db-modal" style={{ width: 'min(30rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>Generate sesi pertemuan?</h3>
                                <p>
                                    Membuat ulang seluruh sesi dari {generate.activeSchedules} jadwal aktif.
                                    <b> {generate.existingSessions} sesi lama akan dihapus permanen</b>
                                    {generate.sessionsWithRecords > 0 && (
                                        <> termasuk <b>{generate.sessionsWithRecords} sesi yang sudah punya data absensi</b></>
                                    )}
                                    {generate.hasOpened && (
                                        <> — termasuk sesi yang sedang <b>Berjalan</b></>
                                    )}
                                    .
                                </p>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setGenerateOpen(false)} disabled={processing}>
                                        Batal
                                    </button>
                                    <button className="db-btn danger" type="button" onClick={confirmGenerate} disabled={processing || generate.sessionsWithRecords > 0 || generate.hasOpened}>
                                        {processing ? 'Memproses…' : 'Ya, generate ulang'}
                                    </button>
                                </div>
                                {(generate.sessionsWithRecords > 0 || generate.hasOpened) && (
                                    <p className="db-hint" style={{ marginTop: 10 }}>
                                        Generate dikunci karena ada data absensi/sesi berjalan. Hapus manual sesi terkait bila memang ingin mengulang.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminShell>
    );
}
