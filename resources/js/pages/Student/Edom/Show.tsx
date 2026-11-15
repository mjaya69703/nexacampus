// Form evaluasi dosen (EDOM) — Inertia React.
// Skala 1-5 dan jawaban teks divalidasi ulang di server.
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft, ClipboardList, MessageSquare, Send, Star, TriangleAlert,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Question = {
    id: number;
    category: string | null;
    categoryLabel: string;
    text: string;
    answerType: string;
    isRequired: boolean;
};

type Props = {
    shell: ShellProps;
    period: { id: number; name: string };
    course: { id: number; name: string; code: string; label: string | null };
    lecturer: { id: number; name: string };
    questions: Question[];
    urls: { index: string; submit: string };
};

const SCALE_LABELS: Record<number, string> = {
    1: 'Sangat kurang',
    2: 'Kurang',
    3: 'Cukup',
    4: 'Baik',
    5: 'Sangat baik',
};

export default function EdomShow({ shell, period, course, lecturer, questions, urls }: Props) {
    // Nilai awal string kosong supaya input teks tetap terkontrol.
    const form = useForm<{ answers: Record<string, string | number> }>({
        answers: Object.fromEntries(questions.map((question) => [String(question.id), ''])),
    });
    const [error, setError] = useState<string | null>(null);

    const setAnswer = (id: number, value: string | number): void => {
        form.setData('answers', { ...form.data.answers, [String(id)]: value });
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        setError(null);

        // Cek cepat di klien; server tetap menjadi otoritas validasi.
        const missing = questions.find((question) => {
            if (!question.isRequired) return false;
            const value = form.data.answers[String(question.id)];
            return value === '' || value === null || value === undefined;
        });

        if (missing) {
            setError(`Pertanyaan wajib belum dijawab: "${missing.text}"`);
            return;
        }

        form.post(urls.submit, { preserveScroll: true });
    };

    const groups = questions.reduce<Record<string, Question[]>>((carry, question) => {
        const key = question.categoryLabel || 'Umum';
        carry[key] = carry[key] ?? [];
        carry[key].push(question);
        return carry;
    }, {});

    return (
        <AdminShell shell={shell}>
            <Head title={`Evaluasi ${course.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Academic"
                        title={`Evaluasi ${course.name}`}
                        description={`${course.code}${course.label ? ` · Kelas ${course.label}` : ''} — ${lecturer.name}`}
                        badges={[period.name, `${questions.length} pertanyaan`]}
                        actions={(
                            <Link href={urls.index} className="db-btn ghost"><ArrowLeft size={15} /> Kembali</Link>
                        )}
                    />

                    <div className="db-note info">
                        <UserRound size={15} />
                        <span>
                            Evaluasi untuk <b>{lecturer.name}</b>. Jawabanmu tidak ditampilkan ke dosen secara individual —
                            hanya rekap agregat yang dipakai untuk perbaikan pembelajaran.
                        </span>
                    </div>

                    {error && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span>{error}</span>
                        </div>
                    )}

                    <form onSubmit={submit} style={{ display: 'grid', gap: 14 }}>
                        {Object.entries(groups).map(([category, items]) => (
                            <section className="db-card" key={category}>
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ClipboardList size={16} /> {category}</h2>
                                    <span className="db-badge gray">{items.length} pertanyaan</span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 18 }}>
                                    {items.map((question) => {
                                        const value = form.data.answers[String(question.id)];
                                        const fieldError = form.errors[`answers.${question.id}`];

                                        return (
                                            <div key={question.id} style={{ display: 'grid', gap: 8 }}>
                                                <div>
                                                    <b style={{ color: 'var(--db-heading)', fontSize: 13.5 }}>{question.text}</b>
                                                    {question.isRequired && <span className="db-badge red" style={{ marginLeft: 8 }}>Wajib</span>}
                                                </div>

                                                {question.answerType === 'scale' ? (
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                                        {[1, 2, 3, 4, 5].map((score) => {
                                                            const active = Number(value) === score;

                                                            return (
                                                                <button
                                                                    key={score}
                                                                    type="button"
                                                                    className={`db-btn ${active ? 'primary' : 'ghost'} sm`}
                                                                    aria-pressed={active}
                                                                    onClick={() => setAnswer(question.id, score)}
                                                                >
                                                                    <Star size={12} /> {score} <span style={{ opacity: .8 }}>{SCALE_LABELS[score]}</span>
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                ) : (
                                                    <label style={{ display: 'grid', gap: 4 }}>
                                                        <span className="db-hint" style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
                                                            <MessageSquare size={11} /> Jawaban teks (maks. 2000 karakter)
                                                        </span>
                                                        <textarea
                                                            className="db-input"
                                                            rows={3}
                                                            maxLength={2000}
                                                            value={String(value ?? '')}
                                                            onChange={(event) => setAnswer(question.id, event.target.value)}
                                                        />
                                                    </label>
                                                )}

                                                {fieldError && <span className="db-error">{fieldError}</span>}
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}

                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                            <Link href={urls.index} className="db-btn ghost">Batal</Link>
                            <button type="submit" className="db-btn primary" disabled={form.processing}>
                                <Send size={15} /> {form.processing ? 'Mengirim…' : 'Kirim Evaluasi'}
                            </button>
                        </div>
                    </form>

                    <div className="db-note muted">
                        <TriangleAlert size={15} />
                        <span>Evaluasi yang sudah dikirim tidak bisa diubah. Pastikan penilaian sudah sesuai sebelum mengirim.</span>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
