// Daftar bank pertanyaan EDOM (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, CircleHelp, ListChecks, MessageSquare, Plus, Star } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type Row = {
    id: number;
    no: number;
    category: string;
    categoryLabel: string;
    questionText: string;
    answerType: string;
    answerTypeLabel: string;
    sortOrder: number;
    isRequired: boolean;
    isActive: boolean;
    answersCount: number;
    editUrl: string;
    toggleUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; scale: number; text: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        category: string;
        answer_type: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    categories: Option[];
    answerTypes: Option[];
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function EdomQuestionsIndex({ shell, can, stats, data, filters, categories, answerTypes, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = (filters.category !== '' ? 1 : 0) + (filters.answer_type !== '' ? 1 : 0) + (filters.is_active !== '' ? 1 : 0);

    const visit = (overrides: Record<string, string | number | undefined>, clear = false, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clear) setSelected([]);
            },
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ category: '', answer_type: '', is_active: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
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

        if (pending.kind === 'delete') {
            router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
            return;
        }

        router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Pertanyaan EDOM · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CircleHelp}
                        eyebrow="Kepegawaian"
                        title="Pertanyaan EDOM"
                        description="Kelola instrumen kuesioner, kategori penilaian, dan format jawaban untuk evaluasi kinerja pengajaran dosen."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Pertanyaan</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik pertanyaan EDOM"
                        items={[
                            { label: 'Total Pertanyaan', value: stats.total, icon: ListChecks },
                            { label: 'Pertanyaan Aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Tipe Skala', value: stats.scale, icon: Star, tone: 'gold' },
                            { label: 'Tipe Teks', value: stats.text, icon: MessageSquare },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Bank Pertanyaan Evaluasi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'question_text',
                                        label: 'Pertanyaan',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13, maxWidth: 460 }}>{row.questionText}</b>
                                                <small className="db-hint">{row.answersCount} jawaban mahasiswa</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'category',
                                        label: 'Kategori',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray">{row.categoryLabel}</span>,
                                    },
                                    {
                                        key: 'answer_type',
                                        label: 'Tipe',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.answerType === 'scale' ? 'amber' : 'gray'}`}>{row.answerTypeLabel}</span>
                                        ),
                                    },
                                    {
                                        key: 'sort_order',
                                        label: 'Urutan',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.sortOrder,
                                    },
                                    {
                                        key: 'is_required',
                                        label: 'Wajib',
                                        render: (row) => (
                                            <span className={`db-badge ${row.isRequired ? 'green' : 'gray'}`}>{row.isRequired ? 'Wajib' : 'Opsional'}</span>
                                        ),
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
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari teks pertanyaan atau kategori…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.category}
                                            onChange={(event) => visit({ category: event.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori pertanyaan"
                                        >
                                            <option value="">Semua kategori</option>
                                            {categories.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.answer_type}
                                            onChange={(event) => visit({ answer_type: event.target.value, page: 1 }, true)}
                                            aria-label="Filter tipe jawaban"
                                        >
                                            <option value="">Semua tipe</option>
                                            {answerTypes.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status pertanyaan"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                emptyText="Belum ada pertanyaan EDOM yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus pertanyaan terpilih?' : 'Hapus pertanyaan?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} pertanyaan akan dihapus. Pertanyaan yang sudah memiliki jawaban mahasiswa tidak dapat dihapus.`
                        : pending?.kind === 'delete'
                            ? 'Pertanyaan ini akan dihapus dari bank pertanyaan EDOM.'
                            : ''}
                    confirmLabel={pending?.kind === 'bulk' ? 'Ya, hapus semua' : 'Ya, hapus'}
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
