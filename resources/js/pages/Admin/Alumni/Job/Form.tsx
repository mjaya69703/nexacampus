// Form tambah/ubah lowongan kerja alumni.
import { Head, useForm } from '@inertiajs/react';
import { Briefcase } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type JobPayload = {
    id?: number;
    employer_partner_id: number | string;
    title: string;
    company_name: string;
    industry: string;
    description: string;
    requirements: string;
    location: string;
    job_type: string;
    salary_range: string;
    apply_url: string;
    contact_email: string;
    posted_date: string;
    deadline_date: string;
    is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    job: JobPayload;
    partnerOptions: { id: number; name: string; industry: string | null; isActive: boolean }[];
    jobTypeOptions: Record<string, string>;
    urls: { index: string; submit: string };
};

export default function JobPostingForm({ shell, mode, job, partnerOptions, jobTypeOptions, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        employer_partner_id: String(job.employer_partner_id ?? ''),
        title: job.title,
        company_name: job.company_name,
        industry: job.industry,
        description: job.description,
        requirements: job.requirements,
        location: job.location,
        job_type: job.job_type,
        salary_range: job.salary_range,
        apply_url: job.apply_url,
        contact_email: job.contact_email,
        posted_date: job.posted_date,
        deadline_date: job.deadline_date,
        is_active: job.is_active,
    });

    const onPartnerChange = (value: string) => {
        const previous = partnerOptions.find((partner) => String(partner.id) === String(form.data.employer_partner_id));
        const next = partnerOptions.find((partner) => String(partner.id) === value);

        const companyWasAuto = !form.data.company_name || form.data.company_name === previous?.name;
        const industryWasAuto = !form.data.industry || form.data.industry === previous?.industry;

        form.setData({
            ...form.data,
            employer_partner_id: value,
            company_name: companyWasAuto ? (next?.name ?? '') : form.data.company_name,
            industry: industryWasAuto ? (next?.industry ?? '') : form.data.industry,
        });
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Lowongan Kerja · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Briefcase}
                        eyebrow="Alumni"
                        title={isCreate ? 'Publikasi Lowongan Baru' : `Edit ${job.title}`}
                        description="Lengkapi identitas posisi, penempatan, kompensasi, serta kualifikasi kandidat. Pilih mitra terdaftar agar data perusahaan terisi otomatis."
                        badges={isCreate ? ['Lowongan baru'] : [job.company_name]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Briefcase size={16} /> Identitas Posisi & Perusahaan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Judul lowongan"
                                        required
                                        placeholder="Contoh: Senior Full Stack Developer"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        error={form.errors.title}
                                    />
                                    <TextField
                                        label="Nama perusahaan"
                                        required
                                        placeholder="Contoh: PT Teknologi Nusantara"
                                        value={form.data.company_name}
                                        onChange={(e) => form.setData('company_name', e.target.value)}
                                        error={form.errors.company_name}
                                        hint="Wajib diisi. Bila memilih mitra, nama mitra dipakai sebagai nilai awal."
                                    />
                                    <SelectField
                                        label="Mitra terdaftar (opsional)"
                                        value={form.data.employer_partner_id}
                                        onChange={(e) => onPartnerChange(e.target.value)}
                                        error={form.errors.employer_partner_id}
                                    >
                                        <option value="">-- Pilih dari mitra perusahaan --</option>
                                        {partnerOptions.map((partner) => (
                                            <option key={partner.id} value={partner.id}>{partner.name}{partner.isActive ? '' : ' (nonaktif)'}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Sektor industri"
                                        placeholder="Contoh: Teknologi Informasi / Perbankan"
                                        value={form.data.industry}
                                        onChange={(e) => form.setData('industry', e.target.value)}
                                        error={form.errors.industry}
                                    />
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Penempatan & Kompensasi</h3>
                                    <div className="crud-grid">
                                        <SelectField
                                            label="Tipe pekerjaan"
                                            value={form.data.job_type}
                                            onChange={(e) => form.setData('job_type', e.target.value)}
                                            error={form.errors.job_type}
                                        >
                                            <option value="">-- Pilih tipe --</option>
                                            {Object.entries(jobTypeOptions).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Lokasi kerja"
                                            placeholder="Contoh: Jakarta / Remote"
                                            value={form.data.location}
                                            onChange={(e) => form.setData('location', e.target.value)}
                                            error={form.errors.location}
                                        />
                                        <TextField
                                            label="Rentang gaji"
                                            placeholder="Contoh: 8 - 12 juta / kompetitif"
                                            value={form.data.salary_range}
                                            onChange={(e) => form.setData('salary_range', e.target.value)}
                                            error={form.errors.salary_range}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Periode & Cara Melamar</h3>
                                    <div className="crud-grid">
                                        <TextField
                                            label="Tanggal posting"
                                            required
                                            type="date"
                                            value={form.data.posted_date}
                                            onChange={(e) => form.setData('posted_date', e.target.value)}
                                            error={form.errors.posted_date}
                                        />
                                        <TextField
                                            label="Batas akhir (deadline)"
                                            required
                                            type="date"
                                            min={form.data.posted_date || undefined}
                                            value={form.data.deadline_date}
                                            onChange={(e) => form.setData('deadline_date', e.target.value)}
                                            error={form.errors.deadline_date}
                                        />
                                        <TextField
                                            label="Tautan pendaftaran"
                                            type="url"
                                            placeholder="https://company.com/careers/apply"
                                            value={form.data.apply_url}
                                            onChange={(e) => form.setData('apply_url', e.target.value)}
                                            error={form.errors.apply_url}
                                        />
                                        <TextField
                                            label="Email pengiriman CV"
                                            type="email"
                                            placeholder="recruitment@company.com"
                                            value={form.data.contact_email}
                                            onChange={(e) => form.setData('contact_email', e.target.value)}
                                            error={form.errors.contact_email}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Deskripsi & Persyaratan</h3>
                                    <div className="crud-grid-1">
                                        <TextareaField
                                            label="Deskripsi pekerjaan"
                                            required
                                            rows={5}
                                            placeholder="Jelaskan tanggung jawab utama, lingkup proyek, atau budaya tim…"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />
                                        <TextareaField
                                            label="Kualifikasi & persyaratan"
                                            rows={5}
                                            placeholder="Sebutkan latar belakang pendidikan, keahlian teknis, dan pengalaman…"
                                            value={form.data.requirements}
                                            onChange={(e) => form.setData('requirements', e.target.value)}
                                            error={form.errors.requirements}
                                        />
                                    </div>
                                    <div style={{ marginTop: 16 }}>
                                        <SwitchField
                                            label="Langsung aktif & ditampilkan di portal karir"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Jika nonaktif, lowongan disimpan sebagai draft internal admin."
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Lowongan' : 'Simpan Perubahan'}
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
