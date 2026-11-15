// Daftar profil alumni — kit CRUD shared (Inertia React).
import { Head, router } from '@inertiajs/react';
import { Briefcase, Eye, GraduationCap, Plus, Repeat, Store, Trash2, UserCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    nim: string;
    fullName: string;
    email: string;
    phone: string | null;
    photoUrl: string | null;
    studyProgram: string;
    faculty: string;
    graduationYear: number;
    graduationDate: string | null;
    gpa: number | null;
    employmentStatus: string;
    employmentStatusLabel: string;
    employerName: string | null;
    jobTitle: string | null;
    currentCity: string | null;
    completeness: number;
    isActive: boolean;
    createdAt: string | null;
    showUrl: string | null;
    editUrl: string | null;
    deleteUrl: string;
    restoreUrl: string;
    forceUrl: string;
    toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        restore: boolean;
        toggle: boolean;
        view: boolean;
        convert: boolean;
    };
    stats: { total: number; active: number; working: number; entrepreneur: number; trashed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        faculty_id: string;
        study_program_id: string;
        graduation_year: string;
        employment_status: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        mode: 'all' | 'trash';
        perPage: number;
    };
    facultyOptions: { id: number; name: string; code: string }[];
    studyProgramOptions: { id: number; name: string; facultyId: number }[];
    yearOptions: number[];
    employmentStatusOptions: Record<string, string>;
    batchOptions: { id: number; label: string; count: number }[];
    urls: {
        index: string;
        create: string;
        export: string;
        bulkDestroy: string;
        bulkRestore: string;
        bulkForceDestroy: string;
        batchConvert: string;
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

function initials(name: string) {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus profil alumni?',
        message: (n) => `"${n}" dipindah ke sampah dan tidak tampil di direktori alumni.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan profil alumni?',
        message: (n) => `"${n}" kembali tampil di direktori alumni.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya beserta foto profilnya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus profil terpilih?',
        message: (n) => `${n} profil alumni dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan profil terpilih?',
        message: (n) => `${n} profil alumni kembali tampil di direktori.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen profil terpilih?',
        message: (n) => `${n} profil alumni dihapus selamanya beserta fotonya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function AlumniProfileIndex({
    shell,
    can,
    stats,
    data,
    filters,
    facultyOptions,
    studyProgramOptions,
    yearOptions,
    employmentStatusOptions,
    batchOptions,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [convertOpen, setConvertOpen] = useState(false);
    const [convertProcessing, setConvertProcessing] = useState(false);
    const [batchId, setBatchId] = useState<string>(batchOptions[0] ? String(batchOptions[0].id) : '');
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
        filters.faculty_id,
        filters.study_program_id,
        filters.graduation_year,
        filters.employment_status,
        filters.is_active,
    ].filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({
            faculty_id: '',
            study_program_id: '',
            graduation_year: '',
            employment_status: '',
            is_active: '',
            page: 1,
        }, true);
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

    const runConvert = () => {
        if (!batchId) return;
        setConvertProcessing(true);
        router.post(urls.batchConvert, { graduation_batch_id: Number(batchId) }, {
            preserveScroll: true,
            onSuccess: () => setConvertOpen(false),
            onFinish: () => setConvertProcessing(false),
        });
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const copy = pending ? MODAL_COPY[pending.kind] : null;
    const copyName = pending && 'row' in pending ? pending.row.fullName : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Data Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Alumni"
                        title="Direktori Profil Alumni"
                        description="Kelola data lulusan, status karir, domisili, dan kelengkapan profil untuk penelusuran alumni kampus."
                        badges={[`${stats.total} alumni`, `${stats.active} aktif`]}
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Alumni</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik alumni">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total alumni</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><UserCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Profil aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{stats.working}</span><span className="db-stat-label">Bekerja</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Store size={20} /></span>
                            <div><span className="db-stat-num">{stats.entrepreneur}</span><span className="db-stat-label">Wirausaha</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Profil Alumni</h2>
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
                                        key: 'full_name', label: 'Alumni', sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                {row.photoUrl ? (
                                                    <img
                                                        src={row.photoUrl}
                                                        alt={row.fullName}
                                                        style={{ width: 34, height: 34, borderRadius: '50%', objectFit: 'cover', flex: '0 0 auto' }}
                                                    />
                                                ) : (
                                                    <span
                                                        aria-hidden
                                                        style={{
                                                            width: 34, height: 34, borderRadius: '50%', flex: '0 0 auto',
                                                            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                                            background: 'var(--db-brand-soft)', color: 'var(--db-brand)',
                                                            fontSize: 12, fontWeight: 800,
                                                        }}
                                                    >
                                                        {initials(row.fullName)}
                                                    </span>
                                                )}
                                                <span>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.fullName}</b>
                                                    <small className="db-hint">{row.nim} · {row.email}</small>
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'study_program', label: 'Program Studi', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.studyProgram}</b>
                                                <small className="db-hint">{row.faculty}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'graduation_year', label: 'Lulus / IPK', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.graduationYear}</b>
                                                <small className="db-hint">IPK {row.gpa !== null ? row.gpa.toFixed(2) : '-'} · {row.graduationDate ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'employment_status', label: 'Karir', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${row.employmentStatus === 'working' ? 'green' : row.employmentStatus === 'entrepreneur' ? 'amber' : 'gray'}`}>
                                                    {row.employmentStatusLabel}
                                                </span>
                                                {row.employerName && <small className="db-hint" style={{ display: 'block', marginTop: 4 }}>{row.jobTitle ? `${row.jobTitle} · ` : ''}{row.employerName}</small>}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'current_city', label: 'Domisili', sortable: true,
                                        render: (row) => row.currentCity ?? '-',
                                    },
                                    {
                                        key: 'completeness', label: 'Kelengkapan',
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${row.completeness >= 80 ? 'green' : row.completeness >= 50 ? 'amber' : 'red'}`}>{row.completeness}%</span>
                                                <span
                                                    aria-hidden
                                                    style={{ display: 'block', width: 76, height: 5, borderRadius: 99, background: 'var(--db-soft)', marginTop: 6 }}
                                                >
                                                    <span style={{ display: 'block', width: `${row.completeness}%`, height: '100%', borderRadius: 99, background: 'var(--db-brand)' }} />
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_active', label: 'Aktif',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status aktif">
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
                                searchPlaceholder="Cari nama, NIM, email, perusahaan…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.faculty_id}
                                            onChange={(e) => visit({ faculty_id: e.target.value, study_program_id: '', page: 1 }, true)}
                                            aria-label="Filter fakultas"
                                        >
                                            <option value="">Semua fakultas</option>
                                            {facultyOptions.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.study_program_id}
                                            onChange={(e) => visit({ study_program_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter program studi"
                                        >
                                            <option value="">Semua program studi</option>
                                            {studyProgramOptions
                                                .filter((program) => !filters.faculty_id || String(program.facultyId) === String(filters.faculty_id))
                                                .map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.graduation_year}
                                            onChange={(e) => visit({ graduation_year: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tahun lulus"
                                        >
                                            <option value="">Semua tahun lulus</option>
                                            {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.employment_status}
                                            onChange={(e) => visit({ employment_status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status pekerjaan"
                                        >
                                            <option value="">Semua status kerja</option>
                                            {Object.entries(employmentStatusOptions).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(e) => visit({ is_active: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status aktif"
                                        >
                                            <option value="">Semua status aktif</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
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
                                canUpdate={!isTrash && can.update}
                                editUrl={(row) => row.editUrl ?? urls.index}
                                customActions={!isTrash && can.view ? (row) => (
                                    row.showUrl ? (
                                        <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail">
                                            <Eye size={13} />
                                        </a>
                                    ) : null
                                ) : undefined}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions={(!isTrash && (can.update || can.view)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tambah Alumni"
                                exportHref={exportHref}
                                extraActions={can.convert && batchOptions.length > 0 ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setConvertOpen(true)}>
                                        <Repeat size={14} /> Konversi Yudisium
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada profil alumni yang cocok dengan filter.'}
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

                <ConfirmModal
                    open={convertOpen}
                    title="Konversi Yudisium ke Alumni"
                    message="Pilih batch yudisium yang sudah final. Profil alumni baru akan dibuat untuk mahasiswa yang belum terdaftar, dan profil yang pernah dihapus akan dipulihkan."
                    confirmLabel="Konversi Sekarang"
                    danger={false}
                    processing={convertProcessing}
                    onConfirm={runConvert}
                    onCancel={() => setConvertOpen(false)}
                >
                    <div style={{ marginTop: 4 }}>
                        <label className="crud-label" htmlFor="batch-convert">Batch yudisium</label>
                        <select
                            id="batch-convert"
                            className="db-input"
                            value={batchId}
                            onChange={(e) => setBatchId(e.target.value)}
                        >
                            {batchOptions.map((batch) => (
                                <option key={batch.id} value={batch.id}>{batch.label} — {batch.count} lulusan siap</option>
                            ))}
                        </select>
                    </div>
                </ConfirmModal>
            </div>
        </AdminShell>
    );
}
