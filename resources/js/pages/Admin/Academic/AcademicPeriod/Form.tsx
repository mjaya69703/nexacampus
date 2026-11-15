// Form tambah/ubah periode akademik.
import { Head, useForm } from '@inertiajs/react';
import { Clock3 } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    period: {
        id?: number; academic_year_id: number | string; name: string; code: string;
        type: string; start_at: string; end_at: string; is_active: boolean; desc: string;
    };
    years: { id: number; name: string }[];
    types: string[];
    urls: { index: string; submit: string };
};

export default function AcademicPeriodForm({ shell, mode, period, years, types, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        academic_year_id: period.academic_year_id,
        name: period.name,
        code: period.code,
        type: period.type,
        start_at: period.start_at,
        end_at: period.end_at,
        is_active: period.is_active,
        desc: period.desc,
    });

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Periode Akademik · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Clock3}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Periode Akademik' : `Edit ${period.name}`}
                        description="Tanggal selesai harus setelah tanggal mulai."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Clock3 size={16} /> Form Periode Akademik</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Tahun akademik"
                                        required
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.academic_year_id}
                                    >
                                        <option value="">— Pilih —</option>
                                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Tipe periode"
                                        required
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                        error={form.errors.type}
                                    >
                                        {types.map((type) => <option key={type} value={type}>{type}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Nama periode"
                                        required
                                        placeholder="Contoh: Pengisian KRS Ganjil 2026/2027"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode"
                                        placeholder="Opsional, unik bila diisi"
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <TextField
                                        label="Mulai"
                                        required
                                        type="datetime-local"
                                        value={form.data.start_at}
                                        onChange={(e) => form.setData('start_at', e.target.value)}
                                        error={form.errors.start_at}
                                    />
                                    <TextField
                                        label="Selesai"
                                        required
                                        type="datetime-local"
                                        value={form.data.end_at}
                                        onChange={(e) => form.setData('end_at', e.target.value)}
                                        error={form.errors.end_at}
                                        hint="Harus setelah waktu mulai."
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            value={form.data.desc}
                                            onChange={(e) => form.setData('desc', e.target.value)}
                                            error={form.errors.desc}
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
