// Daftar kuota penerimaan PMB — memakai kit CRUD shared.
import { Head, router } from '@inertiajs/react';
import { Gauge, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; period: string | null; faculty: string; studyProgram: string;
    classType: string | null; classTypeLabel: string; quota: number; acceptedCount: number;
    remaining: number; usage: number; createdAt: string | null;
    editUrl: string; deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; quota: number; accepted: number; remaining: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; admission_period_id: string; faculty_id: string; study_program_id: string;
        class_type: string; sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    periodOptions: { id: number; label: string }[];
    facultyOptions: { id: number; label: string }[];
    programOptions: { id: number; facultyId: number; label: string }[];
    classTypeOptions: { id: string; label: string }[];
    urls: { index: string; create: string; export: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk-delete' } | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function AdmissionQuotaIndex({ shell, can, stats, data, filters, periodOptions, facultyOptions, programOptions, classTypeOptions, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const activeFilterCount = [filters.admission_period_id, filters.faculty_id, filters.study_program_id, filters.class_type]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ admission_period_id: '', faculty_id: '', study_program_id: '', class_type: '', page: 1 }, true);
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

        if (pending.kind === 'delete') {
            router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
        } else {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const visiblePrograms = filters.faculty_id === ''
        ? programOptions
        : programOptions.filter((program) => String(program.facultyId) === String(filters.faculty_id));

    const copy = pending?.kind === 'delete'
        ? {
            title: 'Hapus kuota penerimaan?',
            message: `Kuota "${pending.row.period ?? '-'} · ${pending.row.studyProgram}" dihapus dari dashboard seleksi.`,
            confirm: 'Ya, hapus',
        }
        : {
            title: 'Hapus kuota terpilih?',
            message: `${selected.length} baris kuota akan dihapus permanen.`,
            confirm: 'Ya, hapus',
        };

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Kuota PMB · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Gauge}
                        eyebrow="Admission"
                        title="Kuota Penerimaan PMB"
                        description="Alokasi daya tampung per gelombang, fakultas, program studi, dan jenis kelas. Baris tanpa fakultas berlaku untuk semua fakultas."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Kuota</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik kuota">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Gauge size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Baris kuota</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Gauge size={20} /></span>
                            <div><span className="db-stat-num">{stats.quota}</span><span className="db-stat-label">Total daya tampung</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Gauge size={20} /></span>
                            <div><span className="db-stat-num">{stats.accepted}</span><span className="db-stat-label">Diterima</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Gauge size={20} /></span>
                            <div><span className="db-stat-num">{stats.remaining}</span><span className="db-stat-label">Sisa kuota</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Kuota PMB</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'period', label: 'Periode',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.period ?? '-'}</b>
                                                <small className="db-hint">{row.faculty}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'study_program', label: 'Program studi',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.studyProgram}</b>
                                                <small className="db-hint">{row.classTypeLabel}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'quota', label: 'Kuota', sortable: true, align: 'right', render: (row) => row.quota },
                                    { key: 'accepted_count', label: 'Diterima', sortable: true, align: 'right', render: (row) => row.acceptedCount },
                                    {
                                        key: 'remaining', label: 'Sisa',
                                        align: 'right',
                                        render: (row) => (
                                            <span className={`db-badge ${row.remaining > 0 ? 'green' : 'red'}`} title={`Terisi ${row.usage}%`}>
                                                {row.remaining}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'usage', label: 'Terisi',
                                        align: 'right',
                                        render: (row) => `${row.usage}%`,
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
                                searchPlaceholder="Cari periode, fakultas, prodi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.admission_period_id}
                                            onChange={(e) => visit({ admission_period_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter periode"
                                        >
                                            <option value="">Semua periode</option>
                                            {periodOptions.map((period) => <option key={period.id} value={period.id}>{period.label}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.faculty_id}
                                            onChange={(e) => visit({ faculty_id: e.target.value, study_program_id: '', page: 1 }, true)}
                                            aria-label="Filter fakultas"
                                        >
                                            <option value="">Semua fakultas</option>
                                            {facultyOptions.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.label}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.study_program_id}
                                            onChange={(e) => visit({ study_program_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter program studi"
                                        >
                                            <option value="">Semua program studi</option>
                                            {visiblePrograms.map((program) => <option key={program.id} value={program.id}>{program.label}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.class_type}
                                            onChange={(e) => visit({ class_type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter kelas"
                                        >
                                            <option value="">Semua kelas</option>
                                            {classTypeOptions.map((type) => <option key={type.id} value={type.id}>{type.label}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk-delete' })}
                                bulkLabel="Hapus kuota terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                createLabel="Tambah Kuota"
                                exportHref={exportHref}
                                emptyText="Belum ada kuota yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy.title}
                    message={copy.message}
                    confirmLabel={copy.confirm}
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
