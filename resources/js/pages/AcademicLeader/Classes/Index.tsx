// Monitoring kelas dan kehadiran dalam scope pimpinan akademik (Inertia React).
import { Head, router } from '@inertiajs/react';
import {
    BookOpen, CalendarCheck, Clock3, DoorOpen, FileSpreadsheet, FilterX,
    Search, TriangleAlert, Users,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number;
    course: string;
    label: string;
    code: string;
    program: string;
    faculty: string;
    academicYear: string;
    status: string;
    lecturers: string[];
    lecturerCount: number;
    scheduleCount: number;
    studentCount: number;
    targetMeetings: number;
    sessionCount: number;
    openedSessions: number;
    closedSessions: number;
    cancelledSessions: number;
    missingMeetings: number;
    openedTooLong: number;
    attendanceRate: number | null;
    riskLevel: string;
    riskLabel: string;
};

type Props = {
    shell: ShellProps;
    rows: Row[];
    filters: { q: string; risk: string };
    riskOptions: Record<string, string>;
    stats: { classes: number; opened: number; problem: number; lowAttendance: number };
    urls: { index: string; export: Record<string, string> };
};

const riskTone = (risk: string) => {
    if (risk === 'danger') return 'red';
    if (risk === 'warning') return 'amber';
    return 'green';
};

export default function ClassIndex({ shell, rows, filters, riskOptions, stats, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Record<string, string | undefined>) => {
        const params: Record<string, string> = {
            q: search,
            risk: filters.risk,
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
            <Head title={`Kelas & Kehadiran · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Pemantauan akademik"
                        title="Kelas & Kehadiran"
                        description="Monitor kelas aktif, dosen pengampu, sesi absensi, dan kepatuhan pertemuan."
                        badges={[
                            `${stats.classes} kelas`,
                            `${stats.opened} sesi dibuka`,
                            `${stats.problem} perlu perhatian`,
                            `${stats.lowAttendance} kehadiran rendah`,
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
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.classes}</span>
                                <span className="db-stat-label">Kelas</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><DoorOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.opened}</span>
                                <span className="db-stat-label">Sesi dibuka</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><TriangleAlert size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.problem}</span>
                                <span className="db-stat-label">Perlu perhatian</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CalendarCheck size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.lowAttendance}</span>
                                <span className="db-stat-label">Kehadiran rendah</span>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submitSearch} style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center' }}>
                                <div style={{ display: 'flex', gap: 8, flex: '1 1 280px', minWidth: 220 }}>
                                    <input
                                        className="db-input"
                                        type="search"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Cari kelas, kode, prodi, atau dosen..."
                                        aria-label="Cari kelas"
                                    />
                                    <button type="submit" className="db-btn primary" aria-label="Cari"><Search size={15} /></button>
                                </div>
                                <select
                                    className="db-input"
                                    style={{ width: 'auto', minWidth: 180 }}
                                    value={filters.risk}
                                    onChange={(event) => visit({ risk: event.target.value })}
                                    aria-label="Filter risiko kelas"
                                >
                                    {Object.entries(riskOptions).map(([value, label]) => (
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
                            <h2 className="db-card-title"><BookOpen size={16} /> Monitoring Kelas</h2>
                            <span className="db-badge gray">{rows.length} ditampilkan</span>
                        </div>
                        <div className="db-card-body">
                            {rows.length === 0 ? (
                                <div className="db-empty-calm">
                                    <BookOpen size={22} />
                                    <div>
                                        <b>Tidak ada kelas ditemukan.</b>
                                        <small>Coba ubah kata kunci atau filter risiko.</small>
                                    </div>
                                </div>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Kelas</th>
                                                <th>Dosen Pengampu</th>
                                                <th>Mahasiswa & Sesi</th>
                                                <th>Risiko</th>
                                                <th style={{ textAlign: 'right' }}>Kehadiran</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row) => (
                                                <tr key={row.id}>
                                                    <td>
                                                        <div style={{ color: 'var(--db-heading)', fontWeight: 700 }}>{row.course}</div>
                                                        <div className="db-hint">Kelas {row.label} / {row.code} · {row.academicYear}</div>
                                                        <div className="db-hint">{row.program}</div>
                                                    </td>
                                                    <td>
                                                        {row.lecturers.length > 0 ? (
                                                            <div style={{ color: 'var(--db-heading)', fontWeight: 600 }}>
                                                                {row.lecturers.join(', ')}
                                                            </div>
                                                        ) : (
                                                            <span className="db-badge red">Belum ada dosen</span>
                                                        )}
                                                        <div className="db-hint">{row.scheduleCount} jadwal aktif · {row.lecturerCount} dosen</div>
                                                    </td>
                                                    <td>
                                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5 }}>
                                                            <span className="db-badge gray"><Users size={11} /> {row.studentCount} mhs</span>
                                                            <span className="db-badge gray">
                                                                <CalendarCheck size={11} /> {row.sessionCount}/{row.targetMeetings || '-'} sesi
                                                            </span>
                                                            <span className="db-badge gray">
                                                                <DoorOpen size={11} /> {row.openedSessions} dibuka
                                                            </span>
                                                            {row.openedTooLong > 0 && (
                                                                <span className="db-badge amber">
                                                                    <Clock3 size={11} /> {row.openedTooLong} belum ditutup
                                                                </span>
                                                            )}
                                                            {row.missingMeetings > 0 && (
                                                                <span className="db-badge amber">{row.missingMeetings} sesi kurang</span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span className={`db-badge ${riskTone(row.riskLevel)}`}>{row.riskLabel}</span>
                                                        {row.cancelledSessions > 0 && (
                                                            <div className="db-hint">{row.cancelledSessions} sesi dibatalkan</div>
                                                        )}
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <div style={{ color: 'var(--db-heading)', font: "700 20px/1.1 'Source Serif 4', Georgia, serif" }}>
                                                            {row.attendanceRate !== null ? `${row.attendanceRate}%` : '-'}
                                                        </div>
                                                        <span className="db-hint">dari sesi dinilai</span>
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
