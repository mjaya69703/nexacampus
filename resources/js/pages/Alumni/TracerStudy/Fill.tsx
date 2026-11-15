// Isi survei tracer study — snapshot karir + jawaban pertanyaan (Inertia React).
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Briefcase, ClipboardList, Info, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { SelectField, SwitchField, TextField, TextareaField } from '../../../components/Shared/Crud/fields';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Question = {
    id: string;
    type: string;
    text: string;
    options: string[];
};

type Snapshot = {
    employment_status: string;
    employer_name: string;
    job_title: string;
    job_relevance: string;
    time_to_employment_months: string;
    salary_range: string;
    further_study: boolean;
};

type FormData = {
    snapshot: Snapshot;
    answers: Record<string, string>;
};

type Props = {
    shell: ShellProps;
    campaign: {
        id: number;
        title: string;
        startDate: string | null;
        endDate: string | null;
        questions: Question[];
    };
    snapshot: Snapshot;
    employmentStatusOptions: Record<string, string>;
    jobRelevanceOptions: Record<string, string>;
    urls: { show: string; submit: string };
};

const EMPLOYED = ['working', 'entrepreneur'];

export default function TracerStudyFill({ shell, campaign, snapshot, employmentStatusOptions, jobRelevanceOptions, urls }: Props) {
    const form = useForm<FormData>({
        snapshot,
        answers: Object.fromEntries(campaign.questions.map((question) => [question.id, ''])),
    });

    const employed = EMPLOYED.includes(form.data.snapshot.employment_status);

    const setSnapshot = <K extends keyof Snapshot>(key: K, value: Snapshot[K]) => {
        form.setData('snapshot', { ...form.data.snapshot, [key]: value });
    };

    const setAnswer = (id: string, value: string) => {
        form.setData('answers', { ...form.data.answers, [id]: value });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit, { preserveScroll: true });
    };

    const answerError = (id: string) => form.errors[`answers.${id}`];

    return (
        <AdminShell shell={shell}>
            <Head title={`Isi Survei ${campaign.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Tracer study"
                        title={campaign.title}
                        description="Jawab seluruh pertanyaan dengan jujur. Data digunakan untuk evaluasi mutu pendidikan."
                        badges={[`${campaign.questions.length} pertanyaan`, `${campaign.startDate ?? '-'} - ${campaign.endDate ?? '-'}`]}
                        actions={(
                            <a className="db-btn light" href={urls.show}><ArrowLeft size={15} /> Kembali</a>
                        )}
                    />

                    <form onSubmit={submit} className="db-stack">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Briefcase size={16} /> Data Pekerjaan Saat Ini</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Status Pekerjaan"
                                        required
                                        value={form.data.snapshot.employment_status}
                                        onChange={(event) => setSnapshot('employment_status', event.target.value)}
                                        error={form.errors['snapshot.employment_status']}
                                    >
                                        {Object.entries(employmentStatusOptions).map(([value, label]) => (
                                            <option key={value} value={value}>{label}</option>
                                        ))}
                                    </SelectField>

                                    {employed && (
                                        <TextField
                                            label="Nama Perusahaan"
                                            value={form.data.snapshot.employer_name}
                                            onChange={(event) => setSnapshot('employer_name', event.target.value)}
                                            error={form.errors['snapshot.employer_name']}
                                            placeholder="Nama perusahaan/instansi"
                                        />
                                    )}
                                </div>

                                {employed && (
                                    <div className="crud-grid">
                                        <TextField
                                            label="Jabatan"
                                            value={form.data.snapshot.job_title}
                                            onChange={(event) => setSnapshot('job_title', event.target.value)}
                                            error={form.errors['snapshot.job_title']}
                                            placeholder="Jabatan/posisi"
                                        />
                                        <SelectField
                                            label="Kesesuaian dengan Studi"
                                            value={form.data.snapshot.job_relevance}
                                            onChange={(event) => setSnapshot('job_relevance', event.target.value)}
                                            error={form.errors['snapshot.job_relevance']}
                                        >
                                            <option value="">-- Pilih --</option>
                                            {Object.entries(jobRelevanceOptions).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Waktu Dapat Kerja (bulan)"
                                            type="number"
                                            min={0}
                                            max={600}
                                            value={form.data.snapshot.time_to_employment_months}
                                            onChange={(event) => setSnapshot('time_to_employment_months', event.target.value)}
                                            error={form.errors['snapshot.time_to_employment_months']}
                                            hint="Jumlah bulan setelah lulus hingga mendapatkan pekerjaan pertama."
                                        />
                                        <TextField
                                            label="Range Gaji"
                                            value={form.data.snapshot.salary_range}
                                            onChange={(event) => setSnapshot('salary_range', event.target.value)}
                                            error={form.errors['snapshot.salary_range']}
                                            placeholder="Contoh: 5-8 juta"
                                        />
                                    </div>
                                )}

                                {!employed && (
                                    <div className="db-note info">
                                        <Info size={15} />
                                        <span>Data perusahaan, jabatan, dan gaji tidak perlu diisi karena status kamu bukan pekerja/wirausaha.</span>
                                    </div>
                                )}

                                <SwitchField
                                    label="Studi lanjut"
                                    checked={form.data.snapshot.further_study}
                                    onChange={(value) => setSnapshot('further_study', value)}
                                    hint="Centang jika kamu sedang atau telah menempuh studi lanjut."
                                />
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><ClipboardList size={16} /> Pertanyaan Survei</h2>
                                <span className="db-badge gray">Wajib dijawab</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 16 }}>
                                {campaign.questions.length === 0 ? (
                                    <p className="db-hint" style={{ margin: 0 }}>Belum ada pertanyaan pada kampanye ini.</p>
                                ) : (
                                    campaign.questions.map((question, index) => (
                                        <div
                                            key={question.id}
                                            style={{ padding: '14px', border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-soft)', display: 'grid', gap: 10 }}
                                        >
                                            <div style={{ color: 'var(--db-heading)', fontSize: 13.5, fontWeight: 700, lineHeight: 1.6 }}>
                                                {index + 1}. {question.text || '-'}
                                            </div>

                                            {question.type === 'textarea' ? (
                                                <TextareaField
                                                    label="Jawaban"
                                                    value={form.data.answers[question.id] ?? ''}
                                                    onChange={(event) => setAnswer(question.id, event.target.value)}
                                                    error={answerError(question.id)}
                                                    placeholder="Tulis jawaban kamu..."
                                                    rows={3}
                                                    maxLength={5000}
                                                />
                                            ) : question.type === 'select' ? (
                                                <SelectField
                                                    label="Jawaban"
                                                    required
                                                    value={form.data.answers[question.id] ?? ''}
                                                    onChange={(event) => setAnswer(question.id, event.target.value)}
                                                    error={answerError(question.id)}
                                                >
                                                    <option value="">-- Pilih --</option>
                                                    {question.options.map((option) => (
                                                        <option key={option} value={option}>{option}</option>
                                                    ))}
                                                </SelectField>
                                            ) : question.type === 'radio' ? (
                                                <div style={{ display: 'grid', gap: 8 }}>
                                                    {question.options.map((option, optionIndex) => {
                                                        const inputId = `q-${question.id}-${optionIndex}`;
                                                        return (
                                                            <label
                                                                key={option}
                                                                htmlFor={inputId}
                                                                style={{
                                                                    display: 'flex', alignItems: 'center', gap: 9, padding: '8px 12px',
                                                                    border: '1px solid var(--db-line)', borderRadius: 10, background: 'var(--db-card)',
                                                                    cursor: 'pointer', fontSize: 13,
                                                                }}
                                                            >
                                                                <input
                                                                    id={inputId}
                                                                    type="radio"
                                                                    name={`q-${question.id}`}
                                                                    value={option}
                                                                    checked={(form.data.answers[question.id] ?? '') === option}
                                                                    onChange={(event) => setAnswer(question.id, event.target.value)}
                                                                    style={{ accentColor: 'var(--db-brand)' }}
                                                                />
                                                                {option}
                                                            </label>
                                                        );
                                                    })}
                                                    {answerError(question.id) && <div className="db-error">{answerError(question.id)}</div>}
                                                </div>
                                            ) : (
                                                <TextField
                                                    label="Jawaban"
                                                    value={form.data.answers[question.id] ?? ''}
                                                    onChange={(event) => setAnswer(question.id, event.target.value)}
                                                    error={answerError(question.id)}
                                                    placeholder="Jawaban kamu..."
                                                    maxLength={5000}
                                                />
                                            )}
                                        </div>
                                    ))
                                )}

                                {form.errors.answers && <div className="db-error">{form.errors.answers}</div>}
                            </div>
                        </section>

                        <div className="crud-actions">
                            <a className="db-btn ghost" href={urls.show}>Batal</a>
                            <button
                                className="db-btn primary"
                                type="submit"
                                disabled={form.processing || campaign.questions.length === 0}
                            >
                                <Send size={15} /> {form.processing ? 'Mengirim...' : 'Kirim Jawaban'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
