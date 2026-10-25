// Form bersama untuk membuat dan mengubah tugas.
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, FileText, Info, Paperclip, Save, Trash2, Upload } from 'lucide-react';
import { ChangeEvent, FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

export type AssignmentDefaults = {
    title: string;
    description: string;
    dueAt: string;
    maxScore: number;
    allowedFileTypes: string;
    maxFileSizeMb: number;
    allowTextSubmission: boolean;
    allowFileSubmission: boolean;
    allowResubmission: boolean;
    acceptLateSubmission: boolean;
    isPublished: boolean;
};

export type ExistingFile = { id: number; name: string; url: string; deleteUrl: string };

type OfferingInfo = {
    course: string;
    label: string;
    academicYear: string;
    studyProgram: string;
    enrolledCount: number;
};

type Props = {
    shell: ShellProps;
    pageTitle: string;
    heroDescription: string;
    defaults: AssignmentDefaults;
    existingFiles: ExistingFile[];
    offeringInfo: OfferingInfo;
    urls: { index: string; submit: string; show: string };
    submitMethod: 'post' | 'put';
};

type FormData = AssignmentDefaults & { instructionFiles: File[]; removeFileIds: number[] };

export default function AssignmentForm({ shell, pageTitle, heroDescription, defaults, existingFiles, offeringInfo, urls, submitMethod }: Props) {
    const form = useForm<FormData>({ ...defaults, instructionFiles: [], removeFileIds: [] });

    const toggles = [
        { label: 'Izinkan jawaban teks', hint: 'Mahasiswa dapat menulis jawaban langsung di halaman tugas.', checked: form.data.allowTextSubmission, set: (value: boolean) => form.setData('allowTextSubmission', value) },
        { label: 'Izinkan unggah berkas', hint: 'Mahasiswa dapat melampirkan dokumen atau gambar.', checked: form.data.allowFileSubmission, set: (value: boolean) => form.setData('allowFileSubmission', value) },
        { label: 'Izinkan pengumpulan ulang', hint: 'Mahasiswa dapat memperbaiki kiriman sebelum dinilai.', checked: form.data.allowResubmission, set: (value: boolean) => form.setData('allowResubmission', value) },
        { label: 'Terima keterlambatan', hint: 'Kiriman setelah deadline tetap diterima dan ditandai terlambat.', checked: form.data.acceptLateSubmission, set: (value: boolean) => form.setData('acceptLateSubmission', value) },
        { label: 'Publikasikan tugas', hint: 'Tugas langsung terlihat oleh mahasiswa kelas.', checked: form.data.isPublished, set: (value: boolean) => form.setData('isPublished', value) },
    ];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { forceFormData: true, preserveScroll: true };
        if (submitMethod === 'put') {
            form.put(urls.submit, options);
        } else {
            form.post(urls.submit, options);
        }
    };

    const toggleFileRemoval = (id: number) => {
        form.setData('removeFileIds', form.data.removeFileIds.includes(id)
            ? form.data.removeFileIds.filter((value) => value !== id)
            : [...form.data.removeFileIds, id]);
    };

    return (
        <AdminShell shell={shell}>
            <CrudHero
                icon={ClipboardList}
                eyebrow="Akademik · Tugas"
                title={pageTitle}
                description={heroDescription}
                badges={[`${offeringInfo.enrolledCount} mahasiswa`, offeringInfo.academicYear]}
                actions={<Link className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar tugas</Link>}
            />

            <div className="db-root">
                <div className="db-stack">
                    <div className="db-note info">
                        <Info size={15} />
                        <span>{offeringInfo.course} · {offeringInfo.label} · {offeringInfo.studyProgram}</span>
                    </div>

                    <form onSubmit={submit} className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardList size={16} /> Detail tugas</h2>
                        </div>
                        <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                            <div className="crud-grid">
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <label className="crud-label" htmlFor="assignment-title">Judul tugas <span>*</span></label>
                                    <input
                                        id="assignment-title"
                                        className="db-input"
                                        value={form.data.title}
                                        onChange={(event) => form.setData('title', event.target.value)}
                                        placeholder="Contoh: Analisis studi kasus minggu 5"
                                    />
                                    {form.errors.title && <div className="db-error">{form.errors.title}</div>}
                                </div>

                                <div style={{ gridColumn: '1 / -1' }}>
                                    <label className="crud-label" htmlFor="assignment-description">Deskripsi / instruksi</label>
                                    <textarea
                                        id="assignment-description"
                                        className="db-input"
                                        rows={5}
                                        value={form.data.description}
                                        onChange={(event) => form.setData('description', event.target.value)}
                                        placeholder="Tuliskan instruksi pengerjaan, kriteria penilaian, dan ketentuan lain."
                                    />
                                    {form.errors.description && <div className="db-error">{form.errors.description}</div>}
                                    <div className="crud-hint">Teks ini tampil sebagai instruksi utama pada halaman tugas mahasiswa.</div>
                                </div>

                                <div>
                                    <label className="crud-label" htmlFor="assignment-due">Deadline <span>*</span></label>
                                    <input
                                        id="assignment-due"
                                        type="datetime-local"
                                        className="db-input"
                                        value={form.data.dueAt}
                                        onChange={(event) => form.setData('dueAt', event.target.value)}
                                    />
                                    {form.errors.dueAt && <div className="db-error">{form.errors.dueAt}</div>}
                                </div>

                                <div>
                                    <label className="crud-label" htmlFor="assignment-max-score">Nilai maksimal <span>*</span></label>
                                    <input
                                        id="assignment-max-score"
                                        type="number"
                                        min={1}
                                        max={9999}
                                        className="db-input"
                                        value={form.data.maxScore}
                                        onChange={(event) => form.setData('maxScore', Number(event.target.value))}
                                    />
                                    {form.errors.maxScore && <div className="db-error">{form.errors.maxScore}</div>}
                                </div>

                                <div>
                                    <label className="crud-label" htmlFor="assignment-types">Ekstensi diizinkan</label>
                                    <input
                                        id="assignment-types"
                                        className="db-input"
                                        value={form.data.allowedFileTypes}
                                        onChange={(event) => form.setData('allowedFileTypes', event.target.value)}
                                        placeholder="pdf,doc,docx,jpg"
                                    />
                                    {form.errors.allowedFileTypes && <div className="db-error">{form.errors.allowedFileTypes}</div>}
                                    <div className="crud-hint">Pisahkan dengan koma tanpa titik. Kosongkan untuk menerima semua jenis file.</div>
                                </div>

                                <div>
                                    <label className="crud-label" htmlFor="assignment-size">Ukuran maksimal (MB) <span>*</span></label>
                                    <input
                                        id="assignment-size"
                                        type="number"
                                        min={1}
                                        max={100}
                                        className="db-input"
                                        value={form.data.maxFileSizeMb}
                                        onChange={(event) => form.setData('maxFileSizeMb', Number(event.target.value))}
                                    />
                                    {form.errors.maxFileSizeMb && <div className="db-error">{form.errors.maxFileSizeMb}</div>}
                                </div>
                            </div>

                            <div className="crud-grid">
                                {toggles.map((toggle) => (
                                    <label key={toggle.label} className="db-summary" style={{ cursor: 'pointer' }}>
                                        <div>
                                            <b>{toggle.label}</b>
                                            <small>{toggle.hint}</small>
                                        </div>
                                        <input
                                            type="checkbox"
                                            checked={toggle.checked}
                                            onChange={(event) => toggle.set(event.target.checked)}
                                        />
                                    </label>
                                ))}
                            </div>

                            {existingFiles.length > 0 && (
                                <div>
                                    <label className="crud-label">Berkas instruksi saat ini</label>
                                    <div style={{ display: 'grid', gap: 8 }}>
                                        {existingFiles.map((file) => {
                                            const marked = form.data.removeFileIds.includes(file.id);
                                            return (
                                                <div className="db-summary" key={file.id} style={{ opacity: marked ? 0.55 : 1 }}>
                                                    <div>
                                                        <b style={{ textDecoration: marked ? 'line-through' : 'none' }}><Paperclip size={13} /> {file.name}</b>
                                                        <small>{marked ? 'Akan dihapus saat disimpan' : 'Tetap terlampir'}</small>
                                                    </div>
                                                    <div style={{ display: 'flex', gap: 6 }}>
                                                        <a className="db-btn ghost sm" href={file.url} target="_blank" rel="noreferrer"><FileText size={12} /> Lihat</a>
                                                        <button className="db-btn danger sm" type="button" onClick={() => toggleFileRemoval(file.id)}>
                                                            <Trash2 size={12} /> {marked ? 'Batal' : 'Hapus'}
                                                        </button>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    {form.errors.removeFileIds && <div className="db-error">{form.errors.removeFileIds}</div>}
                                </div>
                            )}

                            <div>
                                <label className="crud-label" htmlFor="assignment-files">Tambah berkas instruksi</label>
                                <input
                                    id="assignment-files"
                                    type="file"
                                    multiple
                                    className="db-input"
                                    onChange={(event: ChangeEvent<HTMLInputElement>) => form.setData('instructionFiles', Array.from(event.target.files ?? []))}
                                />
                                {form.errors.instructionFiles && <div className="db-error">{form.errors.instructionFiles}</div>}
                                {form.data.instructionFiles.length > 0 && (
                                    <div className="crud-hint">{form.data.instructionFiles.length} berkas dipilih: {form.data.instructionFiles.map((file) => file.name).join(', ')}</div>
                                )}
                            </div>

                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
                                <button className="db-btn primary" type="submit" disabled={form.processing}>
                                    <Save size={14} /> {form.processing ? 'Menyimpan…' : 'Simpan tugas'}
                                </button>
                                <Link className="db-btn ghost" href={urls.index}><ArrowLeft size={14} /> Batal</Link>
                                {submitMethod === 'put' && (
                                    <Link className="db-btn ghost" href={urls.show}><Upload size={14} /> Lihat tugas</Link>
                                )}
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
