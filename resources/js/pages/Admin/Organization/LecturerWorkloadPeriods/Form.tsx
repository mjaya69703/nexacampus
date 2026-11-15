// Form tambah/ubah periode Beban Kerja Dosen (BKD).
import { Head, useForm } from '@inertiajs/react';
import { CalendarCheck, Info } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type PeriodPayload = {
    id?: number;
    academic_year_id: string;
    academic_period_id: string;
    name: string;
    code: string;
    starts_at: string;
    ends_at: string;
    status: string;
    minimum_sks: number | string;
    maximum_sks: number | string;
    notes: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    period: PeriodPayload;
    statuses: Option[];
    academicYears: Option[];
    academicPeriods: Option[];
    urls: { index: string; show?: string; submit: string };
};

export default function LecturerWorkloadPeriodForm({ shell, mode, period, statuses, academicYears, academicPeriods, urls }: Props) {
    const isCreate = mode === 'create';
    const cancelHref = !isCreate && urls.show ? urls.show : urls.index;

    const form = useForm({
        academic_year_id: period.academic_year_id,
        academic_period_id: period.academic_period_id,
        name: period.name,
        code: period.code,
        starts_at: period.starts_at,
        ends_at: period.ends_at,
        status: period.status,
        minimum_sks: period.minimum_sks,
        maximum_sks: period.maximum_sks,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Periode BKD · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Periode BKD' : `Edit ${period.name}`}
                        description="Buka siklus periode baru untuk pengajuan dan proses verifikasi laporan Beban Kerja Dosen (BKD)."
                        badges={isCreate ? ['Periode baru'] : [period.code]}
                        actions={<a className="db-btn light" href={cancelHref}>Kembali</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarCheck size={16} /> Formulir Pengaturan Periode BKD</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Nama periode BKD"
                                            required
                                            placeholder="Contoh: BKD Semester Ganjil TA 2025/2026"
                                            value={form.data.name}
                                            onChange={(event) => form.setData('name', event.target.value)}
                                            error={form.errors.name}
                                        />
                                    </div>
                                    <TextField
                                        label="Kode periode"
                                        required
                                        placeholder="Contoh: BKD-20251"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        hint="Kode unik periode, otomatis disimpan dalam huruf kapital."
                                        style={{ textTransform: 'uppercase' }}
                                    />
                                    <SelectField
                                        label="Status siklus"
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
                                        label="Tautan tahun akademik (opsional)"
                                        value={form.data.academic_year_id}
                                        onChange={(event) => form.setData('academic_year_id', event.target.value)}
                                        error={form.errors.academic_year_id}
                                        hint="Kosongkan bila periode bersifat umum / tidak dibatasi tahun akademik."
                                    >
                                        <option value="">-- Tidak Dibatasi / Umum --</option>
                                        {academicYears.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Tautan periode akademik (semester)"
                                        value={form.data.academic_period_id}
                                        onChange={(event) => form.setData('academic_period_id', event.target.value)}
                                        error={form.errors.academic_period_id}
                                        hint="Kosongkan bila periode tidak terikat semester tertentu."
                                    >
                                        <option value="">-- Tidak Dibatasi / Umum --</option>
                                        {academicPeriods.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Tanggal mulai pengajuan"
                                        type="date"
                                        value={form.data.starts_at}
                                        onChange={(event) => form.setData('starts_at', event.target.value)}
                                        error={form.errors.starts_at}
                                    />
                                    <TextField
                                        label="Tanggal batas akhir (deadline)"
                                        type="date"
                                        value={form.data.ends_at}
                                        onChange={(event) => form.setData('ends_at', event.target.value)}
                                        error={form.errors.ends_at}
                                    />
                                    <TextField
                                        label="Minimum SKS wajib"
                                        required
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        placeholder="12"
                                        value={form.data.minimum_sks}
                                        onChange={(event) => form.setData('minimum_sks', event.target.value === '' ? '' : Number(event.target.value))}
                                        error={form.errors.minimum_sks}
                                        hint="Standar UU Guru & Dosen: minimal 12 SKS per semester."
                                    />
                                    <TextField
                                        label="Maksimum SKS diakui"
                                        required
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        placeholder="16"
                                        value={form.data.maximum_sks}
                                        onChange={(event) => form.setData('maximum_sks', event.target.value === '' ? '' : Number(event.target.value))}
                                        error={form.errors.maximum_sks}
                                        hint="Standar UU Guru & Dosen: maksimal 16 SKS per semester; harus ≥ minimum SKS."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan & keterangan periode"
                                            rows={3}
                                            placeholder="Tuliskan keterangan pengingat, regulasi yang berlaku, atau instruksi pengisian untuk para dosen…"
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            error={form.errors.notes}
                                        />
                                    </div>
                                </div>

                                <div className="db-note info" style={{ marginTop: 14 }}>
                                    <Info size={15} />
                                    <span>
                                        Status <b>Dibuka</b> mengizinkan dosen mengisi klaim BKD pada rentang tanggal pengajuan.
                                        Status <b>Masa Review</b> melarang pengajuan baru namun tetap membolehkan asesor memverifikasi laporan,
                                        sedangkan <b>Ditutup</b> mengunci seluruh pengajuan pada periode ini.
                                    </span>
                                </div>

                                <FormActions
                                    cancelHref={cancelHref}
                                    submitLabel={isCreate ? 'Simpan Periode BKD' : 'Simpan Perubahan'}
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
