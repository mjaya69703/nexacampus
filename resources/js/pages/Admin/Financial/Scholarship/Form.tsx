// Form program beasiswa — create + edit (paritas _form Blade).
import { Head, useForm } from '@inertiajs/react';
import { Award } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Scholarship = {
    name: string; description: string | null; type: string; discount_type: string;
    discount_percentage: number | string | null; fixed_amount: number | string | null;
    duration_semesters: number | string; requirements: string | null; is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    scholarship: Scholarship;
    types: string[];
    discountTypes: string[];
    urls: { index: string; submit: string };
};

const TYPE_TEXT: Record<string, string> = {
    full: 'Pembebasan Penuh (Full)', partial: 'Potongan Sebagian (Partial)',
    merit: 'Prestasi Akademik (Merit)', need_based: 'Bantuan Ekonomi (Need Based)',
};

export default function ScholarshipForm({ shell, mode, scholarship, types, discountTypes, urls }: Props) {
    const isCreate = mode === 'create';
    const str = (v: number | string | null | undefined) => (v === null || v === undefined || v === '' ? '' : String(v));
    const form = useForm({
        name: scholarship.name ?? '',
        description: scholarship.description ?? '',
        type: scholarship.type ?? 'partial',
        discount_type: scholarship.discount_type ?? 'percentage',
        discount_percentage: str(scholarship.discount_percentage),
        fixed_amount: str(scholarship.fixed_amount),
        duration_semesters: str(scholarship.duration_semesters) || '1',
        requirements: scholarship.requirements ?? '',
        is_active: scholarship.is_active,
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
    const isFixed = form.data.discount_type === 'fixed';

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Ubah'} Beasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Keuangan"
                        title={isCreate ? 'Tambah Beasiswa' : 'Ubah Beasiswa'}
                        description="Penerima aktif otomatis memotong tagihan saat penerbitan invoice berkala."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Award size={16} /> Data Program</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama Program Beasiswa"
                                        required
                                        value={form.data.name}
                                        placeholder="Contoh: Beasiswa Prestasi Rektor"
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={errors.name}
                                    />
                                    <SelectField
                                        label="Kategori / Jenis"
                                        required
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                        error={errors.type}
                                    >
                                        {types.map((t) => <option key={t} value={t}>{TYPE_TEXT[t] ?? t}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Metode Potongan"
                                        required
                                        value={form.data.discount_type}
                                        onChange={(e) => form.setData('discount_type', e.target.value)}
                                        error={errors.discount_type}
                                    >
                                        {discountTypes.map((t) => (
                                            <option key={t} value={t}>
                                                {t === 'fixed' ? 'Nominal Tetap (Rp)' : 'Persentase (%) dari SPP'}
                                            </option>
                                        ))}
                                    </SelectField>
                                    {isFixed ? (
                                        <TextField
                                            label="Nominal Potongan Tetap"
                                            required
                                            type="number"
                                            min={1}
                                            step={1000}
                                            placeholder="Contoh: 2500000"
                                            value={form.data.fixed_amount}
                                            onChange={(e) => form.setData('fixed_amount', e.target.value)}
                                            error={errors.fixed_amount}
                                        />
                                    ) : (
                                        <TextField
                                            label="Persentase Potongan"
                                            required
                                            type="number"
                                            min={0.01}
                                            max={100}
                                            step={0.01}
                                            placeholder="Contoh: 50"
                                            value={form.data.discount_percentage}
                                            onChange={(e) => form.setData('discount_percentage', e.target.value)}
                                            error={errors.discount_percentage}
                                        />
                                    )}
                                    <TextField
                                        label="Durasi Berlaku (Semester)"
                                        required
                                        type="number"
                                        min={1}
                                        max={20}
                                        placeholder="Contoh: 8"
                                        value={form.data.duration_semesters}
                                        onChange={(e) => form.setData('duration_semesters', e.target.value)}
                                        error={errors.duration_semesters}
                                    />
                                    <SwitchField
                                        label="Status Program"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi Program"
                                            value={form.data.description}
                                            placeholder="Penjelasan singkat mengenai program beasiswa dan cakupannya…"
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={errors.description}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Persyaratan & Kriteria Penerima"
                                            value={form.data.requirements}
                                            placeholder="Contoh: IPK minimal 3.50, tidak sedang menerima beasiswa lain…"
                                            onChange={(e) => form.setData('requirements', e.target.value)}
                                            error={errors.requirements}
                                        />
                                    </div>
                                </div>
                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Beasiswa' : 'Simpan Perubahan'}
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
