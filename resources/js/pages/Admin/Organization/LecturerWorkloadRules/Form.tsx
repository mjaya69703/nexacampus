// Form tambah/ubah aturan konversi SKS BKD.
import { Head, useForm } from '@inertiajs/react';
import { Cog } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type RulePayload = {
    id?: number;
    category: string;
    source_code: string;
    name: string;
    sks_value: number;
    maximum_sks: number | string;
    is_active: boolean;
    description: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    rule: RulePayload;
    categories: Option[];
    urls: { index: string; submit: string };
};

export default function LecturerWorkloadRuleForm({ shell, mode, rule, categories, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        category: rule.category,
        source_code: rule.source_code,
        name: rule.name,
        sks_value: rule.sks_value,
        maximum_sks: rule.maximum_sks,
        is_active: rule.is_active,
        description: rule.description,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Aturan SKS BKD · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Cog}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Aturan Konversi SKS' : `Edit ${rule.name}`}
                        description="Tentukan kategori aktivitas, kode sumber konversi, serta nilai dan batas maksimum SKS yang diakui dalam Beban Kerja Dosen."
                        badges={isCreate ? ['Aturan baru'] : [rule.source_code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Cog size={16} /> Parameter Konversi SKS</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Kategori aktivitas"
                                        required
                                        value={form.data.category}
                                        onChange={(event) => form.setData('category', event.target.value)}
                                        error={form.errors.category}
                                        hint="Menentukan kelompok aktivitas BKD tempat aturan ini diberlakukan."
                                    >
                                        {categories.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Kode sumber (source code)"
                                        required
                                        placeholder="Contoh: DEKAN / RESEARCH / KAPRODI"
                                        value={form.data.source_code}
                                        onChange={(event) => form.setData('source_code', event.target.value)}
                                        error={form.errors.source_code}
                                        hint="Kode unik per kategori, otomatis disimpan dalam huruf kapital."
                                    />
                                    <TextField
                                        label="Nama aturan / keterangan"
                                        required
                                        placeholder="Contoh: Menjabat sebagai Dekan Fakultas"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Nilai konversi SKS"
                                        required
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        value={form.data.sks_value}
                                        onChange={(event) => form.setData('sks_value', event.target.value === '' ? 0 : Number(event.target.value))}
                                        error={form.errors.sks_value}
                                        hint="Poin SKS yang diakui per item atau jabatan."
                                    />
                                    <TextField
                                        label="Batas maksimum SKS (opsional)"
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        placeholder="Tidak dibatasi"
                                        value={form.data.maximum_sks}
                                        onChange={(event) => form.setData('maximum_sks', event.target.value === '' ? '' : Number(event.target.value))}
                                        error={form.errors.maximum_sks}
                                        hint="Batas atas akumulasi kuota per semester; harus ≥ nilai konversi SKS."
                                    />
                                    <SwitchField
                                        label="Status aturan aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Aturan nonaktif tidak muncul pada pilihan penyusunan laporan BKD dosen."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi & landasan regulasi (opsional)"
                                            rows={3}
                                            placeholder="Tuliskan keterangan rinci, rujukan pasal/PO BKD, atau panduan verifikasi untuk asesor…"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Aturan Konversi' : 'Simpan Perubahan'}
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
