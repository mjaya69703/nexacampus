// Daftar dosen dalam scope pimpinan akademik (Inertia React).
// Statistik memakai seluruh data scope, filter hanya memengaruhi daftar.
import { Head, router } from '@inertiajs/react';
import {
    BookOpen, FileSpreadsheet, FilterX, Search, TrendingUp, UserRound, UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number;
    name: string;
    nidn: string;
    program: string;
    faculty: string;
    activeClasses: number;
    workloadStatus: string;
    workloadStatusLabel: string;
    workloadSks: number;
    workloadPeriod: string;
    performanceScore: number | null;
    edomScore: number | null;
    edomResponses: number;
    edomPeriod: string;
    url: string;
};

type Props = {
    shell: ShellProps;
    rows: Row[];
    filters: { q: string; status: string };
    statusOptions: Record<string, string>;
    stats: { lecturers: number; classes: number; pending: number; avgScore: number };
    urls: { index: string; export: Record<string, string> };
};

const statusTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'in_approval' || status === 'revision') return 'amber';
    if (status === 'none' || status === 'cancelled') return 'gray';
    return '';
};

const scoreTone = (score: number | null) => {
    if (score === null) return 'gray';
    if (score >= 80) return 'green';
    if (score >= 60) return 'amber';
    return 'red';
};

const formatScore = (score: number | null) => (score === null ? '-' : score.toFixed(2));

export default function LecturerIndex({ shell, rows, filters, statusOptions, stats, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Record<string, string | undefined>) => {
        const params: Record<string, string> = {
            q: search,
            status: filters.status,
            ...overrides,
        };

        router.get(
            urls.index,
            Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '')),
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search });
    };

    const reset = () => {
        setSearch('');
        router.get(urls.index, {}, { preserveState: true, replace: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Dosen Dalam Scope · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={UsersRound}
                        eyebrow="Pemantauan akademik"
                        title="Dosen Dalam Scope"
                        description="Pusat navigasi dosen sesuai fakultas atau program studi yang dipimpin."
                        badges={[
                            `${stats.lecturers} dosen`,
                            `${stats.classes} kelas aktif`,
                            `${stats.pending} BKD perlu tindak lanjut`,
                            `Rata-rata performa ${stats.avgScore.toFixed(2)}`,
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

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon"><UserRound size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.lecturers}</span>
                                <span className="db-stat-label">Dosen</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><BookOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.classes}</span>
                                <span className="db-stat-label">Kelas aktif</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><TrendingUp size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.avgScore.toFixed(2)}</span>
                                <span className="db-stat-label">Rata-rata performa</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><FilterX size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.pending}</span>
                                <span className="db-stat-label">BKD perlu tindak lanjut</span>
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
                                        placeholder="Cari nama, NIDN, prodi, atau fakultas..."
                                        aria-label="Cari dosen"
                                    />
                                    <button type="submit" className="db-btn primary" aria-label="Cari"><Search size={15} /></button>
                                </div>
                                <select
                                    className="db-input"
                                    style={{ width: 'auto', minWidth: 200 }}
                                    value={filters.status}
                                    onChange={(event) => visit({ status: event.target.value })}
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
                            <h2 className="db-card-title"><UsersRound size={16} /> Daftar Dosen</h2>
                            <span className="db-badge gray">{rows.length} ditampilkan</span>
                        </div>
                        <div className="db-card-body">
                            {rows.length === 0 ? (
                                <div className="db-empty-calm">
                                    <UserRound size={22} />
                                    <div>
                                        <b>Tidak ada dosen ditemukan.</b>
                                        <small>Coba ubah kata kunci atau filter status BKD.</small>
                                    </div>
                                </div>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Dosen</th>
                                                <th>Fakultas</th>
                                                <th>Kelas</th>
                                                <th>BKD</th>
                                                <th>Performa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row) => (
                                                <tr key={row.id}>
                                                    <td>
                                                        <a href={row.url} style={{ color: 'var(--db-heading)', fontWeight: 700, textDecoration: 'none' }}>
                                                            {row.name}
                                                        </a>
                                                        <div className="db-hint">{row.nidn} / {row.program}</div>
                                                    </td>
                                                    <td className="db-hint">{row.faculty}</td>
                                                    <td><span className="db-badge gray">{row.activeClasses} kelas</span></td>
                                                    <td>
                                                        <span className={`db-badge ${statusTone(row.workloadStatus)}`}>{row.workloadStatusLabel}</span>
                                                        <div className="db-hint">{row.workloadSks} SKS / {row.workloadPeriod}</div>
                                                    </td>
                                                    <td>
                                                        <span className={`db-badge ${scoreTone(row.performanceScore)}`}>
                                                            {formatScore(row.performanceScore)}
                                                        </span>
                                                        <div className="db-hint">
                                                            EDOM {formatScore(row.edomScore)} · {row.edomResponses} respon
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
