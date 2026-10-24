// Konsultasi dosen mahasiswa (Inertia React).
// Slot hanya berasal dari dosen pengampu kelas aktif dan pembimbing akademik.
import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarCheck, CalendarClock, CalendarDays, CheckCircle2, Clock,
    Inbox, MapPin, MessageSquare, Send, Trash2, TriangleAlert, UserRound,
} from 'lucide-react';
import type { FormEvent } from 'react';
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

type Slot = {
    key: string;
    slotId: number;
    dateValue: string;
    timeValue: string;
    startsAt: string;
    lecturer: string;
    studyProgram: string;
    title: string;
    date: string;
    time: string;
    mode: string;
    place: string;
    notes: string | null;
    remaining: number;
    capacity: number;
    hasOwnBooking: boolean;
};

type Appointment = {
    id: number;
    lecturer: string;
    schedule: string;
    topic: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    place: string;
    studentNotes: string | null;
    lecturerNotes: string | null;
    canCancel: boolean;
};

type Props = {
    shell: ShellProps;
    student: Student;
    slots: Slot[];
    appointments: Appointment[];
    stats: { availableSlots: number; requested: number; confirmed: number; completed: number };
    urls: { index: string; book: string; cancel: string };
};

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

function SlotCard({ slot, bookUrl }: { slot: Slot; bookUrl: string }) {
    // date/time ikut dikirim karena controller memvalidasi keduanya; nilai
    // berasal dari slot yang dipilih sehingga tidak bisa diubah mahasiswa.
    const form = useForm({
        date: slot.dateValue,
        time: slot.timeValue,
        topic: '',
        notes: '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        form.post(bookUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <article className="db-card" style={{ margin: 0 }}>
            <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                    <span className="db-stat-icon" style={{ width: 42, height: 42, flexShrink: 0 }}>
                        <UserRound size={19} />
                    </span>
                    <div style={{ minWidth: 0, flex: 1 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <b style={{ color: 'var(--db-heading)', fontSize: 14 }}>{slot.lecturer}</b>
                            <span className={`db-badge ${slot.hasOwnBooking ? '' : 'green'}`}>
                                {slot.hasOwnBooking ? 'Sudah diajukan' : 'Tersedia'}
                            </span>
                        </div>
                        <div className="db-hint">{slot.studyProgram}</div>
                        <div className="db-hint" style={{ marginTop: 4 }}>
                            <Clock size={12} /> {slot.date} · {slot.time}
                        </div>
                        <div className="db-hint" style={{ marginTop: 4 }}>
                            <strong>{slot.title}</strong> · {slot.mode} · {slot.place}
                        </div>
                        {slot.notes && <div className="db-hint" style={{ marginTop: 4 }}>{slot.notes}</div>}
                    </div>
                </div>

                {slot.hasOwnBooking ? (
                    <div className="db-note info">
                        <CheckCircle2 size={15} />
                        <span>Kamu sudah mengajukan konsultasi pada slot ini. Cek status di panel Request Saya.</span>
                    </div>
                ) : (
                    <form onSubmit={submit} style={{ display: 'grid', gap: 8 }}>
                        <label style={{ display: 'grid', gap: 4 }}>
                            <span className="db-hint" style={{ fontWeight: 700 }}>Topik</span>
                            <input
                                type="text"
                                className="db-input"
                                placeholder="Mis. konsultasi rencana studi"
                                value={form.data.topic}
                                onChange={(event) => form.setData('topic', event.target.value)}
                            />
                            {form.errors.topic && <span className="db-error">{form.errors.topic}</span>}
                            {form.errors.time && <span className="db-error">{form.errors.time}</span>}
                        </label>

                        <label style={{ display: 'grid', gap: 4 }}>
                            <span className="db-hint" style={{ fontWeight: 700 }}>Catatan (opsional)</span>
                            <textarea
                                className="db-input"
                                rows={2}
                                placeholder="Ringkasan yang ingin dibahas"
                                value={form.data.notes}
                                onChange={(event) => form.setData('notes', event.target.value)}
                            />
                            {form.errors.notes && <span className="db-error">{form.errors.notes}</span>}
                        </label>

                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <button type="submit" className="db-btn primary" disabled={form.processing}>
                                <Send size={15} /> {form.processing ? 'Mengirim…' : 'Ajukan Konsultasi'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </article>
    );
}

export default function ConsultationsIndex({ shell, student, slots, appointments, stats, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Konsultasi Dosen · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Academic"
                        title="Konsultasi Dosen"
                        description={`Pilih slot konsultasi dari dosen pengampu atau pembimbing akademik — ${student.program}.`}
                        badges={[
                            `NIM ${student.nim ?? '-'}`,
                            `Semester ${student.semester ?? '-'}`,
                            `${stats.availableSlots} slot tersedia`,
                        ]}
                    />

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CalendarDays size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.availableSlots}</span>
                                <span className="db-stat-label">Slot tersedia</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarClock size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.requested}</span>
                                <span className="db-stat-label">Menunggu</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarCheck size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.confirmed}</span>
                                <span className="db-stat-label">Terkonfirmasi</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.completed}</span>
                                <span className="db-stat-label">Selesai</span>
                            </div>
                        </div>
                    </section>

                    <div className="db-grid-2" style={{ alignItems: 'start' }}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><CalendarDays size={16} /> Slot Konsultasi</h2>
                                <span className="db-badge gray">{slots.length} slot</span>
                            </div>
                            <div className="db-card-body">
                                {slots.length === 0 ? (
                                    <EmptyState
                                        icon={CalendarDays}
                                        title="Belum ada slot konsultasi"
                                        description="Slot akan muncul setelah dosen pengampu atau pembimbing akademik membuka jadwal konsultasi."
                                    />
                                ) : (
                                    <div style={{ display: 'grid', gap: 12 }}>
                                        {slots.map((slot) => (
                                            <SlotCard key={slot.key} slot={slot} bookUrl={withId(urls.book, slot.slotId)} />
                                        ))}
                                    </div>
                                )}

                                {slots.length >= 24 && (
                                    <div className="db-hint" style={{ marginTop: 12 }}>
                                        Menampilkan 24 slot terdekat. Slot lain akan muncul setelah jadwal di atas terlewati.
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><MessageSquare size={16} /> Request Saya</h2>
                                <span className="db-badge gray">{appointments.length} request</span>
                            </div>
                            <div className="db-card-body">
                                {appointments.length === 0 ? (
                                    <EmptyState
                                        icon={Inbox}
                                        title="Belum ada request konsultasi"
                                        description="Ajukan slot di samping untuk mulai berkonsultasi dengan dosen."
                                    />
                                ) : (
                                    <div style={{ display: 'grid', gap: 12 }}>
                                        {appointments.map((appointment) => (
                                            <article key={appointment.id} className="db-card" style={{ margin: 0 }}>
                                                <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                                        <span className={`db-badge ${toneClass(appointment.statusTone)}`}>{appointment.statusLabel}</span>
                                                        <span className="db-hint"><UserRound size={12} /> {appointment.lecturer}</span>
                                                    </div>

                                                    <b style={{ color: 'var(--db-heading)', fontSize: 13.5 }}>{appointment.topic}</b>
                                                    <div className="db-hint"><Clock size={12} /> {appointment.schedule}</div>
                                                    <div className="db-hint"><MapPin size={12} /> {appointment.place}</div>

                                                    {appointment.studentNotes && (
                                                        <div className="db-hint">Catatan saya: {appointment.studentNotes}</div>
                                                    )}

                                                    {appointment.lecturerNotes && (
                                                        <div className="db-note info">
                                                            <MessageSquare size={14} />
                                                            <span>{appointment.lecturerNotes}</span>
                                                        </div>
                                                    )}

                                                    {appointment.canCancel && (
                                                        <button
                                                            type="button"
                                                            className="db-btn sm danger"
                                                            style={{ justifySelf: 'flex-start' }}
                                                            onClick={() => router.post(withId(urls.cancel, appointment.id), {}, { preserveScroll: true })}
                                                        >
                                                            <Trash2 size={13} /> Batalkan
                                                        </button>
                                                    )}
                                                </div>
                                            </article>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="db-note muted">
                        <TriangleAlert size={15} />
                        <span>
                            Konsultasi hanya bisa diajukan untuk dosen pengampu kelas yang aktif di KRS atau pembimbing akademik.
                            Batalkan request bila berhalangan agar slot bisa dipakai mahasiswa lain.
                        </span>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
