// Job board alumni — daftar lowongan aktif dengan filter (Inertia React).
import { Head, router } from '@inertiajs/react';
import {
    Briefcase, Building2, CalendarClock, MapPin, RotateCcw, Search, Wallet,
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
    title: string;
    companyName: string;
    industry: string | null;
    location: string | null;
    salaryRange: string | null;
    jobType: string | null;
    jobTypeLabel: string | null;
    postedDate: string | null;
    deadlineDate: string | null;
    deadlineIn: string | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; job_type: string; industry: string };
    jobTypeOptions: Record<string, string>;
    urls: { index: string };
};

export default function JobBoardIndex({ shell, data, filters, jobTypeOptions, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [industry, setIndustry] = useState(filters.industry);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        const params: Record<string, string | number> = {
            ...filters,
            q: search,
            industry,
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
        setIndustry('');
        router.get(urls.index, {}, { preserveState: true, replace: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Job Board · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Briefcase}
                        eyebrow="Portal alumni"
                        title="Job Board"
                        description="Temukan lowongan kerja dari mitra perusahaan kampus dan jaringan alumni."
                        badges={[`${data.total} lowongan aktif`]}
                    />

                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submitSearch} style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center' }}>
                                <div style={{ display: 'flex', gap: 8, flex: '1 1 260px', minWidth: 220 }}>
                                    <input
                                        className="db-input"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Cari lowongan, perusahaan, atau lokasi..."
                                        aria-label="Cari lowongan"
                                    />
                                    <button type="submit" className="db-btn primary" aria-label="Cari"><Search size={15} /></button>
                                </div>
                                <select
                                    className="db-input"
                                    style={{ width: 'auto', minWidth: 160 }}
                                    value={filters.job_type}
                                    onChange={(event) => visit({ job_type: event.target.value, page: 1 })}
                                    aria-label="Filter tipe pekerjaan"
                                >
                                    <option value="">Semua tipe</option>
                                    {Object.entries(jobTypeOptions).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                                <div style={{ display: 'flex', gap: 8, flex: '0 1 200px', minWidth: 160 }}>
                                    <input
                                        className="db-input"
                                        value={industry}
                                        onChange={(event) => setIndustry(event.target.value)}
                                        onBlur={() => visit({ industry, page: 1 })}
                                        onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); visit({ industry, page: 1 }); } }}
                                        placeholder="Industri..."
                                        aria-label="Filter industri"
                                    />
                                    <button type="button" className="db-btn ghost" onClick={reset} title="Reset filter" aria-label="Reset filter">
                                        <RotateCcw size={15} />
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                    {data.rows.length === 0 ? (
                        <section className="db-card">
                            <div className="db-card-body" style={{ display: 'grid', justifyItems: 'center', gap: 8, padding: '44px 24px', textAlign: 'center' }}>
                                <span className="db-stat-icon"><Briefcase size={20} /></span>
                                <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 17 }}>Tidak ada lowongan ditemukan</h2>
                                <p className="db-hint" style={{ margin: 0 }}>Coba ubah kata kunci atau filter pencarian kamu.</p>
                            </div>
                        </section>
                    ) : (
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 14 }}>
                            {data.rows.map((row) => (
                                <a key={row.id} href={row.url} className="db-card" style={{ display: 'block', textDecoration: 'none', height: '100%' }}>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 8, height: '100%', alignContent: 'start' }}>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                            <span className="db-badge">{row.jobTypeLabel ?? row.jobType ?? 'Lowongan'}</span>
                                            <span className="db-badge amber"><CalendarClock size={11} /> {row.deadlineIn ?? row.deadlineDate ?? '-'}</span>
                                        </div>
                                        <div>
                                            <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 15, lineHeight: 1.4 }}>{row.title}</h2>
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5, marginTop: 4 }}>
                                                <Building2 size={12} /> {row.companyName}
                                            </span>
                                        </div>
                                        <div style={{ display: 'grid', gap: 4 }}>
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                <MapPin size={12} /> {row.location ?? 'Remote'}
                                            </span>
                                            {row.industry && (
                                                <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                    <Briefcase size={12} /> {row.industry}
                                                </span>
                                            )}
                                            {row.salaryRange && (
                                                <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                    <Wallet size={12} /> {row.salaryRange}
                                                </span>
                                            )}
                                        </div>
                                        <div style={{ marginTop: 'auto', paddingTop: 8, borderTop: '1px solid var(--db-line)', display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                                            <span className="db-hint">Diposting {row.postedDate ?? '-'}</span>
                                            <span className="db-badge gray">s.d. {row.deadlineDate ?? '-'}</span>
                                        </div>
                                    </div>
                                </a>
                            ))}
                        </div>
                    )}

                    <Pagination
                        page={data.currentPage}
                        totalPages={data.lastPage}
                        total={data.total}
                        unit="lowongan"
                        onPrev={() => visit({ page: Math.max(1, data.currentPage - 1) })}
                        onNext={() => visit({ page: Math.min(data.lastPage, data.currentPage + 1) })}
                    />
                </div>
            </div>
        </AdminShell>
    );
}
