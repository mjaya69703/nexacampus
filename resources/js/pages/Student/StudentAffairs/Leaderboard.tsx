// Portal mahasiswa — poin dan papan peringkat kemahasiswaan.
import { Head } from '@inertiajs/react';
import { Award, CalendarDays, Crown, Star, TrendingUp, Trophy, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StudentAffairsStats, toneClass } from '../../Admin/StudentAffairs/shared';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type LeaderRow = {
    rank: number;
    userId: number;
    name: string;
    nim: string | null;
    program: string | null;
    total: number;
    isMe: boolean;
};

type Entry = {
    id: number;
    points: number;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    description: string;
    awardedAt: string | null;
};

type Props = {
    shell: ShellProps;
    student: { name: string; nim: string | null; program: string | null };
    stats: { myPoints: number; myRank: number; participants: number; myEntries: number };
    leaderboard: LeaderRow[];
    entries: Entry[];
    urls: { events: string };
};

export default function StudentLeaderboard({ shell, student, stats, leaderboard, entries, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Poin & Leaderboard · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Trophy}
                        eyebrow="Kemahasiswaan"
                        title="Poin & Leaderboard"
                        description={`Poin dari keikutsertaan event dan kontribusi kemahasiswaan ${student.name}.`}
                        badges={[
                            student.nim || 'NIM belum tersedia',
                            student.program || 'Program studi belum tersedia',
                        ]}
                        actions={(
                            <a className="db-btn light" href={urls.events}><CalendarDays size={15} /> Event Mahasiswa</a>
                        )}
                    />

                    <StudentAffairsStats
                        label="Ringkasan poin mahasiswa"
                        items={[
                            { label: 'Total poin saya', value: stats.myPoints, icon: Star, tone: 'gold' },
                            { label: 'Peringkat saya', value: `#${stats.myRank}`, icon: Crown, tone: 'green' },
                            { label: 'Mahasiswa terlibat', value: stats.participants, icon: Users },
                            { label: 'Entri poin saya', value: stats.myEntries, icon: Award },
                        ]}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Crown size={15} /> Papan Peringkat</h2>
                                    <span className="db-badge gold">20 besar</span>
                                </div>
                                <div className="db-card-body">
                                    {leaderboard.length === 0 ? (
                                        <EmptyState
                                            icon={Trophy}
                                            iconSize={24}
                                            title="Belum ada peringkat"
                                            description="Poin akan muncul setelah event pertama selesai dan kehadiran dicatat."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 7 }}>
                                            {leaderboard.map((row) => (
                                                <div
                                                    key={row.userId}
                                                    className="db-fact wide"
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 12,
                                                        borderColor: row.isMe ? 'var(--db-brand)' : undefined,
                                                    }}
                                                >
                                                    <span className={`db-badge ${row.rank === 1 ? 'gold' : row.rank <= 3 ? 'amber' : 'gray'}`} style={{ minWidth: 36, justifyContent: 'center' }}>
                                                        #{row.rank}
                                                    </span>
                                                    <span style={{ flex: 1, minWidth: 0 }}>
                                                        <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>
                                                            {row.name}{row.isMe ? ' (Anda)' : ''}
                                                        </b>
                                                        <small className="db-hint">{row.nim || '—'}{row.program ? ` · ${row.program}` : ''}</small>
                                                    </span>
                                                    <span className="db-badge gold">{row.total} poin</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><TrendingUp size={15} /> Riwayat Poin Saya</h2>
                                    <span className="db-badge gray">{stats.myEntries} entri</span>
                                </div>
                                <div className="db-card-body">
                                    {entries.length === 0 ? (
                                        <EmptyState
                                            icon={Star}
                                            iconSize={24}
                                            title="Belum ada poin"
                                            description="Ikuti event kemahasiswaan; kehadiran yang dicatat admin akan memberi poin otomatis."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 9 }}>
                                            {entries.map((entry) => (
                                                <article key={entry.id} className="db-fact wide" style={{ display: 'grid', gap: 6 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 6 }}>
                                                        <b style={{ color: 'var(--db-heading)', fontSize: 12.5 }}>{entry.description}</b>
                                                        <span className={`db-badge ${entry.points >= 0 ? 'green' : 'red'}`}>
                                                            {entry.points > 0 ? `+${entry.points}` : entry.points}
                                                        </span>
                                                    </div>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                                        <span className={`db-badge ${toneClass(entry.categoryTone)}`}>{entry.categoryLabel}</span>
                                                        <span className="db-hint">{entry.awardedAt || 'Tanggal belum dicatat'}</span>
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <div className="adm-note gold" style={{ margin: 0 }}>
                                <Star size={16} />
                                <div>
                                    <b style={{ display: 'block', marginBottom: 2, color: 'var(--db-heading)' }}>Cara mendapat poin.</b>
                                    Poin diberikan saat kehadiran event dicatat admin, atau melalui penyesuaian manual oleh pengelola kemahasiswaan. Peringkat dihitung dari total poin seluruh mahasiswa.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
