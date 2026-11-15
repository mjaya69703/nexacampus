// Form tambah/ubah kampanye tracer study + editor pertanyaan.
import { Head, useForm } from '@inertiajs/react';
import { ClipboardList, GripVertical, Plus, Send, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Question = {
    id: string;
    type: string;
    text: string;
    options: string;
};

type CampaignPayload = {
    id?: number;
    title: string;
    description: string;
    academic_year_id: number | string | null;
    target_graduation_years: number[];
    target_study_program_ids: number[];
    start_date: string;
    end_date: string;
    status: string;
    questions: Question[];
    total_sent: number;
    total_responded: number;
    responses_count: number;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    campaign: CampaignPayload;
    statusOptions: Record<string, string>;
    academicYearOptions: { id: number; name: string }[];
    studyProgramOptions: { id: number; name: string; code: string }[];
    yearOptions: number[];
    urls: { index: string; submit: string };
};

const QUESTION_TYPES: Record<string, string> = {
    text: 'Isian singkat',
    textarea: 'Isian panjang',
    radio: 'Pilihan tunggal (radio)',
    select: 'Dropdown',
};

function newQuestion(): Question {
    return {
        id: `q_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
        type: 'text',
        text: '',
        options: '',
    };
}

export default function TracerStudyForm({ shell, mode, campaign, statusOptions, academicYearOptions, studyProgramOptions, yearOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const isLocked = !isCreate && campaign.status !== 'draft';

    const form = useForm({
        title: campaign.title,
        description: campaign.description,
        academic_year_id: String(campaign.academic_year_id ?? ''),
        target_graduation_years: (campaign.target_graduation_years ?? []).map(String),
        target_study_program_ids: (campaign.target_study_program_ids ?? []).map(String),
        start_date: campaign.start_date,
        end_date: campaign.end_date,
        status: campaign.status,
        questions: (campaign.questions ?? []) as Question[],
    });

    const statusEntries = Object.entries(statusOptions).filter(([value]) => {
        if (!isCreate && campaign.status === 'active') return value !== 'draft';
        if (!isCreate && campaign.status === 'closed') return value === 'closed';
        return true;
    });

    const fieldError = (key: string) => (form.errors as Record<string, string | undefined>)[key];

    const setQuestion = (index: number, patch: Partial<Question>) => {
        const next = form.data.questions.map((question, i) => (i === index ? { ...question, ...patch } : question));
        form.setData('questions', next);
    };

    const addQuestion = () => {
        form.setData('questions', [...form.data.questions, newQuestion()]);
    };

    const removeQuestion = (index: number) => {
        form.setData('questions', form.data.questions.filter((_, i) => i !== index));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
            return;
        }

        form.put(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Kampanye Tracer Study · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Alumni"
                        title={isCreate ? 'Susun Kampanye Tracer Study' : `Edit ${campaign.title}`}
                        description="Tentukan periode, sasaran alumni, dan daftar pertanyaan. Kampanye berstatus draft belum dapat diisi alumni."
                        badges={isCreate ? ['Kampanye baru'] : [`Status: ${statusOptions[campaign.status] ?? campaign.status}`, `${campaign.questions.length} pertanyaan`]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    {!isCreate && (
                        <section className="db-stats" aria-label="Statistik kampanye">
                            <div className="db-stat">
                                <span className="db-stat-icon"><Send size={20} /></span>
                                <div><span className="db-stat-num">{campaign.total_sent}</span><span className="db-stat-label">Sasaran terkirim</span></div>
                            </div>
                            <div className="db-stat">
                                <span className="db-stat-icon green"><ClipboardList size={20} /></span>
                                <div><span className="db-stat-num">{campaign.responses_count}</span><span className="db-stat-label">Respon masuk</span></div>
                            </div>
                            <div className="db-stat">
                                <span className="db-stat-icon gold"><ClipboardList size={20} /></span>
                                <div><span className="db-stat-num">{campaign.total_responded}</span><span className="db-stat-label">Counter respon</span></div>
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardList size={16} /> Informasi Kampanye</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Judul kampanye"
                                        required
                                        placeholder="Contoh: Tracer Study Lulusan 2026"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        error={form.errors.title}
                                    />
                                    <SelectField
                                        label="Tahun akademik"
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value)}
                                        error={form.errors.academic_year_id}
                                    >
                                        <option value="">-- Tanpa tahun akademik --</option>
                                        {academicYearOptions.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Mulai"
                                        required
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(e) => form.setData('start_date', e.target.value)}
                                        error={form.errors.start_date}
                                    />
                                    <TextField
                                        label="Selesai"
                                        required
                                        type="date"
                                        min={form.data.start_date || undefined}
                                        value={form.data.end_date}
                                        onChange={(e) => form.setData('end_date', e.target.value)}
                                        error={form.errors.end_date}
                                    />
                                    <SelectField
                                        label="Status"
                                        required
                                        value={form.data.status}
                                        onChange={(e) => form.setData('status', e.target.value)}
                                        error={form.errors.status}
                                        disabled={!isCreate && campaign.status === 'closed'}
                                        hint={isLocked ? 'Kampanye yang sudah berjalan tidak bisa dikembalikan ke draft.' : 'Status aktif akan menghitung jumlah alumni sasaran saat disimpan.'}
                                    >
                                        {statusEntries.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </SelectField>
                                </div>

                                <div className="crud-grid-1" style={{ marginTop: 20 }}>
                                    <TextareaField
                                        label="Deskripsi & tujuan"
                                        rows={4}
                                        placeholder="Jelaskan tujuan tracer study dan imbauan pengisian untuk alumni…"
                                        value={form.data.description}
                                        onChange={(e) => form.setData('description', e.target.value)}
                                        error={form.errors.description}
                                    />
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Sasaran Alumni</h3>
                                    <div className="crud-grid">
                                        <div>
                                            <label className="crud-label" htmlFor="target-years">Tahun lulus</label>
                                            <select
                                                id="target-years"
                                                className="db-input"
                                                multiple
                                                size={Math.min(Math.max(yearOptions.length, 3), 6)}
                                                value={form.data.target_graduation_years}
                                                onChange={(e) => form.setData('target_graduation_years', Array.from(e.target.selectedOptions).map((option) => option.value))}
                                            >
                                                {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                                            </select>
                                            <div className="crud-hint">Kosongkan untuk semua tahun. Tahan Ctrl/Cmd untuk memilih beberapa tahun.</div>
                                            {form.errors.target_graduation_years && <div className="db-error">{form.errors.target_graduation_years}</div>}
                                        </div>
                                        <div>
                                            <label className="crud-label" htmlFor="target-programs">Program studi</label>
                                            <select
                                                id="target-programs"
                                                className="db-input"
                                                multiple
                                                size={Math.min(Math.max(studyProgramOptions.length, 3), 6)}
                                                value={form.data.target_study_program_ids}
                                                onChange={(e) => form.setData('target_study_program_ids', Array.from(e.target.selectedOptions).map((option) => option.value))}
                                            >
                                                {studyProgramOptions.map((program) => (
                                                    <option key={program.id} value={program.id}>{program.name} ({program.code})</option>
                                                ))}
                                            </select>
                                            <div className="crud-hint">Kosongkan untuk semua program studi.</div>
                                            {form.errors.target_study_program_ids && <div className="db-error">{form.errors.target_study_program_ids}</div>}
                                        </div>
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap', marginBottom: 12 }}>
                                        <h3 className="db-card-title">Pertanyaan Kuesioner ({form.data.questions.length})</h3>
                                        <button className="db-btn ghost sm" type="button" onClick={addQuestion}>
                                            <Plus size={13} /> Tambah Pertanyaan
                                        </button>
                                    </div>

                                    {form.data.questions.length === 0 && (
                                        <p className="db-hint">Belum ada pertanyaan. Alumni tetap dapat mengisi data pekerjaan utama, tetapi kuesioner tambahan akan kosong.</p>
                                    )}

                                    {form.data.questions.map((question, index) => (
                                        <div key={question.id} className="db-card" style={{ marginBottom: 14, boxShadow: 'none' }}>
                                            <div className="db-card-head" style={{ paddingBottom: 8 }}>
                                                <h4 className="db-card-title" style={{ fontSize: 13 }}>
                                                    <GripVertical size={14} /> Pertanyaan {index + 1}
                                                </h4>
                                                <button className="db-btn danger sm" type="button" onClick={() => removeQuestion(index)} title="Hapus pertanyaan">
                                                    <Trash2 size={13} />
                                                </button>
                                            </div>
                                            <div className="db-card-body">
                                                <div className="crud-grid">
                                                    <SelectField
                                                        label="Tipe pertanyaan"
                                                        value={question.type}
                                                        onChange={(e) => setQuestion(index, { type: e.target.value, options: e.target.value === 'radio' || e.target.value === 'select' ? question.options : '' })}
                                                        error={fieldError(`questions.${index}.type`)}
                                                    >
                                                        {Object.entries(QUESTION_TYPES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                                    </SelectField>
                                                    <TextField
                                                        label="Teks pertanyaan"
                                                        required
                                                        placeholder="Contoh: Bagaimana relevansi pekerjaan Anda dengan bidang studi?"
                                                        value={question.text}
                                                        onChange={(e) => setQuestion(index, { text: e.target.value })}
                                                        error={fieldError(`questions.${index}.text`)}
                                                    />
                                                </div>
                                                {(question.type === 'radio' || question.type === 'select') && (
                                                    <div style={{ marginTop: 12 }}>
                                                        <TextareaField
                                                            label="Pilihan jawaban"
                                                            rows={4}
                                                            placeholder={'Satu pilihan per baris, contoh:\nSangat relevan\nCukup relevan\nTidak relevan'}
                                                            value={question.options}
                                                            onChange={(e) => setQuestion(index, { options: e.target.value })}
                                                            error={fieldError(`questions.${index}.options`)}
                                                            hint="Satu pilihan per baris. ID pertanyaan tetap stabil agar jawaban lama tidak tertukar."
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Kampanye' : 'Simpan Perubahan'}
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
