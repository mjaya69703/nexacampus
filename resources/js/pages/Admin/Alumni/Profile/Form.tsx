// Form tambah/ubah profil alumni.
import { Head, useForm } from '@inertiajs/react';
import { GraduationCap, Image as ImageIcon, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type ProfilePayload = {
    id?: number;
    nim: string;
    full_name: string;
    graduation_date: string;
    graduation_year: number | string;
    faculty_id: number | string;
    study_program_id: number | string;
    gpa: number | string | null;
    birth_date: string;
    gender: string;
    email: string;
    phone: string;
    address: string;
    current_city: string;
    current_province: string;
    employment_status: string;
    employer_name: string;
    job_title: string;
    job_industry: string;
    linkedin_url: string;
    is_active: boolean;
    photo_url: string | null;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    profile: ProfilePayload;
    facultyOptions: { id: number; name: string; code: string }[];
    studyProgramOptions: { id: number; name: string; facultyId: number }[];
    employmentStatusOptions: Record<string, string>;
    urls: { index: string; submit: string };
};

export default function AlumniProfileForm({
    shell,
    mode,
    profile,
    facultyOptions,
    studyProgramOptions,
    employmentStatusOptions,
    urls,
}: Props) {
    const isCreate = mode === 'create';
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);

    const form = useForm({
        nim: profile.nim,
        full_name: profile.full_name,
        graduation_date: profile.graduation_date,
        graduation_year: String(profile.graduation_year ?? ''),
        faculty_id: String(profile.faculty_id ?? ''),
        study_program_id: String(profile.study_program_id ?? ''),
        gpa: profile.gpa === '' || profile.gpa === null ? '' : String(profile.gpa),
        birth_date: profile.birth_date,
        gender: profile.gender,
        email: profile.email,
        phone: profile.phone,
        address: profile.address,
        current_city: profile.current_city,
        current_province: profile.current_province,
        employment_status: profile.employment_status,
        employer_name: profile.employer_name,
        job_title: profile.job_title,
        job_industry: profile.job_industry,
        linkedin_url: profile.linkedin_url,
        is_active: profile.is_active,
        photo: null as File | null,
        remove_photo: false as boolean,
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    const previewUrl = photoPreview ?? (form.data.remove_photo ? null : profile.photo_url);

    const programsForFaculty = studyProgramOptions.filter(
        (program) => !form.data.faculty_id || String(program.facultyId) === String(form.data.faculty_id),
    );

    const onFacultyChange = (value: string) => {
        const programStillValid = studyProgramOptions.some(
            (program) => String(program.id) === String(form.data.study_program_id)
                && String(program.facultyId) === value,
        );

        form.setData({
            ...form.data,
            faculty_id: value,
            study_program_id: programStillValid ? form.data.study_program_id : '',
        });
    };

    const onProgramChange = (value: string) => {
        const program = studyProgramOptions.find((item) => String(item.id) === value);

        form.setData({
            ...form.data,
            study_program_id: value,
            faculty_id: program ? String(program.facultyId) : form.data.faculty_id,
        });
    };

    const onPhotoChange = (file: File | null) => {
        if (photoPreview) URL.revokeObjectURL(photoPreview);
        form.setData({
            ...form.data,
            photo: file,
            remove_photo: file ? false : form.data.remove_photo,
        });
        setPhotoPreview(file ? URL.createObjectURL(file) : null);
    };

    const removeExistingPhoto = () => {
        if (photoPreview) URL.revokeObjectURL(photoPreview);
        form.setData({ ...form.data, photo: null, remove_photo: true });
        setPhotoPreview(null);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit, { forceFormData: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Profil Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Alumni"
                        title={isCreate ? 'Tambah Profil Alumni' : `Edit ${profile.full_name}`}
                        description="Lengkapi biodata, riwayat akademik, kontak, dan status karir lulusan. NIM dan email dipakai sebagai identitas utama alumni."
                        badges={isCreate ? ['Profil baru'] : [profile.nim]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Biodata & Akademik</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="NIM"
                                        required
                                        placeholder="Contoh: 2020101001"
                                        value={form.data.nim}
                                        onChange={(e) => form.setData('nim', e.target.value)}
                                        error={form.errors.nim}
                                    />
                                    <TextField
                                        label="Nama lengkap"
                                        required
                                        placeholder="Nama sesuai ijazah"
                                        value={form.data.full_name}
                                        onChange={(e) => form.setData('full_name', e.target.value)}
                                        error={form.errors.full_name}
                                    />
                                    <TextField
                                        label="Email"
                                        required
                                        type="email"
                                        placeholder="email@domain.com"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        error={form.errors.email}
                                    />
                                    <TextField
                                        label="Tanggal lulus"
                                        required
                                        type="date"
                                        value={form.data.graduation_date}
                                        onChange={(e) => form.setData('graduation_date', e.target.value)}
                                        error={form.errors.graduation_date}
                                    />
                                    <TextField
                                        label="Tahun lulus"
                                        required
                                        type="number"
                                        min={1990}
                                        max={2100}
                                        value={form.data.graduation_year}
                                        onChange={(e) => form.setData('graduation_year', e.target.value)}
                                        error={form.errors.graduation_year}
                                    />
                                    <TextField
                                        label="IPK akhir"
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        max={4}
                                        placeholder="0.00 - 4.00"
                                        value={form.data.gpa}
                                        onChange={(e) => form.setData('gpa', e.target.value)}
                                        error={form.errors.gpa}
                                    />
                                    <SelectField
                                        label="Fakultas"
                                        required
                                        value={form.data.faculty_id}
                                        onChange={(e) => onFacultyChange(e.target.value)}
                                        error={form.errors.faculty_id}
                                        hint={isCreate ? undefined : 'Mengubah fakultas akan mengosongkan program studi yang tidak sesuai.'}
                                    >
                                        <option value="">-- Pilih fakultas --</option>
                                        {facultyOptions.map((faculty) => (
                                            <option key={faculty.id} value={faculty.id}>{faculty.name}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Program studi"
                                        required
                                        value={form.data.study_program_id}
                                        onChange={(e) => onProgramChange(e.target.value)}
                                        error={form.errors.study_program_id}
                                        hint={form.data.faculty_id ? 'Pilihan disaring sesuai fakultas.' : 'Pilih fakultas terlebih dahulu agar pilihan lebih akurat.'}
                                    >
                                        <option value="">-- Pilih program studi --</option>
                                        {programsForFaculty.map((program) => (
                                            <option key={program.id} value={program.id}>{program.name}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Jenis kelamin"
                                        value={form.data.gender}
                                        onChange={(e) => form.setData('gender', e.target.value)}
                                        error={form.errors.gender}
                                    >
                                        <option value="">-- Pilih --</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </SelectField>
                                    <TextField
                                        label="Tanggal lahir"
                                        type="date"
                                        value={form.data.birth_date}
                                        onChange={(e) => form.setData('birth_date', e.target.value)}
                                        error={form.errors.birth_date}
                                    />
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Kontak & Domisili</h3>
                                    <div className="crud-grid">
                                        <TextField
                                            label="No. telepon / WhatsApp"
                                            placeholder="08xxxxxxxxx"
                                            value={form.data.phone}
                                            onChange={(e) => form.setData('phone', e.target.value)}
                                            error={form.errors.phone}
                                        />
                                        <TextField
                                            label="LinkedIn"
                                            type="url"
                                            placeholder="https://linkedin.com/in/username"
                                            value={form.data.linkedin_url}
                                            onChange={(e) => form.setData('linkedin_url', e.target.value)}
                                            error={form.errors.linkedin_url}
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Alamat lengkap"
                                                rows={2}
                                                placeholder="Jalan, RT/RW, kelurahan, kecamatan…"
                                                value={form.data.address}
                                                onChange={(e) => form.setData('address', e.target.value)}
                                                error={form.errors.address}
                                            />
                                        </div>
                                        <TextField
                                            label="Kota saat ini"
                                            placeholder="Contoh: Jakarta Selatan"
                                            value={form.data.current_city}
                                            onChange={(e) => form.setData('current_city', e.target.value)}
                                            error={form.errors.current_city}
                                        />
                                        <TextField
                                            label="Provinsi saat ini"
                                            placeholder="Contoh: DKI Jakarta"
                                            value={form.data.current_province}
                                            onChange={(e) => form.setData('current_province', e.target.value)}
                                            error={form.errors.current_province}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Status Karir</h3>
                                    <div className="crud-grid">
                                        <SelectField
                                            label="Status pekerjaan"
                                            required
                                            value={form.data.employment_status}
                                            onChange={(e) => form.setData('employment_status', e.target.value)}
                                            error={form.errors.employment_status}
                                        >
                                            {Object.entries(employmentStatusOptions).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Nama perusahaan / instansi"
                                            placeholder="Contoh: PT Nexa Indonesia"
                                            value={form.data.employer_name}
                                            onChange={(e) => form.setData('employer_name', e.target.value)}
                                            error={form.errors.employer_name}
                                        />
                                        <TextField
                                            label="Jabatan / posisi"
                                            placeholder="Contoh: Software Engineer"
                                            value={form.data.job_title}
                                            onChange={(e) => form.setData('job_title', e.target.value)}
                                            error={form.errors.job_title}
                                        />
                                        <TextField
                                            label="Bidang industri"
                                            placeholder="Contoh: Teknologi Informasi"
                                            value={form.data.job_industry}
                                            onChange={(e) => form.setData('job_industry', e.target.value)}
                                            error={form.errors.job_industry}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Foto & Status</h3>
                                    <div className="crud-grid">
                                        <div>
                                            <span className="crud-label">Foto profil</span>
                                            <input
                                                className="db-input"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                onChange={(e) => onPhotoChange(e.target.files?.[0] ?? null)}
                                            />
                                            {form.errors.photo && <div className="db-error">{form.errors.photo}</div>}
                                            <div className="crud-hint">Maksimal 2MB, format JPG/PNG/WebP.</div>
                                            {previewUrl && (
                                                <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                                                    <ImageIcon size={14} />
                                                    <img src={previewUrl} alt="Pratinjau foto" style={{ width: 56, height: 56, borderRadius: '50%', objectFit: 'cover' }} />
                                                    <span style={{ flex: 1 }}>{form.data.photo?.name ?? 'Foto saat ini'}</span>
                                                    <button className="db-btn ghost sm" type="button" onClick={removeExistingPhoto}>
                                                        <X size={13} /> Hapus
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                        <SwitchField
                                            label="Profil aktif & ditampilkan"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Profil nonaktif tidak muncul di penelusuran publik dan statistik aktif."
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Profil Alumni' : 'Simpan Perubahan'}
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
