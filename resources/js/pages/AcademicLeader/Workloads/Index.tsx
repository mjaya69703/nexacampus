// Rekap BKD dosen dalam scope pimpinan akademik (Inertia React).
// Statistik dari seluruh scope; filter dan paginasi hanya untuk daftar.
import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2, Clock3, FileSignature, FileSpreadsheet, FilterX, Scale,
    Search, TriangleAlert, UserRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { Pagination } from '../../../components/Shared/Pagination';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number;
    lecturerProfileId: number;
    name: string;
    nidn: string;
    program: string;
    faculty: string;
    periodName: string;
    status: string;
    statusLabel: string;
    teachingSks: number;
    structuralSks: number;
    tridharmaSks: number;
    totalSks: number;
    submittedAt: string | null;
    approvedAt: string | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasScope: boolean;
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; status: string };
    statusOptions: Record<string, string>;
    stats: { total: number; approval: number; approved: number; avgSks: number };
    urls: { index: string; export: Record<string, string> };
};

const statusTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'in_approval' || status === 'revision') return 'amber';
    if (status === 'cancelled') return 'gray';
    return '';
};

export default function WorkloadIndex({ shell, hasScope, data, filters, statusOptions, stats, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        const params: Record<string, string | number> = {
            q: search,
            status: filters.status,
            ...overrides,
        };

        router.get(
            urls.index,
            Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== undefined)),
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ page: 1 });
    };

    const reset = () => {
        setSearch('');
        router.get(urls.index, {}, { preserveState: true, replace: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`BKD Dosen · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Scale}
                        eyebrow="Pemantauan akademik"
                        title="BKD Dosen"
                        description="Lihat beban kerja dosen sesuai fakultas atau program studi dalam scope jabatan aktif."
                        badges={[
                            `${stats.total} pengajuan`,
                            `${stats.approval} menunggu approval`,
                            `${stats.approved} disetujui`,
                            `Rata-rata ${stats.avgSks.toFixed(2)} SKS`,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.export.xlsx} title="Export Excel">
                                    <FileSpreadsheet size={15} /> Excel
                                </a>
                                <a className="db-btn light" href={urls.export.csv} title="Export CSV">CSV</a>
                                <a className="db-btn light" href={urls.export.pdf} title="Export PDF">PDF</a>
                            </>
                        )}
                    />

                    {!hasScope && (
                        <div className="db-note warning">
                            <TriangleAlert size={15} />
                            <span>Scope belum tersedia. Akun ini belum memiliki scope fakultas atau program studi aktif.</span>
                        </div>
                    )}

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon"><FileSignature size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.total}</span>
                                <span className="db-stat-label">Pengajuan</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Clock3 size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.approval}</span>
                                <span className="db-stat-label">Menunggu approval</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.approved}</span>
                                <span className="db-stat-label">Disetujui</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Scale size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.avgSks.toFixed(2)}</span>
                                <span className="db-stat-label">Rata-rata SKS</span>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submitSearch} style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center' }}>
                                <div style={{ display: 'flex', gap: 8, flex: '1 1 260px', minWidth: 220 }}>
                                    <input
                                        className="db-input"
                                        type="search"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Cari nama, NIDN, atau program studi..."
                                        aria-label="Cari BKD"
                                    />
                                    <button type="submit" className="db-btn primary" aria-label="Cari"><Search size={15} /></button>
                                </div>
                                <select
                                    className="db-input"
                                    style={{ width: 'auto', minWidth: 200 }}
                                    value={filters.status}
                                    onChange={(event) => visit({ status: event.target.value, page: 1 })}
                                    aria-label="Filter status BKD"
                                >
                                    {Object.entries(statusOptions).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                                <button type="button" className="db-btn ghost" onClick={reset}>
                                    <FilterX size={15} /> Reset
                                </button>
                            </form>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Scale size={16} /> Daftar BKD</h2>
                            <span className="db-badge gray">{data.total} data</span>
                        </div>
                        <div className="db-card-body">
                            {data.rows.length === 0 ? (
                                <div className="db-empty-calm">
                                    <UserRound size={22} />
                                    <div>
                                        <b>Belum ada data BKD.</b>
                                        <small>Ubah filter atau tunggu dosen mengirim beban kerja.</small>
                                    </div>
                                </div>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Dosen</th>
                                                <th>Periode</th>
                                                <th>Komponen SKS</th>
                                                <th>Status</th>
                                                <th style={{ textAlign: 'right' }}>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.rows.map((row) => (
                                                <tr key={row.id}>
                                                    <td>
                                                        <a href={row.url} style={{ color: 'var(--db-heading)', fontWeight: 700, textDecoration: 'none' }}>
                                                            {row.name}
                                                        </a>
                                                        <div className="db-hint">{row.nidn} / {row.program}</div>
                                                    </td>
                                                    <td className="db-hint">
                                                        {row.periodName}
                                                        {row.submittedAt && <div>Dikirim {row.submittedAt}</div>}
                                                    </td>
                                                    <td>
                                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5 }}>
                                                            <span className="db-badge gray">Mengajar {row.teachingSks}</span>
                                                            <span className="db-badge gray">Jabatan {row.structuralSks}</span>
                                                            <span className="db-badge gray">Tridharma {row.tridharmaSks}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span className={`db-badge ${statusTone(row.status)}`}>{row.statusLabel}</span>
                                                        {row.approvedAt && <div className="db-hint">Disetujui {row.approvedAt}</div>}
                                                    </td>
                                                    <td style={{ textAlign: 'right', color: 'var(--db-heading)', fontWeight: 700 }}>
                                                        {row.totalSks} SKS
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    <Pagination
                        page={data.currentPage}
                        totalPages={data.lastPage}
                        total={data.total}
                        unit="pengajuan BKD"
                        onPrev={() => visit({ page: Math.max(1, data.currentPage - 1) })}
                        onNext={() => visit({ page: Math.min(data.lastPage, data.currentPage + 1) })}
                    />
                </div>
            </div>
        </AdminShell>
    );
}
