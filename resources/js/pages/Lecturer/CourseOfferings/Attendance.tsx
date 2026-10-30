// Absensi kelas — daftar sesi, QR bergulir (polling), dan penutupan sesi.
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarCheck,
    Check,
    Copy,
    Link2,
    Play,
    QrCode,
    RefreshCw,
    Square,
    TriangleAlert,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type SessionRow = {
    id: number; meetingNo: number | null; date: string; time: string; topic: string;
    status: string; attendanceMethod: string; recordsCount: number;
    presentCount: number; lateCount: number; absentCount: number;
    editUrl: string; openUrl: string; closeUrl: string; qrUrl: string;
};

type QrPayload = {
    session_id: number; slot: number; token: string; interval: number;
    expires_at: number; url: string; svg: string;
} | null;

type QrState = {
    meetingNo: number | null; topic: string; status: string; openedAt: string; payload: QrPayload;
} | null;

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

const statusLabel = (status: string) => {
    if (status === 'Opened') return 'Dibuka';
    if (status === 'Closed') return 'Ditutup';
    if (status === 'Cancelled') return 'Dibatalkan';
    return status;
};

export default function CourseOfferingsAttendance({ shell, classInfo, sessions, stats, urls }: Props) {
    const [busyId, setBusyId] = useState<number | null>(null);
    const [qrSession, setQrSession] = useState<SessionRow | null>(null);
    const [qr, setQr] = useState<QrState>(null);
    const [qrLoading, setQrLoading] = useState(false);
    const [copied, setCopied] = useState(false);
    const [confirmClose, setConfirmClose] = useState(false);

    const fetchQr = (session: SessionRow, silent = false) => {
        if (!silent) setQrLoading(true);

        fetch(session.qrUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data: QrState) => setQr(data))
            .catch(() => {
                if (!silent) {
                    setQr({
                        meetingNo: session.meetingNo,
                        topic: session.topic,
                        status: session.status,
                        openedAt: '-',
                        payload: null,
                    });
                }
            })
            .finally(() => {
                if (!silent) setQrLoading(false);
            });
    };

    const loadQr = (session: SessionRow) => {
        setQrSession(session);
        setConfirmClose(false);
        setQr(null);
        fetchQr(session);
    };

    const closeQr = () => {
        setQrSession(null);
        setQr(null);
        setConfirmClose(false);
        setCopied(false);
    };

    // Token QR berganti tiap beberapa detik; polling menjaga QR di layar tetap valid.
    useEffect(() => {
        if (!qrSession || qr?.status !== 'Opened') return;

        const seconds = Math.max(2, qr.payload?.interval ?? 2);
        const timer = window.setInterval(() => fetchQr(qrSession, true), seconds * 1000);

        return () => window.clearInterval(timer);
    }, [qrSession, qr?.status, qr?.payload?.interval]);

    const openSession = (session: SessionRow) => {
        setBusyId(session.id);
        router.post(session.openUrl, {}, {
            preserveScroll: true,
            onSuccess: () => loadQr(session),
            onFinish: () => setBusyId(null),
        });
    };

    const closeSession = () => {
        if (!qrSession) return;

        setBusyId(qrSession.id);
        router.post(qrSession.closeUrl, {}, {
            preserveScroll: true,
            onSuccess: () => closeQr(),
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
                                            {sessions.map((session) => {
                                                const isOpen = session.status === 'Opened';

                                                return (
                                                    <tr key={session.id}>
                                                        <td><b>{session.meetingNo ?? '-'}</b><br /><small>{session.time}</small></td>
                                                        <td>{session.date}</td>
                                                        <td>{session.topic}</td>
                                                        <td><span className={`db-badge ${tone(session.status)}`}>{statusLabel(session.status)}</span></td>
                                                        <td>{session.presentCount}/{stats.enrolled}</td>
                                                        <td>{session.lateCount}</td>
                                                        <td>{session.absentCount}</td>
                                                        <td>
                                                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                                <a className="db-btn ghost sm" href={session.editUrl}>Isi manual</a>
                                                                {isOpen ? (
                                                                    <button className="db-btn primary sm" type="button" onClick={() => loadQr(session)}>
                                                                        <QrCode size={12} /> QR aktif
                                                                    </button>
                                                                ) : (
                                                                    <button className="db-btn ghost sm" type="button" disabled={busyId === session.id} onClick={() => openSession(session)}>
                                                                        <Play size={12} /> {busyId === session.id ? 'Membuka…' : 'Buka QR'}
                                                                    </button>
                                                                )}
                                                                <button className="db-btn ghost sm" type="button" onClick={() => loadQr(session)}>
                                                                    <QrCode size={12} /> Lihat QR
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>

            {qrSession && typeof document !== 'undefined' && createPortal(
                <div className="db-root">
                    <div className="db-modal-backdrop" role="presentation" onClick={closeQr}>
                        <div
                            className="db-modal wide"
                            role="dialog"
                            aria-modal="true"
                            aria-label="QR absensi"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <div className="db-modal-icon"><QrCode size={22} /></div>
                            <h3>Absensi pertemuan {qr?.meetingNo ?? qrSession.meetingNo ?? '-'}</h3>
                            <p>
                                {qr?.topic ?? qrSession.topic} · {statusLabel(qr?.status ?? qrSession.status)}
                                {qr?.openedAt && qr.openedAt !== '-' ? ` · dibuka ${qr.openedAt}` : ''}
                            </p>

                            {qrLoading && <p className="db-hint">Memuat QR…</p>}

                            {!qrLoading && !qr?.payload && (
                                <div className="db-note warning">
                                    <TriangleAlert size={15} />
                                    <span>
                                        {qr?.status === 'Closed'
                                            ? 'Sesi ini sudah ditutup. Buka kembali sesi untuk menampilkan QR baru.'
                                            : 'Sesi belum dibuka. Klik “Buka QR” pada daftar sesi terlebih dahulu.'}
                                    </span>
                                </div>
                            )}

                            {qr?.payload && (
                                <div className="db-qr-panel">
                                    <div
                                        className="db-qr-frame"
                                        // SVG dibuat server dari token HMAC, bukan input pengguna.
                                        dangerouslySetInnerHTML={{ __html: qr.payload.svg }}
                                    />
                                    <div className="db-qr-meta">
                                        <span><Link2 size={12} /> Slot {qr.payload.slot}</span>
                                        <span><RefreshCw size={12} /> Berganti tiap {qr.payload.interval} detik</span>
                                    </div>
                                    <div className="db-qr-link">
                                        <input className="db-input" readOnly value={qr.payload.url} aria-label="Tautan absensi mahasiswa" />
                                        <button className="db-btn ghost sm" type="button" onClick={copyPayload}>
                                            {copied ? <Check size={13} /> : <Copy size={13} />} {copied ? 'Tersalin' : 'Salin'}
                                        </button>
                                    </div>
                                    <small className="db-hint">
                                        Mahasiswa memindai QR atau membuka tautan. Token lama masih diterima beberapa slot.
                                    </small>
                                </div>
                            )}

                            {confirmClose ? (
                                <div className="db-note danger" style={{ marginTop: 14, textAlign: 'left' }}>
                                    <TriangleAlert size={15} />
                                    <span>
                                        Tutup absensi pertemuan {qr?.meetingNo ?? qrSession.meetingNo ?? '-'}? Mahasiswa tidak bisa
                                        lagi memindai QR setelah sesi ditutup.
                                    </span>
                                </div>
                            ) : null}

                            <div className="db-modal-actions">
                                {(qr?.status ?? qrSession.status) === 'Opened' ? (
                                    confirmClose ? (
                                        <>
                                            <button className="db-btn danger" type="button" disabled={busyId === qrSession.id} onClick={closeSession}>
                                                <Square size={13} /> {busyId === qrSession.id ? 'Menutup…' : 'Ya, tutup absensi'}
                                            </button>
                                            <button className="db-btn ghost" type="button" onClick={() => setConfirmClose(false)}>Batal</button>
                                        </>
                                    ) : (
                                        <>
                                            <button className="db-btn danger" type="button" onClick={() => setConfirmClose(true)}>
                                                <Square size={13} /> Tutup absensi
                                            </button>
                                            <button className="db-btn ghost" type="button" onClick={closeQr}><X size={14} /> Sembunyikan</button>
                                        </>
                                    )
                                ) : (
                                    <button className="db-btn ghost" type="button" onClick={closeQr}><X size={14} /> Tutup</button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </AdminShell>
    );
}
