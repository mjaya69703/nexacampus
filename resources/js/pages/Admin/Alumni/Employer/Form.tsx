// Form tambah/ubah mitra perusahaan alumni.
import { Head, useForm } from '@inertiajs/react';
import { Building2, Image as ImageIcon, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type PartnerPayload = {
    id?: number;
    name: string;
    industry: string;
    website: string;
    contact_person: string;
    contact_email: string;
    contact_phone: string;
    address: string;
    city: string;
    province: string;
    description: string;
    is_active: boolean;
    logo_url: string | null;
    jobs_count: number;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    partner: PartnerPayload;
    urls: { index: string; submit: string };
};

export default function EmployerPartnerForm({ shell, mode, partner, urls }: Props) {
    const isCreate = mode === 'create';
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    const form = useForm({
        name: partner.name,
        industry: partner.industry,
        website: partner.website,
        contact_person: partner.contact_person,
        contact_email: partner.contact_email,
        contact_phone: partner.contact_phone,
        address: partner.address,
        city: partner.city,
        province: partner.province,
        description: partner.description,
        is_active: partner.is_active,
        logo: null as File | null,
        remove_logo: false as boolean,
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    const previewUrl = logoPreview ?? (form.data.remove_logo ? null : partner.logo_url);

    const onLogoChange = (file: File | null) => {
        if (logoPreview) URL.revokeObjectURL(logoPreview);
        form.setData({
            ...form.data,
            logo: file,
            remove_logo: file ? false : form.data.remove_logo,
        });
        setLogoPreview(file ? URL.createObjectURL(file) : null);
    };

    const removeExistingLogo = () => {
        if (logoPreview) URL.revokeObjectURL(logoPreview);
        form.setData({ ...form.data, logo: null, remove_logo: true });
        setLogoPreview(null);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit, { forceFormData: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Mitra Perusahaan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Building2}
                        eyebrow="Alumni"
                        title={isCreate ? 'Tambah Mitra Perusahaan' : `Edit ${partner.name}`}
                        description="Daftarkan perusahaan atau instansi mitra untuk publikasi lowongan karir dan rekrutmen lulusan."
                        badges={isCreate ? ['Mitra baru'] : [`${partner.jobs_count} lowongan tertaut`]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Building2 size={16} /> Profil Perusahaan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama perusahaan"
                                        required
                                        placeholder="Contoh: PT Teknologi Nusantara"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Sektor industri"
                                        placeholder="Contoh: Teknologi Informasi / Perbankan"
                                        value={form.data.industry}
                                        onChange={(e) => form.setData('industry', e.target.value)}
                                        error={form.errors.industry}
                                        hint="Dipakai sebagai filter lowongan."
                                    />
                                    <TextField
                                        label="Website resmi"
                                        type="url"
                                        placeholder="https://www.company.com"
                                        value={form.data.website}
                                        onChange={(e) => form.setData('website', e.target.value)}
                                        error={form.errors.website}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi singkat"
                                            rows={3}
                                            placeholder="Profil singkat atau fokus bisnis instansi…"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Kontak & Domisili</h3>
                                    <div className="crud-grid">
                                        <TextField
                                            label="Nama kontak person"
                                            placeholder="Contoh: Budi Santoso (HR Manager)"
                                            value={form.data.contact_person}
                                            onChange={(e) => form.setData('contact_person', e.target.value)}
                                            error={form.errors.contact_person}
                                        />
                                        <TextField
                                            label="Email kontak"
                                            type="email"
                                            placeholder="hr@company.com"
                                            value={form.data.contact_email}
                                            onChange={(e) => form.setData('contact_email', e.target.value)}
                                            error={form.errors.contact_email}
                                        />
                                        <TextField
                                            label="No. telepon kontak"
                                            placeholder="021-xxxx / 08xxxxxxxx"
                                            value={form.data.contact_phone}
                                            onChange={(e) => form.setData('contact_phone', e.target.value)}
                                            error={form.errors.contact_phone}
                                        />
                                        <TextField
                                            label="Kota"
                                            placeholder="Contoh: Jakarta Pusat"
                                            value={form.data.city}
                                            onChange={(e) => form.setData('city', e.target.value)}
                                            error={form.errors.city}
                                        />
                                        <TextField
                                            label="Provinsi"
                                            placeholder="Contoh: DKI Jakarta"
                                            value={form.data.province}
                                            onChange={(e) => form.setData('province', e.target.value)}
                                            error={form.errors.province}
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Alamat kantor"
                                                rows={2}
                                                placeholder="Gedung, jalan, RT/RW, kode pos…"
                                                value={form.data.address}
                                                onChange={(e) => form.setData('address', e.target.value)}
                                                error={form.errors.address}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <div className="crud-grid">
                                        <div>
                                            <span className="crud-label">Logo perusahaan</span>
                                            <input
                                                className="db-input"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                onChange={(e) => onLogoChange(e.target.files?.[0] ?? null)}
                                            />
                                            {form.errors.logo && <div className="db-error">{form.errors.logo}</div>}
                                            <div className="crud-hint">Maksimal 2MB, format JPG/PNG/WebP.</div>
                                            {previewUrl && (
                                                <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                                                    <ImageIcon size={14} />
                                                    <img src={previewUrl} alt="Pratinjau logo" style={{ width: 56, height: 56, borderRadius: 8, objectFit: 'contain', background: 'var(--db-soft)' }} />
                                                    <span style={{ flex: 1 }}>{form.data.logo?.name ?? 'Logo saat ini'}</span>
                                                    <button className="db-btn ghost sm" type="button" onClick={removeExistingLogo}>
                                                        <X size={13} /> Hapus
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                        <SwitchField
                                            label="Mitra aktif & siap menerima lowongan"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Mitra aktif dapat ditautkan ke lowongan baru dan tampil di direktori karir."
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Mitra Perusahaan' : 'Simpan Perubahan'}
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
