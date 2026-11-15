// Form tambah/ubah unit kerja beserta anggota pegawai.
import { Head, useForm } from '@inertiajs/react';
import { Building2, Search, Trash2, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type SearchResult = {
    id: number;
    label: string;
    meta?: { email?: string | null; username?: string | null; role?: string | null };
};

type Member = {
    user_id: number;
    name: string;
    email: string | null;
    role: string;
    position: string;
    is_active: boolean;
};

type UnitPayload = {
    id?: number;
    name: string;
    code: string;
    description: string;
    is_active: boolean;
    members: Member[];
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    unit: UnitPayload;
    positions: Option[];
    urls: { index: string; submit: string; searchUsers: string };
};

export default function WorkUnitForm({ shell, mode, unit, positions, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: unit.name,
        code: unit.code,
        description: unit.description,
        is_active: unit.is_active,
        members: unit.members as Member[],
    });

    const [search, setSearch] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkPosition, setBulkPosition] = useState('member');
    const [bulkActive, setBulkActive] = useState(true);

    const excludeKey = form.data.members.map((member) => member.user_id).join(',');

    useEffect(() => {
        const keyword = search.trim();

        if (keyword === '') {
            setResults([]);
            setSearching(false);
            return;
        }

        const timer = setTimeout(async () => {
            setSearching(true);

            try {
                const params = new URLSearchParams({ q: keyword, exclude: excludeKey });
                const response = await fetch(`${urls.searchUsers}?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await response.json();
                setResults(Array.isArray(json.options) ? json.options : []);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 350);

        return () => clearTimeout(timer);
    }, [search, excludeKey, urls.searchUsers]);

    const toggleSelected = (id: number) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const addSelectedMembers = () => {
        const candidates = results.filter((result) => selectedIds.includes(result.id)
            && !form.data.members.some((member) => member.user_id === result.id));

        if (candidates.length === 0) {
            return;
        }

        form.setData('members', [
            ...form.data.members,
            ...candidates.map((candidate) => ({
                user_id: candidate.id,
                name: candidate.label,
                email: candidate.meta?.email ?? null,
                role: candidate.meta?.role ?? '',
                position: bulkPosition,
                is_active: bulkActive,
            })),
        ]);

        setSelectedIds([]);
        setSearch('');
        setResults([]);
    };

    const updateMember = (userId: number, patch: Partial<Member>) => {
        form.setData('members', form.data.members.map((member) => (
            member.user_id === userId ? { ...member, ...patch } : member
        )));
    };

    const removeMember = (userId: number) => {
        form.setData('members', form.data.members.filter((member) => member.user_id !== userId));
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Unit Kerja · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Building2}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Unit Kerja / Biro' : `Edit ${unit.name}`}
                        description="Buat struktur unit kerja atau lembaga operasional kampus untuk penugasan tiket, routing approval, dan manajemen pembatasan lingkup data SDM."
                        badges={isCreate ? ['Unit kerja baru'] : [unit.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Building2 size={16} /> Identitas Unit Kerja</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <TextField
                                            label="Nama unit kerja / biro"
                                            required
                                            placeholder="Contoh: Biro Administrasi Akademik dan Kemahasiswaan (BAAK)"
                                            value={form.data.name}
                                            onChange={(event) => form.setData('name', event.target.value)}
                                            error={form.errors.name}
                                        />
                                        <TextField
                                            label="Kode identitas unit (singkatan)"
                                            required
                                            placeholder="Contoh: BAAK / LPPM / UPT-TI"
                                            value={form.data.code}
                                            onChange={(event) => form.setData('code', event.target.value)}
                                            error={form.errors.code}
                                            hint="Kode unik (otomatis huruf kapital) untuk kode tiket dan routing sistem."
                                            style={{ textTransform: 'uppercase' }}
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Deskripsi & tugas pokok unit kerja"
                                                rows={3}
                                                placeholder="Tuliskan penjelasan singkat mengenai fungsi, tanggung jawab, atau cakupan layanan unit kerja ini…"
                                                value={form.data.description}
                                                onChange={(event) => form.setData('description', event.target.value)}
                                                error={form.errors.description}
                                            />
                                        </div>
                                        <SwitchField
                                            label="Status keaktifan unit kerja"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Unit nonaktif tidak muncul pada pilihan penugasan tiket dan penambahan staf baru."
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Users size={16} /> Anggota Unit Kerja</h2>
                                </div>
                                <div className="db-card-body">
                                    <p className="db-hint" style={{ marginTop: 0, marginBottom: 14 }}>
                                        Cari dan pilih satu atau beberapa pegawai sekaligus untuk ditugaskan ke dalam struktur unit kerja ini.
                                    </p>

                                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(220px, 2fr) minmax(170px, 1fr) auto auto', gap: 10, alignItems: 'end' }}>
                                        <div>
                                            <label className="crud-label" htmlFor="work-unit-member-search">Cari dosen / pegawai</label>
                                            <input
                                                id="work-unit-member-search"
                                                type="search"
                                                className="db-input"
                                                value={search}
                                                onChange={(event) => setSearch(event.target.value)}
                                                placeholder="Cari nama, email, username, atau NIK/NIDN…"
                                            />
                                        </div>
                                        <div>
                                            <label className="crud-label" htmlFor="work-unit-bulk-position">Posisi penugasan default</label>
                                            <select
                                                id="work-unit-bulk-position"
                                                className="db-input"
                                                value={bulkPosition}
                                                onChange={(event) => setBulkPosition(event.target.value)}
                                            >
                                                {positions.map((position) => (
                                                    <option key={position.id} value={position.id}>{position.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div style={{ paddingBottom: 8 }}>
                                            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 7, cursor: 'pointer' }}>
                                                <input
                                                    type="checkbox"
                                                    checked={bulkActive}
                                                    onChange={(event) => setBulkActive(event.target.checked)}
                                                    style={{ width: 16, height: 16, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                />
                                                <span className="db-hint" style={{ marginTop: 0 }}>Aktif</span>
                                            </label>
                                        </div>
                                        <button
                                            className="db-btn primary sm"
                                            type="button"
                                            onClick={addSelectedMembers}
                                            disabled={selectedIds.length === 0}
                                            style={{ marginBottom: 1 }}
                                        >
                                            <UserPlus size={14} /> Tambahkan
                                        </button>
                                    </div>

                                    {search.trim() !== '' && (
                                        <div
                                            style={{
                                                marginTop: 10,
                                                border: '1px solid var(--db-line)',
                                                borderRadius: 12,
                                                background: 'var(--db-card)',
                                                overflow: 'hidden',
                                            }}
                                        >
                                            {searching && <div className="db-hint" style={{ padding: '10px 14px', marginTop: 0 }}>Mencari pegawai…</div>}
                                            {!searching && results.length === 0 && (
                                                <div className="db-hint" style={{ padding: '10px 14px', marginTop: 0 }}>
                                                    Tidak ada pegawai yang cocok atau semua hasil sudah masuk ke unit ini.
                                                </div>
                                            )}
                                            {!searching && results.map((result) => {
                                                const alreadyMember = form.data.members.some((member) => member.user_id === result.id);

                                                return (
                                                    <label
                                                        key={result.id}
                                                        style={{
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            gap: 12,
                                                            padding: '10px 14px',
                                                            borderTop: '1px solid var(--db-line)',
                                                            cursor: alreadyMember ? 'not-allowed' : 'pointer',
                                                            opacity: alreadyMember ? 0.55 : 1,
                                                        }}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            disabled={alreadyMember}
                                                            checked={selectedIds.includes(result.id)}
                                                            onChange={() => toggleSelected(result.id)}
                                                            style={{ width: 16, height: 16, accentColor: 'var(--db-brand)' }}
                                                        />
                                                        <span style={{ flex: 1, minWidth: 0 }}>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{result.label}</b>
                                                            <small className="db-hint" style={{ marginTop: 0 }}>
                                                                {result.meta?.email ?? '-'}{result.meta?.username ? ` · ${result.meta.username}` : ''}
                                                            </small>
                                                        </span>
                                                        <span className="db-badge gray">{result.meta?.role || 'Staff'}</span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}

                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '20px 0 10px' }}>
                                        <Search size={14} style={{ color: 'var(--db-muted)' }} />
                                        <b style={{ color: 'var(--db-heading)', fontSize: 12.5 }}>
                                            Daftar anggota unit kerja saat ini ({form.data.members.length} pegawai)
                                        </b>
                                    </div>

                                    <div style={{ border: '1px solid var(--db-line)', borderRadius: 12, overflow: 'hidden', background: 'var(--db-card)' }}>
                                        {form.data.members.length === 0 && (
                                            <div className="db-hint" style={{ padding: '18px 14px', marginTop: 0, textAlign: 'center' }}>
                                                Belum ada anggota yang ditugaskan. Unit tetap dapat disimpan dan keanggotaan dapat diatur kapan saja.
                                            </div>
                                        )}
                                        {form.data.members.map((member) => (
                                            <div
                                                key={member.user_id}
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 12,
                                                    flexWrap: 'wrap',
                                                    padding: '11px 14px',
                                                    borderTop: '1px solid var(--db-line)',
                                                }}
                                            >
                                                <span
                                                    className="db-badge"
                                                    style={{ width: 34, height: 34, justifyContent: 'center', borderRadius: 999, fontSize: 13, padding: 0 }}
                                                >
                                                    {(member.name || 'U').slice(0, 1).toUpperCase()}
                                                </span>
                                                <span style={{ flex: '1 1 220px', minWidth: 0 }}>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{member.name}</b>
                                                    <small className="db-hint" style={{ marginTop: 0 }}>
                                                        {member.email ?? '-'}{member.role ? ` · ${member.role}` : ''}
                                                    </small>
                                                </span>
                                                <select
                                                    className="db-input"
                                                    style={{ width: 170 }}
                                                    value={member.position}
                                                    onChange={(event) => updateMember(member.user_id, { position: event.target.value })}
                                                    aria-label={`Posisi ${member.name}`}
                                                >
                                                    {positions.map((position) => (
                                                        <option key={position.id} value={position.id}>{position.label}</option>
                                                    ))}
                                                </select>
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 7, cursor: 'pointer' }}>
                                                    <input
                                                        type="checkbox"
                                                        checked={member.is_active}
                                                        onChange={(event) => updateMember(member.user_id, { is_active: event.target.checked })}
                                                        style={{ width: 16, height: 16, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className="db-hint" style={{ marginTop: 0 }}>{member.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                                </label>
                                                <button
                                                    className="db-btn danger sm"
                                                    type="button"
                                                    onClick={() => removeMember(member.user_id)}
                                                    title={`Hapus ${member.name} dari unit`}
                                                >
                                                    <Trash2 size={13} />
                                                </button>
                                            </div>
                                        ))}
                                    </div>

                                    {form.errors.members && <div className="db-error">{form.errors.members}</div>}
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Unit Kerja' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
