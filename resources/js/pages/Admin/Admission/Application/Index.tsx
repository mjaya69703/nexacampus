// Daftar pendaftar PMB — filter berjenjang, export, dan konversi massal.
import { Head, router } from '@inertiajs/react';
import { FileSignature, GraduationCap, Trash2, UserCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: number | string; label: string };

type Row = {
    id: number; no: number; applicationNumber: string; fullName: string; email: string;
    phone: string; period: string | null; faculty: string | null; studyProgram: string | null;
    classType: string | null; classTypeLabel: string; status: string; statusLabel: string;
    documentsCount: number; scoresCount: number; examCount: number;
    finalScore: string | number | null; convertedAt: string | null; submittedAt: string | null;
    createdAt: string | null; showUrl: string; deleteUrl: string;
    restoreUrl: string; forceUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; update: boolean; delete: boolean; restore: boolean; convert: boolean };
    stats: { total: number; underReview: number; accepted: number; converted: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; admission_period_id: string; faculty_id: string; study_program_id: string;
        class_type: string; status: string; sort: string; direction: 'asc' | 'desc';
        mode: 'all' | 'trash'; perPage: number;
    };
    options: {
        periods: Option[]; faculties: Option[]; studyPrograms: Option[];
        statuses: Option[]; classTypes: Option[];
    };
    urls: {
        index: string; export: string; bulkDestroy: string; bulkRestore: string;
        bulkForceDestroy: string; bulkConvert: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' | 'bulk-convert' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

function statusBadge(status: string) {
    switch (status) {
        case 'accepted':
            return 'green';
        case 'rejected':
            return 'red';
        case 'waitlisted':
            return 'amber';
        case 'under_review':
            return '';
        default:
            return 'gray';
    }
}

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus aplikasi pendaftaran?',
        message: (n) => `Aplikasi ${n} dipindah ke sampah. Data pendaftar & dokumen tetap tersimpan.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan aplikasi?',
        message: (n) => `Aplikasi ${n} kembali tampil di daftar pendaftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `Aplikasi ${n} dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus aplikasi terpilih?',
        message: (n) => `${n} aplikasi dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan aplikasi terpilih?',
        message: (n) => `${n} aplikasi kembali tampil di daftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen aplikasi terpilih?',
        message: (n) => `${n} aplikasi dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-convert': {
        title: () => 'Konversi pendaftar terpilih?',
        message: (n) => `${n} pendaftar akan dibuatkan akun mahasiswa dan NIM. Hanya pendaftar berstatus accepted yang diproses.`,
        confirm: 'Ya, konversi',
        danger: false,
    },
};

export default function AdmissionApplicationIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
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

    const activeFilterCount = [
        filters.admission_period_id, filters.faculty_id, filters.study_program_id,
        filters.class_type, filters.status,
    ].filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ admission_period_id: '', faculty_id: '', study_program_id: '', class_type: '', status: '', page: 1 }, true);
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
            case 'bulk-convert':
                router.post(urls.bulkConvert, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const copy = pending ? MODAL_COPY[pending.kind] : null;
    const copyName = pending && 'row' in pending ? pending.row.applicationNumber : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Pendaftar PMB · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileSignature}
                        eyebrow="Admission"
                        title="Pendaftar PMB"
                        description="Pantau berkas pendaftar, verifikasi dokumen, nilai seleksi, dan konversi calon mahasiswa menjadi mahasiswa aktif."
                    />

                    <section className="db-stats" aria-label="Statistik pendaftar">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total pendaftar</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><FileSignature size={20} /></span>
                            <div><span className="db-stat-num">{stats.underReview}</span><span className="db-stat-label">Dalam review</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><UserCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.accepted}</span><span className="db-stat-label">Diterima</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.converted}</span><span className="db-stat-label">Dikonversi</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pendaftar</h2>
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
                                        key: 'full_name', label: 'Pendaftar', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.fullName}</b>
                                                <small className="db-hint">{row.applicationNumber} • {row.email}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'phone', label: 'No. HP', sortable: true,
                                        render: (row) => row.phone,
                                    },
                                    { key: 'period', label: 'Periode', render: (row) => row.period ?? '-' },
                                    {
                                        key: 'study_program', label: 'Program Studi', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.studyProgram ?? '-'}</b>
                                                <small className="db-hint">{row.faculty ?? '-'} • {row.classTypeLabel}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status', label: 'Status', sortable: true,
                                        render: (row) => <span className={`db-badge ${statusBadge(row.status)}`}>{row.statusLabel}</span>,
                                    },
                                    {
                                        key: 'final_score', label: 'Nilai', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{row.finalScore ?? '-'}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>
                                                    {row.documentsCount} dokumen • {row.scoresCount} nilai
                                                </small>
                                            </span>
                                        ),
                                    },
                                    { key: 'submitted_at', label: 'Tanggal Daftar', sortable: true, render: (row) => row.submittedAt ?? '-' },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama, no. pendaftaran, email, HP…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.admission_period_id}
                                            onChange={(e) => visit({ admission_period_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter periode"
                                        >
                                            <option value="">Semua periode</option>
                                            {options.periods.map((period) => <option key={period.id} value={period.id}>{period.label}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.faculty_id}
                                            onChange={(e) => visit({ faculty_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter fakultas"
                                        >
                                            <option value="">Semua fakultas</option>
                                            {options.faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.label}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.study_program_id}
                                            onChange={(e) => visit({ study_program_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter program studi"
                                        >
                                            <option value="">Semua program studi</option>
                                            {options.studyPrograms.map((program) => <option key={program.id} value={program.id}>{program.label}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.class_type}
                                            onChange={(e) => visit({ class_type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter kelas"
                                        >
                                            <option value="">Semua kelas</option>
                                            {options.classTypes.map((type) => <option key={type.id} value={type.id}>{type.label}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(e) => visit({ status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {options.statuses.map((status) => <option key={status.id} value={status.id}>{status.label}</option>)}
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
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Detail & review">Detail</a>
                                )}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions={can.view || can.restore || can.delete}
                                canCreate={false}
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'Semua tersaring (.pdf)', href: `${exportHref}&format=pdf` },
                                ]}
                                extraActions={!isTrash && can.convert && selected.length > 0 ? (
                                    <button className="db-btn primary sm" type="button" onClick={() => setPending({ kind: 'bulk-convert' })}>
                                        <GraduationCap size={13} /> Konversi terpilih
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada pendaftar yang cocok dengan filter.'}
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
            </div>
        </AdminShell>
    );
}
