// Form tambah/ubah organisasi mahasiswa (admin).
import { Head, useForm } from '@inertiajs/react';
import { ImagePlus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    organization: {
        id: number | null;
        name: string;
        type: string;
        faculty_id: number | null;
        study_program_id: number | null;
        advisor: AsyncOption | null;
        description: string;
        founded_at: string;
        contact_email: string;
        contact_phone: string;
        instagram_url: string;
        is_active: boolean;
        logo_url: string | null;
    };
    options: {
        types: Record<string, string>;
        faculties: Array<{ id: number; name: string }>;
        studyPrograms: Array<{ id: number; name: string; facultyId: number | null }>;
    };
    urls: { index: string; submit: string; searchLecturers: string };
};

export default function OrganizationForm({ shell, mode, organization, options, urls }: Props) {
    const isCreate = mode === 'create';
    const [logoPreview, setLogoPreview] = useState<string | null>(organization.logo_url);

    const form = useForm({
        name: organization.name,
        type: organization.type,
        faculty_id: organization.faculty_id ? String(organization.faculty_id) : '',
        study_program_id: organization.study_program_id ? String(organization.study_program_id) : '',
        advisor_id: organization.advisor ? String(organization.advisor.id) : '',
        description: organization.description,
        founded_at: organization.founded_at,
        contact_email: organization.contact_email,
        contact_phone: organization.contact_phone,
        instagram_url: organization.instagram_url,
        is_active: organization.is_active,
        logo: null as File | null,
    });

    const advisorValue = form.data.advisor_id
        ? { id: form.data.advisor_id, label: organization.advisor?.label ?? 'Dosen pembina' }
        : null;

    const studyPrograms = useMemo(() => {
        if (!form.data.faculty_id) return options.studyPrograms;

        return options.studyPrograms.filter((program) => String(program.facultyId) === form.data.faculty_id);
    }, [form.data.faculty_id, options.studyPrograms]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const payload = {
            ...form.data,
            advisor_id: form.data.advisor_id === '' ? null : form.data.advisor_id,
            faculty_id: form.data.faculty_id === '' ? null : form.data.faculty_id,
            study_program_id: form.data.study_program_id === '' ? null : form.data.study_program_id,
        };

        form.transform(() => payload);

        if (isCreate) {
            form.post(urls.submit, { forceFormData: true });
            return;
        }

        form.put(urls.submit, { forceFormData: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Organisasi Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Kemahasiswaan"
                        title={isCreate ? 'Tambah Organisasi Mahasiswa' : `Edit ${organization.name}`}
                        description="Lengkapi identitas, profil, dan kontak organisasi. Data keanggotaan dikelola dari halaman detail."
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Identitas Organisasi</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <TextField
                                            label="Nama organisasi"
                                            required
                                            value={form.data.name}
                                            onChange={(event) => form.setData('name', event.target.value)}
                                            error={form.errors.name}
                                            placeholder="Contoh: Himpunan Mahasiswa Teknik Informatika"
                                        />
                                        <SelectField
                                            label="Jenis organisasi"
                                            required
                                            value={form.data.type}
                                            onChange={(event) => form.setData('type', event.target.value)}
                                            error={form.errors.type}
                                        >
                                            {Object.entries(options.types).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Fakultas"
                                            value={form.data.faculty_id}
                                            onChange={(event) => {
                                                form.setData('faculty_id', event.target.value);
                                                form.setData('study_program_id', '');
                                            }}
                                            error={form.errors.faculty_id}
                                            hint="Kosongkan untuk organisasi tingkat universitas."
                                        >
                                            <option value="">Tingkat universitas</option>
                                            {options.faculties.map((faculty) => (
                                                <option key={faculty.id} value={faculty.id}>{faculty.name}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Program studi"
                                            value={form.data.study_program_id}
                                            onChange={(event) => form.setData('study_program_id', event.target.value)}
                                            error={form.errors.study_program_id}
                                        >
                                            <option value="">Lintas program studi</option>
                                            {studyPrograms.map((program) => (
                                                <option key={program.id} value={program.id}>{program.name}</option>
                                            ))}
                                        </SelectField>
                                        <AsyncSelect
                                            label="Dosen pembina"
                                            fetchUrl={urls.searchLecturers}
                                            value={advisorValue}
                                            onChange={(option) => form.setData('advisor_id', option ? String(option.id) : '')}
                                            error={form.errors.advisor_id}
                                            hint="Cari berdasarkan nama atau NIDN dosen."
                                        />
                                        <TextField
                                            label="Tanggal berdiri"
                                            type="date"
                                            value={form.data.founded_at}
                                            onChange={(event) => form.setData('founded_at', event.target.value)}
                                            error={form.errors.founded_at}
                                        />
                                        <SwitchField
                                            label="Status organisasi"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            onLabel="Aktif"
                                            offLabel="Nonaktif"
                                            error={form.errors.is_active}
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Profil & Kontak</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Deskripsi"
                                                rows={4}
                                                value={form.data.description}
                                                onChange={(event) => form.setData('description', event.target.value)}
                                                error={form.errors.description}
                                                placeholder="Tuliskan visi, misi, dan fokus kegiatan organisasi."
                                            />
                                        </div>
                                        <TextField
                                            label="Email kontak"
                                            type="email"
                                            value={form.data.contact_email}
                                            onChange={(event) => form.setData('contact_email', event.target.value)}
                                            error={form.errors.contact_email}
                                        />
                                        <TextField
                                            label="Telepon / WhatsApp"
                                            value={form.data.contact_phone}
                                            onChange={(event) => form.setData('contact_phone', event.target.value)}
                                            error={form.errors.contact_phone}
                                        />
                                        <TextField
                                            label="Instagram"
                                            value={form.data.instagram_url}
                                            onChange={(event) => form.setData('instagram_url', event.target.value)}
                                            error={form.errors.instagram_url}
                                            hint="Gunakan URL lengkap, misalnya https://instagram.com/ormawa."
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Logo Organisasi</h2>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                                        {logoPreview ? (
                                            <img
                                                src={logoPreview}
                                                alt="Logo organisasi"
                                                style={{ width: 84, height: 84, objectFit: 'cover', borderRadius: 16, border: '1px solid var(--db-line)' }}
                                            />
                                        ) : (
                                            <span className="db-stat-icon" style={{ width: 84, height: 84, borderRadius: 16 }}>
                                                <ImagePlus size={28} />
                                            </span>
                                        )}
                                        <div style={{ flex: 1, minWidth: 220 }}>
                                            <label className="crud-label" htmlFor="organization-logo">Unggah logo baru</label>
                                            <input
                                                id="organization-logo"
                                                className="db-input"
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                onChange={(event) => {
                                                    const file = event.target.files?.[0] ?? null;
                                                    form.setData('logo', file);
                                                    setLogoPreview(file ? URL.createObjectURL(file) : organization.logo_url);
                                                }}
                                            />
                                            {form.errors.logo && <div className="db-error">{form.errors.logo}</div>}
                                            <div className="crud-hint">Format PNG, JPG, atau WEBP. Maksimal 2 MB.</div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Organisasi' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
