// Form tambah/ubah aturan penomoran NIM.
import { Head, useForm } from '@inertiajs/react';
import { Hash } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    rule: {
        id?: number;
        name: string;
        pattern: string;
        sequence_scope: string;
        sequence_padding: number;
        sequence_start: number;
        is_active: boolean;
        description: string;
    };
    scopeOptions: { id: string; label: string }[];
    urls: { index: string; submit: string };
};

const TOKENS = [
    { token: '{year}', label: 'Tahun 4 digit (2026)' },
    { token: '{yy}', label: 'Tahun 2 digit (26)' },
    { token: '{period_code}', label: 'Kode gelombang PMB' },
    { token: '{faculty_code}', label: 'Kode fakultas' },
    { token: '{program_code}', label: 'Kode program studi' },
    { token: '{class_type}', label: 'Jenis kelas' },
    { token: '{sequence}', label: 'Nomor urut (dengan padding)' },
];

export default function NimRuleForm({ shell, mode, rule, scopeOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: rule.name,
        pattern: rule.pattern,
        sequence_scope: rule.sequence_scope,
        sequence_padding: rule.sequence_padding,
        sequence_start: rule.sequence_start,
        is_active: rule.is_active,
        description: rule.description,
    });

    const year = new Date().getFullYear();
    const padding = Math.max(1, Number(form.data.sequence_padding) || 1);
    const start = Math.max(1, Number(form.data.sequence_start) || 1);

    const preview = TOKENS.reduce((pattern, item) => {
        const replacements: Record<string, string> = {
            '{year}': String(year),
            '{yy}': String(year).slice(-2),
            '{period_code}': `ADM${year}W1`,
            '{faculty_code}': 'ENG',
            '{program_code}': 'IF',
            '{class_type}': 'REGULAR',
            '{sequence}': String(start).padStart(padding, '0'),
        };

        return pattern.split(item.token).join(replacements[item.token] ?? item.token);
    }, form.data.pattern);

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Aturan NIM · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Hash}
                        eyebrow="Admission"
                        title={isCreate ? 'Tambah Aturan NIM' : 'Edit Aturan NIM'}
                        description="Rancang pola NIM otomatis dari token tahun, kode periode/fakultas/prodi, jenis kelas, dan nomor urut."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Hash size={16} /> Pola Penomoran</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama aturan"
                                        required
                                        placeholder="Contoh: Aturan NIM S1 Reguler 2026"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Pola NIM"
                                        required
                                        placeholder="{yy}{program_code}{sequence}"
                                        value={form.data.pattern}
                                        onChange={(e) => form.setData('pattern', e.target.value)}
                                        error={form.errors.pattern}
                                        hint="Gabungkan token di bawah ini, tanpa pemisah otomatis."
                                    />
                                    <SelectField
                                        label="Cakupan reset nomor urut"
                                        required
                                        value={form.data.sequence_scope}
                                        onChange={(e) => form.setData('sequence_scope', e.target.value)}
                                        error={form.errors.sequence_scope}
                                    >
                                        {scopeOptions.map((scope) => (
                                            <option key={scope.id} value={scope.id}>{scope.label}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Padding nomor urut"
                                        required
                                        type="number"
                                        min={1}
                                        max={10}
                                        value={String(form.data.sequence_padding)}
                                        onChange={(e) => form.setData('sequence_padding', e.target.value === '' ? 1 : Number(e.target.value))}
                                        error={form.errors.sequence_padding}
                                        hint="Jumlah digit; 4 menghasilkan 0001."
                                    />
                                    <TextField
                                        label="Nomor urut mulai"
                                        required
                                        type="number"
                                        min={1}
                                        value={String(form.data.sequence_start)}
                                        onChange={(e) => form.setData('sequence_start', e.target.value === '' ? 1 : Number(e.target.value))}
                                        error={form.errors.sequence_start}
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Mengaktifkan aturan ini akan menonaktifkan aturan lain."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            rows={2}
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <div style={{
                                    marginTop: 18,
                                    padding: 16,
                                    borderRadius: 12,
                                    border: '1px solid var(--db-line)',
                                    background: 'var(--db-soft)',
                                }}>
                                    <div className="db-hint" style={{ marginBottom: 6 }}>Pratinjau contoh NIM</div>
                                    <div style={{ fontFamily: 'ui-monospace, Cascadia Code, Consolas, monospace', fontSize: 20, fontWeight: 700, color: 'var(--db-heading)', letterSpacing: '.5px' }}>
                                        {preview || '—'}
                                    </div>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                                        {TOKENS.map((item) => (
                                            <span key={item.token} className="db-badge gray" title={item.label}>{item.token}</span>
                                        ))}
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Aturan' : 'Simpan Perubahan'}
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
