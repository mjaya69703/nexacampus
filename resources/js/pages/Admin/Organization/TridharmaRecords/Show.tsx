// Detail portofolio Tridharma: ringkasan, milestone, anggaran, luaran,
// tim, lampiran, verifikasi, dan alur persetujuan.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Award,
    BadgeCheck,
    Banknote,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock3,
    FileText,
    FolderOpen,
    History,
    Info,
    ListChecks,
    Paperclip,
    Pencil,
    Plus,
    Send,
    ShieldCheck,
    Target,
    Users,
    Wallet,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import { OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type RecordPayload = {
    id: number;
    ownerName: string;
    ownerEmail: string | null;
    type: string;
    typeLabel: string;
    title: string;
    scheme: string | null;
    abstract: string | null;
    startsAt: string | null;
    endsAt: string | null;
    fundingAmount: number;
    fundingLabel: string;
    fundingSource: string;
    status: string;
    statusLabel: string;
    isVerified: boolean;
    verifierName: string | null;
    verifiedAt: string | null;
    verificationNotes: string | null;
    adminNotes: string | null;
    createdAt: string | null;
    canSubmit: boolean;
    canVerify: boolean;
    canComplete: boolean;
    canArchive: boolean;
};

type Member = {
    id: number;
    name: string;
    detail: string;
    role: string;
    roleLabel: string;
    isLeader: boolean;
    isExternal: boolean;
};

type Milestone = {
    id: number;
    title: string;
    dueDate: string | null;
    progress: number;
    status: string;
    statusLabel: string;
    notes: string | null;
};

type Budget = {
    id: number;
    category: string;
    description: string | null;
    plannedLabel: string;
    realizedLabel: string;
    notes: string | null;
};

type Output = {
    id: number;
    outputType: string;
    typeLabel: string;
    title: string;
    publisher: string | null;
    indexing: string | null;
    doi: string | null;
    url: string | null;
    publishedAt: string | null;
    status: string;
    statusLabel: string;
    notes: string | null;
};

type Attachment = {
    id: number;
    fileName: string;
    documentType: string;
    documentTypeLabel: string;
    fileSize: number;
    uploadedBy: string | null;
    previewUrl: string;
};

type ApprovalStep = {
    id: number;
    name: string;
    status: string;
    statusLabel: string;
    actorName: string | null;
    actedAt: string | null;
    notes: string | null;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; verify: boolean; complete: boolean; approve: boolean };
    record: RecordPayload;
    members: Member[];
    milestones: Milestone[];
    budgets: Budget[];
    outputs: Output[];
    attachments: Attachment[];
    approvalSteps: ApprovalStep[];
    options: {
        memberRoles: Option[];
        milestoneStatuses: Option[];
        outputTypes: Option[];
        outputStatuses: Option[];
        documentTypes: Option[];
    };
    memberUserIds: number[];
    urls: {
        index: string;
        edit: string;
        submitApproval: string;
        verify: string;
        complete: string;
        archive: string;
        addMember: string;
        addMilestone: string;
        addBudget: string;
        addOutput: string;
        uploadAttachment: string;
        searchUsers: string;
    };
};

type PendingAction = 'submit' | 'complete' | 'archive' | null;

const ATTACH_ACCEPT = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xlsx';

const statusTone = (status: string) => {
    if (['approved', 'active', 'completed', 'published', 'accepted'].includes(status)) return 'green';
    if (['rejected', 'blocked'].includes(status)) return 'red';
    if (['in_approval', 'submitted', 'in_progress'].includes(status)) return 'amber';
    if (status === 'archived') return 'gray';
    return 'gray';
};

const formatSize = (bytes: number) => {
    if (!bytes) return '0 KB';
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

const confirmCopy: Record<Exclude<PendingAction, null>, { title: string; message: string; confirmLabel: string }> = {
    submit: {
        title: 'Ajukan proposal ke approval?',
        message: 'Proposal Tridharma ini akan dikirim ke alur persetujuan yang berlaku.',
        confirmLabel: 'Ya, ajukan',
    },
    complete: {
        title: 'Tandai kegiatan selesai?',
        message: 'Kegiatan akan ditandai selesai dan waktu penyelesaian dicatat otomatis.',
        confirmLabel: 'Ya, tandai selesai',
    },
    archive: {
        title: 'Arsipkan kegiatan?',
        message: 'Kegiatan akan dipindahkan ke status diarsipkan dan tidak lagi aktif di daftar utama.',
        confirmLabel: 'Ya, arsipkan',
    },
};

export default function TridharmaRecordShow({
    shell,
    can,
    record,
    members,
    milestones,
    budgets,
    outputs,
    attachments,
    approvalSteps,
    options,
    memberUserIds,
    urls,
}: Props) {
    const [pending, setPending] = useState<PendingAction>(null);
    const [processing, setProcessing] = useState(false);
    const [memberOption, setMemberOption] = useState<AsyncOption | null>(null);

    const verifyForm = useForm({ verification_notes: '' });

    const memberForm = useForm({
        user_id: null as number | null,
        member_name: '',
        institution: '',
        email: '',
        role: 'member',
    });

    const milestoneForm = useForm({
        title: '',
        due_date: '',
        status: 'pending',
        progress_percentage: 0,
        description: '',
    });

    const budgetForm = useForm({
        category: '',
        description: '',
        planned_amount: '',
        realized_amount: '',
        notes: '',
    });

    const outputForm = useForm({
        output_type: 'article',
        title: '',
        publisher: '',
        indexing: '',
        doi: '',
        url: '',
        published_at: '',
        status: 'draft',
        notes: '',
    });

    const attachmentForm = useForm({
        document_type: 'evidence',
        attachment: null as File | null,
    });

    const runAction = () => {
        if (!pending) return;

        const url = pending === 'submit'
            ? urls.submitApproval
            : pending === 'complete'
                ? urls.complete
                : urls.archive;

        setProcessing(true);
        router.post(url, {}, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        });
    };

    const submitVerify = (event: FormEvent) => {
        event.preventDefault();
        verifyForm.post(urls.verify, { preserveScroll: true });
    };

    const submitMember = (event: FormEvent) => {
        event.preventDefault();
        memberForm.post(urls.addMember, {
            preserveScroll: true,
            onSuccess: () => {
                memberForm.reset();
                setMemberOption(null);
            },
        });
    };

    const submitMilestone = (event: FormEvent) => {
        event.preventDefault();
        milestoneForm.post(urls.addMilestone, {
            preserveScroll: true,
            onSuccess: () => milestoneForm.reset(),
        });
    };

    const submitBudget = (event: FormEvent) => {
        event.preventDefault();
        budgetForm.post(urls.addBudget, {
            preserveScroll: true,
            onSuccess: () => budgetForm.reset(),
        });
    };

    const submitOutput = (event: FormEvent) => {
        event.preventDefault();
        outputForm.post(urls.addOutput, {
            preserveScroll: true,
            onSuccess: () => outputForm.reset(),
        });
    };

    const submitAttachment = (event: FormEvent) => {
        event.preventDefault();
        attachmentForm.post(urls.uploadAttachment, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => attachmentForm.reset(),
        });
    };

    const memberSearchUrl = `${urls.searchUsers}?context=member&exclude=${memberUserIds.join(',')}`;
    const pendingCopy = pending ? confirmCopy[pending] : null;

    return (
        <AdminShell shell={shell}>
            <Head title={`${record.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow={`Kepegawaian · ${record.typeLabel}`}
                        title={record.title}
                        description={`Oleh: ${record.ownerName} · Periode: ${record.startsAt ?? '-'} s.d. ${record.endsAt ?? '-'}`}
                        badges={[record.statusLabel, record.isVerified ? 'Terverifikasi' : 'Belum verifikasi']}
                        actions={(
                            <>
                                {can.update && (
                                    <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Edit</a>
                                )}
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali ke daftar</a>
                            </>
                        )}
                    />

                    <OrgStats
                        label="Ringkasan kegiatan Tridharma"
                        items={[
                            { label: 'Status Kegiatan', value: record.statusLabel, icon: Info },
                            { label: 'Status Verifikasi', value: record.isVerified ? 'Terverifikasi' : 'Belum Verifikasi', icon: ShieldCheck, tone: record.isVerified ? 'green' : 'gold' },
                            { label: 'Dana Disetujui', value: record.fundingLabel, icon: Banknote },
                            { label: 'Sumber Pendanaan', value: record.fundingSource, icon: Wallet },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Info size={16} /> Abstrak & Aksi Kegiatan</h2>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                {can.update && record.canSubmit && (
                                    <button className="db-btn primary sm" type="button" onClick={() => setPending('submit')}>
                                        <Send size={14} /> Submit Approval
                                    </button>
                                )}
                                {can.complete && record.canComplete && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('complete')}>
                                        <CheckCircle2 size={14} /> Tandai Selesai
                                    </button>
                                )}
                                {can.update && record.canArchive && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('archive')}>
                                        <FolderOpen size={14} /> Arsipkan
                                    </button>
                                )}
                            </div>
                        </div>
                        <div className="db-card-body">
                            {record.abstract ? (
                                <div className="db-note muted" style={{ display: 'block' }}>{record.abstract}</div>
                            ) : (
                                <p className="db-hint" style={{ margin: 0 }}>Tidak ada uraian abstrak/ringkasan kegiatan untuk rekor ini.</p>
                            )}

                            {record.verificationNotes && (
                                <div className="db-note success" style={{ marginTop: 12 }}>
                                    <ShieldCheck size={16} />
                                    <span>
                                        <b style={{ display: 'block', marginBottom: 3 }}>
                                            Catatan Verifikasi{record.verifierName ? ` · ${record.verifierName}` : ''}
                                        </b>
                                        {record.verificationNotes}
                                    </span>
                                </div>
                            )}

                            {can.verify && record.canVerify && (
                                <form onSubmit={submitVerify} style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 14 }}>
                                    <input
                                        className="db-input"
                                        style={{ flex: 1, minWidth: 220 }}
                                        placeholder="Catatan verifikasi (opsional)…"
                                        value={verifyForm.data.verification_notes}
                                        onChange={(event) => verifyForm.setData('verification_notes', event.target.value)}
                                        aria-label="Catatan verifikasi"
                                    />
                                    <button className="db-btn primary" type="submit" disabled={verifyForm.processing}>
                                        <ShieldCheck size={15} /> Verifikasi
                                    </button>
                                </form>
                            )}
                            {verifyForm.errors.verification_notes && <div className="db-error">{verifyForm.errors.verification_notes}</div>}
                        </div>
                    </section>

                    <div className="db-grid-2">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Target size={16} /> Milestones & Target Tahapan</h2>
                                    <span className="db-badge gray">{milestones.length} tahap</span>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-table-wrap">
                                        <table className="crud-table">
                                            <thead>
                                                <tr>
                                                    <th>Target Capaian</th>
                                                    <th>Jatuh Tempo</th>
                                                    <th style={{ textAlign: 'center' }}>Progress</th>
                                                    <th style={{ textAlign: 'right' }}>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {milestones.length === 0 && (
                                                    <tr>
                                                        <td colSpan={4} style={{ textAlign: 'center', padding: 24 }}>
                                                            <span className="db-hint">Belum ada target tahapan milestone.</span>
                                                        </td>
                                                    </tr>
                                                )}
                                                {milestones.map((milestone) => (
                                                    <tr key={milestone.id}>
                                                        <td>
                                                            <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{milestone.title}</b>
                                                            {milestone.notes && <small className="db-hint" style={{ display: 'block' }}>{milestone.notes}</small>}
                                                        </td>
                                                        <td><span className="db-hint" style={{ marginTop: 0 }}>{milestone.dueDate ?? '-'}</span></td>
                                                        <td style={{ textAlign: 'center' }}><span className="db-badge">{milestone.progress}%</span></td>
                                                        <td style={{ textAlign: 'right' }}><span className={`db-badge ${statusTone(milestone.status)}`}>{milestone.statusLabel}</span></td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    {can.update && (
                                        <form onSubmit={submitMilestone} style={{ marginTop: 14 }}>
                                            <div className="crud-grid">
                                                <TextField
                                                    label="Nama target milestone"
                                                    required
                                                    placeholder="Contoh: Proposal disetujui"
                                                    value={milestoneForm.data.title}
                                                    onChange={(event) => milestoneForm.setData('title', event.target.value)}
                                                    error={milestoneForm.errors.title}
                                                />
                                                <TextField
                                                    label="Jatuh tempo"
                                                    type="date"
                                                    value={milestoneForm.data.due_date}
                                                    onChange={(event) => milestoneForm.setData('due_date', event.target.value)}
                                                    error={milestoneForm.errors.due_date}
                                                />
                                                <SelectField
                                                    label="Status"
                                                    required
                                                    value={milestoneForm.data.status}
                                                    onChange={(event) => milestoneForm.setData('status', event.target.value)}
                                                    error={milestoneForm.errors.status}
                                                >
                                                    {options.milestoneStatuses.map((status) => (
                                                        <option key={status.id} value={status.id}>{status.label}</option>
                                                    ))}
                                                </SelectField>
                                                <TextField
                                                    label="Progress (%)"
                                                    type="number"
                                                    min={0}
                                                    max={100}
                                                    required
                                                    value={milestoneForm.data.progress_percentage}
                                                    onChange={(event) => milestoneForm.setData('progress_percentage', Number(event.target.value))}
                                                    error={milestoneForm.errors.progress_percentage}
                                                />
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <TextareaField
                                                        label="Keterangan"
                                                        rows={2}
                                                        placeholder="Detail singkat target tahapan…"
                                                        value={milestoneForm.data.description}
                                                        onChange={(event) => milestoneForm.setData('description', event.target.value)}
                                                        error={milestoneForm.errors.description}
                                                    />
                                                </div>
                                            </div>
                                            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 12 }}>
                                                <button className="db-btn primary" type="submit" disabled={milestoneForm.processing}>
                                                    <Plus size={15} /> Tambah Milestone
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Banknote size={16} /> Rincian Anggaran (Budget)</h2>
                                    <span className="db-badge gray">{budgets.length} item</span>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-table-wrap">
                                        <table className="crud-table">
                                            <thead>
                                                <tr>
                                                    <th>Kategori / Keterangan</th>
                                                    <th style={{ textAlign: 'right' }}>Rencana</th>
                                                    <th style={{ textAlign: 'right' }}>Realisasi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {budgets.length === 0 && (
                                                    <tr>
                                                        <td colSpan={3} style={{ textAlign: 'center', padding: 24 }}>
                                                            <span className="db-hint">Belum ada rincian anggaran.</span>
                                                        </td>
                                                    </tr>
                                                )}
                                                {budgets.map((budget) => (
                                                    <tr key={budget.id}>
                                                        <td>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{budget.category}</b>
                                                            <span className="db-hint" style={{ marginTop: 0 }}>{budget.description ?? '-'}</span>
                                                        </td>
                                                        <td style={{ textAlign: 'right' }}>{budget.plannedLabel}</td>
                                                        <td style={{ textAlign: 'right', color: 'var(--db-green)', fontWeight: 700 }}>{budget.realizedLabel}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    {can.update && (
                                        <form onSubmit={submitBudget} style={{ marginTop: 14 }}>
                                            <div className="crud-grid">
                                                <TextField
                                                    label="Kategori anggaran"
                                                    required
                                                    placeholder="Contoh: Bahan habis pakai"
                                                    value={budgetForm.data.category}
                                                    onChange={(event) => budgetForm.setData('category', event.target.value)}
                                                    error={budgetForm.errors.category}
                                                />
                                                <TextField
                                                    label="Keterangan"
                                                    placeholder="Rincian singkat anggaran…"
                                                    value={budgetForm.data.description}
                                                    onChange={(event) => budgetForm.setData('description', event.target.value)}
                                                    error={budgetForm.errors.description}
                                                />
                                                <TextField
                                                    label="Rencana (Rp)"
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    required
                                                    value={budgetForm.data.planned_amount}
                                                    onChange={(event) => budgetForm.setData('planned_amount', event.target.value)}
                                                    error={budgetForm.errors.planned_amount}
                                                />
                                                <TextField
                                                    label="Realisasi (Rp)"
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    required
                                                    value={budgetForm.data.realized_amount}
                                                    onChange={(event) => budgetForm.setData('realized_amount', event.target.value)}
                                                    error={budgetForm.errors.realized_amount}
                                                />
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <TextareaField
                                                        label="Catatan anggaran"
                                                        rows={2}
                                                        placeholder="Catatan tambahan mengenai penggunaan anggaran…"
                                                        value={budgetForm.data.notes}
                                                        onChange={(event) => budgetForm.setData('notes', event.target.value)}
                                                        error={budgetForm.errors.notes}
                                                    />
                                                </div>
                                            </div>
                                            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 12 }}>
                                                <button className="db-btn primary" type="submit" disabled={budgetForm.processing}>
                                                    <Plus size={15} /> Tambah Anggaran
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ClipboardList size={16} /> Output / Luaran Ilmiah & HKI</h2>
                                    <span className="db-badge gray">{outputs.length} luaran</span>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'grid', gap: 8 }}>
                                        {outputs.length === 0 && <span className="db-hint">Belum ada luaran terdaftar.</span>}
                                        {outputs.map((output) => (
                                            <div key={output.id} className="db-note" style={{ display: 'block' }}>
                                                <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', gap: 8, marginBottom: 4 }}>
                                                    <span className="db-badge">{output.typeLabel}</span>
                                                    <span className={`db-badge ${statusTone(output.status)}`}>{output.statusLabel}</span>
                                                </div>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{output.title}</b>
                                                <small className="db-hint" style={{ marginTop: 2 }}>
                                                    {[output.publisher, output.indexing, output.publishedAt].filter(Boolean).join(' · ') || 'Belum ada penerbit/indexing'}
                                                </small>
                                                {output.doi || output.url ? (
                                                    <small className="db-hint" style={{ display: 'block', marginTop: 2 }}>
                                                        {output.doi ? `DOI: ${output.doi}` : null}
                                                        {output.doi && output.url ? ' · ' : null}
                                                        {output.url ? <a href={output.url} target="_blank" rel="noreferrer" style={{ color: 'var(--db-brand)' }}>Tautan luaran</a> : null}
                                                    </small>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>

                                    {can.update && (
                                        <form onSubmit={submitOutput} style={{ marginTop: 14 }}>
                                            <div className="crud-grid">
                                                <SelectField
                                                    label="Jenis luaran"
                                                    required
                                                    value={outputForm.data.output_type}
                                                    onChange={(event) => outputForm.setData('output_type', event.target.value)}
                                                    error={outputForm.errors.output_type}
                                                >
                                                    {options.outputTypes.map((type) => (
                                                        <option key={type.id} value={type.id}>{type.label}</option>
                                                    ))}
                                                </SelectField>
                                                <SelectField
                                                    label="Status luaran"
                                                    required
                                                    value={outputForm.data.status}
                                                    onChange={(event) => outputForm.setData('status', event.target.value)}
                                                    error={outputForm.errors.status}
                                                >
                                                    {options.outputStatuses.map((status) => (
                                                        <option key={status.id} value={status.id}>{status.label}</option>
                                                    ))}
                                                </SelectField>
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <TextField
                                                        label="Judul luaran"
                                                        required
                                                        placeholder="Judul artikel, laporan, HKI, dataset, atau modul…"
                                                        value={outputForm.data.title}
                                                        onChange={(event) => outputForm.setData('title', event.target.value)}
                                                        error={outputForm.errors.title}
                                                    />
                                                </div>
                                                <TextField
                                                    label="Penerbit / jurnal"
                                                    placeholder="Nama jurnal, penerbit, atau instansi…"
                                                    value={outputForm.data.publisher}
                                                    onChange={(event) => outputForm.setData('publisher', event.target.value)}
                                                    error={outputForm.errors.publisher}
                                                />
                                                <TextField
                                                    label="Indexing"
                                                    placeholder="Contoh: Scopus Q2 / Sinta 2"
                                                    value={outputForm.data.indexing}
                                                    onChange={(event) => outputForm.setData('indexing', event.target.value)}
                                                    error={outputForm.errors.indexing}
                                                />
                                                <TextField
                                                    label="DOI"
                                                    placeholder="Contoh: 10.1234/abcd.2026"
                                                    value={outputForm.data.doi}
                                                    onChange={(event) => outputForm.setData('doi', event.target.value)}
                                                    error={outputForm.errors.doi}
                                                />
                                                <TextField
                                                    label="URL luaran"
                                                    type="url"
                                                    placeholder="https://…"
                                                    value={outputForm.data.url}
                                                    onChange={(event) => outputForm.setData('url', event.target.value)}
                                                    error={outputForm.errors.url}
                                                />
                                                <TextField
                                                    label="Tanggal terbit"
                                                    type="date"
                                                    value={outputForm.data.published_at}
                                                    onChange={(event) => outputForm.setData('published_at', event.target.value)}
                                                    error={outputForm.errors.published_at}
                                                />
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <TextareaField
                                                        label="Catatan luaran"
                                                        rows={2}
                                                        placeholder="Keterangan tambahan mengenai luaran…"
                                                        value={outputForm.data.notes}
                                                        onChange={(event) => outputForm.setData('notes', event.target.value)}
                                                        error={outputForm.errors.notes}
                                                    />
                                                </div>
                                            </div>
                                            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 12 }}>
                                                <button className="db-btn primary" type="submit" disabled={outputForm.processing}>
                                                    <Plus size={15} /> Tambah Luaran
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Users size={16} /> Tim Peneliti / Pengabdi</h2>
                                    <span className="db-badge gray">{members.length} anggota</span>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'grid', gap: 8 }}>
                                        {members.length === 0 && <span className="db-hint">Belum ada anggota tim terdaftar.</span>}
                                        {members.map((member) => (
                                            <div key={member.id} className="db-note" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                                                <span>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{member.name}</b>
                                                    <small className="db-hint" style={{ marginTop: 0 }}>
                                                        {member.roleLabel} · {member.detail}{member.isExternal ? ' · Eksternal' : ''}
                                                    </small>
                                                </span>
                                                <span className={`db-badge ${member.isLeader ? '' : 'gray'}`.trim()}>{member.roleLabel}</span>
                                            </div>
                                        ))}
                                    </div>

                                    {can.update && (
                                        <form onSubmit={submitMember} style={{ marginTop: 14 }}>
                                            <div className="crud-grid">
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <AsyncSelect
                                                        key={`member-${members.length}`}
                                                        label="Anggota internal (opsional)"
                                                        fetchUrl={memberSearchUrl}
                                                        value={memberOption}
                                                        onChange={(option) => {
                                                            setMemberOption(option);
                                                            memberForm.setData('user_id', option ? Number(option.id) : null);
                                                        }}
                                                        error={memberForm.errors.user_id}
                                                        hint="Kosongkan bila ingin menambahkan anggota eksternal. Ketik minimal 2 karakter untuk mencari."
                                                        placeholder="Cari nama / email pengguna internal…"
                                                    />
                                                </div>

                                                <SelectField
                                                    label="Peran dalam tim"
                                                    required
                                                    value={memberForm.data.role}
                                                    onChange={(event) => memberForm.setData('role', event.target.value)}
                                                    error={memberForm.errors.role}
                                                >
                                                    {options.memberRoles.map((role) => (
                                                        <option key={role.id} value={role.id}>{role.label}</option>
                                                    ))}
                                                </SelectField>

                                                {!memberOption && (
                                                    <>
                                                        <TextField
                                                            label="Nama anggota eksternal"
                                                            placeholder="Nama lengkap mitra/anggota…"
                                                            value={memberForm.data.member_name}
                                                            onChange={(event) => memberForm.setData('member_name', event.target.value)}
                                                            error={memberForm.errors.member_name}
                                                        />
                                                        <TextField
                                                            label="Institusi / asal"
                                                            placeholder="Contoh: Universitas Mitra / PT Swasta"
                                                            value={memberForm.data.institution}
                                                            onChange={(event) => memberForm.setData('institution', event.target.value)}
                                                            error={memberForm.errors.institution}
                                                        />
                                                        <TextField
                                                            label="Email eksternal"
                                                            type="email"
                                                            placeholder="nama@institusi.ac.id"
                                                            value={memberForm.data.email}
                                                            onChange={(event) => memberForm.setData('email', event.target.value)}
                                                            error={memberForm.errors.email}
                                                        />
                                                    </>
                                                )}
                                            </div>
                                            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 12 }}>
                                                <button className="db-btn primary" type="submit" disabled={memberForm.processing}>
                                                    <Plus size={15} /> Tambah Anggota
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Paperclip size={16} /> Lampiran Dokumen & Bukti</h2>
                                    <span className="db-badge gray">{attachments.length} berkas</span>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'grid', gap: 8 }}>
                                        {attachments.length === 0 && <span className="db-hint">Belum ada lampiran dokumen.</span>}
                                        {attachments.map((attachment) => (
                                            <a
                                                key={attachment.id}
                                                className="db-note"
                                                href={attachment.previewUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                                style={{ display: 'flex', alignItems: 'center', gap: 10, color: 'inherit' }}
                                            >
                                                <FileText size={16} style={{ color: 'var(--db-brand)' }} />
                                                <span style={{ flex: 1, minWidth: 0 }}>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                                        {attachment.fileName}
                                                    </b>
                                                    <small className="db-hint" style={{ marginTop: 0 }}>
                                                        {attachment.documentTypeLabel} · {formatSize(attachment.fileSize)}{attachment.uploadedBy ? ` · ${attachment.uploadedBy}` : ''}
                                                    </small>
                                                </span>
                                            </a>
                                        ))}
                                    </div>

                                    {can.update && (
                                        <form onSubmit={submitAttachment} style={{ marginTop: 14 }}>
                                            <div className="crud-grid">
                                                <SelectField
                                                    label="Jenis dokumen"
                                                    required
                                                    value={attachmentForm.data.document_type}
                                                    onChange={(event) => attachmentForm.setData('document_type', event.target.value)}
                                                    error={attachmentForm.errors.document_type}
                                                >
                                                    {options.documentTypes.map((type) => (
                                                        <option key={type.id} value={type.id}>{type.label}</option>
                                                    ))}
                                                </SelectField>
                                                <div>
                                                    <span className="crud-label">Berkas</span>
                                                    <input
                                                        type="file"
                                                        className="db-input"
                                                        accept={ATTACH_ACCEPT}
                                                        onChange={(event) => attachmentForm.setData('attachment', event.target.files?.[0] ?? null)}
                                                    />
                                                    {attachmentForm.errors.attachment && <div className="db-error">{attachmentForm.errors.attachment}</div>}
                                                </div>
                                            </div>
                                            {attachmentForm.data.attachment && (
                                                <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                                                    <Paperclip size={14} />
                                                    <span style={{ flex: 1 }}>{attachmentForm.data.attachment.name}</span>
                                                    <button className="db-btn ghost sm" type="button" onClick={() => attachmentForm.setData('attachment', null)}>
                                                        <X size={13} /> Batal
                                                    </button>
                                                </div>
                                            )}
                                            <div className="crud-hint">Maks 8MB. Format: PDF, DOC, DOCX, XLSX, JPG, JPEG, PNG, WEBP.</div>
                                            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 12 }}>
                                                <button className="db-btn primary" type="submit" disabled={attachmentForm.processing}>
                                                    <Plus size={15} /> Unggah Dokumen
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Info size={16} /> Informasi Kegiatan</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="db-facts">
                                        <div className="db-fact"><span>Owner</span><b>{record.ownerName}</b></div>
                                        <div className="db-fact"><span>Email</span><b>{record.ownerEmail ?? '-'}</b></div>
                                        <div className="db-fact"><span>Jenis</span><b>{record.typeLabel}</b></div>
                                        <div className="db-fact"><span>Skema</span><b>{record.scheme ?? '-'}</b></div>
                                        <div className="db-fact"><span>Mulai</span><b>{record.startsAt ?? '-'}</b></div>
                                        <div className="db-fact"><span>Selesai</span><b>{record.endsAt ?? '-'}</b></div>
                                        <div className="db-fact"><span>Verifikator</span><b>{record.verifierName ?? '-'}</b></div>
                                        <div className="db-fact"><span>Waktu Verifikasi</span><b>{record.verifiedAt ?? '-'}</b></div>
                                        <div className="db-fact"><span>Dibuat</span><b>{record.createdAt ?? '-'}</b></div>
                                        <div className="db-fact"><span>Sumber Dana</span><b>{record.fundingSource}</b></div>
                                        <div className="db-fact wide"><span>Catatan Admin</span><b>{record.adminNotes ?? '-'}</b></div>
                                    </div>
                                </div>
                            </section>

                            {approvalSteps.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><History size={16} /> Alur Persetujuan (Approval Timeline)</h2>
                                    </div>
                                    <div className="db-card-body">
                                        <div style={{ display: 'grid', gap: 8 }}>
                                            {approvalSteps.map((step) => (
                                                <div key={step.id} className="db-note" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                                                    <span>
                                                        <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{step.name}</b>
                                                        <small className="db-hint" style={{ marginTop: 0 }}>
                                                            {step.actorName ?? 'Menunggu approver'}{step.actedAt ? ` · ${step.actedAt}` : ''}
                                                        </small>
                                                        {step.notes && <small className="db-hint" style={{ display: 'block', marginTop: 2 }}>{step.notes}</small>}
                                                    </span>
                                                    <span className={`db-badge ${statusTone(step.status)}`}>{step.statusLabel}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ListChecks size={16} /> Kelengkapan Portofolio</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="db-facts">
                                        <div className="db-fact"><span>Milestone</span><b>{milestones.length} tahap</b></div>
                                        <div className="db-fact"><span>Anggaran</span><b>{budgets.length} item</b></div>
                                        <div className="db-fact"><span>Luaran</span><b>{outputs.length} luaran</b></div>
                                        <div className="db-fact"><span>Lampiran</span><b>{attachments.length} berkas</b></div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div className="db-note info">
                        <Clock3 size={16} />
                        <span>Status kegiatan dan verifikasi diperbarui otomatis setiap kali aksi dijalankan. Riwayat perubahan tetap tercatat pada log aktivitas sistem.</span>
                    </div>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pendingCopy?.title ?? ''}
                    message={pendingCopy?.message ?? ''}
                    confirmLabel={pendingCopy?.confirmLabel}
                    danger={pending === 'archive'}
                    processing={processing}
                    onConfirm={runAction}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
