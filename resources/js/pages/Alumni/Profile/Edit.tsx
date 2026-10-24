// Edit profil alumni — form kontak & karir (Inertia React).
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextField, TextareaField } from '../../../components/Shared/Crud/fields';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type ProfileForm = {
    phone: string;
    address: string;
    current_city: string;
    current_province: string;
    employment_status: string;
    employer_name: string;
    job_title: string;
    job_industry: string;
    linkedin_url: string;
};

type Props = {
    shell: ShellProps;
    profile: ProfileForm;
    employmentStatusOptions: Record<string, string>;
    urls: { index: string; update: string };
};

const EMPLOYED = ['working', 'entrepreneur'];

export default function ProfileEdit({ shell, profile, employmentStatusOptions, urls }: Props) {
    const form = useForm<ProfileForm>(profile);
    const employed = EMPLOYED.includes(form.data.employment_status);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Edit Profil Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Pencil}
                        eyebrow="Portal alumni"
                        title="Edit Profil"
                        description="Perbarui informasi kontak, domisili, dan data karir kamu."
                        actions={(
                            <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                        )}
                    />

                    <form onSubmit={submit} className="db-stack">
                        <div className="cx-cards-2">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Pencil size={16} /> Informasi Kontak</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                    <TextField
                                        label="No. Telepon"
                                        value={form.data.phone}
                                        onChange={(event) => form.setData('phone', event.target.value)}
                                        error={form.errors.phone}
                                        placeholder="08xxxxxxxxxx"
                                        autoComplete="tel"
                                    />
                                    <TextareaField
                                        label="Alamat"
                                        value={form.data.address}
                                        onChange={(event) => form.setData('address', event.target.value)}
                                        error={form.errors.address}
                                        placeholder="Alamat lengkap"
                                        rows={3}
                                    />
                                    <div className="crud-grid">
                                        <TextField
                                            label="Kota"
                                            value={form.data.current_city}
                                            onChange={(event) => form.setData('current_city', event.target.value)}
                                            error={form.errors.current_city}
                                            placeholder="Kota saat ini"
                                        />
                                        <TextField
                                            label="Provinsi"
                                            value={form.data.current_province}
                                            onChange={(event) => form.setData('current_province', event.target.value)}
                                            error={form.errors.current_province}
                                            placeholder="Provinsi"
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Save size={16} /> Informasi Karir</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                    <SelectField
                                        label="Status Pekerjaan"
                                        required
                                        value={form.data.employment_status}
                                        onChange={(event) => form.setData('employment_status', event.target.value)}
                                        error={form.errors.employment_status}
                                    >
                                        {Object.entries(employmentStatusOptions).map(([value, label]) => (
                                            <option key={value} value={value}>{label}</option>
                                        ))}
                                    </SelectField>

                                    {employed && (
                                        <>
                                            <TextField
                                                label="Nama Perusahaan"
                                                value={form.data.employer_name}
                                                onChange={(event) => form.setData('employer_name', event.target.value)}
                                                error={form.errors.employer_name}
                                                placeholder="Nama perusahaan/instansi"
                                            />
                                            <div className="crud-grid">
                                                <TextField
                                                    label="Jabatan"
                                                    value={form.data.job_title}
                                                    onChange={(event) => form.setData('job_title', event.target.value)}
                                                    error={form.errors.job_title}
                                                    placeholder="Jabatan/posisi"
                                                />
                                                <TextField
                                                    label="Industri"
                                                    value={form.data.job_industry}
                                                    onChange={(event) => form.setData('job_industry', event.target.value)}
                                                    error={form.errors.job_industry}
                                                    placeholder="Teknologi, Keuangan, ..."
                                                />
                                            </div>
                                        </>
                                    )}

                                    {!employed && (
                                        <div className="db-note info">
                                            Data perusahaan/jabatan akan dikosongkan karena status kamu bukan pekerja/wirausaha.
                                        </div>
                                    )}

                                    <TextField
                                        label="LinkedIn URL"
                                        type="url"
                                        value={form.data.linkedin_url}
                                        onChange={(event) => form.setData('linkedin_url', event.target.value)}
                                        error={form.errors.linkedin_url}
                                        placeholder="https://linkedin.com/in/username"
                                    />
                                </div>
                            </section>
                        </div>

                        <FormActions
                            cancelHref={urls.index}
                            submitLabel={form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                            processing={form.processing}
                        />
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
