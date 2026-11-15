// Keberatan nilai mahasiswa (Inertia React).
// Hanya nilai dari kelas yang benar-benar diambil pada KRS approved yang
// bisa diajukan; satu nilai hanya boleh punya satu pengajuan aktif.
import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle, CheckCircle2, ClipboardList, FileText, Gavel, Inbox,
    MessageSquare, Paperclip, Scale, Send, Star, Trash2, TriangleAlert,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type GradeOption = {
    id: number;
    course: string;
    academicYear: string;
    lecturer: string;
    score: number | null;
    letter: string;
    hasActiveAppeal: boolean;
};

type Attachment = { id: number; name: string; url: string };

type Appeal = {
    id: number;
    course: string;
    academicYear: string;
    lecturer: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    category: string;
    reason: string;
    expectedOutcome: string | null;
    originalScore: number | null;
    requestedScore: number | null;
    resolvedScore: number | null;
    submittedAt: string;
    resolvedAt: string | null;
    response: string | null;
    attachments: Attachment[];
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    gradeOptions: GradeOption[];
    appeals: Appeal[];
    categories: Array<{ value: string; label: string }>;
    summary: { availableGrades: number; activeAppeals: number; resolvedAppeals: number };
    urls: { index: string; store: string };
};

const toneClass = (tone: string): string => {
    switch (tone) {
        case 'green':
            return 'green';
        case 'red':
            return 'red';
        case 'amber':
            return 'amber';
        case 'blue':
            return '';
        default:
            return 'gray';
    }
};

const scoreLabel = (value: number | null): string => (value === null ? '-' : String(value));

export default function GradeAppealsIndex({
    shell, hasProfile, gradeOptions, appeals, categories, summary, urls,
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [showForm, setShowForm] = useState(false);
    const selectable = gradeOptions.filter((grade) => !grade.hasActiveAppeal);

    const form = useForm<{
        gradeId: string;
        category: string;
        reason: string;
        expectedOutcome: string;
        requestedScore: string;
        attachments: File[];
    }>({
        gradeId: selectable[0] ? String(selectable[0].id) : '',
        category: categories[0]?.value ?? '',
        reason: '',
        expectedOutcome: '',
        requestedScore: '',
        attachments: [],
    });

    const addFiles = (files: FileList | null): void => {
        if (!files) return;

        const merged = [...form.data.attachments, ...Array.from(files)].slice(0, 4);
        form.setData('attachments', merged);

        if (fileInput.current) fileInput.current.value = '';
    };

    const removeFile = (index: number): void => {
        form.setData('attachments', form.data.attachments.filter((_, position) => position !== index));
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        form.post(urls.store, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Keberatan Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Gavel}
                        eyebrow="Academic"
                        title="Keberatan Nilai"
                        description="Ajukan klarifikasi atau keberatan atas nilai yang sudah dipublikasikan dosen."
                        badges={[`${summary.activeAppeals} diproses`, `${summary.resolvedAppeals} selesai`]}
                        actions={gradeOptions.length > 0 ? (
                            <button
                                type="button"
                                className={`db-btn ${showForm ? 'ghost' : 'primary'}`}
                                onClick={() => setShowForm((current) => !current)}
                            >
                                <Scale size={15} /> {showForm ? 'Tutup Form' : 'Ajukan Keberatan'}
                            </button>
                        ) : undefined}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Pengajuan keberatan tidak bisa dilakukan.</span>
                        </div>
                    )}

                    {hasProfile && (
                        <section className="db-stats">
                            <div className="db-stat">
                                <span className="db-stat-icon"><Star size={20} /></span>
                                <div>
                                    <span className="db-stat-num">{summary.availableGrades}</span>
                                    <span className="db-stat-label">Nilai terpublikasi</span>
                                </div>
                            </div>
                            <div className="db-stat">
                                <span className="db-stat-icon gold"><ClipboardList size={20} /></span>
                                <div>
                                    <span className="db-stat-num">{summary.activeAppeals}</span>
                                    <span className="db-stat-label">Sedang diproses</span>
                                </div>
                            </div>
                            <div className="db-stat">
                                <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                                <div>
                                    <span className="db-stat-num">{summary.resolvedAppeals}</span>
                                    <span className="db-stat-label">Selesai</span>
                                </div>
                            </div>
                        </section>
                    )}

                    {showForm && hasProfile && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Scale size={16} /> Form Keberatan Nilai</h2>
                            </div>
                            <div className="db-card-body">
                                {selectable.length === 0 ? (
                                    <div className="db-note warning">
                                        <AlertTriangle size={15} />
                                        <span>Tidak ada nilai yang bisa diajukan. Semua nilai sudah memiliki pengajuan aktif atau belum dipublikasikan.</span>
                                    </div>
                                ) : (
                                    <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                        <div className="db-grid-2">
                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Mata kuliah <span style={{ color: 'var(--db-red)' }}>*</span></span>
                                                <select
                                                    className="db-input"
                                                    value={form.data.gradeId}
                                                    onChange={(event) => form.setData('gradeId', event.target.value)}
                                                >
                                                    {selectable.map((grade) => (
                                                        <option key={grade.id} value={grade.id}>
                                                            {grade.course} · {grade.letter} ({scoreLabel(grade.score)}) · {grade.academicYear}
                                                        </option>
                                                    ))}
                                                </select>
                                                {form.errors.gradeId && <span className="db-error">{form.errors.gradeId}</span>}
                                            </label>

                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Kategori <span style={{ color: 'var(--db-red)' }}>*</span></span>
                                                <select
                                                    className="db-input"
                                                    value={form.data.category}
                                                    onChange={(event) => form.setData('category', event.target.value)}
                                                >
                                                    {categories.map((category) => (
                                                        <option key={category.value} value={category.value}>{category.label}</option>
                                                    ))}
                                                </select>
                                                {form.errors.category && <span className="db-error">{form.errors.category}</span>}
                                            </label>
                                        </div>

                                        <label style={{ display: 'grid', gap: 4 }}>
                                            <span className="db-hint" style={{ fontWeight: 700 }}>Alasan keberatan <span style={{ color: 'var(--db-red)' }}>*</span></span>
                                            <textarea
                                                className="db-input"
                                                rows={4}
                                                minLength={20}
                                                maxLength={4000}
                                                placeholder="Jelaskan perhitungan atau komponen nilai yang menurutmu keliru (minimal 20 karakter)."
                                                value={form.data.reason}
                                                onChange={(event) => form.setData('reason', event.target.value)}
                                            />
                                            <span className="db-hint">{form.data.reason.length}/4000 karakter</span>
                                            {form.errors.reason && <span className="db-error">{form.errors.reason}</span>}
                                        </label>

                                        <div className="db-grid-2">
                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Nilai yang diharapkan (opsional)</span>
                                                <input
                                                    type="number"
                                                    className="db-input"
                                                    min={0}
                                                    max={100}
                                                    step="0.01"
                                                    value={form.data.requestedScore}
                                                    onChange={(event) => form.setData('requestedScore', event.target.value)}
                                                />
                                                {form.errors.requestedScore && <span className="db-error">{form.errors.requestedScore}</span>}
                                            </label>

                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Hasil yang diharapkan (opsional)</span>
                                                <input
                                                    type="text"
                                                    className="db-input"
                                                    placeholder="Mis. nilai komponen tugas diperbaiki"
                                                    value={form.data.expectedOutcome}
                                                    onChange={(event) => form.setData('expectedOutcome', event.target.value)}
                                                />
                                                {form.errors.expectedOutcome && <span className="db-error">{form.errors.expectedOutcome}</span>}
                                            </label>
                                        </div>

                                        <div style={{ display: 'grid', gap: 6 }}>
                                            <span className="db-hint" style={{ fontWeight: 700 }}>Lampiran pendukung (opsional, maks. 4 file)</span>
                                            <input
                                                ref={fileInput}
                                                type="file"
                                                className="db-input"
                                                multiple
                                                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                                onChange={(event) => addFiles(event.target.files)}
                                                disabled={form.data.attachments.length >= 4}
                                            />
                                            <span className="db-hint"><Paperclip size={11} /> Maksimal 5 MB per file.</span>
                                            {form.errors.attachments && <span className="db-error">{form.errors.attachments}</span>}

                                            {form.data.attachments.length > 0 && (
                                                <div style={{ display: 'grid', gap: 6, marginTop: 4 }}>
                                                    {form.data.attachments.map((file, index) => (
                                                        <div key={`${file.name}-${index}`} className="db-note muted" style={{ margin: 0 }}>
                                                            <FileText size={14} />
                                                            <span style={{ flex: 1 }}>{file.name}</span>
                                                            <button type="button" className="db-btn ghost sm" onClick={() => removeFile(index)}>
                                                                <Trash2 size={12} /> Hapus
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                                            <button type="button" className="db-btn ghost" onClick={() => setShowForm(false)}>Batal</button>
                                            <button type="submit" className="db-btn primary" disabled={form.processing}>
                                                <Send size={15} /> {form.processing ? 'Mengirim…' : 'Kirim Pengajuan'}
                                            </button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Gavel size={16} /> Riwayat Pengajuan</h2>
                            <span className="db-badge gray">{appeals.length} pengajuan</span>
                        </div>
                        <div className="db-card-body">
                            {appeals.length === 0 ? (
                                <EmptyState
                                    icon={Inbox}
                                    title="Belum ada pengajuan"
                                    description="Pengajuan keberatan nilai akan tercatat di sini beserta tanggapan dosen."
                                />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {appeals.map((appeal) => (
                                        <article key={appeal.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{appeal.course}</b>
                                                    <small>{appeal.category} · {appeal.academicYear} · {appeal.lecturer}</small>
                                                </div>
                                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                    <span className={`db-badge ${toneClass(appeal.statusTone)}`}>{appeal.statusLabel}</span>
                                                    <span className="db-badge gray">Diajukan {appeal.submittedAt}</span>
                                                </div>
                                            </div>

                                            <div className="db-hint" style={{ marginTop: 8 }}>
                                                Nilai asal <b>{scoreLabel(appeal.originalScore)}</b>
                                                {appeal.requestedScore !== null && <> · diminta <b>{scoreLabel(appeal.requestedScore)}</b></>}
                                                {appeal.resolvedScore !== null && <> · hasil <b>{scoreLabel(appeal.resolvedScore)}</b></>}
                                            </div>

                                            <div style={{ marginTop: 8 }}>
                                                <div className="db-hint" style={{ fontWeight: 700 }}>Alasan</div>
                                                <div style={{ fontSize: 13, color: 'var(--db-heading)' }}>{appeal.reason}</div>
                                            </div>

                                            {appeal.expectedOutcome && (
                                                <div className="db-hint" style={{ marginTop: 6 }}>Harapan: {appeal.expectedOutcome}</div>
                                            )}

                                            {appeal.attachments.length > 0 && (
                                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 8 }}>
                                                    {appeal.attachments.map((attachment) => (
                                                        <a
                                                            key={attachment.id}
                                                            href={attachment.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="db-btn ghost sm"
                                                        >
                                                            <Paperclip size={12} /> {attachment.name}
                                                        </a>
                                                    ))}
                                                </div>
                                            )}

                                            {appeal.response && (
                                                <div className="db-note info" style={{ marginTop: 10 }}>
                                                    <MessageSquare size={14} />
                                                    <span>{appeal.response}</span>
                                                </div>
                                            )}

                                            {appeal.resolvedAt && (
                                                <div className="db-hint" style={{ marginTop: 6 }}>Diselesaikan {appeal.resolvedAt}</div>
                                            )}
                                        </article>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <div className="db-note muted">
                        <TriangleAlert size={15} />
                        <span>
                            Keberatan nilai hanya untuk nilai yang sudah dipublikasikan pada kelas yang masih tercatat di KRS.
                            Satu nilai hanya boleh memiliki satu pengajuan aktif.
                        </span>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
