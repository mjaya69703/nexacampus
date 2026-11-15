// Daftar respon tracer study — kit CRUD shared (Inertia React).
import { Head, router } from '@inertiajs/react';
import { BarChart3, Briefcase, Download, GraduationCap, TrendingUp, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    campaignTitle: string | null;
    alumniName: string;
    nim: string;
    studyProgram: string;
    graduationYear: number | null;
    employmentStatus: string | null;
    employmentStatusLabel: string;
    employerName: string | null;
    jobTitle: string | null;
    jobRelevance: string | null;
    jobRelevanceLabel: string;
    timeToEmployment: number | null;
    furtherStudy: boolean;
    submittedAt: string | null;
    showUrl: string | null;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; export: boolean };
    campaign: { id: number | null; title: string | null; status: string | null; url: string | null };
    stats: {
        total: number;
        working: number;
        entrepreneur: number;
        studying: number;
        unemployed: number;
        employed: number;
        employedPercentage: number;
        relevant: number;
        relevantPercentage: number;
        furtherStudy: number;
        avgTimeToEmployment: number | null;
    };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        campaign: string;
        study_program_id: string;
        graduation_year: string;
        employment_status: string;
        job_relevance: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    employmentStatusOptions: Record<string, string>;
    jobRelevanceOptions: Record<string, string>;
    campaignOptions: { id: number; title: string }[];
    studyProgramOptions: { id: number; name: string }[];
    graduationYearOptions: number[];
    urls: { index: string; export: string };
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined));
}

export default function TracerResponseIndex({
    shell,
    can,
    campaign,
    stats,
    data,
    filters,
    employmentStatusOptions,
    jobRelevanceOptions,
    campaignOptions,
    studyProgramOptions,
    graduationYearOptions,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        router.get(urls.index, buildQuery(filters, search, overrides), { preserveState: true, replace: true });
    };

    const activeFilterCount = [
        filters.campaign,
        filters.study_program_id,
        filters.graduation_year,
        filters.employment_status,
        filters.job_relevance,
    ].filter((v) => v !== '').length;

    const resetFilters = () => visit({
        campaign: '', study_program_id: '', graduation_year: '', employment_status: '', job_relevance: '', page: 1,
    });

    const toggleSort = (key: string) => {
        visit({ sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc', page: 1 });
    };

    const query = new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString();
    const exportHref = `${urls.export}?${query}`;

    return (
        <AdminShell shell={shell}>
            <Head title={`Respon Tracer Study · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BarChart3}
                        eyebrow="Alumni · Tracer Study"
                        title={campaign.id ? `Respon: ${campaign.title}` : 'Respon Tracer Study'}
                        description="Telusuri jawaban alumni, status pekerjaan, relevansi bidang kerja, dan kesiapan studi lanjut."
                        badges={[`${stats.total} respon`, `${stats.employedPercentage}% bekerja/wirausaha`, `${stats.relevantPercentage}% relevan`]}
                        actions={(
                            <>
                                {campaign.id && campaign.url && <a className="db-btn light" href={campaign.url}>Detail kampanye</a>}
                                <a className="db-btn light" href={urls.index}>Semua respon</a>
                                {can.export && <a className="db-btn light" href={urls.export}><Download size={14} /> Unduh CSV</a>}
                            </>
                        )}
                    />

                    <section className="db-stats" aria-label="Statistik respon">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total respon</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{stats.employed}</span><span className="db-stat-label">Bekerja / wirausaha</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><TrendingUp size={20} /></span>
                            <div><span className="db-stat-num">{stats.relevant}</span><span className="db-stat-label">Pekerjaan relevan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.furtherStudy}</span><span className="db-stat-label">Studi lanjut</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><TrendingUp size={20} /></span>
                            <div><span className="db-stat-num">{stats.avgTimeToEmployment ?? '-'}</span><span className="db-stat-label">Rata-rata bulan ke kerja</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Respon</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'alumni', label: 'Alumni',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.alumniName}</b>
                                                <small className="db-hint">{row.nim} · {row.studyProgram} · {row.graduationYear ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'employment_status', label: 'Status Kerja', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.employmentStatusLabel}</b>
                                                <small className="db-hint">{row.furtherStudy ? 'Melanjutkan studi' : 'Tidak studi lanjut'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'employer_name', label: 'Perusahaan / Jabatan', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.employerName ?? '-'}</b>
                                                <small className="db-hint">{row.jobTitle ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'job_relevance', label: 'Relevansi',
                                        render: (row) => (
                                            <span className={`db-badge ${row.jobRelevance === 'relevant' ? 'green' : row.jobRelevance === 'partially_relevant' ? 'amber' : 'gray'}`}>
                                                {row.jobRelevanceLabel}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'submitted_at', label: 'Dikirim', sortable: true,
                                        render: (row) => row.submittedAt ?? '-',
                                    },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 })}
                                searchPlaceholder="Cari nama, NIM, perusahaan, jabatan…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select className="db-input" value={filters.campaign} onChange={(e) => visit({ campaign: e.target.value, page: 1 })} aria-label="Filter kampanye">
                                            <option value="">Semua kampanye</option>
                                            {campaignOptions.map((option) => <option key={option.id} value={option.id}>{option.title}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.study_program_id} onChange={(e) => visit({ study_program_id: e.target.value, page: 1 })} aria-label="Filter program studi">
                                            <option value="">Semua program studi</option>
                                            {studyProgramOptions.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.graduation_year} onChange={(e) => visit({ graduation_year: e.target.value, page: 1 })} aria-label="Filter tahun lulus">
                                            <option value="">Semua tahun lulus</option>
                                            {graduationYearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.employment_status} onChange={(e) => visit({ employment_status: e.target.value, page: 1 })} aria-label="Filter status kerja">
                                            <option value="">Semua status kerja</option>
                                            {Object.entries(employmentStatusOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.job_relevance} onChange={(e) => visit({ job_relevance: e.target.value, page: 1 })} aria-label="Filter relevansi">
                                            <option value="">Semua relevansi</option>
                                            {Object.entries(jobRelevanceOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                selectable={false}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                onDeleteRow={undefined}
                                customActions={(row) => row.showUrl ? <a className="db-btn ghost sm" href={row.showUrl} title="Detail respon"><BarChart3 size={13} /></a> : null}
                                showActions={can.view}
                                canCreate={false}
                                exportHref={can.export ? exportHref : undefined}
                                exportExtra={can.export ? [
                                    { label: 'Semua tersaring (.pdf)', href: `${exportHref}&format=pdf` },
                                ] : []}
                                emptyText="Belum ada respon yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
