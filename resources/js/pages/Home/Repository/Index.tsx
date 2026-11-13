// Repositori publik skripsi/tugas akhir — pencarian, filter, dan daftar karya.
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight, BookMarked, Building2, CalendarDays, FileText, GraduationCap,
    RotateCcw, Search, ShieldCheck, UserRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AcademicBaseProps, AcademicShell, AcademicStat } from '../../../components/Home/Academic/AcademicShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { Pagination } from '../../../components/Shared/Pagination';
import '../../../../css/admission-public.css';

type Row = {
    id: number; title: string; abstract: string; author: string; nim: string | null;
    program: string | null; year: string | null; typeLabel: string; finalGrade: string | null;
    completedAt: string | null; plagiarismStatusLabel: string; plagiarismStatusTone: string;
    plagiarismScore: number | null; supervisors: string; detailUrl: string;
};

type Props = AcademicBaseProps & {
    stats: { total: number; programs: number; years: number };
    rows: Row[];
    page: { currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; program: string; year: string; type: string; sort: string };
    options: { programs: { id: number; name: string }[]; years: { id: number; name: string }[]; types: Record<string, string> };
};

const indexUrl = '/repositori-skripsi';

export default function RepositoryIndex({ campus, links, user, stats, rows, page, filters, options }: Props) {
    const [query, setQuery] = useState(filters.q);

    const visit = (overrides: Record<string, string | number> = {}) => {
        router.get(indexUrl, { ...filters, q: query, ...overrides }, { preserveState: true, replace: true });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        visit({ page: 1 });
    };

    const reset = () => {
        setQuery('');
        router.get(indexUrl, {}, { preserveState: true, replace: true });
    };

    return (
        <AcademicShell
            campus={campus}
            links={links}
            user={user}
            activeTab="Repositori"
            eyebrow="Repositori karya"
            title="Repositori Skripsi & Tugas Akhir"
            description={`Telusuri karya akademik yang telah dinyatakan selesai dan dipublikasikan oleh ${campus.name}.`}
            icon={BookMarked}
        >
            <Head title={`Repositori Skripsi · ${campus.name}`} />

            <div className="academic-overview">
                <div>
                    <h2>Karya akademik yang dapat diakses publik</h2>
                    <p>Setiap karya telah melalui finalisasi nilai dan persetujuan publikasi. Unduhan publik dibatasi pada laporan final.</p>
                </div>
                <div className="academic-stats">
                    <AcademicStat value={stats.total} label="Karya terpublikasi" />
                    <AcademicStat value={stats.programs} label="Program studi" />
                    <AcademicStat value={stats.years} label="Tahun akademik" />
                </div>
            </div>

            <section className="adm-card" style={{ marginBottom: 18 }}>
                <div className="adm-card-head">
                    <h2 className="adm-card-title"><Search size={16} /> Cari Karya</h2>
                    <span className="adm-badge">{page.total} hasil</span>
                </div>
                <div className="adm-card-body">
                    <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
                            <input
                                className="adm-input"
                                style={{ flex: '1 1 260px' }}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Cari judul, abstrak, kata kunci, nama, atau NIM…"
                                aria-label="Kata kunci pencarian"
                            />
                            <button className="adm-btn primary" type="submit"><Search size={14} /> Cari</button>
                            <button className="adm-btn ghost" type="button" onClick={reset}><RotateCcw size={14} /> Reset</button>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))', gap: 10 }}>
                            <label className="adm-field" style={{ display: 'grid', gap: 6 }}>
                                <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--adm-muted)' }}>Program studi</span>
                                <select className="adm-input" value={filters.program} onChange={(event) => visit({ program: event.target.value, page: 1 })}>
                                    <option value="">Semua program</option>
                                    {options.programs.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                                </select>
                            </label>
                            <label className="adm-field" style={{ display: 'grid', gap: 6 }}>
                                <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--adm-muted)' }}>Tahun akademik</span>
                                <select className="adm-input" value={filters.year} onChange={(event) => visit({ year: event.target.value, page: 1 })}>
                                    <option value="">Semua tahun</option>
                                    {options.years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                </select>
                            </label>
                            <label className="adm-field" style={{ display: 'grid', gap: 6 }}>
                                <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--adm-muted)' }}>Jenis karya</span>
                                <select className="adm-input" value={filters.type} onChange={(event) => visit({ type: event.target.value, page: 1 })}>
                                    <option value="">Semua jenis</option>
                                    {Object.entries(options.types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </label>
                            <label className="adm-field" style={{ display: 'grid', gap: 6 }}>
                                <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--adm-muted)' }}>Urutkan</span>
                                <select className="adm-input" value={filters.sort} onChange={(event) => visit({ sort: event.target.value, page: 1 })}>
                                    <option value="latest">Terbaru</option>
                                    <option value="title">Judul A–Z</option>
                                    <option value="score">Nilai tertinggi</option>
                                </select>
                            </label>
                        </div>
                    </form>
                </div>
            </section>

            {rows.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title="Karya tidak ditemukan"
                    description="Belum ada karya yang cocok dengan kata kunci atau filter yang dipilih. Coba ubah filter pencarian."
                    action={<button type="button" className="adm-btn ghost sm" onClick={reset}><RotateCcw size={13} /> Reset Pencarian</button>}
                />
            ) : (
                <div className="adm-grid adm-cols-2">
                    {rows.map((row) => (
                        <Link
                            key={row.id}
                            href={row.detailUrl}
                            style={{
                                display: 'flex', flexDirection: 'column', gap: 10, padding: 20,
                                textDecoration: 'none', color: 'inherit',
                                border: '1px solid var(--adm-line)', borderRadius: 14,
                                background: 'var(--adm-card)', boxShadow: '0 1px 3px rgba(20,39,63,.04)',
                            }}
                        >
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, justifyContent: 'space-between' }}>
                                <span className="adm-badge">{row.typeLabel}</span>
                                <span className={`adm-badge ${row.plagiarismStatusTone}`}>
                                    <ShieldCheck size={11} /> {row.plagiarismStatusLabel}{row.plagiarismScore !== null ? ` · ${row.plagiarismScore}%` : ''}
                                </span>
                            </div>
                            <h3 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 18px/1.35 'Source Serif 4', Georgia, serif" }}>{row.title}</h3>
                            <p style={{ margin: 0, color: 'var(--adm-muted)', fontSize: 13, lineHeight: 1.65 }}>{row.abstract || 'Abstrak belum tersedia.'}</p>
                            <div style={{ display: 'grid', gap: 5, marginTop: 2, color: 'var(--adm-muted)', fontSize: 12.5 }}>
                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><UserRound size={13} /> {row.author}{row.nim ? ` · ${row.nim}` : ''}</span>
                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><GraduationCap size={13} /> {row.program ?? '-'}</span>
                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><CalendarDays size={13} /> {row.completedAt ?? '-'}{row.year ? ` · ${row.year}` : ''}</span>
                                {row.supervisors && <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><Building2 size={13} /> {row.supervisors}</span>}
                            </div>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginTop: 4, color: 'var(--adm-brand)', fontSize: 12.5, fontWeight: 700 }}>
                                Lihat detail <ArrowRight size={14} />
                            </span>
                        </Link>
                    ))}
                </div>
            )}

            <Pagination
                page={page.currentPage}
                totalPages={page.lastPage}
                total={page.total}
                unit="karya"
                onPrev={() => visit({ page: Math.max(1, page.currentPage - 1) })}
                onNext={() => visit({ page: Math.min(page.lastPage, page.currentPage + 1) })}
            />
        </AcademicShell>
    );
}
