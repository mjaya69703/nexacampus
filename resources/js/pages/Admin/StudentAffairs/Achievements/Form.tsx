// Form tambah/ubah prestasi mahasiswa (admin).
import { Head, useForm } from '@inertiajs/react';
import { FileText, Trophy } from 'lucide-react';
import { useState } from 'react';
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
    achievement: {
        id: number | null;
        student: AsyncOption | null;
        student_organization_id: number | null;
        title: string;
        category: string;
        level: string;
        rank: string;
        achieved_at: string;
        organizer: string;
        description: string;
        status: string;
        is_featured: boolean;
        rejection_reason: string;
        certificate_url: string | null;
        certificate_name: string | null;
    };
    options: {
        categories: Record<string, string>;
        levels: Record<string, string>;
        ranks: Record<string, string>;
        statuses: Record<string, string>;
        organizations: Array<{ id: number; name: string }>;
    };
    urls: { index: string; submit: string; searchStudents: string };
};

export default function AchievementForm({ shell, mode, achievement, options, urls }: Props) {
    const isCreate = mode === 'create';
    const [student, setStudent] = useState<AsyncOption | null>(achievement.student);

    const form = useForm({
        user_id: achievement.student ? String(achievement.student.id) : '',
        student_organization_id: achievement.student_organization_id ? String(achievement.student_organization_id) : '',
        title: achievement.title,
        category: achievement.category,
        level: achievement.level,
        rank: achievement.rank,
        achieved_at: achievement.achieved_at,
        organizer: achievement.organizer,
        description: achievement.description,
        status: achievement.status,
        is_featured: achievement.is_featured,
        rejection_reason: achievement.rejection_reason,
        certificate: null as File | null,
        remove_certificate: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const payload = {
            ...form.data,
            student_organization_id: form.data.student_organization_id === '' ? null : form.data.student_organization_id,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Prestasi Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Trophy}
                        eyebrow="Kemahasiswaan"
                        title={isCreate ? 'Tambah Prestasi Mahasiswa' : `Edit ${achievement.title}`}
                        description="Catat capaian mahasiswa secara lengkap. Prestasi berstatus terverifikasi akan tampil di halaman publik."
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Data Prestasi</h2></div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <AsyncSelect
                                            label="Mahasiswa"
                                            required
                                            fetchUrl={urls.searchStudents}
                                            value={student}
                                            onChange={(option) => {
                                                setStudent(option);
                                                form.setData('user_id', option ? String(option.id) : '');
                                            }}
                                            error={form.errors.user_id}
                                            placeholder="Cari nama atau NIM…"
                                        />
                                        <SelectField
                                            label="Organisasi"
                                            value={form.data.student_organization_id}
                                            onChange={(event) => form.setData('student_organization_id', event.target.value)}
                                            error={form.errors.student_organization_id}
                                            hint="Opsional — kaitkan prestasi dengan ormawa."
                                        >
                                            <option value="">Prestasi individu</option>
                                            {options.organizations.map((organization) => (
                                                <option key={organization.id} value={organization.id}>{organization.name}</option>
                                            ))}
                                        </SelectField>
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextField
                                                label="Judul prestasi"
                                                required
                                                value={form.data.title}
                                                onChange={(event) => form.setData('title', event.target.value)}
                                                error={form.errors.title}
                                                placeholder="Contoh: Juara 1 Hackathon Nasional 2026"
                                            />
                                        </div>
                                        <SelectField
                                            label="Kategori"
                                            required
                                            value={form.data.category}
                                            onChange={(event) => form.setData('category', event.target.value)}
                                            error={form.errors.category}
                                        >
                                            {Object.entries(options.categories).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Tingkat"
                                            required
                                            value={form.data.level}
                                            onChange={(event) => form.setData('level', event.target.value)}
                                            error={form.errors.level}
                                        >
                                            {Object.entries(options.levels).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Peringkat"
                                            required
                                            value={form.data.rank}
                                            onChange={(event) => form.setData('rank', event.target.value)}
                                            error={form.errors.rank}
                                        >
                                            {Object.entries(options.ranks).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Tanggal capaian"
                                            type="date"
                                            required
                                            value={form.data.achieved_at}
                                            onChange={(event) => form.setData('achieved_at', event.target.value)}
                                            error={form.errors.achieved_at}
                                        />
                                        <TextField
                                            label="Penyelenggara"
                                            value={form.data.organizer}
                                            onChange={(event) => form.setData('organizer', event.target.value)}
                                            error={form.errors.organizer}
                                            placeholder="Contoh: Kementerian Pendidikan"
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Deskripsi"
                                                rows={3}
                                                value={form.data.description}
                                                onChange={(event) => form.setData('description', event.target.value)}
                                                error={form.errors.description}
                                                placeholder="Ringkasan kompetisi dan capaian mahasiswa."
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Verifikasi & Publikasi</h2></div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <SelectField
                                            label="Status verifikasi"
                                            required
                                            value={form.data.status}
                                            onChange={(event) => form.setData('status', event.target.value)}
                                            error={form.errors.status}
                                            hint="Prestasi terverifikasi tampil di galeri publik."
                                        >
                                            {Object.entries(options.statuses).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <SwitchField
                                            label="Sorotan publik"
                                            checked={form.data.is_featured}
                                            onChange={(value) => form.setData('is_featured', value)}
                                            onLabel="Tampil di sorotan"
                                            offLabel="Tidak disorot"
                                            hint="Hanya berlaku untuk prestasi terverifikasi."
                                        />
                                        {form.data.status === 'rejected' && (
                                            <div style={{ gridColumn: '1 / -1' }}>
                                                <TextareaField
                                                    label="Alasan penolakan"
                                                    required
                                                    rows={2}
                                                    value={form.data.rejection_reason}
                                                    onChange={(event) => form.setData('rejection_reason', event.target.value)}
                                                    error={form.errors.rejection_reason}
                                                />
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Sertifikat</h2></div>
                                <div className="db-card-body">
                                    {achievement.certificate_url && (
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
                                            <span className="db-stat-icon" style={{ width: 38, height: 38, borderRadius: 10 }}><FileText size={17} /></span>
                                            <div style={{ flex: 1 }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>
                                                    {achievement.certificate_name || 'Sertifikat prestasi'}
                                                </b>
                                                <a className="db-hint" href={achievement.certificate_url} target="_blank" rel="noopener noreferrer">
                                                    Unduh sertifikat saat ini
                                                </a>
                                            </div>
                                            <label className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 7 }}>
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.remove_certificate}
                                                    onChange={(event) => form.setData('remove_certificate', event.target.checked)}
                                                />
                                                Hapus
                                            </label>
                                        </div>
                                    )}
                                    <label className="crud-label" htmlFor="achievement-certificate">Unggah sertifikat baru</label>
                                    <input
                                        id="achievement-certificate"
                                        className="db-input"
                                        type="file"
                                        accept="application/pdf,image/png,image/jpeg"
                                        onChange={(event) => form.setData('certificate', event.target.files?.[0] ?? null)}
                                    />
                                    {form.errors.certificate && <div className="db-error">{form.errors.certificate}</div>}
                                    <div className="crud-hint">Format PDF, PNG, atau JPG. Maksimal 4 MB.</div>
                                </div>
                            </section>

                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Prestasi' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
