// Form tarif biaya kuliah — create + edit (paritas _form Blade).
import { Head, useForm } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Fee = {
    academic_year_id: number | string; study_program_id: number | string; semester: number | string;
    base_fee: number | string; lab_fee: number | string | null; library_fee: number | string | null;
    activity_fee: number | string | null; late_penalty_per_day: number | string | null;
    payment_deadline: string; is_active: boolean; notes: string | null;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    fee: Fee;
    years: { id: number; name: string }[];
    programs: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

export default function TuitionFeeForm({ shell, mode, fee, years, programs, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        academic_year_id: fee.academic_year_id === '' ? '' : String(fee.academic_year_id),
        study_program_id: fee.study_program_id === '' ? '' : String(fee.study_program_id),
        semester: fee.semester === '' ? '' : String(fee.semester),
        base_fee: fee.base_fee === '' ? '' : String(fee.base_fee),
        lab_fee: fee.lab_fee === null || fee.lab_fee === '' ? '' : String(fee.lab_fee),
        library_fee: fee.library_fee === null || fee.library_fee === '' ? '' : String(fee.library_fee),
        activity_fee: fee.activity_fee === null || fee.activity_fee === '' ? '' : String(fee.activity_fee),
        late_penalty_per_day: fee.late_penalty_per_day === null || fee.late_penalty_per_day === '' ? '' : String(fee.late_penalty_per_day),
        payment_deadline: fee.payment_deadline ?? '',
        is_active: fee.is_active,
        notes: fee.notes ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const errors = form.errors as Record<string, string>;
    const total = ['base_fee', 'lab_fee', 'library_fee', 'activity_fee']
        .reduce((sum, key) => sum + (Number(form.data[key as keyof typeof form.data]) || 0), 0);

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Ubah'} Tarif · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Wallet}
                        eyebrow="Keuangan"
                        title={isCreate ? 'Tambah Tarif Biaya' : 'Ubah Tarif Biaya'}
                        description="Kombinasi tahun, prodi, dan semester harus unik."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Wallet size={16} /> Data Tarif</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Tahun Akademik"
                                        required
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value)}
                                        error={errors.academic_year_id}
                                    >
                                        <option value="">Pilih tahun…</option>
                                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Program Studi"
                                        required
                                        value={form.data.study_program_id}
                                        onChange={(e) => form.setData('study_program_id', e.target.value)}
                                        error={errors.study_program_id}
                                    >
                                        <option value="">Pilih prodi…</option>
                                        {programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Semester"
                                        required
                                        type="number"
                                        min={1}
                                        max={14}
                                        placeholder="Contoh: 1"
                                        value={form.data.semester}
                                        onChange={(e) => form.setData('semester', e.target.value)}
                                        error={errors.semester}
                                    />
                                    <TextField
                                        label="Batas Pembayaran"
                                        required
                                        type="date"
                                        value={form.data.payment_deadline}
                                        onChange={(e) => form.setData('payment_deadline', e.target.value)}
                                        error={errors.payment_deadline}
                                    />
                                    <TextField
                                        label="Biaya Pokok (SPP)"
                                        required
                                        type="number"
                                        min={0}
                                        step={1000}
                                        value={form.data.base_fee}
                                        onChange={(e) => form.setData('base_fee', e.target.value)}
                                        error={errors.base_fee}
                                    />
                                    <TextField
                                        label="Biaya Lab"
                                        type="number"
                                        min={0}
                                        step={1000}
                                        value={form.data.lab_fee}
                                        onChange={(e) => form.setData('lab_fee', e.target.value)}
                                        error={errors.lab_fee}
                                    />
                                    <TextField
                                        label="Biaya Perpustakaan"
                                        type="number"
                                        min={0}
                                        step={1000}
                                        value={form.data.library_fee}
                                        onChange={(e) => form.setData('library_fee', e.target.value)}
                                        error={errors.library_fee}
                                    />
                                    <TextField
                                        label="Biaya Kegiatan"
                                        type="number"
                                        min={0}
                                        step={1000}
                                        value={form.data.activity_fee}
                                        onChange={(e) => form.setData('activity_fee', e.target.value)}
                                        error={errors.activity_fee}
                                    />
                                    <TextField
                                        label="Denda Keterlambatan / Hari"
                                        type="number"
                                        min={0}
                                        step={1000}
                                        value={form.data.late_penalty_per_day}
                                        onChange={(e) => form.setData('late_penalty_per_day', e.target.value)}
                                        error={errors.late_penalty_per_day}
                                    />
                                    <SwitchField
                                        label="Status"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            placeholder="Contoh: Tarif SK Rektor No. 12/2026…"
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={errors.notes}
                                        />
                                    </div>
                                </div>
                                <p className="db-hint" style={{ marginTop: 12 }}>
                                    Estimasi total tagihan: <b>Rp {total.toLocaleString('id-ID')}</b> (SPP + lab + perpus + kegiatan).
                                </p>
                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Tarif' : 'Simpan Perubahan'}
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
