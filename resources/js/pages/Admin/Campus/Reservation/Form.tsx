// Form tambah/ubah reservasi ruangan.
import { Head, useForm } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import { useState } from 'react';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type ReservationPayload = {
    id?: number;
    room_id: number | null;
    user_id: number;
    user_label: string;
    title: string;
    purpose: string;
    reservation_date: string;
    start_time: string;
    end_time: string;
    attendee_count: number;
    status?: string;
    statusLabel?: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    reservation: ReservationPayload;
    roomOptions: { id: number; name: string; capacity: number | null }[];
    urls: { index: string; submit: string; searchUsers: string };
};

export default function ReservationForm({ shell, mode, reservation, roomOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const [userOption, setUserOption] = useState<AsyncOption | null>({ id: reservation.user_id, label: reservation.user_label });

    const form = useForm({
        room_id: reservation.room_id as number | null,
        user_id: reservation.user_id,
        title: reservation.title,
        purpose: reservation.purpose,
        reservation_date: reservation.reservation_date,
        start_time: reservation.start_time,
        end_time: reservation.end_time,
        attendee_count: reservation.attendee_count,
    });

    const selectedRoom = roomOptions.find((r) => r.id === form.data.room_id) ?? null;

    const onUserChange = (option: AsyncOption | null) => {
        setUserOption(option);
        form.setData('user_id', option ? Number(option.id) : 0);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Buat' : 'Edit'} Reservasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Kampus"
                        title={isCreate ? 'Buat Reservasi' : `Edit ${reservation.title}`}
                        description={isCreate
                            ? 'Permohonan baru selalu berstatus pending dan diverifikasi sebelum disetujui.'
                            : `Status saat ini: ${reservation.statusLabel ?? reservation.status}. Perubahan jadwal approved dicek bentrok ulang.`}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarCheck size={16} /> Form Reservasi</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Ruangan"
                                        required
                                        value={form.data.room_id === null ? '' : String(form.data.room_id)}
                                        onChange={(e) => form.setData('room_id', e.target.value === '' ? null : Number(e.target.value))}
                                        error={form.errors.room_id}
                                        hint={selectedRoom?.capacity ? `Kapasitas: ${selectedRoom.capacity} orang.` : 'Hanya ruangan aktif.'}
                                    >
                                        <option value="">Pilih ruangan</option>
                                        {roomOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <AsyncSelect
                                        label="Pemohon"
                                        required
                                        fetchUrl={urls.searchUsers}
                                        value={userOption}
                                        onChange={onUserChange}
                                        error={form.errors.user_id}
                                        hint="Ketik nama atau email (default: Anda)."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Judul kegiatan"
                                            required
                                            placeholder="Contoh: Rapat koordinasi"
                                            value={form.data.title}
                                            onChange={(e) => form.setData('title', e.target.value)}
                                            error={form.errors.title}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Tujuan / keperluan"
                                            rows={2}
                                            value={form.data.purpose}
                                            onChange={(e) => form.setData('purpose', e.target.value)}
                                            error={form.errors.purpose}
                                        />
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-res-date">Tanggal <span>*</span></label>
                                        <input
                                            id="crud-res-date"
                                            type="date"
                                            className="db-input"
                                            value={form.data.reservation_date}
                                            onChange={(e) => form.setData('reservation_date', e.target.value)}
                                        />
                                        {form.errors.reservation_date && <div className="db-error">{form.errors.reservation_date}</div>}
                                        {isCreate && <div className="crud-hint">Minimal hari ini.</div>}
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-start">Jam mulai <span>*</span></label>
                                        <input
                                            id="crud-start"
                                            type="time"
                                            className="db-input"
                                            value={form.data.start_time}
                                            onChange={(e) => form.setData('start_time', e.target.value)}
                                        />
                                        {form.errors.start_time && <div className="db-error">{form.errors.start_time}</div>}
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-end">Jam selesai <span>*</span></label>
                                        <input
                                            id="crud-end"
                                            type="time"
                                            className="db-input"
                                            value={form.data.end_time}
                                            onChange={(e) => form.setData('end_time', e.target.value)}
                                        />
                                        {form.errors.end_time && <div className="db-error">{form.errors.end_time}</div>}
                                        <div className="crud-hint">Harus setelah jam mulai.</div>
                                    </div>
                                    <TextField
                                        label="Jumlah peserta"
                                        required
                                        type="number"
                                        min={1}
                                        value={String(form.data.attendee_count)}
                                        onChange={(e) => form.setData('attendee_count', e.target.value === '' ? 1 : Number(e.target.value))}
                                        error={form.errors.attendee_count}
                                        hint={selectedRoom?.capacity ? `Maks ${selectedRoom.capacity} (kapasitas ruangan).` : undefined}
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Buat Permohonan' : 'Simpan Perubahan'}
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
