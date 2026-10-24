// Evaluasi Dosen oleh Mahasiswa (EDOM) — daftar target (Inertia React).
// Target hanya kelas yang masih aktif di KRS pada periode evaluasi.
import { Head, Link } from '@inertiajs/react';
import {
    CalendarCheck, CalendarDays, CheckCircle2, ClipboardList, Inbox,
    Star, TriangleAlert, UserRound,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Period = { id: number; name: string; startsAt: string | null; endsAt: string | null };

type Target = {
    periodId: number;
    offeringId: number;
    lecturerId: number;
    course: string;
    label: string | null;
    academicYear: string;
    lecturer: string;
    submittedAt: string | null;
    completed: boolean;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    period: Period | null;
    targets: Target[];
    stats: { total: number; completed: number; pending: number };
};

export default function EdomIndex({ shell, hasProfile, period, targets, stats }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Evaluasi Dosen · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Academic"
                        title="Evaluasi Dosen (EDOM)"
                        description="Beri penilaian atas proses pembelajaran dosen pada kelas yang kamu ambil. Jawaban bersifat anonim."
                        badges={period
                            ? [period.name, `${stats.completed}/${stats.total} selesai`]
                            : ['Belum ada periode aktif']}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Evaluasi tidak bisa ditampilkan.</span>
                        </div>
                    )}

                    {period === null ? (
                        <div className="db-note warning">
                            <CalendarDays size={15} />
                            <span><b>Tidak ada periode EDOM yang dibuka.</b> Evaluasi akan tersedia saat admin membuka periode penilaian.</span>
                        </div>
                    ) : (
                        <>
                            <div className="db-note info">
                                <CalendarCheck size={15} />
                                <span>
                                    Periode <b>{period.name}</b>
                                    {period.startsAt && ` · ${period.startsAt}`}
                                    {period.endsAt && ` s.d. ${period.endsAt}`}.
                                    Satu dosen per kelas hanya bisa dievaluasi sekali.
                                </span>
                            </div>

                            <section className="db-stats">
                                <div className="db-stat">
                                    <span className="db-stat-icon"><UserRound size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.total}</span>
                                        <span className="db-stat-label">Dosen / kelas</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.completed}</span>
                                        <span className="db-stat-label">Sudah dinilai</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon gold"><Star size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{stats.pending}</span>
                                        <span className="db-stat-label">Menunggu penilaian</span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ClipboardList size={16} /> Daftar Evaluasi</h2>
                                    <span className="db-badge gray">{targets.length} kelas</span>
                                </div>
                                <div className="db-card-body">
                                    {targets.length === 0 ? (
                                        <EmptyState
                                            icon={Inbox}
                                            title="Belum ada kelas untuk dievaluasi"
                                            description="Target evaluasi muncul dari kelas yang masih aktif di KRS pada tahun akademik periode ini."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 10 }}>
                                            {targets.map((target) => (
                                                <article key={`${target.offeringId}-${target.lecturerId}`} className="db-class">
                                                    <div className="db-class-top">
                                                        <div style={{ minWidth: 0 }}>
                                                            <b>{target.course}</b>
                                                            <small>
                                                                <UserRound size={11} /> {target.lecturer}
                                                                {target.label ? ` · Kelas ${target.label}` : ''}
                                                                {` · ${target.academicYear}`}
                                                            </small>
                                                        </div>
                                                        <span className={`db-badge ${target.completed ? 'green' : 'amber'}`}>
                                                            {target.completed ? 'Sudah dinilai' : 'Belum dinilai'}
                                                        </span>
                                                    </div>

                                                    <div className="db-class-actions">
                                                        {target.completed ? (
                                                            <span className="db-hint">
                                                                <CheckCircle2 size={12} /> Dikirim {target.submittedAt ?? '-'}
                                                            </span>
                                                        ) : (
                                                            <Link href={target.url} className="db-btn primary sm">
                                                                <Star size={13} /> Isi Evaluasi
                                                            </Link>
                                                        )}
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
