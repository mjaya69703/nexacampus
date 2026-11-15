// Ajukan tridharma — employee self-service (Inertia React).
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, FileUp, NotebookPen, Plus, Save, Send, Trash2, UserPlus, UsersRound, Wallet, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import '../../../../../css/employee.css';

type TeamMember = {
    kind: 'internal' | 'external';
    user_id: number | null;
    name: string;
    email: string;
    institution: string;
    role: string;
};

type Candidate = { id: number; name: string; email: string; code: string | null };

type Props = {
    shell: ShellProps;
    leader: { name: string; email: string };
    indexUrl: string;
    basePath: string;
    searchUsersUrl: string;
};

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <span className="em-error">{message}</span>;
}

export default function TridharmaCreate({ shell, leader, indexUrl, basePath, searchUsersUrl }: Props) {
    const form = useForm({
        type: 'research',
        title: '',
        scheme: '',
        abstract: '',
        starts_at: '',
        ends_at: '',
        funding_amount: '',
        funding_source: '',
        proposal: null as File | null,
        team: [] as TeamMember[],
        action: 'draft' as 'draft' | 'submit',
    });

    const [memberSearch, setMemberSearch] = useState('');
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [searching, setSearching] = useState(false);
    const [external, setExternal] = useState({ member_name: '', institution: '', email: '', role: 'member' });

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
    }, [memberSearch, searchUsersUrl, form.data.team]);

    const addInternal = (candidate: Candidate) => {
        if (form.data.team.some((member) => member.kind === 'internal' && member.user_id === candidate.id)) {
            setMemberSearch('');
            setCandidates([]);
            return;
        }
        form.setData('team', [...form.data.team, { kind: 'internal', user_id: candidate.id, name: candidate.name, email: candidate.email, institution: '', role: 'member' }]);
        setMemberSearch('');
        setCandidates([]);
    };

    const addExternal = () => {
        if (!external.member_name.trim()) return;
        form.setData('team', [...form.data.team, { kind: 'external', user_id: null, name: external.member_name.trim(), email: external.email.trim(), institution: external.institution.trim(), role: external.role }]);
        setExternal({ member_name: '', institution: '', email: '', role: 'member' });
    };

    const removeMember = (index: number) => {
        form.setData('team', form.data.team.filter((_, i) => i !== index));
    };

    const save = (action: 'draft' | 'submit') => {
        form.setData('action', action);
        form.post(basePath, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Tridharma · ${shell.appName}`} />
            <div className="em-root">
                <div className="em-stack">
                    <section className="em-hero">
                        <div className="em-hero-inner">
                            <div className="em-hero-copy">
                                <span className="em-hero-icon"><NotebookPen size={26} /></span>
                                <div>
                                    <small>Form pengajuan</small>
                                    <h1>Ajukan Tridharma</h1>
                                    <p>Simpan sebagai draft atau langsung kirim ke approval setelah proposal siap.</p>
                                    <div className="em-hero-pills">
                                        <span className="em-pill"><UserPlus size={12} /> Anda otomatis menjadi ketua</span>
                                        <span className="em-pill"><FileUp size={12} /> File disimpan privat</span>
                                    </div>
                                </div>
                            </div>
                            <div className="em-hero-actions">
                                <a className="em-btn light" href={indexUrl}><ArrowLeft size={15} /> Kembali</a>
                            </div>
                        </div>
                    </section>

                    <div className="em-layout" style={{ gridTemplateColumns: 'minmax(0, 1.75fr) minmax(0, 1fr)' }}>
                        <div className="em-stack">
                            <section className="em-card">
                                <div className="em-card-head"><h2 className="em-card-title"><NotebookPen size={16} /> Identitas kegiatan</h2></div>
                                <div className="em-card-body" style={{ display: 'grid', gap: 14 }}>
                                    <div className="em-grid-2">
                                        <div className="em-field">
                                            <label>Tipe <i>*</i></label>
                                            <select className="em-select" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                                <option value="research">Penelitian</option>
                                                <option value="community_service">Pengabdian</option>
                                                <option value="publication">Publikasi</option>
                                            </select>
                                            <FieldError message={form.errors.type} />
                                        </div>
                                        <div className="em-field">
                                            <label>Judul <i>*</i></label>
                                            <input className="em-input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Judul kegiatan atau luaran" />
                                            <FieldError message={form.errors.title} />
                                        </div>
                                    </div>
                                    <div className="em-field">
                                        <label>Abstrak / Deskripsi</label>
                                        <textarea className="em-textarea" rows={4} value={form.data.abstract} onChange={(e) => form.setData('abstract', e.target.value)} placeholder="Ringkasan tujuan, metode, mitra, atau rencana luaran" />
                                        <FieldError message={form.errors.abstract} />
                                    </div>
                                </div>
                            </section>

                            <section className="em-card">
                                <div className="em-card-head"><h2 className="em-card-title"><Wallet size={16} /> Skema dan pendanaan</h2></div>
                                <div className="em-card-body" style={{ display: 'grid', gap: 14 }}>
                                    <div className="em-grid-2">
                                        <div className="em-field">
                                            <label>Skema</label>
                                            <input className="em-input" value={form.data.scheme} onChange={(e) => form.setData('scheme', e.target.value)} placeholder="Hibah internal, mandiri, institusi" />
                                            <FieldError message={form.errors.scheme} />
                                        </div>
                                        <div className="em-field">
                                            <label>Sumber dana</label>
                                            <input className="em-input" value={form.data.funding_source} onChange={(e) => form.setData('funding_source', e.target.value)} placeholder="LPPM, fakultas, mitra, mandiri" />
                                            <FieldError message={form.errors.funding_source} />
                                        </div>
                                        <div className="em-field">
                                            <label>Mulai</label>
                                            <input className="em-input" type="date" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} />
                                            <FieldError message={form.errors.starts_at} />
                                        </div>
                                        <div className="em-field">
                                            <label>Selesai</label>
                                            <input className="em-input" type="date" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} />
                                            <FieldError message={form.errors.ends_at} />
                                        </div>
                                        <div className="em-field">
                                            <label>Nominal dana</label>
                                            <input className="em-input" type="number" min={0} value={form.data.funding_amount} onChange={(e) => form.setData('funding_amount', e.target.value)} placeholder="0" />
                                            <FieldError message={form.errors.funding_amount} />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="em-card">
                                <div className="em-card-head">
                                    <h2 className="em-card-title"><UsersRound size={16} /> Tim pengusul</h2>
                                    <span className="em-badge gray">{form.data.team.length + 1}</span>
                                </div>
                                <div className="em-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <div className="em-member">
                                        <div>
                                            <div className="em-member-pills">
                                                <span className="em-badge">Ketua</span>
                                                <span className="em-badge green">Internal</span>
                                            </div>
                                            <b>{leader.name} (Anda)</b>
                                            <small>{leader.email}</small>
                                        </div>
                                    </div>
                                    {form.data.team.map((member, index) => (
                                        <div className="em-member" key={`${member.kind}-${member.user_id ?? member.name}-${index}`}>
                                            <div style={{ minWidth: 0 }}>
                                                <div className="em-member-pills">
                                                    <span className="em-badge gray">{member.role.replace(/_/g, ' ')}</span>
                                                    <span className={`em-badge ${member.kind === 'internal' ? 'green' : 'amber'}`}>{member.kind === 'internal' ? 'Internal' : 'Eksternal'}</span>
                                                </div>
                                                <b>{member.name}</b>
                                                <small>{member.institution || member.email || '-'}</small>
                                            </div>
                                            <button type="button" className="em-icon-btn" aria-label="Hapus anggota" onClick={() => removeMember(index)}>
                                                <Trash2 size={14} />
                                            </button>
                                        </div>
                                    ))}

                                    <div className="em-grid-2" style={{ marginTop: 6 }}>
                                        <div className="em-field">
                                            <label>Cari user internal</label>
                                            <input className="em-input" value={memberSearch} onChange={(e) => setMemberSearch(e.target.value)} placeholder="Nama, email, username, min. 2 huruf" />
                                            {memberSearch.trim().length >= 2 && (
                                                <div className="em-search-list">
                                                    {searching && <small className="em-hint">Mencari…</small>}
                                                    {!searching && candidates.length === 0 && <small className="em-hint">Tidak ada user yang cocok.</small>}
                                                    {candidates.map((candidate) => (
                                                        <button type="button" key={candidate.id} className="em-search-item" onClick={() => addInternal(candidate)}>
                                                            <span><b>{candidate.name}</b><small>{candidate.email}{candidate.code ? ` · ${candidate.code}` : ''}</small></span>
                                                            <Plus size={14} />
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                        <div className="em-field">
                                            <label>Anggota eksternal</label>
                                            <input className="em-input" value={external.member_name} onChange={(e) => setExternal({ ...external, member_name: e.target.value })} placeholder="Nama eksternal" />
                                            <input className="em-input" value={external.institution} onChange={(e) => setExternal({ ...external, institution: e.target.value })} placeholder="Institusi" />
                                            <input className="em-input" type="email" value={external.email} onChange={(e) => setExternal({ ...external, email: e.target.value })} placeholder="Email" />
                                            <select className="em-select" value={external.role} onChange={(e) => setExternal({ ...external, role: e.target.value })}>
                                                <option value="member">Anggota</option>
                                                <option value="partner">Mitra</option>
                                                <option value="student_collaborator">Kolaborator Mahasiswa</option>
                                            </select>
                                            <button type="button" className="em-btn ghost sm" onClick={addExternal} disabled={!external.member_name.trim()}>
                                                <Plus size={14} /> Tambah eksternal
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div className="em-stack">
                            <section className="em-card">
                                <div className="em-card-head"><h2 className="em-card-title"><FileUp size={16} /> Proposal awal</h2></div>
                                <div className="em-card-body" style={{ display: 'grid', gap: 12 }}>
                                    <p className="em-hint" style={{ margin: 0 }}>PDF, dokumen, gambar, atau spreadsheet pendukung (maks. 8 MB).</p>
                                    <label className="em-upload" style={{ display: 'flex' }} htmlFor="tri-proposal">
                                        <FileUp size={15} />
                                        <span style={{ fontSize: 12.5, fontWeight: 700 }}>{form.data.proposal ? form.data.proposal.name : 'Pilih berkas…'}</span>
                                        <input id="tri-proposal" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xlsx" onChange={(e) => form.setData('proposal', e.target.files?.[0] ?? null)} />
                                    </label>
                                    <FieldError message={form.errors.proposal} />
                                    <button type="button" className="em-btn primary" disabled={form.processing} onClick={() => save('submit')}>
                                        <Send size={15} /> {form.processing ? 'Mengirim…' : 'Submit approval'}
                                    </button>
                                    <button type="button" className="em-btn ghost" disabled={form.processing} onClick={() => save('draft')}>
                                        <Save size={15} /> Simpan draft
                                    </button>
                                    <a className="em-btn ghost" href={indexUrl}><X size={15} /> Batal</a>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
