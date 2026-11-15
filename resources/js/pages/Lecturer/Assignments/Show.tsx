// Review tugas — statistik, filter, penilaian per mahasiswa, dan sinkronisasi grade book.
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, CheckCircle2, ClipboardList, Download, ExternalLink, FileText, GraduationCap,
    RefreshCw, RotateCcw, Search, Send, Trash2, Undo2, Upload, Users,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type SubmissionFile = { id: number; name: string; size: string; url: string };

type Row = {
    studentId: number; submissionId: number | null; name: string; nim: string;
    status: string; submittedAt: string; content: string | null;
    score: number | string | null; feedback: string | null; gradedAt: string | null;
    returnNote: string | null; files: SubmissionFile[];
};

type Assignment = {
    title: string; description: string; dueAt: string; maxScore: number; isPublished: boolean; isPastDue: boolean;
    course: string; label: string; academicYear: string;
    allowTextSubmission: boolean; allowFileSubmission: boolean; allowResubmission: boolean; acceptLateSubmission: boolean;
    allowedFileTypes: string; maxFileSize: string; files: { id: number; name: string; url: string }[];
    gradeBookComponent: string;
};

type Statistics = {
    total_students: number; submitted_count: number; graded_count: number; returned_count: number;
    missing_count: number; late_count: number; submission_rate: number;
    average_score: number | null; highest_score: number | null; lowest_score: number | null;
};

type Props = {
    shell: ShellProps;
    id: number;
    offeringId: number;
    assignment: Assignment;
    rows: Row[];
    statistics: Statistics;
    filters: { status: string; search: string };
    gradeBookWeight: number;
    urls: {
        index: string; edit: string; destroy: string; grade: string; return: string; sync: string;
        reportCsv: string; reportXlsx: string; reportPdf: string;
    };
};

const statusTone = (status: string) => {
    if (status === 'graded') return 'green';
    if (status === 'returned') return 'amber';
    if (status === 'submitted') return 'gray';
    if (status === 'late') return 'amber';
    if (status === 'missing') return 'red';
    return 'gray';
};

const statusLabel = (status: string) => ({
    graded: 'Dinilai', returned: 'Dikembalikan', submitted: 'Dikumpulkan', late: 'Terlambat',
    missing: 'Tidak mengumpulkan', not_submitted: 'Belum mengumpulkan',
}[status] ?? status);

function SubmissionRow({ row, maxScore, urls }: { row: Row; maxScore: number; urls: Props['urls'] }) {
    const [mode, setMode] = useState<'grade' | 'return' | null>(null);
    const gradeForm = useForm<{ submissionId: number | null; score: string | number; feedback: string }>({
        submissionId: row.submissionId,
        score: row.score ?? '',
        feedback: row.feedback ?? '',
    });
    const returnForm = useForm<{ submissionId: number | null; note: string }>({ submissionId: row.submissionId, note: '' });

    const submitGrade = (event: FormEvent) => {
        event.preventDefault();
        gradeForm.post(urls.grade, {
            preserveScroll: true,
            onSuccess: () => setMode(null),
        });
    };

    const submitReturn = (event: FormEvent) => {
        event.preventDefault();
        returnForm.post(urls.return, {
            preserveScroll: true,
            onSuccess: () => setMode(null),
        });
    };

    const hasSubmission = row.submissionId !== null;

    return (
        <article className="db-class">
            <div className="db-class-top">
                <div style={{ minWidth: 0 }}>
                    <b>{row.name}</b>
                    <small>{row.nim} · {row.submittedAt} · {row.files.length} berkas</small>
                </div>
                <span className={`db-badge ${statusTone(row.status)}`}>{statusLabel(row.status)}</span>
            </div>

            {row.content && (
                <div className="db-note muted" style={{ marginTop: 8 }}>
                    <FileText size={14} />
                    <span style={{ whiteSpace: 'pre-wrap' }}>{row.content}</span>
                </div>
            )}

            {row.files.length > 0 && (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 8 }}>
                    {row.files.map((file) => (
                        <a key={file.id} className="db-btn ghost sm" href={file.url} target="_blank" rel="noreferrer">
                            <Download size={12} /> {file.name} <span className="db-hint">({file.size})</span>
                        </a>
                    ))}
                </div>
            )}

            {row.returnNote && (
                <div className="db-note warning" style={{ marginTop: 8 }}>
                    <Undo2 size={14} />
                    <span>Catatan pengembalian: {row.returnNote}</span>
                </div>
            )}

            {row.score !== null && row.score !== '' && (
                <div style={{ display: 'flex', gap: 16, marginTop: 8, fontSize: 13 }}>
                    <span><b>Nilai:</b> {row.score}/{maxScore}</span>
                    {row.gradedAt && <span className="db-hint">Dinilai {row.gradedAt}</span>}
                </div>
            )}

            {row.feedback && <div className="db-hint" style={{ marginTop: 6 }}>Feedback: {row.feedback}</div>}

            <div className="db-class-actions">
                {hasSubmission && (
                    <button className="db-btn primary sm" type="button" onClick={() => setMode(mode === 'grade' ? null : 'grade')}>
                        <GraduationCap size={12} /> {row.score !== null && row.score !== '' ? 'Ubah nilai' : 'Beri nilai'}
                    </button>
                )}
                {hasSubmission && (
                    <button className="db-btn ghost sm" type="button" onClick={() => setMode(mode === 'return' ? null : 'return')}>
                        <RotateCcw size={12} /> Kembalikan
                    </button>
                )}
                {!hasSubmission && <span className="db-hint">Belum ada kiriman untuk dinilai.</span>}
            </div>

            {mode === 'grade' && (
                <form onSubmit={submitGrade} style={{ display: 'grid', gap: 10, marginTop: 10, borderTop: '1px solid var(--db-line)', paddingTop: 10 }}>
                    <div className="crud-grid">
                        <div>
                            <label className="crud-label">Nilai (0–{maxScore}) <span>*</span></label>
                            <input
                                className="db-input"
                                type="number"
                                min={0}
                                max={maxScore}
                                step="0.01"
                                value={gradeForm.data.score}
                                onChange={(event) => gradeForm.setData('score', event.target.value)}
                            />
                            {gradeForm.errors.score && <div className="db-error">{gradeForm.errors.score}</div>}
                        </div>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label className="crud-label">Feedback</label>
                            <textarea
                                className="db-input"
                                rows={3}
                                value={gradeForm.data.feedback}
                                onChange={(event) => gradeForm.setData('feedback', event.target.value)}
                            />
                            {gradeForm.errors.feedback && <div className="db-error">{gradeForm.errors.feedback}</div>}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button className="db-btn primary sm" type="submit" disabled={gradeForm.processing}>
                            <Send size={12} /> {gradeForm.processing ? 'Menyimpan…' : 'Simpan nilai'}
                        </button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setMode(null)}>Batal</button>
                    </div>
                </form>
            )}

            {mode === 'return' && (
                <form onSubmit={submitReturn} style={{ display: 'grid', gap: 10, marginTop: 10, borderTop: '1px solid var(--db-line)', paddingTop: 10 }}>
                    <div>
                        <label className="crud-label">Catatan revisi <span>*</span></label>
                        <textarea
                            className="db-input"
                            rows={3}
                            value={returnForm.data.note}
                            onChange={(event) => returnForm.setData('note', event.target.value)}
                            placeholder="Jelaskan bagian yang perlu diperbaiki mahasiswa."
                        />
                        {returnForm.errors.note && <div className="db-error">{returnForm.errors.note}</div>}
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button className="db-btn primary sm" type="submit" disabled={returnForm.processing}>
                            <Undo2 size={12} /> {returnForm.processing ? 'Mengirim…' : 'Kembalikan untuk revisi'}
                        </button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setMode(null)}>Batal</button>
                    </div>
                </form>
            )}
        </article>
    );
}

export default function AssignmentsShow({ shell, assignment, rows, statistics, filters, gradeBookWeight, urls }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const syncForm = useForm<{ weight: number }>({ weight: gradeBookWeight });

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(urls.index, { statusFilter: status, search }, { preserveState: true, preserveScroll: true });
    };

    const syncGradeBook = (event: FormEvent) => {
        event.preventDefault();
        syncForm.post(urls.sync, { preserveScroll: true });
    };

    const destroy = () => {
        router.delete(urls.destroy, { onFinish: () => setConfirmDelete(false) });
    };

    const cards = [
        { label: 'Mahasiswa', value: statistics.total_students, icon: <Users size={20} />, tone: 'db-stat-icon' },
        { label: 'Dikumpulkan', value: `${statistics.submitted_count} (${statistics.submission_rate}%)`, icon: <Upload size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Dinilai', value: statistics.graded_count, icon: <CheckCircle2 size={20} />, tone: 'db-stat-icon green' },
        { label: 'Rata-rata', value: statistics.average_score ?? '-', icon: <GraduationCap size={20} />, tone: 'db-stat-icon' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`${assignment.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik · Review Tugas"
                        title={assignment.title}
                        description={`${assignment.course} · ${assignment.label} · ${assignment.academicYear}`}
                        badges={[
                            assignment.isPublished ? 'Published' : 'Draft',
                            assignment.isPastDue ? 'Melewati deadline' : 'Masih berjalan',
                            `Deadline ${assignment.dueAt}`,
                        ]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar tugas</Link>
                                <Link className="db-btn light" href={urls.edit}><FileText size={15} /> Edit</Link>
                                <button className="db-btn light" type="button" onClick={() => setConfirmDelete(true)}><Trash2 size={15} /> Hapus</button>
                            </div>
                        }
                    />

                    {confirmDelete && (
                        <div className="db-note danger">
                            <Trash2 size={15} />
                            <span>Hapus tugas ini beserta seluruh kiriman mahasiswa? Tindakan tidak bisa dibatalkan.</span>
                            <span style={{ display: 'inline-flex', gap: 6, marginLeft: 'auto' }}>
                                <button className="db-btn danger sm" type="button" onClick={destroy}>Ya, hapus</button>
                                <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(false)}>Batal</button>
                            </span>
                        </div>
                    )}

                    <section className="db-stats" aria-label="Statistik tugas">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div><span className="db-stat-num">{card.value}</span><span className="db-stat-label">{card.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><FileText size={16} /> Instruksi</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                <p style={{ margin: 0, whiteSpace: 'pre-wrap', lineHeight: 1.6 }}>
                                    {assignment.description || 'Belum ada deskripsi tugas.'}
                                </p>
                                <div style={{ display: 'grid', gap: 6, fontSize: 13 }}>
                                    <div><b>Deadline:</b> {assignment.dueAt}</div>
                                    <div><b>Nilai maksimal:</b> {assignment.maxScore}</div>
                                    <div><b>Ekstensi:</b> {assignment.allowedFileTypes || 'Semua jenis'}</div>
                                    <div><b>Ukuran maksimal:</b> {assignment.maxFileSize}</div>
                                    <div><b>Jawaban teks:</b> {assignment.allowTextSubmission ? 'Diizinkan' : 'Tidak'}</div>
                                    <div><b>Unggah berkas:</b> {assignment.allowFileSubmission ? 'Diizinkan' : 'Tidak'}</div>
                                    <div><b>Kumpul ulang:</b> {assignment.allowResubmission ? 'Diizinkan' : 'Tidak'}</div>
                                    <div><b>Terima terlambat:</b> {assignment.acceptLateSubmission ? 'Ya' : 'Tidak'}</div>
                                </div>
                                {assignment.files.length > 0 && (
                                    <div style={{ display: 'grid', gap: 6 }}>
                                        {assignment.files.map((file) => (
                                            <a key={file.id} className="db-btn ghost sm" href={file.url} target="_blank" rel="noreferrer">
                                                <PaperclipIcon /> {file.name}
                                            </a>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><RefreshCw size={16} /> Sinkronisasi grade book</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                <p className="db-hint" style={{ margin: 0 }}>
                                    Nilai tugas akan masuk ke komponen <b>{assignment.gradeBookComponent}</b> pada buku nilai kelas.
                                </p>
                                <form onSubmit={syncGradeBook} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                                    <div style={{ minWidth: 140 }}>
                                        <label className="crud-label">Bobot komponen (%)</label>
                                        <input
                                            className="db-input"
                                            type="number"
                                            min={0}
                                            max={100}
                                            value={syncForm.data.weight}
                                            onChange={(event) => syncForm.setData('weight', Number(event.target.value))}
                                        />
                                        {syncForm.errors.weight && <div className="db-error">{syncForm.errors.weight}</div>}
                                    </div>
                                    <button className="db-btn primary sm" type="submit" disabled={syncForm.processing}>
                                        <RefreshCw size={13} /> {syncForm.processing ? 'Menyinkronkan…' : 'Sinkronkan'}
                                    </button>
                                </form>
                                <div className="crud-hint">Jalankan setelah nilai tugas diperbarui agar buku nilai ikut menyesuaikan.</div>
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Users size={16} /> Kiriman mahasiswa ({rows.length})</h2>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                <a className="db-btn ghost sm" href={urls.reportCsv}><Download size={12} /> CSV</a>
                                <a className="db-btn ghost sm" href={urls.reportXlsx}><Download size={12} /> XLSX</a>
                                <a className="db-btn ghost sm" href={urls.reportPdf}><Download size={12} /> PDF</a>
                            </div>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={applyFilters} className="crud-toolbar" style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 12 }}>
                                <input
                                    className="db-input"
                                    placeholder="Cari nama atau NIM…"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    style={{ maxWidth: 260 }}
                                />
                                <select className="db-input" value={status} onChange={(event) => setStatus(event.target.value)} style={{ maxWidth: 200 }}>
                                    <option value="all">Semua status</option>
                                    <option value="review">Perlu review</option>
                                    <option value="graded">Sudah dinilai</option>
                                    <option value="returned">Dikembalikan</option>
                                    <option value="missing">Belum mengumpulkan</option>
                                </select>
                                <button className="db-btn primary sm" type="submit"><Search size={13} /> Terapkan</button>
                            </form>

                            {rows.length === 0 ? (
                                <EmptyState icon={ClipboardList} title="Tidak ada data" description="Belum ada kiriman yang cocok dengan filter." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {rows.map((row) => (
                                        <SubmissionRow key={row.studentId} row={row} maxScore={assignment.maxScore} urls={urls} />
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <Link className="db-btn ghost" href={urls.index}><ArrowLeft size={14} /> Kembali</Link>
                            <Link className="db-btn ghost" href={urls.edit}><FileText size={14} /> Edit tugas</Link>
                            <span className="db-hint" style={{ marginLeft: 'auto', display: 'inline-flex', gap: 6, alignItems: 'center' }}>
                                <ExternalLink size={13} /> Nilai tertinggi {statistics.highest_score ?? '-'} · terendah {statistics.lowest_score ?? '-'} · terlambat {statistics.late_count}
                            </span>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}

function PaperclipIcon() {
    return <FileText size={12} />;
}
