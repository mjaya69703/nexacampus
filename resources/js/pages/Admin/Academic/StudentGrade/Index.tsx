// Daftar nilai mahasiswa — kit CRUD (publish massal, hapus lunak, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Award, Eye, Megaphone, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    year: string | null; course: string; classLabel: string | null;
    finalScore: number | null; letter: string | null; point: number | null;
    lifecycle: string; lifecycleTone: string; result: string | null; resultTone: string;
    components: number; createdAt: string | null;
    showUrl: string; editUrl: string; deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean };
    stats: { total: number; draft: number; finalized: number; published: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; nim: string; year: string; program: string; offering: string;
        letter: string; lifecycle: string; result: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    yearOptions: { id: number; name: string }[];
    programOptions: { id: number; name: string }[];
    letters: string[];
    lifecycles: string[];
    results: string[];
    urls: {
        index: string; create: string; store: string; export: string;
        bulkPublish: string; offeringOptions: string;
    };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'publish' } | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function StudentGradeIndex({ shell, can, stats, data, filters, yearOptions, programOptions, letters, lifecycles, results, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [nim, setNim] = useState(filters.nim);
    const [offering, setOffering] = useState<AsyncOption | null>(
        filters.offering ? { id: filters.offering, label: `Offering #${filters.offering}` } : null,
    );
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
            router.post(urls.bulkPublish, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = (format: string) => {
        const params = new URLSearchParams(
            Object.entries(buildQuery(filters, search, { format })).map(([k, v]) => [k, String(v)]),
        );
        if (selected.length > 0) {
            selected.forEach((id) => params.append('ids[]', String(id)));
        }
        return `${urls.export}?${params.toString()}`;
    };

    const modal = pending?.kind === 'publish'
        ? { title: 'Publish nilai terpilih?', message: `${selected.length} nilai berstatus Finalized akan dipublish dan masuk transkrip. Yang belum Finalized dilewati.`, confirm: 'Ya, publish' }
        : { title: 'Hapus nilai?', message: 'Nilai dihapus (lunak) dan transkrip mahasiswa disinkron ulang.', confirm: 'Ya, hapus' };

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Akademik"
                        title="Nilai Mahasiswa"
                        description="Header dua tahap: buat, lengkapi komponen di halaman edit, finalize saat bobot 100%, lalu publish."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat Nilai</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik nilai">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total Nilai</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.draft}</span><span className="db-stat-label">Draft</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.finalized}</span><span className="db-stat-label">Finalized</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.published}</span><span className="db-stat-label">Published</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Nilai</h2>
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
                                    {
                                        key: 'course', label: 'Mata Kuliah', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.course}</b>
                                                <small className="db-hint">{row.year ?? '-'} · Kelas {row.classLabel ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'final_score', label: 'Skor / Huruf', sortable: true, align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>
                                                    {row.finalScore ?? '-'} {row.letter ? `(${row.letter})` : ''}
                                                </b>
                                                <small className="db-hint">Indeks {row.point ?? '-'} · {row.components} komponen</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'grade_status', label: 'Lifecycle',
                                        render: (row) => <span className={`db-badge ${row.lifecycleTone}`}>{row.lifecycle}</span>,
                                    },
                                    {
                                        key: 'result_status', label: 'Hasil',
                                        render: (row) => (row.result ? <span className={`db-badge ${row.resultTone}`}>{row.result}</span> : '-'),
                                    },
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
                                    <>
                                        <input
                                            className="db-input"
                                            style={{ maxWidth: 130 }}
                                            value={nim}
                                            placeholder="NIM…"
                                            onChange={(e) => setNim(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ nim, page: 1 }, true); } }}
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
                                            value={filters.letter}
                                            onChange={(e) => visit({ letter: e.target.value, page: 1 }, true)}
                                            aria-label="Filter huruf"
                                        >
                                            <option value="">Semua huruf</option>
                                            {letters.map((l) => <option key={l} value={l}>{l}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.lifecycle}
                                            onChange={(e) => visit({ lifecycle: e.target.value, page: 1 }, true)}
                                            aria-label="Filter lifecycle"
                                        >
                                            <option value="">Semua lifecycle</option>
                                            {lifecycles.map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.result}
                                            onChange={(e) => visit({ result: e.target.value, page: 1 }, true)}
                                            aria-label="Filter hasil"
                                        >
                                            <option value="">Semua hasil</option>
                                            {results.map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                        <div style={{ minWidth: 220 }}>
                                            <AsyncSelect
                                                label=""
                                                fetchUrl={urls.offeringOptions}
                                                value={offering}
                                                placeholder="Filter offering…"
                                                onChange={(option) => {
                                                    setOffering(option);
                                                    visit({ offering: option ? String(option.id) : '', page: 1 }, true);
                                                }}
                                            />
                                        </div>
                                    </>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                        {can.update && (
                                            <a className="db-btn ghost sm" href={row.editUrl} title="Ubah + komponen">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                extraActions={can.update && selected.length > 0 ? (
                                    <button
                                        type="button"
                                        className="db-btn ghost sm"
                                        onClick={() => setPending({ kind: 'publish' })}
                                        title="Publish yang terpilih"
                                    >
                                        <Megaphone size={13} /> Publish terpilih
                                    </button>
                                ) : undefined}
                                canCreate={false}
                                createLabel="Buat Nilai"
                                exportHref={exportHref('xlsx')}
                                exportExtra={[
                                    { label: 'CSV', href: exportHref('csv') },
                                ]}
                                emptyText="Belum ada nilai yang cocok dengan filter."
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
                    danger={pending?.kind === 'delete'}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
