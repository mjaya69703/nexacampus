// Detail tugas mahasiswa: instruksi, submission, dan feedback dosen
// (Inertia React). Pengganti komponen Livewire assignments.show.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, CalendarClock, CheckCircle2, Clock, Download, ExternalLink,
    FileText, Info, Lightbulb, Paperclip, Send, Star, Trash2, TriangleAlert,
    Upload,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { RichTextField } from '../../../components/Shared/Crud/RichTextField';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type InstructionFile = {
    id: number;
    fileName: string;
    fileType: string | null;
    sizeLabel: string;
    previewUrl: string;
};

type SubmissionFile = {
    id: number;
    fileName: string;
    fileType: string | null;
    sizeLabel: string;
    previewUrl: string;
};

type Grade = {
    status: string;
    score: number | null;
    maxScore: number;
    feedback: string | null;
    returnNote: string | null;
    gradedAt: string | null;
};

type Submission = {
    id: number;
    content: string | null;
    status: string;
    submittedAt: string | null;
    lastResubmittedAt: string | null;
    files: SubmissionFile[];
    grade: Grade | null;
};

type Assignment = {
    id: number;
    title: string;
    description: string | null;
    courseCode: string;
    courseName: string;
    classLabel: string | null;
    academicYear: string;
    dueAt: string | null;
    dueHuman: string | null;
    isPastDue: boolean;
    maxScore: number;
    allowTextSubmission: boolean;
    allowFileSubmission: boolean;
    allowResubmission: boolean;
    acceptLateSubmission: boolean;
    allowedExtensions: string[];
    maxFileSizeLabel: string;
    maxFiles: number | null;
    canSubmit: boolean;
    submissionLockedByGrade: boolean;
    statusLabel: string;
    statusTone: string;
};

type Props = {
    shell: ShellProps;
    assignment: Assignment;
    instructionFiles: InstructionFile[];
    submission: Submission | null;
    urls: { index: string; submit: string; removeFile: string };
};

const withId = (url: string, id: number): string => url.replace('__ID__', String(id));

const toneClass = (tone: string): string => {
    switch (tone) {
        case 'green':
            return 'green';
        case 'red':
            return 'red';
        case 'amber':
            return 'amber';
        case 'brand':
            return '';
        default:
            return 'gray';
    }
};

const cannotSubmitReason = (assignment: Assignment): string => {
    if (assignment.submissionLockedByGrade) {
        return 'Submission sudah dinilai dosen dan tidak bisa diubah lagi.';
    }

    if (assignment.isPastDue && !assignment.acceptLateSubmission) {
        return 'Deadline sudah lewat dan tugas ini tidak menerima submission telat.';
    }

    return 'Submission tidak bisa diubah lagi.';
};

export default function AssignmentShow({ shell, assignment, instructionFiles, submission, urls }: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ content: string; files: File[] }>({
        content: submission?.content ?? '',
        files: [],
    });

    useEffect(() => {
        form.setData('content', submission?.content ?? '');
        // Reset hanya saat submission berubah (setelah kirim ulang),
        // bukan pada setiap render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [submission?.id, submission?.content]);

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        form.post(withId(urls.submit, assignment.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('files');
                form.clearErrors();

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    const removeFile = (fileId: number): void => {
        router.delete(withId(urls.removeFile, fileId), { preserveScroll: true });
    };

    const grade = submission?.grade;
    const graded = grade?.status === 'graded';
    const returned = grade?.status === 'returned';

    return (
        <AdminShell shell={shell}>
            <Head title={`${assignment.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Assignment"
                        title={assignment.title}
                        description={`${assignment.courseCode} — ${assignment.courseName}${assignment.classLabel ? ` / ${assignment.classLabel}` : ''}`}
                        badges={[
                            assignment.academicYear,
                            assignment.dueAt ? `Deadline ${assignment.dueAt}` : 'Tanpa deadline',
                            `Max ${assignment.maxScore.toFixed(2)}`,
                            assignment.statusLabel,
                        ]}
                        actions={(
                            <a className="db-btn light" href={urls.index}>
                                <ArrowLeft size={15} /> Kembali
                            </a>
                        )}
                    />

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className={`db-stat-icon ${toneClass(assignment.statusTone) === 'green' ? 'green' : toneClass(assignment.statusTone) === 'red' ? 'red' : 'gold'}`}>
                                <CheckCircle2 size={20} />
                            </span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{assignment.statusLabel}</span>
                                <span className="db-stat-label">Status</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarClock size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{assignment.isPastDue ? 'Lewat' : 'Aktif'}</span>
                                <span className="db-stat-label">Deadline {assignment.dueHuman ? `· ${assignment.dueHuman}` : ''}</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Star size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>
                                    {graded && grade?.score !== null
                                        ? `${grade.score.toFixed(2)} / ${grade.maxScore.toFixed(2)}`
                                        : '-'}
                                </span>
                                <span className="db-stat-label">Nilai</span>
                            </div>
                        </div>
                    </section>

                    <div className="db-grid-2" style={{ alignItems: 'start' }}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><FileText size={16} /> Instruksi</h2>
                                </div>
                                <div className="db-card-body">
                                    {assignment.description ? (
                                        <div
                                            style={{ color: 'var(--db-ink)', lineHeight: 1.7 }}
                                            dangerouslySetInnerHTML={{ __html: assignment.description }}
                                        />
                                    ) : (
                                        <div className="db-hint">Tidak ada instruksi tambahan.</div>
                                    )}

                                    {instructionFiles.length > 0 && (
                                        <div style={{ marginTop: 16 }}>
                                            <div style={{ fontWeight: 700, color: 'var(--db-heading)', marginBottom: 8 }}>Lampiran Instruksi</div>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                                {instructionFiles.map((file) => (
                                                    <a
                                                        key={file.id}
                                                        className="db-btn sm ghost"
                                                        href={file.previewUrl}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Paperclip size={13} /> {file.fileName}
                                                        <span className="db-hint">({file.sizeLabel})</span>
                                                    </a>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Upload size={16} /> Submission Kamu</h2>
                                    {submission && <span className={`db-badge ${toneClass(assignment.statusTone)}`}>{assignment.statusLabel}</span>}
                                </div>
                                <div className="db-card-body">
                                    {submission ? (
                                        <>
                                            <div className="db-note success">
                                                <CheckCircle2 size={15} />
                                                <span>
                                                    <b>Submission tersimpan.</b>{' '}
                                                    {submission.submittedAt ?? '-'}
                                                    {submission.lastResubmittedAt ? ` · dikirim ulang ${submission.lastResubmittedAt}` : ''}
                                                </span>
                                            </div>

                                            {submission.content && (
                                                <div
                                                    style={{ marginTop: 12, padding: 12, borderRadius: 10, background: 'var(--db-soft)', color: 'var(--db-ink)', lineHeight: 1.7 }}
                                                    dangerouslySetInnerHTML={{ __html: submission.content }}
                                                />
                                            )}

                                            {submission.files.length > 0 && (
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                                                    {submission.files.map((file) => (
                                                        <span
                                                            key={file.id}
                                                            className="db-btn sm ghost"
                                                            style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'default' }}
                                                        >
                                                            <a href={file.previewUrl} target="_blank" rel="noreferrer" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'inherit', textDecoration: 'none' }}>
                                                                <Download size={13} /> {file.fileName}
                                                            </a>
                                                            <span className="db-hint">({file.sizeLabel})</span>
                                                            {assignment.canSubmit && (
                                                                <button
                                                                    type="button"
                                                                    className="db-btn sm danger"
                                                                    style={{ minHeight: 24, padding: '0 6px' }}
                                                                    title="Hapus lampiran"
                                                                    onClick={() => removeFile(file.id)}
                                                                >
                                                                    <Trash2 size={12} />
                                                                </button>
                                                            )}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}

                                            {graded && grade && (
                                                <div className="db-card" style={{ marginTop: 14 }}>
                                                    <div className="db-card-body">
                                                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                                                            <div>
                                                                <div style={{ fontWeight: 700, color: 'var(--db-brand)' }}>Hasil Penilaian</div>
                                                                <div className="db-hint">Dinilai {grade.gradedAt ?? '-'}</div>
                                                            </div>
                                                            <div style={{ fontSize: 20, fontWeight: 800, color: 'var(--db-brand)' }}>
                                                                {grade.score !== null ? grade.score.toFixed(2) : '-'} / {grade.maxScore.toFixed(2)}
                                                            </div>
                                                        </div>

                                                        {grade.feedback && (
                                                            <div
                                                                style={{ marginTop: 12, padding: 12, borderRadius: 10, background: 'var(--db-soft)', lineHeight: 1.7 }}
                                                                dangerouslySetInnerHTML={{ __html: grade.feedback }}
                                                            />
                                                        )}
                                                    </div>
                                                </div>
                                            )}

                                            {returned && (
                                                <div className="db-note warning" style={{ marginTop: 14 }}>
                                                    <TriangleAlert size={15} />
                                                    <span>
                                                        <b>Dosen meminta revisi.</b>{' '}
                                                        {grade?.returnNote
                                                            ? <span dangerouslySetInnerHTML={{ __html: grade.returnNote }} />
                                                            : 'Periksa kembali jawaban dan kirim ulang sebelum deadline.'}
                                                    </span>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <div className="db-hint">Belum ada submission untuk tugas ini.</div>
                                    )}

                                    {assignment.canSubmit ? (
                                        <form onSubmit={submit} style={{ marginTop: 16, display: 'grid', gap: 14 }}>
                                            {assignment.allowTextSubmission && (
                                                <RichTextField
                                                    label="Jawaban Teks"
                                                    value={form.data.content}
                                                    onChange={(value) => form.setData('content', value)}
                                                    error={form.errors.content}
                                                    height={260}
                                                    id={`student-assignment-${assignment.id}`}
                                                />
                                            )}

                                            {!assignment.allowTextSubmission && form.errors.content && (
                                                <div className="db-error">{form.errors.content}</div>
                                            )}

                                            {assignment.allowFileSubmission && (
                                                <div>
                                                    <label className="crud-label" htmlFor="assignment-files">
                                                        Upload Berkas
                                                    </label>
                                                    <input
                                                        id="assignment-files"
                                                        ref={fileInput}
                                                        type="file"
                                                        className="db-input"
                                                        multiple={assignment.maxFiles === null || assignment.maxFiles > 1}
                                                        accept={assignment.allowedExtensions.map((extension) => `.${extension}`).join(',')}
                                                        onChange={(event) => form.setData('files', Array.from(event.target.files ?? []))}
                                                    />
                                                    <div className="db-hint" style={{ marginTop: 6 }}>
                                                        Format: {assignment.allowedExtensions.join(', ')}. Maks {assignment.maxFileSizeLabel} per berkas
                                                        {assignment.maxFiles !== null ? `, maksimal ${assignment.maxFiles} berkas` : ''}.
                                                    </div>
                                                    {form.errors.files && <div className="db-error" style={{ marginTop: 4 }}>{form.errors.files}</div>}
                                                    {form.errors['files.0'] && <div className="db-error" style={{ marginTop: 4 }}>{form.errors['files.0']}</div>}
                                                    {form.progress && (
                                                        <div className="db-hint" style={{ marginTop: 4 }}>Mengunggah… {form.progress.percentage}%</div>
                                                    )}
                                                </div>
                                            )}

                                            {!assignment.allowTextSubmission && !assignment.allowFileSubmission && (
                                                <div className="db-note muted">
                                                    <Info size={15} />
                                                    <span>Tugas ini tidak menerima jawaban teks maupun berkas. Hubungi dosen pengampu bila ada kendala.</span>
                                                </div>
                                            )}

                                            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                                <button type="submit" className="db-btn primary" disabled={form.processing}>
                                                    <Send size={15} /> {form.processing ? 'Memproses…' : submission ? 'Kirim Ulang' : 'Kirim Submission'}
                                                </button>
                                            </div>
                                        </form>
                                    ) : (
                                        <div className="db-note warning" style={{ marginTop: 14 }}>
                                            <TriangleAlert size={15} />
                                            <span>{cannotSubmitReason(assignment)}</span>
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Info size={16} /> Aturan Submit</h2>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'grid', gap: 8 }}>
                                        {[
                                            ['Jawaban teks', assignment.allowTextSubmission],
                                            ['Upload berkas', assignment.allowFileSubmission],
                                            ['Resubmit', assignment.allowResubmission],
                                            ['Submission telat', assignment.acceptLateSubmission],
                                        ].map(([label, enabled]) => (
                                            <div
                                                key={String(label)}
                                                style={{ display: 'flex', justifyContent: 'space-between', gap: 10, paddingBottom: 8, borderBottom: '1px solid var(--db-line)' }}
                                            >
                                                <span className="db-hint">{label}</span>
                                                <b style={{ color: enabled ? 'var(--db-green)' : 'var(--db-red)', fontSize: 12.5 }}>{enabled ? 'Ya' : 'Tidak'}</b>
                                            </div>
                                        ))}
                                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
                                            <span className="db-hint">Format</span>
                                            <b style={{ fontSize: 12.5 }}>{assignment.allowedExtensions.join(', ')}</b>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
                                            <span className="db-hint">Maks ukuran</span>
                                            <b style={{ fontSize: 12.5 }}>{assignment.maxFileSizeLabel} / berkas</b>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Lightbulb size={16} /> Tips</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="db-hint">
                                        Pastikan lampiran muncul di daftar sebelum mengirim. Kalau dosen meminta revisi, baca catatannya dulu
                                        lalu kirim ulang jawaban final sebelum deadline.
                                    </div>
                                </div>
                            </section>

                            <div className="db-hint">
                                <ExternalLink size={12} /> Semua berkas dapat dibuka kembali dari halaman ini selama tugas masih tersedia.
                            </div>

                            <div className="db-hint">
                                <Clock size={12} /> {assignment.isPastDue
                                    ? (assignment.acceptLateSubmission ? 'Deadline lewat, submission telat masih diterima.' : 'Deadline lewat, submission ditutup.')
                                    : 'Deadline masih aktif.'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
