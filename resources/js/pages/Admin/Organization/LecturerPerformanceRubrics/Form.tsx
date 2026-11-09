// Form tambah/ubah rubrik performa dosen.
import { Head, useForm } from '@inertiajs/react';
import { Scale } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type RubricPayload = {
    id?: number;
    code: string;
    name: string;
    edom_weight: number;
    teaching_weight: number;
    attendance_weight: number;
    workload_weight: number;
    minimum_responses: number;
    target_workload_sks: number;
    is_active: boolean;
    notes: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    rubric: RubricPayload;
    urls: { index: string; submit: string };
};

const toNumber = (value: string) => (value === '' ? 0 : Number(value));

export default function LecturerPerformanceRubricForm({ shell, mode, rubric, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        code: rubric.code,
        name: rubric.name,
        edom_weight: rubric.edom_weight,
        teaching_weight: rubric.teaching_weight,
        attendance_weight: rubric.attendance_weight,
        workload_weight: rubric.workload_weight,
        minimum_responses: rubric.minimum_responses,
        target_workload_sks: rubric.target_workload_sks,
        is_active: rubric.is_active,
        notes: rubric.notes,
    });

    const totalWeight = Number(form.data.edom_weight)
        + Number(form.data.teaching_weight)
        + Number(form.data.attendance_weight)
        + Number(form.data.workload_weight);

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Rubrik Performa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Scale}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Rubrik Performa Dosen' : `Edit ${rubric.name}`}
                        description="Atur proporsi bobot EDOM, kepatuhan mengajar, absensi, dan realisasi BKD sebagai dasar perhitungan skor akhir kinerja dosen."
                        badges={isCreate ? ['Rubrik baru'] : [rubric.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Scale size={16} /> Bobot & Aturan Penilaian</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Kode rubrik"
                                        required
                                        placeholder="Contoh: STANDAR-2026"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        hint="Kode unik (huruf kapital) untuk identifikasi rubrik."
                                    />
                                    <TextField
                                        label="Nama rubrik penilaian"
                                        required
                                        placeholder="Contoh: Rubrik Evaluasi Kinerja Dosen Reguler"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Bobot EDOM (%)"
                                        required
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        value={form.data.edom_weight}
                                        onChange={(event) => form.setData('edom_weight', toNumber(event.target.value))}
                                        error={form.errors.edom_weight}
                                        hint="Penilaian mahasiswa terhadap pelaksanaan perkuliahan."
                                    />
                                    <TextField
                                        label="Bobot mengajar (%)"
                                        required
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        value={form.data.teaching_weight}
                                        onChange={(event) => form.setData('teaching_weight', toNumber(event.target.value))}
                                        error={form.errors.teaching_weight}
                                        hint="Kepatuhan jadwal dan kehadiran perkuliahan."
                                    />
                                    <TextField
                                        label="Bobot absensi (%)"
                                        required
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        value={form.data.attendance_weight}
                                        onChange={(event) => form.setData('attendance_weight', toNumber(event.target.value))}
                                        error={form.errors.attendance_weight}
                                        hint="Kedisiplinan absensi harian pegawai."
                                    />
                                    <TextField
                                        label="Bobot BKD (%)"
                                        required
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        value={form.data.workload_weight}
                                        onChange={(event) => form.setData('workload_weight', toNumber(event.target.value))}
                                        error={form.errors.workload_weight}
                                        hint="Capaian SKS tri dharma dalam Beban Kerja Dosen."
                                    />
                                    <TextField
                                        label="Minimal respon EDOM (mahasiswa)"
                                        required
                                        type="number"
                                        min={1}
                                        value={form.data.minimum_responses}
                                        onChange={(event) => form.setData('minimum_responses', event.target.value === '' ? 1 : Number(event.target.value))}
                                        error={form.errors.minimum_responses}
                                        hint="Kuorum minimal pengisi EDOM agar nilai dianggap valid."
                                    />
                                    <TextField
                                        label="Target BKD (SKS/semester)"
                                        required
                                        type="number"
                                        min={1}
                                        step={0.01}
                                        value={form.data.target_workload_sks}
                                        onChange={(event) => form.setData('target_workload_sks', event.target.value === '' ? 1 : Number(event.target.value))}
                                        error={form.errors.target_workload_sks}
                                        hint="Target SKS BKD standar per semester."
                                    />
                                    <SwitchField
                                        label="Jadikan rubrik aktif utama"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Jika aktif, rubrik aktif sebelumnya otomatis dinonaktifkan."
                                        onLabel="Aktif utama"
                                        offLabel="Tidak aktif"
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <div className={`db-badge ${totalWeight === 100 ? 'green' : 'amber'}`} style={{ fontSize: 12, padding: '6px 12px' }}>
                                            Total bobot saat ini: {Number.isInteger(totalWeight) ? totalWeight : totalWeight.toFixed(2)}%
                                            {totalWeight !== 100 ? ' — disarankan tepat 100%' : ' — komposisi ideal'}
                                        </div>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan tambahan (opsional)"
                                            rows={2}
                                            placeholder="Keterangan mengenai dasar keputusan penetapan bobot rubrik ini…"
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            error={form.errors.notes}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Rubrik' : 'Simpan Perubahan'}
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
