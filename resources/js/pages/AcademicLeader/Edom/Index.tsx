// Monitoring EDOM & performa dosen (Inertia React).
// Jumlah dosen = profil dosen unik; jumlah review ditampilkan terpisah.
import { Head, router } from '@inertiajs/react';
import {
    BarChart3, ClipboardList, FilterX, MessageSquare, Search, Star,
    TrendingUp, TriangleAlert,
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
    periodName: string;
    edomScore: number | null;
    finalScore: number | null;
    teachingCompliance: number | null;
    attendanceCompliance: number | null;
    responses: number;
    workloadSks: number | null;
    calculatedAt: string;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasScope: boolean;
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; period: number | null };
    periods: { id: number; name: string }[];
    stats: { lecturers: number; reviews: number; responses: number; avgEdom: number; avgFinal: number };
    urls: { index: string; export: Record<string, string> };
};

const scoreTone = (score: number | null) => {
    if (score === null) return 'gray';
    if (score >= 80) return 'green';
    if (score >= 60) return 'amber';
    return 'red';
};

const formatScore = (score: number | null) => (score === null ? '-' : score.toFixed(2));
const formatPercent = (score: number | null) => (score === null ? '-' : `${score}%`);

export default function EdomIndex({ shell, hasScope, data, filters, periods, stats, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        const params: Record<string, string | number> = {
            q: search,
            period: filters.period ?? '',
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
            <Head title={`EDOM & Performa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Star}
                        eyebrow="Pemantauan akademik"
                        title="EDOM & Performa Dosen"
                        description="Pantau agregat evaluasi mahasiswa dan sinyal kepatuhan mengajar sesuai scope jabatan aktif."
                        badges={[
                            `${stats.lecturers} dosen`,
                            `${stats.reviews} review`,
                            `${stats.responses} respon`,
                            `Rata-rata EDOM ${stats.avgEdom.toFixed(2)}`,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.export.xlsx} title="Export Excel">Excel</a>
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
                            <span className="db-stat-icon"><TrendingUp size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.lecturers}</span>
                                <span className="db-stat-label">Dosen</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><ClipboardList size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.reviews}</span>
                                <span className="db-stat-label">Review</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><MessageSquare size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.responses}</span>
                                <span className="db-stat-label">Respon EDOM</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><BarChart3 size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.avgEdom.toFixed(2)}</span>
                                <span className="db-stat-label">Rata-rata EDOM</span>
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
                                        placeholder="Cari dosen, NIDN, prodi, atau periode..."
                                        aria-label="Cari review EDOM"
                                    />
                                    <button type="submit" className="db-btn primary" aria-label="Cari"><Search size={15} /></button>
                                </div>
                                <select
                                    className="db-input"
                                    style={{ width: 'auto', minWidth: 200 }}
                                    value={filters.period ?? ''}
                                    onChange={(event) => visit({ period: event.target.value || undefined, page: 1 })}
                                    aria-label="Filter periode EDOM"
                                >
                                    <option value="">Semua periode EDOM</option>
                                    {periods.map((period) => (
                                        <option key={period.id} value={period.id}>{period.name}</option>
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
                            <h2 className="db-card-title"><ClipboardList size={16} /> Daftar Performa</h2>
                            <span className="db-badge gray">{data.total} review</span>
                        </div>
                        <div className="db-card-body">
                            {data.rows.length === 0 ? (
                                <div className="db-empty-calm">
                                    <Star size={22} />
                                    <div>
                                        <b>Belum ada review performa.</b>
                                        <small>Belum ada review pada scope dan filter ini.</small>
                                    </div>
                                </div>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Dosen</th>
                                                <th>Periode</th>
                                                <th>EDOM</th>
                                                <th>Kepatuhan</th>
                                                <th>Akhir</th>
                                                <th style={{ textAlign: 'right' }}>Respon / BKD</th>
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
                                                        <div>Dihitung {row.calculatedAt}</div>
                                                    </td>
                                                    <td><span className="db-badge gray">{formatScore(row.edomScore)}</span></td>
                                                    <td className="db-hint">
                                                        Mengajar {formatPercent(row.teachingCompliance)}
                                                        <div>Absensi {formatPercent(row.attendanceCompliance)}</div>
                                                    </td>
                                                    <td>
                                                        <span className={`db-badge ${scoreTone(row.finalScore)}`}>{formatScore(row.finalScore)}</span>
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <span className="db-hint">{row.responses} respon</span>
                                                        <div className="db-hint">BKD {row.workloadSks ?? '-'} SKS</div>
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
                        unit="review"
                        onPrev={() => visit({ page: Math.max(1, data.currentPage - 1) })}
                        onNext={() => visit({ page: Math.min(data.lastPage, data.currentPage + 1) })}
                    />
                </div>
            </div>
        </AdminShell>
    );
}
