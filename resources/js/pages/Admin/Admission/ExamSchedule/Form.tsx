// Form tambah/ubah jadwal ujian & seleksi PMB.
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Schedule = {
    id?: number;
    admission_period_id: number | string;
    exam_type: string;
    title: string;
    exam_date: string | null;
    exam_time: string | null;
    venue: string;
    meeting_link: string;
    quota: number;
    notes: string;
    is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    schedule: Schedule;
    periodOptions: { id: number; label: string }[];
    examTypeOptions: { id: string; label: string }[];
    urls: { index: string; submit: string };
};

export default function AdmissionExamScheduleForm({ shell, mode, schedule, periodOptions, examTypeOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        admission_period_id: schedule.admission_period_id,
        exam_type: schedule.exam_type,
        title: schedule.title,
        exam_date: schedule.exam_date ?? '',
        exam_time: schedule.exam_time ?? '',
        venue: schedule.venue ?? '',
        meeting_link: schedule.meeting_link ?? '',
        quota: schedule.quota,
        notes: schedule.notes ?? '',
        is_active: schedule.is_active,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Jadwal Ujian · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Admission"
                        title={isCreate ? 'Tambah Jadwal Ujian' : 'Edit Jadwal Ujian'}
                        description="Tentukan periode, jenis seleksi, waktu pelaksanaan, kuota peserta, serta lokasi atau tautan daring."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Informasi Jadwal</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Periode PMB"
                                        required
                                        value={form.data.admission_period_id}
                                        onChange={(e) => form.setData('admission_period_id', e.target.value)}
                                        error={form.errors.admission_period_id}
                                    >
                                        <option value="">-- Pilih periode --</option>
                                        {periodOptions.map((period) => (
                                            <option key={period.id} value={period.id}>{period.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Jenis seleksi / ujian"
                                        required
                                        value={form.data.exam_type}
                                        onChange={(e) => form.setData('exam_type', e.target.value)}
                                        error={form.errors.exam_type}
                                    >
                                        {examTypeOptions.map((type) => (
                                            <option key={type.id} value={type.id}>{type.label}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Judul jadwal"
                                        required
                                        placeholder="Contoh: Tes Tertulis Gelombang 1"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        error={form.errors.title}
                                    />
                                    <TextField
                                        label="Kuota peserta"
                                        required
                                        type="number"
                                        min={0}
                                        value={String(form.data.quota)}
                                        onChange={(e) => form.setData('quota', e.target.value === '' ? 0 : Number(e.target.value))}
                                        error={form.errors.quota}
                                        hint="Isi 0 bila tidak dibatasi."
                                    />
                                    <TextField
                                        label="Tanggal ujian"
                                        required
                                        type="date"
                                        value={form.data.exam_date}
                                        onChange={(e) => form.setData('exam_date', e.target.value)}
                                        error={form.errors.exam_date}
                                    />
                                    <TextField
                                        label="Jam mulai"
                                        required
                                        type="time"
                                        value={form.data.exam_time}
                                        onChange={(e) => form.setData('exam_time', e.target.value)}
                                        error={form.errors.exam_time}
                                    />
                                    <TextField
                                        label="Lokasi / ruangan"
                                        placeholder="Contoh: Gedung A - Ruang 101"
                                        value={form.data.venue}
                                        onChange={(e) => form.setData('venue', e.target.value)}
                                        error={form.errors.venue}
                                    />
                                    <TextField
                                        label="Tautan daring"
                                        type="url"
                                        placeholder="https://meet.example.com/..."
                                        value={form.data.meeting_link}
                                        onChange={(e) => form.setData('meeting_link', e.target.value)}
                                        error={form.errors.meeting_link}
                                        hint="Opsional, untuk ujian atau wawancara online."
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Jadwal nonaktif tidak ditampilkan ke pendaftar."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            placeholder="Instruksi tambahan untuk peserta atau panitia…"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={form.errors.notes}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
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
