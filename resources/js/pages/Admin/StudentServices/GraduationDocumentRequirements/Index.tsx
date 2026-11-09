// Daftar syarat dokumen yudisium (admin).
import { Head, router } from '@inertiajs/react';
import { FileCheck2, FileText, Globe2, Plus, ShieldCheck } from 'lucide-react';
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
    label: string;
    documentType: string;
    scope: string;
    isRequired: boolean;
    allowedExtensions: string;
    maxSizeKb: number;
    sortOrder: number;
    isActive: boolean;
    editUrl: string;
    toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean };
    stats: { total: number; required: number; active: number; global: number };
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
        is_required: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    studyPrograms: { id: number; name: string }[];
    urls: { index: string; create: string };
};

export default function GraduationDocumentRequirementsIndex({
    shell,
    can,
    stats,
    data,
    filters,
    studyPrograms,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [filters.study_program_id, filters.is_required, filters.is_active]
        .filter((value) => value !== '' && value !== null && value !== undefined).length;

    const visit = (overrides: Record<string, string | number | undefined>, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ study_program_id: '', is_required: '', is_active: '', page: 1 }, '');
    };

    const toggleActive = (row: Row) => {
        router.post(row.toggleUrl, { is_active: !row.isActive }, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Dokumen Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileCheck2}
                        eyebrow="Layanan Mahasiswa"
                        title="Dokumen Yudisium"
                        description="Atur syarat dokumen yudisium, format berkas yang diizinkan, ukuran maksimal, dan cakupannya."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Dokumen</a>
                        ) : undefined}
                    />

                    <ServiceStats
                        label="Statistik dokumen yudisium"
                        items={[
                            { label: 'Total Syarat', value: stats.total, icon: FileText },
                            { label: 'Wajib', value: stats.required, icon: ShieldCheck, tone: 'gold' },
                            { label: 'Aktif', value: stats.active, icon: FileCheck2, tone: 'green' },
                            { label: 'Global', value: stats.global, icon: Globe2, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Syarat Dokumen</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'label',
                                        label: 'Dokumen',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.label}</b>
                                                <small className="db-hint">{row.documentType} · {row.scope}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_required',
                                        label: 'Wajib',
                                        render: (row) => (
                                            <span className={`db-badge ${row.isRequired ? 'amber' : 'gray'}`}>
                                                {row.isRequired ? 'Wajib' : 'Opsional'}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'allowed_extensions',
                                        label: 'Format',
                                        render: (row) => <span className="db-hint" style={{ fontSize: 12 }}>{row.allowedExtensions}</span>,
                                    },
                                    {
                                        key: 'max_size_kb',
                                        label: 'Maks. Ukuran',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => `${row.maxSizeKb} KB`,
                                    },
                                    {
                                        key: 'sort_order',
                                        label: 'Urutan',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.sortOrder,
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
                                searchPlaceholder="Cari label atau tipe dokumen…"
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
                                            value={filters.is_required}
                                            onChange={(event) => visit({ is_required: event.target.value, page: 1 })}
                                            aria-label="Filter kewajiban"
                                        >
                                            <option value="">Semua kewajiban</option>
                                            <option value="1">Wajib</option>
                                            <option value="0">Opsional</option>
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
                                emptyText="Belum ada syarat dokumen yang cocok dengan filter."
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
