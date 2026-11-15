// Form tambah/ubah periode EDOM.
import { Head, useForm } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string | number; label: string };
type AcademicYear = { id: number; name: string; code: string };
type AcademicPeriod = { id: number; name: string; type: string };

type PeriodPayload = {
    id?: number;
    name: string;
    code: string;
    academic_year_id: number | string;
    academic_period_id: number | string;
    starts_at: string;
    ends_at: string;
    status: string;
    minimum_responses: number;
    notes: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    period: PeriodPayload;
    academicYears: AcademicYear[];
    academicPeriods: AcademicPeriod[];
    statuses: Option[];
    urls: { index: string; submit: string };
};

export default function EdomPeriodForm({ shell, mode, period, academicYears, academicPeriods, statuses, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: period.name,
        code: period.code,
        academic_year_id: period.academic_year_id === '' || period.academic_year_id === null ? '' : String(period.academic_year_id),
        academic_period_id: period.academic_period_id === '' || period.academic_period_id === null ? '' : String(period.academic_period_id),
        starts_at: period.starts_at,
        ends_at: period.ends_at,
        status: period.status,
        minimum_responses: period.minimum_responses,
        notes: period.notes,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
            return;
        }

        form.put(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Periode EDOM · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarRange}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Periode EDOM' : `Edit ${period.name}`}
                        description="Atur jadwal pengisian kuesioner EDOM, status periode, dan batas minimal respons mahasiswa."
                        badges={isCreate ? ['Periode baru'] : [period.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarRange size={16} /> Parameter Periode</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama periode"
                                        required
                                        placeholder="Contoh: EDOM Semester Ganjil 2025/2026"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode periode"
                                        required
                                        placeholder="Contoh: EDOM_20251"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        hint="Kode unik untuk identifikasi periode."
                                    />
                                    <SelectField
                                        label="Status periode"
                                        required
                                        value={form.data.status}
                                        onChange={(event) => form.setData('status', event.target.value)}
                                        error={form.errors.status}
                                    >
                                        {statuses.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Tahun akademik"
                                        value={form.data.academic_year_id}
                                        onChange={(event) => form.setData('academic_year_id', event.target.value)}
                                        error={form.errors.academic_year_id}
                                    >
                                        <option value="">Tidak dibatasi</option>
                                        {academicYears.map((year) => (
                                            <option key={year.id} value={year.id}>{year.name}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Periode akademik (semester)"
                                        value={form.data.academic_period_id}
                                        onChange={(event) => form.setData('academic_period_id', event.target.value)}
                                        error={form.errors.academic_period_id}
                                    >
                                        <option value="">Tidak dibatasi</option>
                                        {academicPeriods.map((item) => (
                                            <option key={item.id} value={item.id}>{item.name} - {item.type}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Minimal respons mahasiswa"
                                        required
                                        type="number"
                                        min={1}
                                        value={form.data.minimum_responses}
                                        onChange={(event) => form.setData('minimum_responses', event.target.value === '' ? 1 : Number(event.target.value))}
                                        error={form.errors.minimum_responses}
                                        hint="Batas minimal suara agar nilai EDOM dosen dianggap valid."
                                    />
                                    <TextField
                                        label="Tanggal mulai evaluasi"
                                        type="date"
                                        value={form.data.starts_at}
                                        onChange={(event) => form.setData('starts_at', event.target.value)}
                                        error={form.errors.starts_at}
                                    />
                                    <TextField
                                        label="Tanggal selesai evaluasi"
                                        type="date"
                                        value={form.data.ends_at}
                                        onChange={(event) => form.setData('ends_at', event.target.value)}
                                        error={form.errors.ends_at}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan tambahan"
                                            rows={3}
                                            placeholder="Tuliskan keterangan tambahan untuk periode EDOM ini…"
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            error={form.errors.notes}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Periode' : 'Simpan Perubahan'}
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
