// Detail tridharma — employee self-service (Inertia React).
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, Award, CheckCircle2, Clock3, Eye, FileText, FileUp,
    FlaskConical, Info, Link2, Newspaper, Paperclip, Plus, Save, Send, Trash2, TrendingUp,
    UsersRound, Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import '../../../../../css/employee.css';

type Member = { id: number; name: string; detail: string; role: string; roleLabel: string; isExternal: boolean; isLeader: boolean; removable: boolean };
type Milestone = { id: number; title: string; dueLabel: string | null; progress: number; status: string; statusLabel: string; notes: string | null; locked: boolean; editable: boolean };
type OutputAttachment = { id: number; fileName: string; previewUrl: string };
type Output = {
    id: number; type: string; title: string; publisher: string | null; indexing: string | null;
    doi: string | null; url: string | null; publishedLabel: string | null; status: string;
    statusLabel: string; notes: string | null; locked: boolean; editable: boolean;
    attachments: OutputAttachment[];
};
type DocAttachment = { id: number; fileName: string; documentType: string; previewUrl: string };
type ApprovalStep = { id: number; name: string; status: string; statusLabel: string; actorName: string | null; actedLabel: string | null; notes: string | null };
type RecordDetail = {
    id: number; type: string; typeLabel: string; title: string; scheme: string | null;
    abstract: string | null; startsLabel: string | null; endsLabel: string | null;
    fundingLabel: string; fundingSource: string | null; status: string; statusLabel: string;
    progress: number; canManageTeam: boolean; canUpdateProgress: boolean; canSubmit: boolean;
    members: Member[]; milestones: Milestone[]; outputs: Output[];
    attachments: DocAttachment[]; approvalSteps: ApprovalStep[];
};
type Candidate = { id: number; name: string; email: string; code: string | null };

type Props = {
    shell: ShellProps;
    record: RecordDetail;
    indexUrl: string;
    basePath: string;
    searchUsersUrl: string;
};

const statusTone = (status: string): string => {
    if (['approved', 'active', 'completed', 'published', 'accepted'].includes(status)) return 'green';
    if (['rejected', 'blocked'].includes(status)) return 'red';
    if (['submitted', 'in_approval', 'in_progress'].includes(status)) return 'amber';
    if (status === 'draft') return 'gray';
    return 'gray';
};

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <span className="em-error">{message}</span>;
}

const typeIcon = (type: string, size = 26) => {
    if (type === 'publication') return <Newspaper size={size} />;
    if (type === 'community_service') return <UsersRound size={size} />;
    return <FlaskConical size={size} />;
};

export default function TridharmaShow({ shell, record, indexUrl, basePath, searchUsersUrl }: Props) {
    const base = `${basePath}/${record.id}`;
    const [milestoneOpen, setMilestoneOpen] = useState(false);
    const [outputOpen, setOutputOpen] = useState(false);
    const [editingOutput, setEditingOutput] = useState<Output | null>(null);
    const [editingMilestone, setEditingMilestone] = useState<number | null>(null);
    const [memberSearch, setMemberSearch] = useState('');
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [searching, setSearching] = useState(false);
    const [external, setExternal] = useState({ member_name: '', institution: '', email: '', role: 'member' });

    const milestoneForm = useForm({ title: '', due_date: '', progress_percentage: 0, completion_notes: '' });
    const progressForm = useForm<{ progress: Record<number, number>; notes: Record<number, string> }>({ progress: {}, notes: {} });
    const outputForm = useForm({
        output_id: '' as string | number, output_type: 'article', title: '', publisher: '',
        indexing: '', doi: '', url: '', status: 'published', published_at: '', notes: '',
        document: null as File | null,
    });
    const memberForm = useForm({ kind: 'external', user_id: '', member_name: '', institution: '', email: '', role: 'member' });
    const docForm = useForm({ document: null as File | null });

    useEffect(() => {
        const needle = memberSearch.trim();
        if (needle.length < 2) {
            setCandidates([]);
            return;
        }
        setSearching(true);
        const timer = window.setTimeout(async () => {
            try {
                const params = new URLSearchParams({ q: needle });
                const response = await fetch(`${searchUsersUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                const json = await response.json();
                setCandidates(json.users ?? []);
            } catch {
                setCandidates([]);
            } finally {
                setSearching(false);
            }
        }, 350);
        return () => window.clearTimeout(timer);
    }, [memberSearch, searchUsersUrl, record.members]);

    const submitMilestone = (e: React.FormEvent) => {
        e.preventDefault();
        milestoneForm.post(`${base}/milestones`, { preserveScroll: true, onSuccess: () => { milestoneForm.reset(); setMilestoneOpen(false); } });
    };

    const submitProgress = (id: number) => {
        router.patch(`${base}/milestones/${id}`, {
            progress_percentage: progressForm.data.progress[id] ?? 0,
            completion_notes: progressForm.data.notes[id] ?? '',
        }, { preserveScroll: true, onSuccess: () => setEditingMilestone(null) });
    };

    const openOutputEdit = (output: Output) => {
        setEditingOutput(output);
        outputForm.setData({
            output_id: output.id, output_type: output.type, title: output.title,
            publisher: output.publisher ?? '', indexing: output.indexing ?? '', doi: output.doi ?? '',
            url: output.url ?? '', status: output.status, published_at: '', notes: output.notes ?? '',
            document: null,
        });
        setOutputOpen(true);
    };

    const submitOutput = (e: React.FormEvent) => {
        e.preventDefault();
        outputForm.post(`${base}/outputs`, {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => { outputForm.reset(); setOutputOpen(false); setEditingOutput(null); },
        });
    };

    const addInternal = (candidate: Candidate) => {
        memberForm.setData({ kind: 'internal', user_id: String(candidate.id), member_name: '', institution: '', email: '', role: 'member' });
        memberForm.post(`${base}/members`, {
            preserveScroll: true,
            onSuccess: () => { memberForm.reset(); setMemberSearch(''); setCandidates([]); },
        });
    };

    const addExternal = (e: React.FormEvent) => {
        e.preventDefault();
        if (!external.member_name.trim()) return;
        memberForm.setData({ kind: 'external', user_id: '', ...external });
        memberForm.post(`${base}/members`, {
            preserveScroll: true,
            onSuccess: () => { memberForm.reset(); setExternal({ member_name: '', institution: '', email: '', role: 'member' }); },
        });
    };

    const removeMember = (id: number) => {
        router.delete(`${base}/members/${id}`, { preserveScroll: true });
    };

    const uploadDoc = (e: React.FormEvent) => {
        e.preventDefault();
        docForm.post(`${base}/attachments`, { forceFormData: true, preserveScroll: true, onSuccess: () => docForm.reset() });
    };

    const submitApproval = () => {
        router.post(`${base}/submit`, {}, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${record.title} · ${shell.appName}`} />
            <div className="em-root">
                <div className="em-stack">
                    <section className="em-hero">
                        <div className="em-hero-inner">
                            <div className="em-hero-copy">
                                <span className="em-hero-icon">{typeIcon(record.type)}</span>
                                <div style={{ minWidth: 0 }}>
                                    <small>{record.typeLabel}</small>
                                    <h1>{record.title}</h1>
                                    <p>{[record.scheme, record.startsLabel && record.endsLabel ? `${record.startsLabel} – ${record.endsLabel}` : null, record.fundingSource].filter(Boolean).join(' · ') || 'Detail kegiatan tridharma.'}</p>
                                    <div className="em-hero-pills">
                                        <span className="em-pill"><TrendingUp size={12} /> Progress {record.progress}%</span>
                                        <span className="em-pill"><Wallet size={12} /> {record.fundingLabel}</span>
                                        <span className="em-pill"><CheckCircle2 size={12} /> {record.statusLabel}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="em-hero-actions">
                                <a className="em-btn light" href={indexUrl}><ArrowLeft size={15} /> Kembali</a>
                                {record.canSubmit && (
                                    <button type="button" className="em-btn light" onClick={submitApproval}>
                                        <Send size={15} /> Ajukan approval
                                    </button>
                                )}
                            </div>
                        </div>
                    </section>

                    {record.abstract && (
                        <section className="em-card">
                            <div className="em-card-body">
                                <span className="em-kicker">Abstrak / Deskripsi</span>
                                <p className="em-hint" style={{ margin: '8px 0 0', color: 'var(--em-ink)', fontSize: 13.5 }}>{record.abstract}</p>
                            </div>
                        </section>
                    )}

                    <div className="em-layout" style={{ gridTemplateColumns: 'minmax(0, 1.6fr) minmax(0, 1fr)' }}>
                        <div className="em-stack">
                            <section className="em-card">
                                <div className="em-card-head">
                                    <h2 className="em-card-title"><TrendingUp size={16} /> Milestone & progress</h2>
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                        <span className="em-badge gray">{record.progress}%</span>
                                        {record.canUpdateProgress && !milestoneOpen && (
                                            <button type="button" className="em-btn ghost sm" onClick={() => setMilestoneOpen(true)}>
                                                <Plus size={13} /> Tambah
                                            </button>
                                        )}
                                    </div>
                                </div>
                                <div className="em-card-body" style={{ display: 'grid', gap: 12 }}>
                                    <div className="em-progress"><i style={{ width: `${record.progress}%` }} /></div>
                                    {milestoneOpen && (
                                        <form onSubmit={submitMilestone} style={{ display: 'grid', gap: 12, padding: 14, border: '1px solid var(--em-line)', borderRadius: 12, background: 'var(--em-soft)' }}>
                                            <div className="em-field">
                                                <label>Judul milestone <i>*</i></label>
                                                <input className="em-input" value={milestoneForm.data.title} onChange={(e) => milestoneForm.setData('title', e.target.value)} placeholder="Tahapan kegiatan" />
                                                <FieldError message={milestoneForm.errors.title} />
                                            </div>
                                            <div className="em-grid-2">
                                                <div className="em-field">
                                                    <label>Tenggat</label>
                                                    <input className="em-input" type="date" value={milestoneForm.data.due_date} onChange={(e) => milestoneForm.setData('due_date', e.target.value)} />
                                                    <FieldError message={milestoneForm.errors.due_date} />
                                                </div>
                                                <div className="em-field">
                                                    <label>Progress (%) <i>*</i></label>
                                                    <input className="em-input" type="number" min={0} max={100} value={milestoneForm.data.progress_percentage} onChange={(e) => milestoneForm.setData('progress_percentage', Number(e.target.value))} />
                                                    <FieldError message={milestoneForm.errors.progress_percentage} />
                                                </div>
                                            </div>
                                            <div className="em-field">
                                                <label>Catatan penyelesaian</label>
                                                <textarea className="em-textarea" rows={2} value={milestoneForm.data.completion_notes} onChange={(e) => milestoneForm.setData('completion_notes', e.target.value)} />
                                            </div>
                                            <div className="em-form-actions" style={{ border: 0, padding: 0, margin: 0 }}>
                                                <button type="button" className="em-btn ghost sm" onClick={() => setMilestoneOpen(false)}>Batal</button>
                                                <button type="submit" className="em-btn primary sm" disabled={milestoneForm.processing}>
                                                    <Save size={14} /> {milestoneForm.processing ? 'Menyimpan…' : 'Simpan'}
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                    {record.milestones.length === 0 && (
                                        <p className="em-hint" style={{ margin: 0 }}>Belum ada milestone. Tambahkan tahapan untuk melacak progress.</p>
                                    )}
                                    <div className="em-timeline">
                                        {record.milestones.map((milestone) => (
                                            <div className="em-timeline-item" key={milestone.id}>
                                                <span className={`em-timeline-dot${milestone.progress >= 100 ? ' green' : ''}`} />
                                                <div style={{ display: 'grid', gap: 8 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', gap: 8, alignItems: 'flex-start' }}>
                                                        <div>
                                                            <b style={{ color: 'var(--em-heading)', fontSize: 13.5 }}>{milestone.title}</b>
                                                            <small className="em-hint" style={{ display: 'block' }}>
                                                                {milestone.dueLabel ? `Tenggat ${milestone.dueLabel} · ` : ''}{milestone.statusLabel}
                                                                {milestone.notes ? ` — ${milestone.notes}` : ''}
                                                            </small>
                                                        </div>
                                                        <span className="em-badge gray">{milestone.progress}%</span>
                                                    </div>
                                                    <div className="em-progress"><i style={{ width: `${milestone.progress}%` }} /></div>
                                                    {milestone.editable && editingMilestone !== milestone.id && (
                                                        <button type="button" className="em-btn ghost sm" style={{ justifySelf: 'start' }} onClick={() => {
                                                            setEditingMilestone(milestone.id);
                                                            progressForm.setData({
                                                                progress: { ...progressForm.data.progress, [milestone.id]: milestone.progress },
                                                                notes: { ...progressForm.data.notes, [milestone.id]: '' },
                                                            });
                                                        }}>
                                                            Perbarui progress
                                                        </button>
                                                    )}
                                                    {editingMilestone === milestone.id && (
                                                        <div style={{ display: 'grid', gap: 8 }}>
                                                            <div className="em-grid-2">
                                                                <div className="em-field">
                                                                    <label>Progress (%)</label>
                                                                    <input className="em-input" type="number" min={0} max={100}
                                                                        value={progressForm.data.progress[milestone.id] ?? milestone.progress}
                                                                        onChange={(e) => progressForm.setData('progress', { ...progressForm.data.progress, [milestone.id]: Number(e.target.value) })} />
                                                                </div>
                                                                <div className="em-field">
                                                                    <label>Catatan</label>
                                                                    <input className="em-input"
                                                                        value={progressForm.data.notes[milestone.id] ?? ''}
                                                                        onChange={(e) => progressForm.setData('notes', { ...progressForm.data.notes, [milestone.id]: e.target.value })}
                                                                        placeholder="Catatan penyelesaian" />
                                                                </div>
                                                            </div>
                                                            <div style={{ display: 'flex', gap: 8 }}>
                                                                <button type="button" className="em-btn primary sm" onClick={() => submitProgress(milestone.id)}>
                                                                    <Save size={14} /> Simpan
                                                                </button>
                                                                <button type="button" className="em-btn ghost sm" onClick={() => setEditingMilestone(null)}>Batal</button>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </section>

                            <section className="em-card">
                                <div className="em-card-head">
                                    <h2 className="em-card-title"><Award size={16} /> Luaran</h2>
                                    {record.canUpdateProgress && !outputOpen && (
                                        <button type="button" className="em-btn ghost sm" onClick={() => { setEditingOutput(null); outputForm.reset(); setOutputOpen(true); }}>
                                            <Plus size={13} /> Tambah
                                        </button>
                                    )}
                                </div>
                                <div className="em-card-body" style={{ display: 'grid', gap: 12 }}>
                                    {outputOpen && (
                                        <form onSubmit={submitOutput} style={{ display: 'grid', gap: 12, padding: 14, border: '1px solid var(--em-line)', borderRadius: 12, background: 'var(--em-soft)' }}>
                                            <div className="em-grid-2">
                                                <div className="em-field">
                                                    <label>Jenis luaran <i>*</i></label>
                                                    <input className="em-input" value={outputForm.data.output_type} onChange={(e) => outputForm.setData('output_type', e.target.value)} placeholder="article, book, patent…" />
                                                    <FieldError message={outputForm.errors.output_type} />
                                                </div>
                                                <div className="em-field">
                                                    <label>Status <i>*</i></label>
                                                    <select className="em-select" value={outputForm.data.status} onChange={(e) => outputForm.setData('status', e.target.value)}>
                                                        <option value="draft">Rencana</option>
                                                        <option value="submitted">Diajukan</option>
                                                        <option value="accepted">Diterima</option>
                                                        <option value="published">Final/Terbit</option>
                                                    </select>
                                                    <FieldError message={outputForm.errors.status} />
                                                </div>
                                            </div>
                                            <div className="em-field">
                                                <label>Judul <i>*</i></label>
                                                <input className="em-input" value={outputForm.data.title} onChange={(e) => outputForm.setData('title', e.target.value)} />
                                                <FieldError message={outputForm.errors.title} />
                                            </div>
                                            <div className="em-grid-2">
                                                <div className="em-field">
                                                    <label>Penerbit</label>
                                                    <input className="em-input" value={outputForm.data.publisher} onChange={(e) => outputForm.setData('publisher', e.target.value)} />
                                                </div>
                                                <div className="em-field">
                                                    <label>Indeksasi</label>
                                                    <input className="em-input" value={outputForm.data.indexing} onChange={(e) => outputForm.setData('indexing', e.target.value)} placeholder="Scopus, Sinta…" />
                                                </div>
                                                <div className="em-field">
                                                    <label>DOI</label>
                                                    <input className="em-input" value={outputForm.data.doi} onChange={(e) => outputForm.setData('doi', e.target.value)} />
                                                </div>
                                                <div className="em-field">
                                                    <label>Tanggal terbit</label>
                                                    <input className="em-input" type="date" value={outputForm.data.published_at} onChange={(e) => outputForm.setData('published_at', e.target.value)} />
                                                </div>
                                            </div>
                                            <div className="em-field">
                                                <label>Tautan</label>
                                                <input className="em-input" value={outputForm.data.url} onChange={(e) => outputForm.setData('url', e.target.value)} placeholder="https://" />
                                                <FieldError message={outputForm.errors.url} />
                                            </div>
                                            <div className="em-field">
                                                <label>Catatan</label>
                                                <textarea className="em-textarea" rows={2} value={outputForm.data.notes} onChange={(e) => outputForm.setData('notes', e.target.value)} />
                                            </div>
                                            <div className="em-field">
                                                <label>Dokumen</label>
                                                <label className="em-upload" style={{ display: 'flex' }} htmlFor="tri-output-doc">
                                                    <FileUp size={15} />
                                                    <span style={{ fontSize: 12.5, fontWeight: 700 }}>{outputForm.data.document ? outputForm.data.document.name : 'Pilih berkas…'}</span>
                                                    <input id="tri-output-doc" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xlsx,.csv,.zip" onChange={(e) => outputForm.setData('document', e.target.files?.[0] ?? null)} />
                                                </label>
                                                <FieldError message={outputForm.errors.document} />
                                            </div>
                                            <div className="em-form-actions" style={{ border: 0, padding: 0, margin: 0 }}>
                                                <button type="button" className="em-btn ghost sm" onClick={() => { setOutputOpen(false); setEditingOutput(null); }}>Batal</button>
                                                <button type="submit" className="em-btn primary sm" disabled={outputForm.processing}>
                                                    <Save size={14} /> {editingOutput ? 'Perbarui' : 'Tambah'} luaran
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                    {record.outputs.length === 0 && (
                                        <p className="em-hint" style={{ margin: 0 }}>Belum ada luaran yang dicatat.</p>
                                    )}
                                    {record.outputs.map((output) => (
                                        <div key={output.id} style={{ border: '1px solid var(--em-line)', borderRadius: 12, padding: '14px 16px', display: 'grid', gap: 8 }}>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', gap: 8, alignItems: 'flex-start' }}>
                                                <div>
                                                    <b style={{ color: 'var(--em-heading)', fontSize: 13.5 }}>{output.title}</b>
                                                    <small className="em-hint" style={{ display: 'block' }}>
                                                        {[output.publisher, output.indexing, output.publishedLabel].filter(Boolean).join(' · ') || output.type}
                                                    </small>
                                                </div>
                                                <span className={`em-badge ${statusTone(output.status)}`}>{output.statusLabel}</span>
                                            </div>
                                            {(output.doi || output.url) && (
                                                <small className="em-hint">
                                                    {output.doi ? `DOI: ${output.doi} ` : ''}
                                                    {output.url && <a href={output.url} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--em-brand)' }}><Link2 size={11} /> Buka tautan</a>}
                                                </small>
                                            )}
                                            {output.attachments.length > 0 && (
                                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                                    {output.attachments.map((file) => (
                                                        <a key={file.id} className="em-btn ghost sm" href={file.previewUrl} target="_blank" rel="noopener noreferrer">
                                                            <FileText size={13} /> {file.fileName.length > 24 ? `${file.fileName.slice(0, 22)}…` : file.fileName} <Eye size={13} />
                                                        </a>
                                                    ))}
                                                </div>
                                            )}
                                            {output.editable && (
                                                <button type="button" className="em-btn ghost sm" style={{ justifySelf: 'start' }} onClick={() => openOutputEdit(output)}>
                                                    Ubah luaran
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>

                        <div className="em-stack">
                            <section className="em-card">
                                <div className="em-card-head"><h3 className="em-card-title"><UsersRound size={16} /> Tim</h3><span className="em-badge gray">{record.members.length}</span></div>
                                <div className="em-card-body tight" style={{ display: 'grid', gap: 8 }}>
                                    {record.members.map((member) => (
                                        <div className="em-member" key={member.id}>
                                            <div style={{ minWidth: 0 }}>
                                                <div className="em-member-pills">
                                                    <span className="em-badge gray">{member.roleLabel}</span>
                                                    <span className={`em-badge ${member.isExternal ? 'amber' : 'green'}`}>{member.isExternal ? 'Eksternal' : 'Internal'}</span>
                                                </div>
                                                <b>{member.name}</b>
                                                <small>{member.detail}</small>
                                            </div>
                                            {member.removable && (
                                                <button type="button" className="em-icon-btn" aria-label="Hapus anggota" onClick={() => removeMember(member.id)}>
                                                    <Trash2 size={14} />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                    {record.canManageTeam && (
                                        <div style={{ display: 'grid', gap: 8, marginTop: 6 }}>
                                            <div className="em-field">
                                                <label>Cari user internal</label>
                                                <input className="em-input" value={memberSearch} onChange={(e) => setMemberSearch(e.target.value)} placeholder="Min. 2 huruf" />
                                                {memberSearch.trim().length >= 2 && (
                                                    <div className="em-search-list">
                                                        {searching && <small className="em-hint">Mencari…</small>}
                                                        {!searching && candidates.length === 0 && <small className="em-hint">Tidak ada user yang cocok.</small>}
                                                        {candidates.map((candidate) => (
                                                            <button type="button" key={candidate.id} className="em-search-item" onClick={() => addInternal(candidate)}>
                                                                <span><b>{candidate.name}</b><small>{candidate.email}</small></span>
                                                                <Plus size={14} />
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="em-field">
                                                <label>Anggota eksternal</label>
                                                <input className="em-input" value={external.member_name} onChange={(e) => setExternal({ ...external, member_name: e.target.value })} placeholder="Nama" />
                                                <div className="em-grid-2">
                                                    <input className="em-input" value={external.institution} onChange={(e) => setExternal({ ...external, institution: e.target.value })} placeholder="Institusi" />
                                                    <input className="em-input" value={external.email} onChange={(e) => setExternal({ ...external, email: e.target.value })} placeholder="Email" />
                                                </div>
                                                <button type="button" className="em-btn ghost sm" onClick={addExternal} disabled={!external.member_name.trim() || memberForm.processing}>
                                                    <Plus size={14} /> Tambah eksternal
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="em-card">
                                <div className="em-card-head"><h3 className="em-card-title"><Paperclip size={16} /> Dokumen</h3><span className="em-badge gray">{record.attachments.length}</span></div>
                                <div className="em-card-body tight" style={{ display: 'grid', gap: 8 }}>
                                    {record.attachments.map((file) => (
                                        <a key={file.id} className="em-search-item" style={{ textDecoration: 'none' }} href={file.previewUrl} target="_blank" rel="noopener noreferrer">
                                            <span><b>{file.fileName}</b><small>{file.documentType}</small></span>
                                            <Eye size={14} />
                                        </a>
                                    ))}
                                    {record.attachments.length === 0 && <p className="em-hint" style={{ margin: 0 }}>Belum ada dokumen pendukung.</p>}
                                    <form onSubmit={uploadDoc} style={{ display: 'grid', gap: 8, marginTop: 4 }}>
                                        <label className="em-upload" style={{ display: 'flex' }} htmlFor="tri-evidence">
                                            <FileUp size={15} />
                                            <span style={{ fontSize: 12.5, fontWeight: 700 }}>{docForm.data.document ? docForm.data.document.name : 'Unggah bukti…'}</span>
                                            <input id="tri-evidence" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xlsx" onChange={(e) => docForm.setData('document', e.target.files?.[0] ?? null)} />
                                        </label>
                                        <FieldError message={docForm.errors.document} />
                                        <button type="submit" className="em-btn ghost sm" disabled={!docForm.data.document || docForm.processing}>
                                            <FileUp size={14} /> {docForm.processing ? 'Mengunggah…' : 'Unggah dokumen'}
                                        </button>
                                    </form>
                                </div>
                            </section>

                            {record.approvalSteps.length > 0 && (
                                <section className="em-card">
                                    <div className="em-card-head"><h3 className="em-card-title"><Clock3 size={16} /> Alur approval</h3></div>
                                    <div className="em-card-body tight">
                                        <div className="em-timeline">
                                            {record.approvalSteps.map((step) => (
                                                <div className="em-timeline-item" key={step.id}>
                                                    <span className={`em-timeline-dot${['approved', 'completed'].includes(step.status) ? ' green' : step.status === 'rejected' ? ' red' : ''}`} />
                                                    <div>
                                                        <b style={{ color: 'var(--em-heading)', fontSize: 13 }}>{step.name}</b>
                                                        <small className="em-hint" style={{ display: 'block' }}>
                                                            {step.statusLabel}{step.actorName ? ` · ${step.actorName}` : ''}{step.actedLabel ? ` · ${step.actedLabel}` : ''}
                                                        </small>
                                                        {step.notes && <small className="em-hint" style={{ display: 'block' }}>“{step.notes}”</small>}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </section>
                            )}

                            <div className="em-note info"><Info size={15} /><span>Tim hanya bisa diubah saat status draft/ditolak. Progress dan luaran terbuka setelah proposal disetujui.</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
