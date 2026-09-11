// Daftar registrasi mahasiswa — kit CRUD + approval inline.
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCheck, ClipboardCheck, Eye, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    year: string | null; semester: number | null;
    registrationStatus: string; registrationTone: string; academicStatus: string | null;
    isActive: boolean; createdAt: string | null;
    showUrl: string; editUrl: string | null; deleteUrl: string;
    restoreUrl: string; forceUrl: string; toggleUrl: string; approveUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean; restore: boolean; toggle: boolean; approve: boolean };
    stats: { total: number; active: number; approved: number; pending: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; nim: string; year: string; program: string; semester: string;
        registration_status: string; academic_status: string; is_active: string;
        sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number;
    };
    yearOptions: { id: number; name: string }[];
    programOptions: { id: number; name: string }[];
    registrationStatuses: string[];
    academicStatuses: string[];
    urls: {
        index: string; create: string; export: string; exportPdf: string; importTemplate: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function StudentRegistrationIndex({ shell, can, stats, data, filters, yearOptions, programOptions, registrationStatuses, academicStatuses, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [approving, setApproving] = useState<Row | null>(null);
    const isTrash = filters.mode === 'trash';

    const approveForm = useForm({ status: 'Approved', notes: '' });

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const visitNim = () => visit({ nim, page: 1 }, true);

    const activeFilterCount = [nim, filters.year, filters.program, filters.semester, filters.registration_status, filters.academic_status, filters.is_active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNim('');
        visit({ nim: '', year: '', program: '', semester: '', registration_status: '', academic_status: '', is_active: '', page: 1 }, true);
    };

    const toggleSort = (key: string) => {
        visit({
            sort: key,
            direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
            page: 1,
        });
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const toggleActive = (row: Row) => {
        router.post(row.toggleUrl, { is_active: !row.isActive }, { preserveScroll: true });
    };

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
            setSelected([]);
        };

        switch (pending.kind) {
            case 'delete':
                router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'restore':
                router.post(pending.row.restoreUrl, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'force':
                router.delete(pending.row.forceUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-delete':
                router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-restore':
                router.post(urls.bulkRestore, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-force':
                router.post(urls.bulkForceDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
        }
    };

    const openApprove = (row: Row, status: 'Approved' | 'Rejected') => {
        approveForm.setData({ status, notes: '' });
        approveForm.clearErrors();
        setApproving(row);
    };

    const confirmApprove = () => {
        if (!approving) return;
        approveForm.post(approving.approveUrl, {
            preserveScroll: true,
            onSuccess: () => setApproving(null),
        });
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const modal = (() => {
        if (!pending) return { title: '', message: '', confirm: 'Ya', danger: true };
        if (pending.kind === 'restore') {
            return { title: 'Pulihkan registrasi?', message: `Registrasi ${pending.row.student} kembali aktif.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'bulk-restore') {
            return { title: 'Pulihkan terpilih?', message: `${selected.length} registrasi kembali aktif.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'force') {
            return { title: 'Hapus permanen?', message: `Registrasi ${pending.row.student} dihapus selamanya.`, confirm: 'Ya, hapus permanen', danger: true };
        }
        if (pending.kind === 'bulk-force') {
            return { title: 'Hapus permanen terpilih?', message: `${selected.length} registrasi dihapus selamanya.`, confirm: 'Ya, hapus permanen', danger: true };
        }
        if (pending.kind === 'bulk-delete') {
            return { title: 'Hapus terpilih?', message: `${selected.length} registrasi dipindah ke sampah.`, confirm: 'Ya, hapus', danger: true };
        }
        return { title: 'Hapus registrasi?', message: `Registrasi ${pending.row.student} dipindah ke sampah.`, confirm: 'Ya, hapus', danger: true };
    })();

    const constApprovable = (row: Row) => !isTrash && can.approve && (row.registrationStatus === 'Draft' || row.registrationStatus === 'Submitted');

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Registrasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardCheck}
                        eyebrow="Akademik"
                        title="Registrasi Mahasiswa"
                        description="Daftar ulang per tahun. Approval mendorong semester ke profil dan membuka jalan KRS."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Registrasi</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik registrasi">
                        <div className="db-stat">
                            <span className="db-stat-icon"><ClipboardCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.approved}</span><span className="db-stat-label">Disetujui</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><ClipboardCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.pending}</span><span className="db-stat-label">Draft + Diajukan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Registrasi</h2>
                            <div className="crud-tabs" role="tablist" aria-label="Mode data">
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={!isTrash}
                                    className={`crud-tab${!isTrash ? ' active' : ''}`}
                                    onClick={() => visit({ mode: 'all', page: 1 }, true)}
                                >
                                    Semua <span className="db-badge gray">{stats.total}</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={isTrash}
                                    className={`crud-tab${isTrash ? ' active' : ''}`}
                                    onClick={() => visit({ mode: 'trash', page: 1 }, true)}
                                >
                                    <Trash2 size={13} /> Sampah <span className="db-badge gray">{stats.trashed}</span>
                                </button>
                            </div>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'student', label: 'Mahasiswa', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim ?? '-'} · {row.program ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'year', label: 'Tahun', render: (row) => `${row.year ?? '-'}${row.semester ? ` · Smt ${row.semester}` : ''}` },
                                    {
                                        key: 'registration_status', label: 'Registrasi',
                                        render: (row) => <span className={`db-badge ${row.registrationTone}`}>{row.registrationStatus}</span>,
                                    },
                                    { key: 'academic_status', label: 'Akademik', render: (row) => row.academicStatus ?? '-' },
                                    {
                                        key: 'is_active', label: 'Aktif',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status">
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isActive}
                                                        onChange={() => toggleActive(row)}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Ya' : 'Tidak'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Ya' : 'Tidak'}</span>
                                            )
                                        ),
                                    },
                                    { key: 'created_at', label: 'Dibuat', sortable: true, render: (row) => row.createdAt ?? '-' },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama, email…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <input
                                            className="db-input"
                                            value={nim}
                                            placeholder="NIM…"
                                            onChange={(e) => setNim(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visitNim(); } }}
                                            aria-label="Filter NIM"
                                        />
                                        <select
                                            className="db-input"
                                            value={filters.year}
                                            onChange={(e) => visit({ year: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tahun"
                                        >
                                            <option value="">Semua tahun</option>
                                            {yearOptions.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.program}
                                            onChange={(e) => visit({ program: e.target.value, page: 1 }, true)}
                                            aria-label="Filter prodi"
                                        >
                                            <option value="">Semua prodi</option>
                                            {programOptions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.registration_status}
                                            onChange={(e) => visit({ registration_status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status registrasi"
                                        >
                                            <option value="">Semua status</option>
                                            {registrationStatuses.map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: isTrash ? 'bulk-force' : 'bulk-delete' })}
                                bulkLabel={isTrash ? 'Hapus permanen terpilih' : 'Hapus terpilih'}
                                canRestore={isTrash && can.restore}
                                onBulkRestore={isTrash ? () => setPending({ kind: 'bulk-restore' }) : undefined}
                                canUpdate={false}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                        {!isTrash && can.update && row.editUrl && (
                                            <a className="db-btn ghost sm" href={row.editUrl} title="Ubah">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                        {constApprovable(row) && (
                                            <>
                                                <button className="db-btn primary sm" type="button" onClick={() => openApprove(row, 'Approved')} title="Setujui">
                                                    <CheckCheck size={13} />
                                                </button>
                                                <button className="db-btn danger sm" type="button" onClick={() => openApprove(row, 'Rejected')} title="Tolak">
                                                    <XCircle size={13} />
                                                </button>
                                            </>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Tambah Registrasi"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada registrasi yang cocok dengan filter.'}
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={modal.title}
                    message={modal.message}
                    confirmLabel={modal.confirm}
                    danger={modal.danger}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />

                {approving && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setApproving(null)} role="dialog" aria-modal="true" aria-label="Approval registrasi">
                            <div className="db-modal" style={{ width: 'min(28rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>{approveForm.data.status === 'Approved' ? 'Setujui' : 'Tolak'} registrasi {approving.student}?</h3>
                                <p>Menyetujui mendorong semester ke profil mahasiswa.</p>
                                <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                                    <TextareaField
                                        label="Catatan (opsional)"
                                        value={approveForm.data.notes}
                                        onChange={(e) => approveForm.setData('notes', e.target.value)}
                                        error={(approveForm.errors as Record<string, string>).notes}
                                    />
                                </div>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setApproving(null)}>Batal</button>
                                    <button
                                        className={`db-btn ${approveForm.data.status === 'Approved' ? 'primary' : 'danger'}`}
                                        type="button"
                                        onClick={confirmApprove}
                                        disabled={approveForm.processing}
                                    >
                                        {approveForm.processing ? 'Memproses…' : approveForm.data.status === 'Approved' ? 'Setujui' : 'Tolak'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminShell>
    );
}
