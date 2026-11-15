// Form tambah/ubah jadwal — offering async + dosen terkendali.
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    schedule: {
        id?: number; course_offering_id: number | string; lecturer_profile_id: number | string;
        room_id: number | string; day_of_week: string; start_time: string; end_time: string;
        session_type: string; delivery_mode: string; meeting_link: string; notes: string;
        is_active: boolean;
    };
    options: {
        rooms: { id: number; label: string }[];
        offeringLecturers: { id: number; label: string }[];
    };
    presetOffering: { id: number; label: string } | null;
    days: string[];
    sessionTypes: string[];
    deliveryModes: string[];
    returnTo: string;
    urls: { index: string; submit: string; searchOfferings: string };
};

export default function CourseScheduleForm({ shell, mode, schedule, options, presetOffering, days, sessionTypes, deliveryModes, returnTo, urls }: Props) {
    const isCreate = mode === 'create';
    const [offering, setOffering] = useState<AsyncOption | null>(presetOffering);
    const [lecturers, setLecturers] = useState<{ id: number; label: string }[]>(options.offeringLecturers);
    const [offeringLocked, setOfferingLocked] = useState(presetOffering !== null || !isCreate);

    const form = useForm({
        course_offering_id: schedule.course_offering_id as number | '',
        lecturer_profile_id: schedule.lecturer_profile_id as number | '',
        room_id: schedule.room_id as number | '',
        day_of_week: schedule.day_of_week,
        start_time: schedule.start_time,
        end_time: schedule.end_time,
        session_type: schedule.session_type,
        delivery_mode: schedule.delivery_mode,
        meeting_link: schedule.meeting_link,
        notes: schedule.notes,
        is_active: schedule.is_active,
        return: returnTo,
    });

    const loadLecturers = async (offeringId: number | '') => {
        if (!offeringId) {
            setLecturers([]);
            return;
        }
        const response = await fetch(`/admin/academic/course-schedules/offering-lecturers/${offeringId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await response.json();
        setLecturers(json.options ?? []);
    };

    useEffect(() => {
        if (!isCreate && schedule.course_offering_id) {
            setOfferingLocked(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const pickOffering = (option: AsyncOption | null) => {
        setOffering(option);
        const id = option ? Number(option.id) : '';
        form.setData('course_offering_id', id);
        form.setData('lecturer_profile_id', '');
        loadLecturers(id);
        if (option) setOfferingLocked(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const errors = form.errors as Record<string, string>;
    const backHref = returnTo === 'workspace' && form.data.course_offering_id
        ? `/admin/academic/course-offerings/${form.data.course_offering_id}`
        : urls.index;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Jadwal · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Jadwal' : 'Edit Jadwal'}
                        description="Dosen hanya bisa dipilih dari yang terdaftar di kelas. Bentrok ruang/dosen ditolak otomatis."
                        actions={<a className="db-btn light" href={backHref}>Kembali</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Form Jadwal</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        {!offeringLocked ? (
                                            <AsyncSelect
                                                label="Kelas penawaran"
                                                required
                                                fetchUrl={urls.searchOfferings}
                                                value={offering}
                                                onChange={pickOffering}
                                                placeholder="Ketik kode/nama MK…"
                                                error={errors.course_offering_id}
                                            />
                                        ) : (
                                            <div>
                                                <span className="crud-label">Kelas penawaran</span>
                                                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                                    <span className="db-badge">{offering ? offering.label : `Kelas #${form.data.course_offering_id}`}</span>
                                                    {isCreate && (
                                                        <button className="db-btn ghost sm" type="button" onClick={() => { setOffering(null); setOfferingLocked(false); form.setData('course_offering_id', ''); }}>
                                                            Ganti
                                                        </button>
                                                    )}
                                                </div>
                                                {errors.course_offering_id && <div className="db-error">{errors.course_offering_id}</div>}
                                            </div>
                                        )}
                                    </div>
                                    <SelectField
                                        label="Dosen"
                                        value={form.data.lecturer_profile_id}
                                        onChange={(e) => form.setData('lecturer_profile_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={errors.lecturer_profile_id}
                                        hint="Hanya dosen kelas ini. Kosongkan untuk Jadwal Umum."
                                    >
                                        <option value="">Jadwal Umum (tanpa dosen)</option>
                                        {lecturers.map((lecturer) => <option key={lecturer.id} value={lecturer.id}>{lecturer.label}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Ruang"
                                        value={form.data.room_id}
                                        onChange={(e) => form.setData('room_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={errors.room_id}
                                        hint="Kosongkan untuk kelas online."
                                    >
                                        <option value="">— Tanpa ruang —</option>
                                        {options.rooms.map((room) => <option key={room.id} value={room.id}>{room.label}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Hari"
                                        required
                                        value={form.data.day_of_week}
                                        onChange={(e) => form.setData('day_of_week', e.target.value)}
                                        error={errors.day_of_week}
                                    >
                                        {days.map((day) => <option key={day} value={day}>{day}</option>)}
                                    </SelectField>
                                    <div className="crud-grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                        <TextField
                                            label="Mulai"
                                            required
                                            type="time"
                                            value={form.data.start_time}
                                            onChange={(e) => form.setData('start_time', e.target.value)}
                                            error={errors.start_time}
                                        />
                                        <TextField
                                            label="Selesai"
                                            required
                                            type="time"
                                            value={form.data.end_time}
                                            onChange={(e) => form.setData('end_time', e.target.value)}
                                            error={errors.end_time}
                                        />
                                    </div>
                                    <SelectField
                                        label="Tipe sesi"
                                        required
                                        value={form.data.session_type}
                                        onChange={(e) => form.setData('session_type', e.target.value)}
                                        error={form.errors.session_type}
                                    >
                                        {sessionTypes.map((type) => <option key={type} value={type}>{type}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Mode"
                                        required
                                        value={form.data.delivery_mode}
                                        onChange={(e) => form.setData('delivery_mode', e.target.value)}
                                        error={form.errors.delivery_mode}
                                    >
                                        {deliveryModes.map((m) => <option key={m} value={m}>{m}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Link meeting"
                                        value={form.data.meeting_link}
                                        onChange={(e) => form.setData('meeting_link', e.target.value)}
                                        error={errors.meeting_link}
                                    />
                                    <SwitchField
                                        label="Aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={errors.is_active}
                                        hint="Nonaktif dikecualikan dari generate sesi."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={errors.notes}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={backHref}
                                    submitLabel={isCreate ? 'Simpan Jadwal' : 'Simpan Perubahan'}
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
