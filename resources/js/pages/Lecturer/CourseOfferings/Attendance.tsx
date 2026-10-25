// Absensi kelas — daftar sesi, buka QR, dan lihat token QR aktif.
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarCheck, Check, Copy, Link2, Play, QrCode, RefreshCw, Users, X } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type SessionRow = {
    id: number; meetingNo: number | null; date: string; time: string; topic: string;
    status: string; attendanceMethod: string; recordsCount: number;
    presentCount: number; lateCount: number; absentCount: number;
    editUrl: string; openUrl: string; qrUrl: string;
};

type QrPayload = { session_id: number; slot: number; token: string; interval: number; expires_at: number; url: string } | null;
type QrState = { meetingNo: number | null; topic: string; status: string; openedAt: string; payload: QrPayload } | null;

type Props = {
    shell: ShellProps;
    offeringId: number;
    classInfo: { course: string; class: string; academicYear: string; studyProgram: string };
    sessions: SessionRow[];
    stats: { sessions: number; opened: number; closed: number; enrolled: number };
    urls: { index: string; show: string };
};

const tone = (status: string) => {
    if (status === 'Closed') return 'green';
    if (status === 'Opened') return 'amber';
    if (status === 'Cancelled') return 'red';
    return 'gray';
};

export default function CourseOfferingsAttendance({ shell, classInfo, sessions, stats, urls }: Props) {
    const [busyId, setBusyId] = useState<number | null>(null);
    const [qr, setQr] = useState<QrState>(null);
    const [qrLoading, setQrLoading] = useState(false);
    const [copied, setCopied] = useState(false);

    const loadQr = (session: SessionRow) => {
        setQrLoading(true);
        fetch(session.qrUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => setQr(data))
            .catch(() => setQr({ meetingNo: session.meetingNo, topic: session.topic, status: session.status, openedAt: '-', payload: null }))
            .finally(() => setQrLoading(false));
    };

    const openSession = (session: SessionRow) => {
        setBusyId(session.id);
        router.post(session.openUrl, {}, {
            preserveScroll: true,
            onSuccess: () => loadQr(session),
            onFinish: () => setBusyId(null),
        });
    };

    const copyPayload = () => {
        if (!qr?.payload) return;
        navigator.clipboard.writeText(qr.payload.url).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1600);
        });
    };

    const cards = [
        { label: 'Sesi', value: stats.sessions, icon: <CalendarCheck size={20} />, tone: 'db-stat-icon' },
        { label: 'Dibuka', value: stats.opened, icon: <Play size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Selesai', value: stats.closed, icon: <Check size={20} />, tone: 'db-stat-icon green' },
        { label: 'Mahasiswa', value: stats.enrolled, icon: <Users size={20} />, tone: 'db-stat-icon' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Absensi ${classInfo.course} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Akademik · Kelas"
                        title="Absensi Kelas"
                        description={`${classInfo.course} · ${classInfo.class} · ${classInfo.academicYear}`}
                        badges={[`${stats.sessions} sesi`, `${stats.opened} dibuka`]}
                        actions={<Link className="db-btn light" href={urls.show}><ArrowLeft size={15} /> Detail kelas</Link>}
                    />

                    <section className="db-stats" aria-label="Ringkasan absensi">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div><span className="db-stat-num">{card.value}</span><span className="db-stat-label">{card.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Sesi absensi</h2>
                        </div>
                        <div className="db-card-body">
                            {sessions.length === 0 ? (
                                <EmptyState icon={CalendarCheck} title="Belum ada sesi" description="Sesi absensi dibuat dari jadwal kelas." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Pertemuan</th><th>Tanggal</th><th>Topik</th><th>Status</th>
                                                <th>Hadir</th><th>Telat</th><th>Alpa</th><th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sessions.map((session) => (
                                                <tr key={session.id}>
                                                    <td><b>{session.meetingNo ?? '-'}</b><br /><small>{session.time}</small></td>
                                                    <td>{session.date}</td>
                                                    <td>{session.topic}</td>
                                                    <td><span className={`db-badge ${tone(session.status)}`}>{session.status}</span></td>
                                                    <td>{session.presentCount}/{stats.enrolled}</td>
                                                    <td>{session.lateCount}</td>
                                                    <td>{session.absentCount}</td>
                                                    <td>
                                                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                            <a className="db-btn ghost sm" href={session.editUrl}>Isi manual</a>
                                                            <button className="db-btn ghost sm" type="button" disabled={busyId === session.id} onClick={() => openSession(session)}>
                                                                <Play size={12} /> {busyId === session.id ? 'Membuka…' : 'Buka QR'}
                                                            </button>
                                                            <button className="db-btn ghost sm" type="button" onClick={() => loadQr(session)}>
                                                                <QrCode size={12} /> Lihat QR
                                                            </button>
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

            {qr && (
                <div className="db-modal-backdrop" role="presentation" onClick={() => setQr(null)}>
                    <div className="db-modal" role="dialog" aria-modal="true" aria-label="QR absensi" onClick={(event) => event.stopPropagation()}>
                        <div className="db-modal-icon"><QrCode size={22} /></div>
                        <h3>Absensi pertemuan {qr.meetingNo ?? '-'}</h3>
                        <p>{qr.topic} · status {qr.status} · dibuka {qr.openedAt}</p>

                        {qrLoading && <p className="db-hint">Memuat token…</p>}

                        {!qrLoading && !qr.payload && (
                            <div className="db-note warning">Sesi belum dibuka. Klik “Buka QR” terlebih dahulu.</div>
                        )}

                        {qr.payload && (
                            <div style={{ display: 'grid', gap: 10 }}>
                                <div className="db-note info"><Link2 size={15} /><span>Token berganti setiap {qr.payload.interval} detik (slot {qr.payload.slot}).</span></div>
                                <label className="crud-label">Tautan absensi mahasiswa</label>
                                <textarea className="db-input" rows={4} readOnly value={qr.payload.url} />
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    <button className="db-btn primary sm" type="button" onClick={copyPayload}>
                                        {copied ? <Check size={13} /> : <Copy size={13} />} {copied ? 'Tersalin' : 'Salin tautan'}
                                    </button>
                                    <button className="db-btn ghost sm" type="button" onClick={() => setQr(null)}>
                                        <RefreshCw size={13} /> Tutup
                                    </button>
                                </div>
                                <small className="db-hint">Masa berlaku slot: {new Date(qr.payload.expires_at * 1000).toLocaleTimeString('id-ID')}</small>
                            </div>
                        )}

                        <div className="db-modal-actions">
                            <button className="db-btn ghost" type="button" onClick={() => setQr(null)}><X size={14} /> Tutup</button>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
}
