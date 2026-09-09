// Daftar mata kuliah — memakai kit CRUD shared (pola fakultas/prodi).
import { Head, router } from '@inertiajs/react';
import { BookOpen, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; code: string; name: string; credits: number;
    semester: number | null;
    scopeType: string | null; scopeLabel: string; scopeName: string | null;
    requirement: string | null; category: string | null; prerequisites: string[];
    isActive: boolean; createdAt: string | null;
    editUrl: string | null; deleteUrl: string;
    restoreUrl: string; forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean };
    stats: { total: number; active: number; credits: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; scope_type: string; scope_faculty: string; scope_program: string;
        semester: string; requirement: string; category: string; is_active: string;
        sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number;
    };
    faculties: { id: number; name: string }[];
    programs: { id: number; name: string }[];
    maxSemester: number;
    importResult: ImportResult;
    urls: {
        index: string; create: string; export: string; exportPdf: string; importTemplate: string; importSubmit: string;
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

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus MK?',
        message: (n) => `"${n}" dipindah ke sampah. Scope dan prasyarat ikut tersimpan dan pulih bersama.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan MK?',
        message: (n) => `"${n}" kembali aktif beserta scope dan prasyaratnya.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya dan tidak bisa dipulihkan.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus MK terpilih?',
        message: (n) => `${n} MK dipindah ke sampah. Yang terikat kurikulum/prasyarat akan dilewati.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan MK terpilih?',
        message: (n) => `${n} MK kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen MK terpilih?',
        message: (n) => `${n} MK dihapus selamanya. Yang terikat kurikulum/prasyarat akan dilewati.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function CourseIndex({ shell, can, stats, data, filters, faculties, programs, maxSemester, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const isTrash = filters.mode === 'trash';

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
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

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const submitImport = (file: File) => {
        setProcessing(true);
        router.post(urls.importSubmit, { file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setImportOpen(false);
            },
        });
    };

    const copy = pending ? MODAL_COPY[pending.kind] : null;
    const copyName = pending && 'row' in pending ? `${pending.row.code} — ${pending.row.name}` : String(selected.length);

    // Opsi 1–8 selalu ada; 9–14 hanya muncul bila datanya ada.
    const semesterOptions = [1, 2, 3, 4, 5, 6, 7, 8];
    for (let s = 9; s <= Math.min(Math.max(maxSemester, 0), 14); s++) {
        semesterOptions.push(s);
    }

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Mata Kuliah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik"
                        title="Mata Kuliah"
                        description="MK hidup dalam scope global, fakultas, atau prodi — plus prasyarat antar MK yang ikut pulih saat restore."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah MK</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} MK berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik MK">
                        <div className="db-stat">
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total MK</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.credits}</span><span className="db-stat-label">Total SKS</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Mata Kuliah</h2>
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
                                        key: 'code', label: 'Mata kuliah', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}><span className="crud-name">{row.code}</span> · {row.name}</b>
                                                <small className="db-hint">{row.credits} SKS · {row.requirement ?? '-'} · {row.category ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'semester_recommendation', label: 'Smt', align: 'right',
                                        render: (row) => row.semester ?? <span className="db-hint">–</span>,
                                    },
                                    {
                                        key: 'scope', label: 'Scope',
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${row.scopeType === 'global' ? 'gray' : ''}`.trim()}>{row.scopeLabel}</span>
                                                {row.scopeName && <small className="db-hint" style={{ display: 'block', marginTop: 4 }}>{row.scopeName}</small>}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'prerequisites', label: 'Prasyarat',
                                        render: (row) => (
                                            row.prerequisites.length > 0
                                                ? <span style={{ display: 'inline-flex', flexWrap: 'wrap', gap: 5 }}>{row.prerequisites.map((code) => <span className="db-badge gray" key={code}>{code}</span>)}</span>
                                                : <span className="db-hint">–</span>
                                        ),
                                    },
                                    {
                                        key: 'is_active', label: 'Status',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status">
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isActive}
                                                        onChange={() => toggleActive(row)}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
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
                                searchPlaceholder="Cari kode, nama, singkatan…"
                                filterBar={(
                                    <>
                                        <select
                                            className="db-input"
                                            value={filters.scope_type}
                                            onChange={(e) => visit({ scope_type: e.target.value, scope_faculty: '', scope_program: '', page: 1 }, true)}
                                            aria-label="Filter scope"
                                        >
                                            <option value="">Semua scope</option>
                                            <option value="global">Global</option>
                                            <option value="faculty">Fakultas</option>
                                            <option value="study_program">Prodi</option>
                                        </select>
                                        {filters.scope_type === 'faculty' && (
                                            <select
                                                className="db-input"
                                                value={filters.scope_faculty}
                                                onChange={(e) => visit({ scope_faculty: e.target.value, page: 1 }, true)}
                                                aria-label="Filter fakultas scope"
                                            >
                                                <option value="">Semua fakultas</option>
                                                {faculties.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                                            </select>
                                        )}
                                        {filters.scope_type === 'study_program' && (
                                            <select
                                                className="db-input"
                                                value={filters.scope_program}
                                                onChange={(e) => visit({ scope_program: e.target.value, page: 1 }, true)}
                                                aria-label="Filter prodi scope"
                                            >
                                                <option value="">Semua prodi</option>
                                                {programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                            </select>
                                        )}
                                        <select
                                            className="db-input"
                                            value={filters.semester}
                                            onChange={(e) => visit({ semester: e.target.value, page: 1 }, true)}
                                            aria-label="Filter semester"
                                        >
                                            <option value="">Smt: semua</option>
                                            {semesterOptions.map((s) => <option key={s} value={s}>Smt {s}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(e) => visit({ is_active: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: isTrash ? 'bulk-force' : 'bulk-delete' })}
                                bulkLabel={isTrash ? 'Hapus permanen terpilih' : 'Hapus terpilih'}
                                canRestore={isTrash && can.restore}
                                onBulkRestore={isTrash ? () => setPending({ kind: 'bulk-restore' }) : undefined}
                                canUpdate={!isTrash && can.update}
                                editUrl={(row) => row.editUrl ?? urls.index}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions={(!isTrash && can.update) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tambah MK"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                extraActions={!isTrash && can.create ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                        <Upload size={14} /> Import
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada MK yang cocok dengan filter.'}
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy ? copy.title(copyName) : ''}
                    message={copy ? copy.message(copyName) : ''}
                    confirmLabel={copy?.confirm ?? 'Ya'}
                    danger={copy?.danger ?? true}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />

                <ImportModal
                    open={importOpen}
                    title="Impor Mata Kuliah"
                    description="Scope: global, F:KODE fakultas, atau P:KODE prodi. Prasyarat ditulis by kode MK yang sudah terdaftar. Satu baris gagal berarti file ditolak."
                    templateUrl={urls.importTemplate}
                    templateLabel="Unduh template"
                    processing={processing}
                    onSubmit={submitImport}
                    onClose={() => setImportOpen(false)}
                />
            </div>
        </AdminShell>
    );
}
