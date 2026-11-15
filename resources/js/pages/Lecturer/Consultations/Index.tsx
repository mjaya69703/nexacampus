// Konsultasi dosen — kelola jadwal mingguan dan proses permintaan konsultasi mahasiswa.
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarClock, CalendarDays, Check, CheckCircle2, Clock, FileText, MapPin, Pencil, Plus, Send, Trash2, User, Video, X, XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Slot = {
    id: number; title: string; weekday: string; time: string; slotMinutes: number; capacity: number;
    mode: string; location: string; isActive: boolean; upcomingCount: number;
};

type Appointment = {
    id: number; student: string; nim: string; studyProgram: string; topic: string;
    studentNotes: string | null; lecturerNotes: string | null; status: string; statusLabel: string;
    schedule: string; mode: string; place: string; statusUrl: string; noteUrl: string;
};

type Option = { value: number | string; label: string };

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    stats: { activeSlots: number; requested: number; confirmed: number; completed: number };
    slots: Slot[];
    appointments: Appointment[];
    weekdays: Option[];
    modes: Option[];
    urls: {
        index: string; storeSlot: string; updateSlot: string; toggleSlot: string; deleteSlot: string;
    };
};

type SlotFormData = {
    title: string; weekday: string | number; startTime: string; endTime: string; slotMinutes: number;
    capacity: number; consultationMode: string; location: string; meetingLink: string;
    startsOn: string; endsOn: string; notes: string; isActive: boolean;
};

const appointmentTone = (status: string) => {
    if (status === 'confirmed') return 'green';
    if (status === 'requested') return 'amber';
    if (status === 'completed') return 'gray';
    if (['cancelled', 'rejected', 'no_show'].includes(status)) return 'red';
    return 'gray';
};

function SlotEditor({ form, weekdays, modes, onSubmit, submitLabel, onCancel }: {
    form: ReturnType<typeof useForm<SlotFormData>>;
    weekdays: Option[];
    modes: Option[];
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    onCancel: () => void;
}) {
    return (
        <form onSubmit={onSubmit} style={{ display: 'grid', gap: 12 }}>
            <div className="crud-grid">
                <div style={{ gridColumn: '1 / -1' }}>
                    <label className="crud-label">Nama jadwal <span>*</span></label>
                    <input className="db-input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} placeholder="Konsultasi akademik mingguan" />
                    {form.errors.title && <div className="db-error">{form.errors.title}</div>}
                </div>
                <div>
                    <label className="crud-label">Hari <span>*</span></label>
                    <select className="db-input" value={form.data.weekday} onChange={(event) => form.setData('weekday', Number(event.target.value))}>
                        {weekdays.map((day) => <option key={day.value} value={day.value}>{day.label}</option>)}
                    </select>
                    {form.errors.weekday && <div className="db-error">{form.errors.weekday}</div>}
                </div>
                <div>
                    <label className="crud-label">Mode <span>*</span></label>
                    <select className="db-input" value={form.data.consultationMode} onChange={(event) => form.setData('consultationMode', event.target.value)}>
                        {modes.map((mode) => <option key={mode.value} value={mode.value}>{mode.label}</option>)}
                    </select>
                    {form.errors.consultationMode && <div className="db-error">{form.errors.consultationMode}</div>}
                </div>
                <div>
                    <label className="crud-label">Mulai <span>*</span></label>
                    <input type="time" className="db-input" value={form.data.startTime} onChange={(event) => form.setData('startTime', event.target.value)} />
                    {form.errors.startTime && <div className="db-error">{form.errors.startTime}</div>}
                </div>
                <div>
                    <label className="crud-label">Selesai <span>*</span></label>
                    <input type="time" className="db-input" value={form.data.endTime} onChange={(event) => form.setData('endTime', event.target.value)} />
                    {form.errors.endTime && <div className="db-error">{form.errors.endTime}</div>}
                </div>
                <div>
                    <label className="crud-label">Durasi per sesi (menit) <span>*</span></label>
                    <input type="number" min={15} max={180} className="db-input" value={form.data.slotMinutes} onChange={(event) => form.setData('slotMinutes', Number(event.target.value))} />
                    {form.errors.slotMinutes && <div className="db-error">{form.errors.slotMinutes}</div>}
                </div>
                <div>
                    <label className="crud-label">Kapasitas <span>*</span></label>
                    <input type="number" min={1} max={20} className="db-input" value={form.data.capacity} onChange={(event) => form.setData('capacity', Number(event.target.value))} />
                    {form.errors.capacity && <div className="db-error">{form.errors.capacity}</div>}
                </div>
                <div>
                    <label className="crud-label">Lokasi</label>
                    <input className="db-input" value={form.data.location} onChange={(event) => form.setData('location', event.target.value)} placeholder="Ruang dosen / gedung" />
                    {form.errors.location && <div className="db-error">{form.errors.location}</div>}
                </div>
                <div>
                    <label className="crud-label">Tautan daring</label>
                    <input className="db-input" value={form.data.meetingLink} onChange={(event) => form.setData('meetingLink', event.target.value)} placeholder="https://meet…" />
                    {form.errors.meetingLink && <div className="db-error">{form.errors.meetingLink}</div>}
                </div>
                <div>
                    <label className="crud-label">Berlaku mulai</label>
                    <input type="date" className="db-input" value={form.data.startsOn} onChange={(event) => form.setData('startsOn', event.target.value)} />
                    {form.errors.startsOn && <div className="db-error">{form.errors.startsOn}</div>}
                </div>
                <div>
                    <label className="crud-label">Berlaku sampai</label>
                    <input type="date" className="db-input" value={form.data.endsOn} onChange={(event) => form.setData('endsOn', event.target.value)} />
                    {form.errors.endsOn && <div className="db-error">{form.errors.endsOn}</div>}
                </div>
                <div style={{ gridColumn: '1 / -1' }}>
                    <label className="crud-label">Catatan</label>
                    <textarea className="db-input" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </div>
                <label className="db-summary" style={{ cursor: 'pointer' }}>
                    <div><b>Aktifkan jadwal</b><small>Jadwal aktif dapat dipesan mahasiswa.</small></div>
                    <input type="checkbox" checked={form.data.isActive} onChange={(event) => form.setData('isActive', event.target.checked)} />
                </label>
            </div>
            <div style={{ display: 'flex', gap: 8 }}>
                <button className="db-btn primary sm" type="submit" disabled={form.processing}><Check size={12} /> {form.processing ? 'Menyimpan…' : submitLabel}</button>
                <button className="db-btn ghost sm" type="button" onClick={onCancel}>Batal</button>
            </div>
        </form>
    );
}

function SlotRow({ slot, weekdays, modes, urls }: { slot: Slot; weekdays: Option[]; modes: Option[]; urls: Props['urls'] }) {
    const [editing, setEditing] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [start, end] = slot.time.split(' - ');
    const modeValue = modes.find((mode) => mode.label === slot.mode)?.value ?? 'in_person';
    const weekdayValue = weekdays.find((day) => day.label === slot.weekday)?.value ?? 1;

    const form = useForm<SlotFormData>({
        title: slot.title, weekday: weekdayValue, startTime: start ?? '09:00', endTime: end ?? '10:00',
        slotMinutes: slot.slotMinutes, capacity: slot.capacity, consultationMode: String(modeValue),
        location: slot.location === '-' ? '' : slot.location, meetingLink: '', startsOn: '', endsOn: '', notes: '', isActive: slot.isActive,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.updateSlot.replace('__SLOT__', String(slot.id)), { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const toggle = () => router.post(urls.toggleSlot.replace('__SLOT__', String(slot.id)), {}, { preserveScroll: true });
    const destroy = () => router.delete(urls.deleteSlot.replace('__SLOT__', String(slot.id)), { preserveScroll: true, onFinish: () => setConfirmDelete(false) });

    return (
        <article className="db-class">
            <div className="db-class-top">
                <div style={{ minWidth: 0 }}>
                    <b>{slot.title}</b>
                    <small>{slot.weekday} · {slot.time} · {slot.slotMinutes} menit/sesi · kapasitas {slot.capacity}</small>
                </div>
                <span className={`db-badge ${slot.isActive ? 'green' : 'gray'}`}>{slot.isActive ? 'Aktif' : 'Nonaktif'}</span>
            </div>
            <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', fontSize: 13, color: 'var(--db-muted)', marginTop: 6 }}>
                <span>{slot.mode}</span>
                <span><MapPin size={12} /> {slot.location}</span>
                <span><CalendarClock size={12} /> {slot.upcomingCount} konsultasi mendatang</span>
            </div>
            <div className="db-class-actions">
                <button className="db-btn ghost sm" type="button" onClick={() => setEditing((value) => !value)}><Pencil size={12} /> {editing ? 'Tutup' : 'Edit'}</button>
                <button className="db-btn ghost sm" type="button" onClick={toggle}>{slot.isActive ? <X size={12} /> : <Check size={12} />} {slot.isActive ? 'Nonaktifkan' : 'Aktifkan'}</button>
                {!confirmDelete ? (
                    <button className="db-btn danger sm" type="button" onClick={() => setConfirmDelete(true)}><Trash2 size={12} /> Hapus</button>
                ) : (
                    <span style={{ display: 'inline-flex', gap: 6, alignItems: 'center' }}>
                        <span className="db-hint">Yakin?</span>
                        <button className="db-btn danger sm" type="button" onClick={destroy}>Ya</button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(false)}>Batal</button>
                    </span>
                )}
            </div>
            {editing && (
                <div style={{ marginTop: 12, borderTop: '1px solid var(--db-line)', paddingTop: 12 }}>
                    <SlotEditor form={form} weekdays={weekdays} modes={modes} onSubmit={submit} submitLabel="Simpan jadwal" onCancel={() => setEditing(false)} />
                </div>
            )}
        </article>
    );
}

function AppointmentNote({ appointment }: { appointment: Appointment }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ lecturerNotes: string }>({ lecturerNotes: appointment.lecturerNotes ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(appointment.noteUrl, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <>
            <button className="db-btn ghost sm" type="button" onClick={() => setOpen((value) => !value)}><Pencil size={12} /> Catatan</button>
            {open && (
                <form onSubmit={submit} style={{ display: 'grid', gap: 8, marginTop: 8, width: '100%' }}>
                    <textarea className="db-input" rows={2} value={form.data.lecturerNotes} onChange={(event) => form.setData('lecturerNotes', event.target.value)} placeholder="Hasil / catatan konsultasi" />
                    {form.errors.lecturerNotes && <div className="db-error">{form.errors.lecturerNotes}</div>}
                    <div style={{ display: 'flex', gap: 6 }}>
                        <button className="db-btn primary sm" type="submit" disabled={form.processing}><Send size={12} /> Simpan</button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setOpen(false)}>Batal</button>
                    </div>
                </form>
            )}
        </>
    );
}

export default function ConsultationsIndex({ shell, hasProfile, stats, slots, appointments, weekdays, modes, urls }: Props) {
    const [creating, setCreating] = useState(false);
    const createForm = useForm<SlotFormData>({
        title: '', weekday: 1, startTime: '09:00', endTime: '11:00', slotMinutes: 30, capacity: 2,
        consultationMode: 'in_person', location: '', meetingLink: '', startsOn: '', endsOn: '', notes: '', isActive: true,
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(urls.storeSlot, { preserveScroll: true, onSuccess: () => { createForm.reset(); setCreating(false); } });
    };

    const setAppointmentStatus = (appointment: Appointment, status: string) => {
        router.post(appointment.statusUrl, { status }, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Konsultasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarClock}
                        eyebrow="Akademik · Konsultasi"
                        title="Konsultasi Mahasiswa"
                        description="Atur jadwal mingguan dan proses permintaan konsultasi mahasiswa."
                        badges={[`${stats.activeSlots} jadwal aktif`, `${stats.requested} menunggu`, `${stats.confirmed} terkonfirmasi`]}
                        actions={hasProfile ? <button className="db-btn light" type="button" onClick={() => setCreating((value) => !value)}><Plus size={15} /> {creating ? 'Tutup form' : 'Tambah jadwal'}</button> : undefined}
                    />

                    {!hasProfile && (
                        <div className="db-note warning"><User size={15} /><span>Profil dosen belum tersedia sehingga jadwal konsultasi belum dapat dikelola. Hubungi administrator untuk melengkapi data.</span></div>
                    )}

                    <section className="db-stats" aria-label="Ringkasan konsultasi">
                        <div className="db-stat"><span className="db-stat-icon"><CalendarDays size={20} /></span><div><span className="db-stat-num">{stats.activeSlots}</span><span className="db-stat-label">Jadwal aktif</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon gold"><Clock size={20} /></span><div><span className="db-stat-num">{stats.requested}</span><span className="db-stat-label">Menunggu</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon green"><CheckCircle2 size={20} /></span><div><span className="db-stat-num">{stats.confirmed}</span><span className="db-stat-label">Terkonfirmasi</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><Check size={20} /></span><div><span className="db-stat-num">{stats.completed}</span><span className="db-stat-label">Selesai bulan ini</span></div></div>
                    </section>

                    {creating && (
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title"><Plus size={16} /> Jadwal konsultasi baru</h2></div>
                            <div className="db-card-body">
                                <SlotEditor form={createForm} weekdays={weekdays} modes={modes} onSubmit={submitCreate} submitLabel="Simpan jadwal" onCancel={() => setCreating(false)} />
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><CalendarDays size={16} /> Jadwal mingguan ({slots.length})</h2></div>
                        <div className="db-card-body">
                            {slots.length === 0 ? (
                                <EmptyState icon={CalendarDays} title="Belum ada jadwal" description="Tambahkan jadwal konsultasi agar mahasiswa dapat memesan." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {slots.map((slot) => <SlotRow key={slot.id} slot={slot} weekdays={weekdays} modes={modes} urls={urls} />)}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><User size={16} /> Permintaan konsultasi ({appointments.length})</h2></div>
                        <div className="db-card-body">
                            {appointments.length === 0 ? (
                                <EmptyState icon={CalendarClock} title="Tidak ada permintaan" description="Permintaan konsultasi mahasiswa akan tampil di sini." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {appointments.map((appointment) => (
                                        <article className="db-class" key={appointment.id}>
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{appointment.student}</b>
                                                    <small>{appointment.nim} · {appointment.studyProgram} · {appointment.schedule}</small>
                                                </div>
                                                <span className={`db-badge ${appointmentTone(appointment.status)}`}>{appointment.statusLabel}</span>
                                            </div>
                                            <div style={{ display: 'grid', gap: 4, marginTop: 6, fontSize: 13 }}>
                                                <div><b>Topik:</b> {appointment.topic}</div>
                                                <div className="db-hint"><Video size={12} /> {appointment.mode} · <MapPin size={12} /> {appointment.place}</div>
                                                {appointment.studentNotes && <div className="db-note muted"><FileText size={13} /><span>{appointment.studentNotes}</span></div>}
                                                {appointment.lecturerNotes && <div className="db-hint">Catatan Anda: {appointment.lecturerNotes}</div>}
                                            </div>
                                            <div className="db-class-actions" style={{ flexWrap: 'wrap' }}>
                                                {appointment.status === 'requested' && (
                                                    <>
                                                        <button className="db-btn primary sm" type="button" onClick={() => setAppointmentStatus(appointment, 'confirmed')}><Check size={12} /> Konfirmasi</button>
                                                        <button className="db-btn danger sm" type="button" onClick={() => setAppointmentStatus(appointment, 'rejected')}><XCircle size={12} /> Tolak</button>
                                                    </>
                                                )}
                                                {appointment.status === 'confirmed' && (
                                                    <>
                                                        <button className="db-btn primary sm" type="button" onClick={() => setAppointmentStatus(appointment, 'completed')}><CheckCircle2 size={12} /> Selesai</button>
                                                        <button className="db-btn ghost sm" type="button" onClick={() => setAppointmentStatus(appointment, 'no_show')}><X size={12} /> Tidak hadir</button>
                                                        <button className="db-btn danger sm" type="button" onClick={() => setAppointmentStatus(appointment, 'cancelled')}><XCircle size={12} /> Batalkan</button>
                                                    </>
                                                )}
                                                <AppointmentNote appointment={appointment} />
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
