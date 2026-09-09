// Detail sesi absensi — koreksi data sesi + roster kehadiran.
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    session: {
        id: number; offeringId: number; meetingNo: number | null;
        date: string; dateLabel: string | null;
        startTime: string; endTime: string;
        lecturerId: number | null; lecturer: string; status: string;
        topic: string; notes: string;
    };
    statuses: string[];
    recordStatuses: string[];
    lecturers: { id: number; label: string }[];
    roster: { studentId: number; nim: string | null; name: string; status: string | null }[];
    summary: { students: number; recorded: number };
    urls: { workspace: string; submit: string; saveRecords: string };
};

export default function AttendanceSessionShow({ shell, session, statuses, recordStatuses, lecturers, roster, summary, urls }: Props) {
    const form = useForm({
        meeting_no: session.meetingNo === null ? '' : String(session.meetingNo),
        meeting_date: session.date,
        start_time: session.startTime,
        end_time: session.endTime,
        lecturer_profile_id: session.lecturerId === null ? '' : String(session.lecturerId),
        status: session.status,
        topic: session.topic,
        notes: session.notes,
    });

    const records = useForm({
        records: roster.map((row) => ({
            student_profile_id: row.studentId,
            status: row.status ?? 'Present',
        })),
    });

    const errors = form.errors as Record<string, string>;

    const setRecordStatus = (studentId: number, status: string) => {
        records.setData('records', records.data.records.map((row) =>
            row.student_profile_id === studentId ? { ...row, status } : row,
        ));
    };

    const submitSession = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(urls.submit);
    };

    const submitRecords = () => {
        records.post(urls.saveRecords, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Sesi ${session.meetingNo ?? ''} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardCheck}
                        eyebrow="Akademik · Sesi absensi"
                        title={`Pertemuan ${session.meetingNo ?? '-'} · ${session.dateLabel ?? '-'}`}
                        description={`${session.startTime || '?'}–${session.endTime || '?'} · ${session.lecturer}`}
                        badges={[session.status, `${summary.recorded}/${summary.students} tercatat`]}
                        actions={<a className="db-btn light" href={urls.workspace}><ArrowLeft size={15} /> Workspace</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Data sesi</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submitSession}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Pertemuan ke"
                                        type="number"
                                        value={form.data.meeting_no}
                                        onChange={(e) => form.setData('meeting_no', e.target.value)}
                                        error={errors.meeting_no}
                                    />
                                    <TextField
                                        label="Tanggal"
                                        required
                                        type="date"
                                        value={form.data.meeting_date}
                                        onChange={(e) => form.setData('meeting_date', e.target.value)}
                                        error={errors.meeting_date}
                                    />
                                    <TextField
                                        label="Mulai"
                                        type="time"
                                        value={form.data.start_time}
                                        onChange={(e) => form.setData('start_time', e.target.value)}
                                        error={errors.start_time}
                                    />
                                    <TextField
                                        label="Selesai"
                                        type="time"
                                        value={form.data.end_time}
                                        onChange={(e) => form.setData('end_time', e.target.value)}
                                        error={errors.end_time}
                                    />
                                    <SelectField
                                        label="Dosen"
                                        value={form.data.lecturer_profile_id}
                                        onChange={(e) => form.setData('lecturer_profile_id', e.target.value)}
                                        error={errors.lecturer_profile_id}
                                    >
                                        <option value="">— Pilih —</option>
                                        {lecturers.map((lecturer) => <option key={lecturer.id} value={lecturer.id}>{lecturer.label}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Status"
                                        required
                                        value={form.data.status}
                                        onChange={(e) => form.setData('status', e.target.value)}
                                        error={errors.status}
                                    >
                                        {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                    </SelectField>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Topik"
                                            value={form.data.topic}
                                            onChange={(e) => form.setData('topic', e.target.value)}
                                            error={errors.topic}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={errors.notes}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.workspace}
                                    submitLabel="Simpan Sesi"
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar hadir ({summary.recorded}/{summary.students})</h2>
                            <button className="db-btn primary sm" type="button" onClick={submitRecords} disabled={records.processing || roster.length === 0}>
                                {records.processing ? 'Menyimpan…' : 'Simpan semua'}
                            </button>
                        </div>
                        <div className="db-card-body tight">
                            {roster.length === 0 && (
                                <p className="db-hint" style={{ margin: 0 }}>
                                    Belum ada mahasiswa dengan KRS Approved berstatus Taken di kelas ini.
                                </p>
                            )}
                            {roster.map((row, i) => (
                                <div className="db-summary" key={row.studentId}>
                                    <div>
                                        <b>{row.name}</b>
                                        <small>{row.nim ?? '-'}</small>
                                    </div>
                                    <select
                                        className="db-input"
                                        style={{ width: 'auto', minHeight: 34 }}
                                        value={records.data.records[i]?.status ?? 'Present'}
                                        onChange={(e) => setRecordStatus(row.studentId, e.target.value)}
                                        aria-label={`Status ${row.name}`}
                                    >
                                        {recordStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                    </select>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
