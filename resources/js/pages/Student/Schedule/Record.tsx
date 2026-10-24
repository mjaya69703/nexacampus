// Isi absensi mahasiswa: scan QR lewat kamera atau tempel tautan QR
// (Inertia React). Lokasi dikirim bila perangkat mengizinkan.
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft, CalendarDays, Camera, CameraOff, CheckCircle2, Clock,
    Info, LocateFixed, QrCode, ScanLine, ShieldAlert, TriangleAlert,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Session = {
    id: number;
    offeringId: number;
    courseName: string;
    meetingNo: number | null;
    meetingDate: string;
    timeRange: string;
    status: string;
    isOpen: boolean;
    windowMessage: string | null;
};

type Attendance = {
    status: string | null;
    statusLabel: string;
    tone: string;
    notes: string | null;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    hasAccess: boolean;
    session: Session;
    attendance: Attendance;
    context: { academicYear: string | null; registrationStatus: string | null };
    urls: { back: string; scan: string };
};

const toneClass = (tone: string): string => {
    switch (tone) {
        case 'green':
            return 'green';
        case 'red':
            return 'red';
        case 'amber':
            return 'amber';
        default:
            return 'gray';
    }
};

export default function ScheduleRecord({
    shell, hasProfile, hasAccess, session, attendance, context, urls,
}: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const detectorRef = useRef<BarcodeDetector | null>(null);
    const loopRef = useRef<number | null>(null);
    const [scanning, setScanning] = useState(false);
    const [scanError, setScanError] = useState<string | null>(null);
    const [geo, setGeo] = useState<{ latitude: number; longitude: number; accuracy: number } | null>(null);
    const [geoState, setGeoState] = useState<'idle' | 'loading' | 'ready' | 'denied'>('idle');

    const form = useForm<{
        payload: string;
        latitude: number | null;
        longitude: number | null;
        accuracy: number | null;
    }>({
        payload: '',
        latitude: null,
        longitude: null,
        accuracy: null,
    });

    const stopCamera = (): void => {
        if (loopRef.current !== null) {
            window.clearTimeout(loopRef.current);
            loopRef.current = null;
        }

        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setScanning(false);
    };

    useEffect(() => () => {
        if (loopRef.current !== null) window.clearTimeout(loopRef.current);
        streamRef.current?.getTracks().forEach((track) => track.stop());
    }, []);

    const requestLocation = (): Promise<{ latitude: number; longitude: number; accuracy: number } | null> => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            return Promise.resolve(null);
        }

        setGeoState('loading');

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const value = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                    };
                    setGeo(value);
                    setGeoState('ready');
                    resolve(value);
                },
                () => {
                    setGeoState('denied');
                    resolve(null);
                },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
            );
        });
    };

    const submitPayload = async (payload: string): Promise<void> => {
        const trimmed = payload.trim();

        if (trimmed === '') {
            form.setError('payload', 'Tempel atau scan kode QR absensi terlebih dahulu.');
            return;
        }

        const location = geo ?? await requestLocation();

        form.transform((data) => ({
            ...data,
            payload: trimmed,
            latitude: location?.latitude ?? null,
            longitude: location?.longitude ?? null,
            accuracy: location?.accuracy ?? null,
        }));

        form.post(urls.scan, {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        void submitPayload(form.data.payload);
    };

    const startCamera = async (): Promise<void> => {
        setScanError(null);

        if (typeof window === 'undefined' || !window.BarcodeDetector) {
            setScanError('Browser ini belum mendukung pemindaian QR langsung. Tempel tautan QR di kolom manual.');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });

            streamRef.current = stream;

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play().catch(() => undefined);
            }

            detectorRef.current = new window.BarcodeDetector({ formats: ['qr_code'] });
            setScanning(true);
            void requestLocation();
            void scanLoop();
        } catch {
            setScanError('Kamera tidak bisa diakses. Periksa izin kamera atau gunakan input manual.');
            stopCamera();
        }
    };

    const scanLoop = async (): Promise<void> => {
        if (!detectorRef.current || !videoRef.current || !streamRef.current) return;

        try {
            const codes = await detectorRef.current.detect(videoRef.current);
            const value = codes[0]?.rawValue?.trim();

            if (value) {
                stopCamera();
                form.setData('payload', value);
                await submitPayload(value);
                return;
            }
        } catch {
            // Frame gagal dibaca (mis. video belum siap) — coba lagi.
        }

        loopRef.current = window.setTimeout(() => { void scanLoop(); }, 450);
    };

    const alreadyRecorded = attendance.status !== null && attendance.status !== undefined;
    const canScan = hasProfile && hasAccess && session.isOpen;

    return (
        <AdminShell shell={shell}>
            <Head title={`Isi Absensi · ${session.courseName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={QrCode}
                        eyebrow="Academic"
                        title={session.courseName}
                        description={`Pertemuan ${session.meetingNo ?? '-'} · ${session.meetingDate} · ${session.timeRange}`}
                        badges={[
                            attendance.statusLabel,
                            session.isOpen ? 'Sesi dibuka' : 'Sesi ditutup',
                        ]}
                        actions={(
                            <Link href={urls.back} className="db-btn ghost"><ArrowLeft size={15} /> Rekap Absensi</Link>
                        )}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Absensi tidak bisa dicatat.</span>
                        </div>
                    )}

                    {hasProfile && !hasAccess && (
                        <div className="db-note warning">
                            <ShieldAlert size={15} />
                            <span>
                                <b>Kamu tidak terdaftar pada kelas ini.</b> Absensi hanya untuk kelas aktif di KRS
                                {context.registrationStatus ? ` (status registrasi: ${context.registrationStatus})` : ''}.
                            </span>
                        </div>
                    )}

                    {hasAccess && !session.isOpen && (
                        <div className="db-note warning">
                            <Clock size={15} />
                            <span><b>Sesi belum dibuka.</b> {session.windowMessage ?? 'Tunggu dosen membuka sesi QR absensi.'}</span>
                        </div>
                    )}

                    <div className="db-grid-2" style={{ alignItems: 'start' }}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><CalendarDays size={16} /> Detail Sesi</h2>
                                <span className={`db-badge ${toneClass(attendance.tone)}`}>{attendance.statusLabel}</span>
                            </div>
                            <div className="db-card-body">
                                <div style={{ display: 'grid', gap: 10 }}>
                                    <div className="db-summary">
                                        <span className="db-summary-icon"><CalendarDays size={18} /></span>
                                        <div><b>Pertemuan {session.meetingNo ?? '-'}</b><small>{session.meetingDate}</small></div>
                                    </div>
                                    <div className="db-summary">
                                        <span className="db-summary-icon"><Clock size={18} /></span>
                                        <div><b>{session.timeRange}</b><small>{context.academicYear ?? 'Tahun akademik aktif'}</small></div>
                                    </div>
                                    <div className="db-summary">
                                        <span className={`db-summary-icon ${attendance.tone === 'green' ? 'green' : ''}`}>
                                            <CheckCircle2 size={18} />
                                        </span>
                                        <div>
                                            <b>{attendance.statusLabel}</b>
                                            <small>{alreadyRecorded ? 'Absensi sudah tercatat.' : 'Belum ada catatan kehadiran.'}</small>
                                        </div>
                                    </div>
                                </div>

                                {attendance.notes && (
                                    <div className="db-note info" style={{ marginTop: 12 }}>
                                        <Info size={15} />
                                        <span>{attendance.notes}</span>
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><ScanLine size={16} /> Scan QR Absensi</h2>
                                {geoState === 'ready' && <span className="db-badge green"><LocateFixed size={11} /> Lokasi aktif</span>}
                            </div>
                            <div className="db-card-body">
                                {!canScan ? (
                                    <div className="db-note muted">
                                        <Info size={15} />
                                        <span>Form absensi aktif saat kamu terdaftar pada kelas ini dan dosen membuka sesi QR.</span>
                                    </div>
                                ) : (
                                    <div style={{ display: 'grid', gap: 12 }}>
                                        <div
                                            style={{
                                                position: 'relative',
                                                borderRadius: 14,
                                                overflow: 'hidden',
                                                background: 'var(--db-soft)',
                                                minHeight: 190,
                                                display: 'grid',
                                                placeItems: 'center',
                                            }}
                                        >
                                            <video
                                                ref={videoRef}
                                                muted
                                                playsInline
                                                style={{
                                                    width: '100%',
                                                    maxHeight: 260,
                                                    display: scanning ? 'block' : 'none',
                                                    objectFit: 'cover',
                                                }}
                                            />
                                            {!scanning && (
                                                <div style={{ padding: 24, textAlign: 'center' }}>
                                                    <QrCode size={38} style={{ color: 'var(--db-brand)' }} />
                                                    <div className="db-hint" style={{ marginTop: 8 }}>
                                                        Arahkan kamera ke QR di layar dosen.
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                            {scanning ? (
                                                <button type="button" className="db-btn danger" onClick={stopCamera}>
                                                    <CameraOff size={15} /> Hentikan Kamera
                                                </button>
                                            ) : (
                                                <button type="button" className="db-btn primary" onClick={() => { void startCamera(); }}>
                                                    <Camera size={15} /> Mulai Scan
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                className="db-btn ghost"
                                                onClick={() => { void requestLocation(); }}
                                                disabled={geoState === 'loading'}
                                            >
                                                <LocateFixed size={15} /> {geoState === 'loading' ? 'Mendeteksi…' : 'Ambil Lokasi'}
                                            </button>
                                        </div>

                                        {scanError && <div className="db-note warning"><TriangleAlert size={15} /><span>{scanError}</span></div>}

                                        {geoState === 'denied' && (
                                            <div className="db-note muted">
                                                <Info size={15} />
                                                <span>Izin lokasi ditolak. Absensi tetap bisa dikirim bila dosen tidak mewajibkan geolokasi.</span>
                                            </div>
                                        )}

                                        <form onSubmit={submit} style={{ display: 'grid', gap: 8 }}>
                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Tautan / kode QR manual</span>
                                                <textarea
                                                    className="db-input"
                                                    rows={3}
                                                    placeholder="Tempel tautan QR yang berisi token & slot absensi…"
                                                    value={form.data.payload}
                                                    onChange={(event) => form.setData('payload', event.target.value)}
                                                />
                                                {form.errors.payload && <span className="db-error">{form.errors.payload}</span>}
                                            </label>

                                            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                                <button type="submit" className="db-btn primary" disabled={form.processing}>
                                                    <QrCode size={15} /> {form.processing ? 'Mengirim…' : 'Kirim Absensi'}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="db-note muted">
                        <TriangleAlert size={15} />
                        <span>
                            Token QR berganti setiap beberapa detik. Jika muncul pesan token kedaluwarsa, pindai ulang QR terbaru.
                            Absensi tercatat satu kali per pertemuan.
                        </span>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
