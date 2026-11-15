// Form tambah/ubah periode & gelombang PMB.
import { Head, useForm } from '@inertiajs/react';
import { CalendarRange, FileText, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Requirement = {
    id?: number;
    document_type: string;
    label: string;
    is_required: boolean;
    allowed_extensions: string;
    max_size_kb: number;
};

type Period = {
    id?: number;
    name: string;
    code: string;
    academic_year_id: number | string;
    wave: number;
    opens_at: string | null;
    closes_at: string | null;
    is_active: boolean;
    is_published: boolean;
    description: string;
    document_requirements: Requirement[];
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    period: Period;
    years: { id: number; name: string; year: number }[];
    urls: { index: string; submit: string };
};

const EMPTY_REQUIREMENT: Requirement = {
    document_type: '',
    label: '',
    is_required: true,
    allowed_extensions: 'pdf,jpg,jpeg,png',
    max_size_kb: 2048,
};

export default function AdmissionPeriodForm({ shell, mode, period, years, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: period.name,
        code: period.code,
        academic_year_id: period.academic_year_id,
        wave: period.wave,
        opens_at: period.opens_at ?? '',
        closes_at: period.closes_at ?? '',
        is_active: period.is_active,
        is_published: period.is_published,
        description: period.description,
        document_requirements: period.document_requirements ?? [],
    });

    const selectedYear = years.find((year) => String(year.id) === String(form.data.academic_year_id));

    const updateRequirement = <K extends keyof Requirement>(index: number, key: K, value: Requirement[K]) => {
        form.setData(
            'document_requirements',
            form.data.document_requirements.map((requirement, i) => (
                i === index ? { ...requirement, [key]: value } : requirement
            )),
        );
    };

    const addRequirement = () => {
        form.setData('document_requirements', [...form.data.document_requirements, { ...EMPTY_REQUIREMENT }]);
    };

    const removeRequirement = (index: number) => {
        form.setData(
            'document_requirements',
            form.data.document_requirements.filter((_, i) => i !== index),
        );
    };

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Periode PMB · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarRange}
                        eyebrow="Admission"
                        title={isCreate ? 'Tambah Periode PMB' : 'Edit Periode PMB'}
                        description="Tentukan jadwal gelombang, tahun akademik, status publikasi, serta syarat dokumen yang wajib diunggah pendaftar."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarRange size={16} /> Informasi Periode</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama gelombang / periode"
                                        required
                                        placeholder="Contoh: PMB 2026 - Gelombang 1"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode unik"
                                        required
                                        placeholder="Contoh: ADM2026W1"
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                        hint="Dipakai pada token {period_code} di pola NIM."
                                    />
                                    <SelectField
                                        label="Tahun akademik"
                                        required
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value)}
                                        error={form.errors.academic_year_id}
                                        hint={selectedYear ? `Tahun angkatan tersimpan: ${selectedYear.year}` : 'Pilih tahun akademik yang sesuai.'}
                                    >
                                        <option value="">-- Pilih tahun akademik --</option>
                                        {years.map((year) => (
                                            <option key={year.id} value={year.id}>{year.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Gelombang ke-"
                                        required
                                        type="number"
                                        min={1}
                                        max={20}
                                        value={String(form.data.wave)}
                                        onChange={(e) => form.setData('wave', e.target.value === '' ? 1 : Number(e.target.value))}
                                        error={form.errors.wave}
                                    />
                                    <TextField
                                        label="Tanggal buka"
                                        required
                                        type="date"
                                        value={form.data.opens_at}
                                        onChange={(e) => form.setData('opens_at', e.target.value)}
                                        error={form.errors.opens_at}
                                    />
                                    <TextField
                                        label="Tanggal tutup"
                                        required
                                        type="date"
                                        value={form.data.closes_at}
                                        onChange={(e) => form.setData('closes_at', e.target.value)}
                                        error={form.errors.closes_at}
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Periode nonaktif tidak menerima pendaftaran baru."
                                    />
                                    <SwitchField
                                        label="Publikasi"
                                        checked={form.data.is_published}
                                        onChange={(value) => form.setData('is_published', value)}
                                        error={form.errors.is_published}
                                        hint="Publikasikan gelombang ini di halaman PMB publik."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Keterangan"
                                            rows={3}
                                            placeholder="Catatan atau informasi tambahan untuk calon mahasiswa…"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginBottom: 14 }}>
                                        <div>
                                            <h3 className="db-card-title" style={{ marginBottom: 4 }}><FileText size={16} /> Syarat Dokumen Pendaftaran</h3>
                                            <p className="db-hint" style={{ margin: 0 }}>Tentukan berkas digital yang harus diunggah calon mahasiswa. Ekstensi dibatasi pada pdf, jpg, jpeg, png, webp.</p>
                                        </div>
                                        <button className="db-btn ghost sm" type="button" onClick={addRequirement}>
                                            <Plus size={14} /> Tambah syarat
                                        </button>
                                    </div>

                                    {form.data.document_requirements.length === 0 && (
                                        <p className="db-hint">Belum ada syarat dokumen. Periode tetap bisa disimpan tanpa syarat.</p>
                                    )}

                                    {form.data.document_requirements.map((requirement, index) => (
                                        <div
                                            key={requirement.id ?? `new-${index}`}
                                            style={{
                                                border: '1px solid var(--db-line)',
                                                borderRadius: 12,
                                                padding: 14,
                                                marginBottom: 12,
                                            }}
                                        >
                                            <div className="crud-grid">
                                                <TextField
                                                    label="Tipe dokumen (kode)"
                                                    required
                                                    placeholder="id_card / photo / diploma"
                                                    value={requirement.document_type}
                                                    onChange={(e) => updateRequirement(index, 'document_type', e.target.value)}
                                                    error={form.errors[`document_requirements.${index}.document_type`]}
                                                />
                                                <TextField
                                                    label="Label untuk pendaftar"
                                                    required
                                                    placeholder="Kartu Identitas / Ijazah"
                                                    value={requirement.label}
                                                    onChange={(e) => updateRequirement(index, 'label', e.target.value)}
                                                    error={form.errors[`document_requirements.${index}.label`]}
                                                />
                                                <TextField
                                                    label="Ekstensi diizinkan"
                                                    placeholder="pdf,jpg,jpeg,png"
                                                    value={requirement.allowed_extensions}
                                                    onChange={(e) => updateRequirement(index, 'allowed_extensions', e.target.value)}
                                                    error={form.errors[`document_requirements.${index}.allowed_extensions`]}
                                                    hint="Pisahkan dengan koma."
                                                />
                                                <TextField
                                                    label="Maks. ukuran (KB)"
                                                    type="number"
                                                    min={1}
                                                    max={10240}
                                                    value={String(requirement.max_size_kb)}
                                                    onChange={(e) => updateRequirement(index, 'max_size_kb', e.target.value === '' ? 2048 : Number(e.target.value))}
                                                    error={form.errors[`document_requirements.${index}.max_size_kb`]}
                                                />
                                                <SwitchField
                                                    label="Wajib diunggah"
                                                    checked={requirement.is_required}
                                                    onChange={(value) => updateRequirement(index, 'is_required', value)}
                                                    error={form.errors[`document_requirements.${index}.is_required`]}
                                                />
                                                <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'flex-end' }}>
                                                    <button className="db-btn danger sm" type="button" onClick={() => removeRequirement(index)}>
                                                        <Trash2 size={13} /> Hapus syarat
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
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
