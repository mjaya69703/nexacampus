// Daftar aturan yudisium (admin).
import { Head, router } from '@inertiajs/react';
import { Globe2, GraduationCap, Plus, School, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ServiceStats } from '../../../../components/Shared/StudentServices/ServiceUI';
import { buildQuery } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    name: string;
    scope: string;
    minimumSemester: number;
    minimumCredits: number;
    minimumGpa: string;
    rules: string;
    isActive: boolean;
    editUrl: string;
    toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean };
    stats: { total: number; active: number; global: number; program: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        study_program_id: string | number;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    studyPrograms: { id: number; name: string }[];
    urls: { index: string; create: string };
};

export default function GraduationPoliciesIndex({ shell, can, stats, data, filters, studyPrograms, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [filters.study_program_id, filters.is_active]
        .filter((value) => value !== '' && value !== null && value !== undefined).length;

    const visit = (overrides: Record<string, string | number | undefined>, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ study_program_id: '', is_active: '', page: 1 }, '');
    };

    const toggleActive = (row: Row) => {
        router.post(row.toggleUrl, { is_active: !row.isActive }, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Aturan Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ShieldCheck}
                        eyebrow="Layanan Mahasiswa"
                        title="Aturan Yudisium"
                        description="Tetapkan syarat kelayakan yudisium global atau per program studi: semester, SKS, IPK, dan pemeriksaan otomatis."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Aturan</a>
                        ) : undefined}
                    />

                    <ServiceStats
                        label="Statistik aturan yudisium"
                        items={[
                            { label: 'Total Aturan', value: stats.total, icon: ShieldCheck },
                            { label: 'Aktif', value: stats.active, icon: GraduationCap, tone: 'green' },
                            { label: 'Global', value: stats.global, icon: Globe2, tone: 'gold' },
                            { label: 'Per Prodi', value: stats.program, icon: School, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Aturan Yudisium</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Aturan',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.scope}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'minimum_semester',
                                        label: 'Min. Semester',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.minimumSemester,
                                    },
                                    {
                                        key: 'minimum_passed_credits',
                                        label: 'Min. SKS',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.minimumCredits,
                                    },
                                    {
                                        key: 'minimum_gpa',
                                        label: 'Min. IPK',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.minimumGpa,
                                    },
                                    {
                                        key: 'rules',
                                        label: 'Pemeriksaan',
                                        render: (row) => <span className="db-hint" style={{ fontSize: 12 }}>{row.rules}</span>,
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            can.update ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title={row.isActive ? 'Nonaktifkan' : 'Aktifkan'}>
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
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={(key) => visit({
                                    sort: key,
                                    direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
                                    page: 1,
                                })}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 })}
                                searchPlaceholder="Cari nama aturan yudisium…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.study_program_id)}
                                            onChange={(event) => visit({ study_program_id: event.target.value, page: 1 })}
                                            aria-label="Filter program studi"
                                        >
                                            <option value="">Semua cakupan</option>
                                            {studyPrograms.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 })}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                selectable={false}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                showActions={can.update}
                                canCreate={false}
                                emptyText="Belum ada aturan yudisium yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
