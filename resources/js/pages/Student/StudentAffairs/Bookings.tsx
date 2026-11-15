// Portal mahasiswa — booking fasilitas kampus untuk organisasi.
import { Head, router, useForm } from '@inertiajs/react';
import {
    Building2, CalendarDays, CheckCircle2, Clock3, MapPin, Plus, Users, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField, TextField } from '../../../components/Shared/Crud/fields';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StudentAffairsStats, toneClass } from '../../Admin/StudentAffairs/shared';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Booking = {
    id: number;
    reservationNumber: string;
    title: string;
    purpose: string | null;
    organization: string | null;
    room: string | null;
    building: string | null;
    capacity: number | null;
    date: string | null;
    startTime: string;
    endTime: string;
    attendeeCount: number;
    status: string;
    statusLabel: string;
    statusTone: string;
    rejectionReason: string | null;
    canCancel: boolean;
    cancelUrl: string;
};

type Props = {
    shell: ShellProps;
    student: { name: string; nim: string | null; program: string | null };
    stats: { total: number; pending: number; approved: number; upcoming: number };
    bookings: Booking[];
    organizations: Array<{ id: number; name: string }>;
    rooms: Array<{ id: number; name: string; building: string | null; capacity: number | null }>;
    urls: { index: string; store: string };
};

export default function StudentBookings({ shell, student, stats, bookings, organizations, rooms, urls }: Props) {
    const [pending, setPending] = useState<Booking | null>(null);
    const [processing, setProcessing] = useState(false);

    const form = useForm({
        student_organization_id: organizations[0] ? String(organizations[0].id) : '',
        room_id: rooms[0] ? String(rooms[0].id) : '',
        title: '',
        purpose: '',
        reservation_date: new Date(Date.now() + 86_400_000).toISOString().slice(0, 10),
        start_time: '09:00',
        end_time: '11:00',
        attendee_count: 20,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, {
            preserveScroll: true,
            onSuccess: () => form.reset('title', 'purpose'),
        });
    };

    const cancelBooking = () => {
        if (!pending) return;
        setProcessing(true);
        router.post(pending.cancelUrl, {}, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        });
    };

    const selectedRoom = rooms.find((room) => String(room.id) === form.data.room_id);

    return (
        <AdminShell shell={shell}>
            <Head title={`Booking Fasilitas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Building2}
                        eyebrow="Kemahasiswaan"
                        title="Booking Fasilitas"
                        description={`Ajukan pemakaian ruangan untuk kegiatan organisasi. ${student.name}${student.nim ? ` · ${student.nim}` : ''}`}
                        badges={[student.program || 'Program studi belum tersedia']}
                    />

                    <StudentAffairsStats
                        label="Ringkasan booking fasilitas"
                        items={[
                            { label: 'Total booking', value: stats.total, icon: Building2 },
                            { label: 'Menunggu', value: stats.pending, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui', value: stats.approved, icon: CheckCircle2, tone: 'green' },
                            { label: 'Aktif', value: stats.upcoming, icon: CalendarDays, tone: 'green' },
                        ]}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarDays size={15} /> Booking Saya</h2>
                                    <span className="db-badge gray">{bookings.length} pengajuan</span>
                                </div>
                                <div className="db-card-body">
                                    {bookings.length === 0 ? (
                                        <EmptyState
                                            icon={Building2}
                                            iconSize={24}
                                            title="Belum ada booking"
                                            description="Ajukan pemakaian ruangan melalui formulir di samping. Booking akan ditinjau admin kemahasiswaan."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 10 }}>
                                            {bookings.map((booking) => (
                                                <article key={booking.id} className="db-fact wide" style={{ display: 'grid', gap: 7 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                        <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{booking.title}</b>
                                                        <span className={`db-badge ${toneClass(booking.statusTone)}`}>{booking.statusLabel}</span>
                                                    </div>
                                                    <span className="db-hint">{booking.reservationNumber} · {booking.organization || 'Organisasi'}</span>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <MapPin size={12} /> {booking.room || 'Ruangan'}{booking.building ? ` · ${booking.building}` : ''}
                                                        </span>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <CalendarDays size={12} /> {booking.date || '—'} · {booking.startTime}–{booking.endTime} WIB
                                                        </span>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <Users size={12} /> {booking.attendeeCount} peserta
                                                        </span>
                                                    </div>
                                                    {booking.purpose && <span className="db-hint">{booking.purpose}</span>}
                                                    {booking.status === 'rejected' && booking.rejectionReason && (
                                                        <span className="db-hint" style={{ color: 'var(--db-red)' }}>
                                                            Alasan penolakan: {booking.rejectionReason}
                                                        </span>
                                                    )}
                                                    {booking.canCancel && (
                                                        <button className="db-btn ghost sm" type="button" style={{ justifySelf: 'start' }} onClick={() => setPending(booking)}>
                                                            <XCircle size={13} /> Batalkan Booking
                                                        </button>
                                                    )}
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><Plus size={15} /> Ajukan Booking</h2></div>
                                <div className="db-card-body">
                                    {organizations.length === 0 ? (
                                        <div className="adm-note" style={{ margin: 0 }}>
                                            <Users size={16} />
                                            <div>
                                                <b style={{ display: 'block', marginBottom: 2, color: 'var(--db-heading)' }}>Anda belum terdaftar di organisasi.</b>
                                                Booking fasilitas hanya dapat diajukan oleh anggota aktif organisasi mahasiswa. Hubungi admin kemahasiswaan untuk pendaftaran keanggotaan.
                                            </div>
                                        </div>
                                    ) : (
                                        <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                            <SelectField
                                                label="Organisasi"
                                                required
                                                value={form.data.student_organization_id}
                                                onChange={(event) => form.setData('student_organization_id', event.target.value)}
                                                error={form.errors.student_organization_id}
                                            >
                                                {organizations.map((organization) => (
                                                    <option key={organization.id} value={organization.id}>{organization.name}</option>
                                                ))}
                                            </SelectField>
                                            <SelectField
                                                label="Ruangan"
                                                required
                                                value={form.data.room_id}
                                                onChange={(event) => form.setData('room_id', event.target.value)}
                                                error={form.errors.room_id}
                                                hint={selectedRoom?.capacity ? `Kapasitas ${selectedRoom.capacity} orang` : undefined}
                                            >
                                                {rooms.map((room) => (
                                                    <option key={room.id} value={room.id}>
                                                        {room.name}{room.building ? ` · ${room.building}` : ''}{room.capacity ? ` (${room.capacity})` : ''}
                                                    </option>
                                                ))}
                                            </SelectField>
                                            <TextField
                                                label="Judul kegiatan"
                                                required
                                                value={form.data.title}
                                                onChange={(event) => form.setData('title', event.target.value)}
                                                error={form.errors.title}
                                                placeholder="Contoh: Rapat kerja pengurus HIMA"
                                            />
                                            <TextareaField
                                                label="Tujuan"
                                                rows={2}
                                                value={form.data.purpose}
                                                onChange={(event) => form.setData('purpose', event.target.value)}
                                                error={form.errors.purpose}
                                            />
                                            <div className="crud-grid">
                                                <TextField
                                                    label="Tanggal"
                                                    type="date"
                                                    required
                                                    value={form.data.reservation_date}
                                                    onChange={(event) => form.setData('reservation_date', event.target.value)}
                                                    error={form.errors.reservation_date}
                                                />
                                                <TextField
                                                    label="Peserta"
                                                    type="number"
                                                    min={1}
                                                    required
                                                    value={form.data.attendee_count}
                                                    onChange={(event) => form.setData('attendee_count', Number(event.target.value))}
                                                    error={form.errors.attendee_count}
                                                />
                                                <TextField
                                                    label="Mulai"
                                                    type="time"
                                                    required
                                                    value={form.data.start_time}
                                                    onChange={(event) => form.setData('start_time', event.target.value)}
                                                    error={form.errors.start_time}
                                                />
                                                <TextField
                                                    label="Selesai"
                                                    type="time"
                                                    required
                                                    value={form.data.end_time}
                                                    onChange={(event) => form.setData('end_time', event.target.value)}
                                                    error={form.errors.end_time}
                                                />
                                            </div>
                                            <div className="crud-actions">
                                                <button className="db-btn primary" type="submit" disabled={form.processing}>
                                                    Ajukan Booking
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </section>

                            <div className="adm-note gold" style={{ margin: 0 }}>
                                <Clock3 size={16} />
                                <div>
                                    <b style={{ display: 'block', marginBottom: 2, color: 'var(--db-heading)' }}>Alur persetujuan.</b>
                                    Booking berstatus menunggu akan ditinjau admin kemahasiswaan. Sistem menolak pengajuan yang bentrok dengan jadwal kuliah atau reservasi lain yang sudah disetujui.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title="Batalkan booking?"
                    message={pending ? `Booking "${pending.title}" (${pending.reservationNumber}) akan dibatalkan.` : ''}
                    confirmLabel="Ya, batalkan"
                    danger
                    processing={processing}
                    onConfirm={cancelBooking}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
