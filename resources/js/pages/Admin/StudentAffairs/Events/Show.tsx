// Detail event kemahasiswaan: informasi acara, kehadiran, dokumentasi, anggaran, dan evaluasi.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, CalendarDays, CheckCircle2, Clock3, Download, FileText, Image as ImageIcon,
    MapPin, MessageSquare, Pencil, Plus, Star, Trash2, Upload, Users, Wallet, XCircle,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import { StudentAffairsModal, StudentAffairsStats, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Registration = {
    id: number;
    student: string;
    nim: string | null;
    program: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    registeredAt: string | null;
    attendedAt: string | null;
    pointsAwarded: number;
    attendanceUrl: string;
};

type DocumentRow = {
    id: number;
    title: string;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    description: string | null;
    originalName: string;
    fileSize: string;
    isImage: boolean;
    fileUrl: string | null;
    uploader: string | null;
    createdAt: string | null;
    downloadUrl: string;
    deleteUrl: string;
};

type BudgetRow = {
    id: number;
    itemName: string;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    plannedAmount: number;
    realizedAmount: number;
    variance: number;
    notes: string | null;
    updateUrl: string;
    deleteUrl: string;
};

type EvaluationRow = {
    id: number;
    student: string;
    rating: number;
    organizationRating: number | null;
    benefitRating: number | null;
    feedback: string | null;
    suggestions: string | null;
    isAnonymous: boolean;
    createdAt: string | null;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; publish: boolean };
    event: {
        id: number;
        title: string;
        categoryLabel: string;
        categoryTone: string;
        description: string | null;
        organization: string | null;
        organizer: string | null;
        location: string | null;
        startsAt: string | null;
        endsAt: string | null;
        registrationDeadline: string | null;
        quota: number | null;
        points: number;
        status: string;
        statusLabel: string;
        statusTone: string;
        isFeatured: boolean;
        coverUrl: string | null;
        creator: string | null;
        createdAt: string | null;
        updatedAt: string | null;
    };
    stats: { registered: number; attended: number; cancelled: number; quota: number | null };
    registrations: Registration[];
    documents: DocumentRow[];
    budgets: BudgetRow[];
    budgetSummary: {
        planned: number;
        realized: number;
        variance: number;
        absorption: number;
        items: number;
    };
    evaluations: EvaluationRow[];
    evaluationSummary: {
        count: number;
        average: number | null;
        averageOrganization: number | null;
        averageBenefit: number | null;
        distribution: Record<string, number>;
        responseRate: number;
        attended: number;
    };
    options: {
        registrationStatuses: Record<string, string>;
        documentCategories: Record<string, string>;
        budgetCategories: Record<string, string>;
    };
    urls: {
        index: string;
        edit: string;
        delete: string;
        publish: string;
        complete: string;
        cancel: string;
        documentsStore: string;
        budgetsStore: string;
    };
};

type Pending = { kind: 'delete' | 'publish' | 'complete' | 'cancel' } | null;

const ACTION_COPY: Record<Exclude<Pending, null>['kind'], { title: string; message: string; confirm: string; danger: boolean }> = {
    publish: { title: 'Publikasikan event?', message: 'Event akan tampil di portal mahasiswa dan pendaftaran dapat dibuka.', confirm: 'Ya, publikasikan', danger: false },
    complete: { title: 'Tandai event selesai?', message: 'Event akan ditandai selesai. Pastikan kehadiran peserta sudah dicatat.', confirm: 'Ya, selesaikan', danger: false },
    cancel: { title: 'Batalkan event?', message: 'Event akan dibatalkan dan tidak lagi tampil untuk pendaftaran mahasiswa.', confirm: 'Ya, batalkan', danger: true },
    delete: { title: 'Hapus event?', message: 'Event beserta data pendaftarannya akan dihapus.', confirm: 'Ya, hapus', danger: true },
};

const rupiah = (value: number) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(value);

export default function EventShow({
    shell, can, event, stats, registrations, documents, budgets, budgetSummary,
    evaluations, evaluationSummary, options, urls,
}: Props) {
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [documentPending, setDocumentPending] = useState<DocumentRow | null>(null);
    const [budgetPending, setBudgetPending] = useState<BudgetRow | null>(null);
    const [budgetModalOpen, setBudgetModalOpen] = useState(false);
    const [editingBudget, setEditingBudget] = useState<BudgetRow | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const documentForm = useForm({
        category: 'photo',
        title: '',
        description: '',
        file: null as File | null,
    });

    const budgetForm = useForm({
        item_name: '',
        category: 'consumption',
        planned_amount: '',
        realized_amount: '',
        notes: '',
    });

    const runAction = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending.kind === 'delete') {
            router.delete(urls.delete, { onFinish: done });
            return;
        }

        router.post(urls[pending.kind], {}, { preserveScroll: true, onFinish: done });
    };

    const submitDocument = (submitEvent: FormEvent) => {
        submitEvent.preventDefault();
        documentForm.post(urls.documentsStore, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                documentForm.reset();
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    };

    const openCreateBudget = () => {
        setEditingBudget(null);
        budgetForm.setData({
            item_name: '',
            category: 'consumption',
            planned_amount: '',
            realized_amount: '',
            notes: '',
        });
        budgetForm.clearErrors();
        setBudgetModalOpen(true);
    };

    const openEditBudget = (budget: BudgetRow) => {
        setEditingBudget(budget);
        budgetForm.setData({
            item_name: budget.itemName,
            category: budget.category,
            planned_amount: String(budget.plannedAmount),
            realized_amount: String(budget.realizedAmount),
            notes: budget.notes ?? '',
        });
        budgetForm.clearErrors();
        setBudgetModalOpen(true);
    };

    const submitBudget = (submitEvent: FormEvent) => {
        submitEvent.preventDefault();
        const submitOptions = {
            preserveScroll: true,
            onSuccess: () => {
                setBudgetModalOpen(false);
                setEditingBudget(null);
                budgetForm.reset();
            },
        };

        if (editingBudget) {
            budgetForm.put(editingBudget.updateUrl, submitOptions);
        } else {
            budgetForm.post(urls.budgetsStore, submitOptions);
        }
    };

    const copy = pending ? ACTION_COPY[pending.kind] : null;
    const maxDistribution = Math.max(1, ...Object.values(evaluationSummary.distribution));

    return (
        <AdminShell shell={shell}>
            <Head title={`${event.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Kemahasiswaan · Event"
                        title={event.title}
                        description={event.description || 'Detail event kemahasiswaan.'}
                        badges={[
                            event.categoryLabel,
                            event.statusLabel,
                            ...(event.isFeatured ? ['Sorotan'] : []),
                        ]}
                        actions={(
                            <>
                                <a className="db-btn ghost" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {can.update && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Edit</a>}
                                {can.publish && event.status === 'draft' && (
                                    <button className="db-btn primary" type="button" onClick={() => setPending({ kind: 'publish' })}>
                                        <CheckCircle2 size={15} /> Publikasikan
                                    </button>
                                )}
                                {can.publish && ['published', 'ongoing'].includes(event.status) && (
                                    <button className="db-btn ghost" type="button" onClick={() => setPending({ kind: 'complete' })}>
                                        <CheckCircle2 size={15} /> Tandai Selesai
                                    </button>
                                )}
                                {can.publish && !['cancelled', 'completed'].includes(event.status) && (
                                    <button className="db-btn danger" type="button" onClick={() => setPending({ kind: 'cancel' })}>
                                        <XCircle size={15} /> Batalkan
                                    </button>
                                )}
                                {can.delete && (
                                    <button className="db-btn danger" type="button" onClick={() => setPending({ kind: 'delete' })}>
                                        <Trash2 size={15} /> Hapus
                                    </button>
                                )}
                            </>
                        )}
                    />

                    <StudentAffairsStats
                        label="Statistik peserta event"
                        items={[
                            { label: 'Pendaftar', value: stats.registered, icon: Users },
                            { label: 'Hadir', value: stats.attended, icon: CheckCircle2, tone: 'green' },
                            { label: 'Dibatalkan', value: stats.cancelled, icon: XCircle, tone: 'red' },
                            { label: 'Poin per peserta', value: event.points, icon: Star, tone: 'gold' },
                        ]}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Informasi Acara</h2></div>
                                <div className="db-card-body">
                                    {event.coverUrl && (
                                        <img
                                            src={event.coverUrl}
                                            alt=""
                                            style={{ width: '100%', maxHeight: 240, objectFit: 'cover', borderRadius: 12, border: '1px solid var(--db-line)', marginBottom: 14 }}
                                        />
                                    )}
                                    <div className="db-grid-2">
                                        <div className="db-fact"><span className="db-hint">Mulai</span><b>{event.startsAt || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Selesai</span><b>{event.endsAt || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Batas pendaftaran</span><b>{event.registrationDeadline || 'Tanpa batas'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Kuota</span><b>{event.quota ?? 'Tidak dibatasi'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Lokasi</span><b style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><MapPin size={13} /> {event.location || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Penyelenggara</span><b>{event.organization || event.organizer || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Kategori</span><b><span className={`db-badge ${toneClass(event.categoryTone)}`}>{event.categoryLabel}</span></b></div>
                                        <div className="db-fact"><span className="db-hint">Status</span><b><span className={`db-badge ${toneClass(event.statusTone)}`}>{event.statusLabel}</span></b></div>
                                    </div>
                                    {event.description && <p className="db-hint" style={{ marginTop: 12 }}>{event.description}</p>}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Users size={15} /> Peserta & Kehadiran</h2>
                                    <span className="db-badge gray">{registrations.length} pendaftar</span>
                                </div>
                                <div className="db-card-body">
                                    {registrations.length === 0 ? (
                                        <span className="db-hint">Belum ada mahasiswa yang mendaftar pada event ini.</span>
                                    ) : (
                                        <div style={{ display: 'grid', gap: 10 }}>
                                            {registrations.map((registration) => (
                                                <article key={registration.id} className="db-fact wide" style={{ display: 'grid', gap: 8 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                        <span>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{registration.student}</b>
                                                            <small className="db-hint">
                                                                {registration.nim || '—'}{registration.program ? ` · ${registration.program}` : ''}
                                                            </small>
                                                        </span>
                                                        <span className={`db-badge ${toneClass(registration.statusTone)}`}>{registration.statusLabel}</span>
                                                    </div>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12 }}>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <Clock3 size={12} /> Daftar {registration.registeredAt || '—'}
                                                        </span>
                                                        {registration.attendedAt && (
                                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                                <CheckCircle2 size={12} /> Hadir {registration.attendedAt}
                                                            </span>
                                                        )}
                                                        {registration.pointsAwarded > 0 && (
                                                            <span className="db-badge gold">{registration.pointsAwarded} poin</span>
                                                        )}
                                                        <label className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginLeft: 'auto' }}>
                                                            Kehadiran
                                                            <select
                                                                className="db-input"
                                                                style={{ width: 'auto' }}
                                                                value={registration.status}
                                                                onChange={(changeEvent) => router.post(
                                                                    registration.attendanceUrl,
                                                                    { status: changeEvent.target.value },
                                                                    { preserveScroll: true },
                                                                )}
                                                                aria-label={`Status kehadiran ${registration.student}`}
                                                            >
                                                                {Object.entries(options.registrationStatuses).map(([value, label]) => (
                                                                    <option key={value} value={value}>{label}</option>
                                                                ))}
                                                            </select>
                                                        </label>
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ImageIcon size={15} /> Dokumentasi Kegiatan</h2>
                                    <span className="db-badge gray">{documents.length} berkas</span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                    {can.update && (
                                        <form onSubmit={submitDocument} style={{ display: 'grid', gap: 10 }}>
                                            <div className="crud-grid">
                                                <SelectField
                                                    label="Kategori"
                                                    required
                                                    value={documentForm.data.category}
                                                    onChange={(changeEvent) => documentForm.setData('category', changeEvent.target.value)}
                                                    error={documentForm.errors.category}
                                                >
                                                    {Object.entries(options.documentCategories).map(([value, label]) => (
                                                        <option key={value} value={value}>{label}</option>
                                                    ))}
                                                </SelectField>
                                                <TextField
                                                    label="Judul"
                                                    required
                                                    value={documentForm.data.title}
                                                    onChange={(changeEvent) => documentForm.setData('title', changeEvent.target.value)}
                                                    error={documentForm.errors.title}
                                                    placeholder="Mis. Foto sesi pembukaan"
                                                />
                                            </div>
                                            <TextareaField
                                                label="Deskripsi"
                                                value={documentForm.data.description}
                                                onChange={(changeEvent) => documentForm.setData('description', changeEvent.target.value)}
                                                error={documentForm.errors.description}
                                                placeholder="Keterangan singkat dokumentasi (opsional)"
                                            />
                                            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'end', gap: 10 }}>
                                                <div style={{ flex: 1, minWidth: 220 }}>
                                                    <label className="crud-label" htmlFor="event-document-file">Berkas <span>*</span></label>
                                                    <input
                                                        id="event-document-file"
                                                        ref={fileInputRef}
                                                        className="db-input"
                                                        type="file"
                                                        accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                                                        onChange={(changeEvent) => documentForm.setData('file', changeEvent.target.files?.[0] ?? null)}
                                                    />
                                                    {documentForm.errors.file && <div className="db-error">{documentForm.errors.file}</div>}
                                                    <div className="crud-hint">Foto, laporan, atau dokumen pendukung. Maksimal 10 MB.</div>
                                                </div>
                                                <button className="db-btn primary" type="submit" disabled={documentForm.processing}>
                                                    <Upload size={14} /> Unggah
                                                </button>
                                            </div>
                                        </form>
                                    )}

                                    {documents.length === 0 ? (
                                        <span className="db-hint">Belum ada dokumentasi yang diunggah.</span>
                                    ) : (
                                        <div className="db-grid-2">
                                            {documents.map((document) => (
                                                <article key={document.id} className="db-fact wide" style={{ display: 'grid', gap: 8 }}>
                                                    {document.fileUrl ? (
                                                        <img
                                                            src={document.fileUrl}
                                                            alt={document.title}
                                                            style={{ width: '100%', height: 120, objectFit: 'cover', borderRadius: 8, border: '1px solid var(--db-line)' }}
                                                        />
                                                    ) : (
                                                        <div style={{ height: 80, display: 'grid', placeItems: 'center', background: 'var(--db-soft, rgba(104, 66, 244, 0.08))', borderRadius: 8 }}>
                                                            <FileText size={22} />
                                                        </div>
                                                    )}
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                                        <span className={`db-badge ${toneClass(document.categoryTone)}`}>{document.categoryLabel}</span>
                                                        <span className="db-badge gray">{document.fileSize}</span>
                                                    </div>
                                                    <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{document.title}</b>
                                                    {document.description && <span className="db-hint">{document.description}</span>}
                                                    <span className="db-hint">{document.originalName} · {document.uploader || '—'} · {document.createdAt || '—'}</span>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 2 }}>
                                                        <a className="db-btn ghost sm" href={document.downloadUrl}><Download size={12} /> Unduh</a>
                                                        {can.update && (
                                                            <button className="db-btn danger sm" type="button" onClick={() => setDocumentPending(document)}>
                                                                <Trash2 size={12} /> Hapus
                                                            </button>
                                                        )}
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Wallet size={15} /> Realisasi Anggaran</h2>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <span className="db-badge gray">{budgetSummary.items} item</span>
                                        {can.update && (
                                            <button className="db-btn primary sm" type="button" onClick={openCreateBudget}>
                                                <Plus size={13} /> Tambah
                                            </button>
                                        )}
                                    </div>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                    <div className="db-grid-2">
                                        <div className="db-fact"><span className="db-hint">Rencana</span><b>{rupiah(budgetSummary.planned)}</b></div>
                                        <div className="db-fact"><span className="db-hint">Realisasi</span><b>{rupiah(budgetSummary.realized)}</b></div>
                                        <div className="db-fact"><span className="db-hint">Selisih</span><b>{rupiah(budgetSummary.variance)}</b></div>
                                        <div className="db-fact"><span className="db-hint">Serapan</span><b>{budgetSummary.absorption}%</b></div>
                                    </div>

                                    {budgets.length === 0 ? (
                                        <span className="db-hint">Belum ada item anggaran untuk event ini.</span>
                                    ) : (
                                        <div style={{ display: 'grid', gap: 8 }}>
                                            {budgets.map((budget) => (
                                                <article key={budget.id} className="db-fact wide" style={{ display: 'grid', gap: 6 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                        <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{budget.itemName}</b>
                                                        <span className={`db-badge ${toneClass(budget.categoryTone)}`}>{budget.categoryLabel}</span>
                                                    </div>
                                                    <div className="db-hint" style={{ display: 'flex', flexWrap: 'wrap', gap: 14 }}>
                                                        <span>Rencana {rupiah(budget.plannedAmount)}</span>
                                                        <span>Realisasi {rupiah(budget.realizedAmount)}</span>
                                                        <span>Selisih {rupiah(budget.variance)}</span>
                                                    </div>
                                                    {budget.notes && <span className="db-hint">{budget.notes}</span>}
                                                    {can.update && (
                                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                                            <button className="db-btn ghost sm" type="button" onClick={() => openEditBudget(budget)}>
                                                                <Pencil size={12} /> Edit
                                                            </button>
                                                            <button className="db-btn danger sm" type="button" onClick={() => setBudgetPending(budget)}>
                                                                <Trash2 size={12} /> Hapus
                                                            </button>
                                                        </div>
                                                    )}
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><MessageSquare size={15} /> Evaluasi Peserta</h2>
                                    <span className="db-badge gray">{evaluationSummary.count} respons</span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {evaluationSummary.count === 0 ? (
                                        <span className="db-hint">Belum ada evaluasi dari peserta.</span>
                                    ) : (
                                        <>
                                            <div className="db-fact">
                                                <span className="db-hint">Rata-rata keseluruhan</span>
                                                <b style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                    <Star size={14} /> {evaluationSummary.average} / 5
                                                </b>
                                            </div>
                                            <div className="db-fact"><span className="db-hint">Materi & manfaat</span><b>{evaluationSummary.averageBenefit ?? '—'} / 5</b></div>
                                            <div className="db-fact"><span className="db-hint">Penyelenggaraan</span><b>{evaluationSummary.averageOrganization ?? '—'} / 5</b></div>
                                            <div className="db-fact">
                                                <span className="db-hint">Partisipasi</span>
                                                <b>{evaluationSummary.count} dari {evaluationSummary.attended} peserta hadir ({evaluationSummary.responseRate}%)</b>
                                            </div>

                                            <div style={{ display: 'grid', gap: 5 }}>
                                                {[5, 4, 3, 2, 1].map((rating) => (
                                                    <div key={rating} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                        <span className="db-hint" style={{ width: 34 }}>{rating} ★</span>
                                                        <div style={{ flex: 1, height: 7, borderRadius: 999, background: 'var(--db-soft, rgba(104, 66, 244, 0.12))', overflow: 'hidden' }}>
                                                            <div
                                                                style={{
                                                                    width: `${((evaluationSummary.distribution[String(rating)] ?? 0) / maxDistribution) * 100}%`,
                                                                    height: '100%',
                                                                    borderRadius: 999,
                                                                    background: 'var(--db-brand)',
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="db-hint" style={{ width: 18, textAlign: 'right' }}>{evaluationSummary.distribution[String(rating)] ?? 0}</span>
                                                    </div>
                                                ))}
                                            </div>

                                            <div style={{ display: 'grid', gap: 8 }}>
                                                {evaluations.slice(0, 5).map((evaluation) => (
                                                    <article key={evaluation.id} className="db-fact wide" style={{ display: 'grid', gap: 4 }}>
                                                        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 6 }}>
                                                            <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{evaluation.student}</b>
                                                            <span className="db-badge gold"><Star size={11} /> {evaluation.rating}/5</span>
                                                        </div>
                                                        {evaluation.feedback && <span className="db-hint">{evaluation.feedback}</span>}
                                                        {evaluation.suggestions && <span className="db-hint">Saran: {evaluation.suggestions}</span>}
                                                        <span className="db-hint">{evaluation.createdAt || '—'}</span>
                                                    </article>
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Metadata</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <div className="db-fact"><span className="db-hint">Dibuat oleh</span><b>{event.creator || '—'}</b></div>
                                    <div className="db-fact"><span className="db-hint">Dibuat pada</span><b>{event.createdAt || '—'}</b></div>
                                    <div className="db-fact"><span className="db-hint">Diperbarui</span><b>{event.updatedAt || '—'}</b></div>
                                </div>
                            </section>

                            <div className="adm-note gold" style={{ margin: 0 }}>
                                <Star size={16} />
                                <div>
                                    <b style={{ display: 'block', marginBottom: 2, color: 'var(--db-heading)' }}>Poin otomatis.</b>
                                    Mengubah kehadiran menjadi “Hadir” akan memberi poin event kepada mahasiswa. Membatalkan kehadiran akan menarik kembali poin tersebut.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy?.title ?? ''}
                    message={copy?.message ?? ''}
                    confirmLabel={copy?.confirm ?? 'Lanjutkan'}
                    danger={copy?.danger}
                    processing={processing}
                    onConfirm={runAction}
                    onCancel={() => setPending(null)}
                />

                <ConfirmModal
                    open={documentPending !== null}
                    title="Hapus dokumentasi?"
                    message={documentPending ? `"${documentPending.title}" akan dihapus permanen.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    onConfirm={() => {
                        if (documentPending) {
                            router.delete(documentPending.deleteUrl, {
                                preserveScroll: true,
                                onFinish: () => setDocumentPending(null),
                            });
                        }
                    }}
                    onCancel={() => setDocumentPending(null)}
                />

                <ConfirmModal
                    open={budgetPending !== null}
                    title="Hapus item anggaran?"
                    message={budgetPending ? `"${budgetPending.itemName}" akan dihapus dari realisasi anggaran.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    onConfirm={() => {
                        if (budgetPending) {
                            router.delete(budgetPending.deleteUrl, {
                                preserveScroll: true,
                                onFinish: () => setBudgetPending(null),
                            });
                        }
                    }}
                    onCancel={() => setBudgetPending(null)}
                />

                {budgetModalOpen && (
                    <StudentAffairsModal
                        title={editingBudget ? 'Edit Item Anggaran' : 'Tambah Item Anggaran'}
                        description="Catat rencana dan realisasi biaya kegiatan."
                        onClose={() => setBudgetModalOpen(false)}
                    >
                        <form onSubmit={submitBudget} style={{ display: 'grid', gap: 12 }}>
                            <TextField
                                label="Nama Item"
                                required
                                value={budgetForm.data.item_name}
                                onChange={(changeEvent) => budgetForm.setData('item_name', changeEvent.target.value)}
                                error={budgetForm.errors.item_name}
                                placeholder="Mis. Konsumsi peserta"
                            />
                            <SelectField
                                label="Kategori"
                                required
                                value={budgetForm.data.category}
                                onChange={(changeEvent) => budgetForm.setData('category', changeEvent.target.value)}
                                error={budgetForm.errors.category}
                            >
                                {Object.entries(options.budgetCategories).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </SelectField>
                            <div className="crud-grid">
                                <TextField
                                    label="Rencana (Rp)"
                                    required
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={budgetForm.data.planned_amount}
                                    onChange={(changeEvent) => budgetForm.setData('planned_amount', changeEvent.target.value)}
                                    error={budgetForm.errors.planned_amount}
                                />
                                <TextField
                                    label="Realisasi (Rp)"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={budgetForm.data.realized_amount}
                                    onChange={(changeEvent) => budgetForm.setData('realized_amount', changeEvent.target.value)}
                                    error={budgetForm.errors.realized_amount}
                                />
                            </div>
                            <TextareaField
                                label="Catatan"
                                value={budgetForm.data.notes}
                                onChange={(changeEvent) => budgetForm.setData('notes', changeEvent.target.value)}
                                error={budgetForm.errors.notes}
                                placeholder="Keterangan tambahan (opsional)"
                            />
                            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                                <button className="db-btn ghost" type="button" onClick={() => setBudgetModalOpen(false)}>Batal</button>
                                <button className="db-btn primary" type="submit" disabled={budgetForm.processing}>
                                    {editingBudget ? 'Simpan Perubahan' : 'Tambah Item'}
                                </button>
                            </div>
                        </form>
                    </StudentAffairsModal>
                )}
            </div>
        </AdminShell>
    );
}
