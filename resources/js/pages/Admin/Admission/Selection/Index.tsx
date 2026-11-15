// Perankingan & keputusan seleksi PMB.
import { Head, router } from '@inertiajs/react';
import { Award, CheckCircle2, Clock, Filter, Users, XCircle } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: number | string; label: string };

type Row = {
    id: number; rank: number; applicationNumber: string; fullName: string;
    studyProgram: string | null; classType: string | null; classTypeLabel: string;
    finalScore: string | number | null; scoresCount: number;
    quotaLimit: number | null; quotaUsed: number | null; quotaFull: boolean;
    status: string; statusLabel: string; decisionUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean };
    stats: { candidates: number; accepted: number; waitlisted: number; rejected: number };
    data: { rows: Row[]; total: number };
    filters: { period_id: string; study_program_id: string; class_type: string };
    options: {
        periods: Option[]; studyPrograms: Option[]; classTypes: Option[];
        decisions: Option[];
    };
    urls: { index: string; bulkDecision: string };
};

function statusBadge(status: string) {
    switch (status) {
        case 'accepted':
            return 'green';
        case 'rejected':
            return 'red';
        case 'waitlisted':
        case 'under_review':
            return 'amber';
        default:
            return 'gray';
    }
}

export default function AdmissionSelectionIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkStatus, setBulkStatus] = useState('accepted');
    const [pendingBulk, setPendingBulk] = useState(false);
    const [processingBulk, setProcessingBulk] = useState(false);
    const [rowProcessing, setRowProcessing] = useState<string | null>(null);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, ...overrides }).filter(([, value]) => value !== '' && value !== undefined),
        );
        router.get(urls.index, params, { preserveState: true, replace: true });
    };

    const activeFilterCount = [filters.period_id, filters.study_program_id, filters.class_type]
        .filter((value) => value !== '' && value !== undefined).length;

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const decide = (row: Row, status: string) => {
        setRowProcessing(`${row.id}:${status}`);
        router.post(row.decisionUrl, { status }, {
            preserveScroll: true,
            onFinish: () => setRowProcessing(null),
        });
    };

    const applyBulk = () => {
        setProcessingBulk(true);
        router.post(urls.bulkDecision, { ids: selected, status: bulkStatus }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessingBulk(false);
                setPendingBulk(false);
                setSelected([]);
            },
        });
    };

    const decisionLabel = options.decisions.find((decision) => decision.id === bulkStatus)?.label ?? bulkStatus;

    return (
        <AdminShell shell={shell}>
            <Head title={`Perankingan & Seleksi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Admission"
                        title="Perankingan & Seleksi"
                        description="Evaluasi skor akhir ujian, pantau keterisian kuota program studi, dan tetapkan keputusan kelulusan secara individual atau massal."
                    />

                    <section className="db-stats" aria-label="Statistik seleksi">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{stats.candidates}</span><span className="db-stat-label">Dinilai & seleksi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.accepted}</span><span className="db-stat-label">Lulus (diterima)</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Clock size={20} /></span>
                            <div><span className="db-stat-num">{stats.waitlisted}</span><span className="db-stat-label">Cadangan (waitlist)</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><XCircle size={20} /></span>
                            <div><span className="db-stat-num">{stats.rejected}</span><span className="db-stat-label">Tidak lulus</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Filter size={16} /> Filter Peringkat</h2>
                            <FilterPanel count={activeFilterCount} onReset={() => visit({ period_id: '', study_program_id: '', class_type: '' })}>
                                <select
                                    className="db-input"
                                    value={filters.period_id}
                                    onChange={(e) => visit({ period_id: e.target.value })}
                                    aria-label="Filter periode"
                                >
                                    <option value="">Semua periode</option>
                                    {options.periods.map((period) => <option key={period.id} value={period.id}>{period.label}</option>)}
                                </select>
                                <select
                                    className="db-input"
                                    value={filters.study_program_id}
                                    onChange={(e) => visit({ study_program_id: e.target.value })}
                                    aria-label="Filter program studi"
                                >
                                    <option value="">Semua program studi</option>
                                    {options.studyPrograms.map((program) => <option key={program.id} value={program.id}>{program.label}</option>)}
                                </select>
                                <select
                                    className="db-input"
                                    value={filters.class_type}
                                    onChange={(e) => visit({ class_type: e.target.value })}
                                    aria-label="Filter tipe kelas"
                                >
                                    <option value="">Semua tipe kelas</option>
                                    {options.classTypes.map((type) => <option key={type.id} value={type.id}>{type.label}</option>)}
                                </select>
                            </FilterPanel>
                        </div>
                        <div className="db-card-body">
                            <p className="db-hint" style={{ margin: 0 }}>
                                Peringkat dihitung dari akumulasi skor seleksi (tertinggi ke terendah). Kuota yang penuh akan menolak keputusan “Terima”.
                            </p>
                        </div>
                    </section>

                    {can.update && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Keputusan Massal</h2>
                                <span className="db-badge">{selected.length} dipilih</span>
                            </div>
                            <div className="db-card-body">
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'flex-end' }}>
                                    <label style={{ flex: '1 1 260px' }}>
                                        <span className="crud-label">Status keputusan</span>
                                        <select
                                            className="db-input"
                                            value={bulkStatus}
                                            onChange={(e) => setBulkStatus(e.target.value)}
                                            aria-label="Status keputusan massal"
                                        >
                                            {options.decisions.map((decision) => (
                                                <option key={decision.id} value={decision.id}>{decision.label}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <button
                                        className="db-btn primary"
                                        type="button"
                                        disabled={selected.length === 0}
                                        onClick={() => setPendingBulk(true)}
                                    >
                                        Terapkan ke Terpilih ({selected.length})
                                    </button>
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Award size={16} /> Tabel Peringkat</h2>
                            <span className="db-hint">{data.total} pendaftar</span>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th style={{ width: 36 }}>
                                                <input
                                                    type="checkbox"
                                                    checked={data.rows.length > 0 && data.rows.every((row) => selected.includes(row.id))}
                                                    onChange={toggleAll}
                                                    aria-label="Pilih semua pendaftar"
                                                />
                                            </th>
                                            <th>Peringkat</th>
                                            <th>Pendaftar</th>
                                            <th>Program Studi</th>
                                            <th>Skor Akhir</th>
                                            <th>Nilai Ujian</th>
                                            <th>Kuota</th>
                                            <th>Status</th>
                                            {can.update && <th style={{ textAlign: 'right' }}>Keputusan</th>}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.rows.length === 0 && (
                                            <tr>
                                                <td colSpan={can.update ? 9 : 8} style={{ textAlign: 'center', padding: 24 }}>
                                                    <span className="db-hint">Belum ada pendaftar yang cocok dengan filter.</span>
                                                </td>
                                            </tr>
                                        )}
                                        {data.rows.map((row) => (
                                            <tr key={row.id}>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        checked={selected.includes(row.id)}
                                                        onChange={() => toggle(row.id)}
                                                        aria-label={`Pilih ${row.fullName}`}
                                                    />
                                                </td>
                                                <td><span className="db-badge">#{row.rank}</span></td>
                                                <td>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.fullName}</b>
                                                    <small className="db-hint">{row.applicationNumber}</small>
                                                </td>
                                                <td>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.studyProgram ?? '-'}</b>
                                                    <small className="db-hint">{row.classTypeLabel}</small>
                                                </td>
                                                <td><b style={{ color: 'var(--db-heading)' }}>{row.finalScore ?? '-'}</b></td>
                                                <td><span className="db-badge gray">{row.scoresCount} komponen</span></td>
                                                <td>
                                                    {row.quotaLimit !== null ? (
                                                        <span className={`db-badge ${row.quotaFull ? 'red' : 'green'}`}>
                                                            {row.quotaUsed}/{row.quotaLimit} terisi
                                                        </span>
                                                    ) : (
                                                        <span className="db-hint">Tanpa batas</span>
                                                    )}
                                                </td>
                                                <td><span className={`db-badge ${statusBadge(row.status)}`}>{row.statusLabel}</span></td>
                                                {can.update && (
                                                    <td>
                                                        <div className="crud-row-actions">
                                                            <button
                                                                className="db-btn primary sm"
                                                                type="button"
                                                                title="Terima"
                                                                disabled={rowProcessing !== null || (row.quotaFull && row.status !== 'accepted')}
                                                                onClick={() => decide(row, 'accepted')}
                                                            >
                                                                Terima
                                                            </button>
                                                            <button
                                                                className="db-btn ghost sm"
                                                                type="button"
                                                                title="Cadangan"
                                                                disabled={rowProcessing !== null}
                                                                onClick={() => decide(row, 'waitlisted')}
                                                            >
                                                                Cadangan
                                                            </button>
                                                            <button
                                                                className="db-btn danger sm"
                                                                type="button"
                                                                title="Tolak"
                                                                disabled={rowProcessing !== null}
                                                                onClick={() => decide(row, 'rejected')}
                                                            >
                                                                Tolak
                                                            </button>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pendingBulk}
                    title="Terapkan keputusan massal?"
                    message={`${selected.length} pendaftar akan diubah menjadi “${decisionLabel}”. Pendaftar dengan kuota penuh akan dilewati saat memilih Terima.`}
                    confirmLabel="Ya, terapkan"
                    danger={false}
                    processing={processingBulk}
                    onConfirm={applyBulk}
                    onCancel={() => setPendingBulk(false)}
                />
            </div>
        </AdminShell>
    );
}
