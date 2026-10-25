// Dashboard operasional layanan mahasiswa — antrean lintas layanan + SLA pengaduan.
import { Head } from '@inertiajs/react';
import { AlertTriangle, ClipboardList, GraduationCap, Headset, MailOpen, UserRoundCog } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type QueueItem = {
    id: number;
    number: string;
    student: string | null;
    nim: string | null;
    subtitle: string;
    status: string;
    statusLabel: string;
    url: string;
};

type Queue = { title: string; items: QueueItem[] };

type ComplaintItem = {
    id: number;
    number: string;
    subject: string;
    student: string;
    workUnit: string;
    status: string;
    statusLabel: string;
    dueAt: string | null;
    isOverdue: boolean;
    url: string;
};

type Props = {
    shell: ShellProps;
    stats: {
        pendingLetters: number;
        pendingLeaves: number;
        pendingTransfers: number;
        pendingGraduations: number;
        openComplaints: number;
        overdueComplaints: number;
    };
    queues: { letters: Queue; leaves: Queue; transfers: Queue; graduations: Queue };
    complaints: ComplaintItem[];
    urls: { index: string; complaints: string };
};

const tone = (status: string) => {
    if (['approved', 'issued', 'activated', 'applied', 'finalized', 'resolved', 'closed'].includes(status)) return 'green';
    if (['revision_requested', 'waiting_student', 'approved_pending_payment', 'in_approval'].includes(status)) return 'amber';
    if (['rejected', 'cancelled'].includes(status)) return 'red';
    if (['under_review', 'in_review', 'responded', 'reopened'].includes(status)) return 'gray';
    return 'gray';
};

function QueueCard({ queue }: { queue: Queue }) {
    return (
        <section className="db-card">
            <div className="db-card-head">
                <h2 className="db-card-title"><ClipboardList size={16} /> {queue.title}</h2>
                <span className="db-badge gray">{queue.items.length} terbaru</span>
            </div>
            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                {queue.items.length === 0 && <p className="db-hint">Tidak ada antrian aktif.</p>}
                {queue.items.map((item) => (
                    <a key={item.id} className="db-class" href={item.url} style={{ textDecoration: 'none' }}>
                        <div className="db-class-top">
                            <div style={{ minWidth: 0 }}>
                                <b>{item.number} · {item.student ?? '-'}</b>
                                <small>{item.nim ? `${item.nim} · ` : ''}{item.subtitle}</small>
                            </div>
                            <span className={`db-badge ${tone(item.status)}`}>{item.statusLabel}</span>
                        </div>
                    </a>
                ))}
            </div>
        </section>
    );
}

export default function StudentServicesDashboard({ shell, stats, queues, complaints, urls }: Props) {
    const cards = [
        { label: 'Surat Diproses', value: stats.pendingLetters, icon: <MailOpen size={20} />, tone: 'db-stat-icon' },
        { label: 'Cuti Diproses', value: stats.pendingLeaves, icon: <ClipboardList size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Pindah Diproses', value: stats.pendingTransfers, icon: <UserRoundCog size={20} />, tone: 'db-stat-icon' },
        { label: 'Yudisium Diproses', value: stats.pendingGraduations, icon: <GraduationCap size={20} />, tone: 'db-stat-icon green' },
        { label: 'Pengaduan Open', value: stats.openComplaints, icon: <Headset size={20} />, tone: 'db-stat-icon' },
        { label: 'Lewat Batas SLA', value: stats.overdueComplaints, icon: <AlertTriangle size={20} />, tone: 'db-stat-icon red' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Dashboard Layanan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Headset}
                        eyebrow="Layanan Mahasiswa"
                        title="Dashboard Layanan"
                        description="Pantau antrean surat, cuti, pindah, yudisium, dan pengaduan mahasiswa."
                        actions={<a className="db-btn light" href={urls.complaints}>Pengaduan</a>}
                    />

                    <section className="db-stats" aria-label="Statistik layanan mahasiswa">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div>
                                    <span className="db-stat-num">{card.value}</span>
                                    <span className="db-stat-label">{card.label}</span>
                                </div>
                            </div>
                        ))}
                    </section>

                    <div className="cx-cards-2">
                        <QueueCard queue={queues.letters} />
                        <QueueCard queue={queues.leaves} />
                    </div>

                    <div className="cx-cards-2">
                        <QueueCard queue={queues.transfers} />
                        <QueueCard queue={queues.graduations} />
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <div>
                                <h2 className="db-card-title"><Headset size={16} /> Pengaduan Prioritas SLA</h2>
                                <p className="db-hint" style={{ margin: '4px 0 0' }}>
                                    Urutan paling atas adalah tiket yang mendekati atau melewati batas waktu SLA.
                                </p>
                            </div>
                            <a className="db-btn ghost sm" href={urls.complaints}>Lihat Semua</a>
                        </div>
                        <div className="db-card-body">
                            {complaints.length === 0 ? (
                                <p className="db-hint">Tidak ada pengaduan aktif.</p>
                            ) : (
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 12 }}>
                                    {complaints.map((item) => (
                                        <a key={item.id} className="db-class" href={item.url} style={{ textDecoration: 'none' }}>
                                            <div className="db-class-top">
                                                <span className={`db-badge ${tone(item.status)}`}>{item.statusLabel}</span>
                                                <span className={`db-hint ${item.isOverdue ? 'db-error' : ''}`} style={item.isOverdue ? { fontWeight: 700 } : undefined}>
                                                    {item.dueAt ?? '-'}
                                                </span>
                                            </div>
                                            <div style={{ marginTop: 8 }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13.5 }}>{item.number} · {item.subject}</b>
                                                <small style={{ color: 'var(--db-muted)' }}>{item.student} / {item.workUnit}</small>
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
