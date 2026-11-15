// Kehadiran saya — employee self-service (Inertia React).
// Tab Absensi (kamera + kompresi WebP + GPS + peta radius Leaflet)
// dan tab Cuti (saldo + pengajuan + riwayat) dalam satu pintu.
import { Head, useForm } from '@inertiajs/react';
import {
    Building2, CalendarDays, Camera, ChevronLeft, ChevronRight, CircleCheck, Clock3, Eye, Info, LocateFixed,
    LogIn, LogOut, MapPin, Navigation, Send, Sprout, Upload, Video, Wallet, X,
} from 'lucide-react';
import L from 'leaflet';
import { useEffect, useRef, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import AttendanceConcept from './AttendanceConcept';
import '../../../../css/employee.css';
import 'leaflet/dist/leaflet.css';

type OfficeLocation = { id: number; name: string; address: string | null; latitude: number; longitude: number; radius: number };
type HistoryRecord = {
    kind: string;
    id: number | string; dateLabel: string; status: string; statusLabel: string;
    checkInLabel: string; checkOutLabel: string; locationName: string; distanceLabel: string;
    checkInPhoto: string | null; checkOutPhoto: string | null;
    checkInTime: string | null; checkOutTime: string | null; workMinutes: number | null;
};
type Balance = { id: number; typeName: string; available: number; used: number };
type LeaveType = { id: number; name: string };
type LeaveRequest = { id: number; requestNumber: string; typeName: string; periodLabel: string; totalDays: number; status: string };

type Props = {
    shell: ShellProps;
    initialTab: 'absensi' | 'cuti';
    employee: { name: string; unitName: string | null };
    today: { dateLabel: string; checkedIn: boolean; checkedOut: boolean; checkInLabel: string; checkOutLabel: string };
    locations: OfficeLocation[];
    records: HistoryRecord[];
    leaveTypes: LeaveType[];
    balances: Balance[];
    leaveRequests: LeaveRequest[];
};

type PresenceTab = 'absensi' | 'cuti';

const leaveStatusMeta = (status: string): { tone: string; label: string } => {
    switch (status) {
        case 'approved': return { tone: 'green', label: 'Disetujui' };
        case 'rejected': return { tone: 'red', label: 'Ditolak' };
        case 'cancelled': return { tone: 'gray', label: 'Dibatalkan' };
        case 'in_approval':
        case 'submitted': return { tone: 'amber', label: 'Menunggu' };
        case 'draft': return { tone: 'gray', label: 'Draft' };
        default: return { tone: 'gray', label: status.replace(/_/g, ' ') };
    }
};

const statusTone = (status: string): string => {
    if (status === 'inside_radius' || status === 'leave_approved') return 'green';
    if (status === 'outside_radius' || status === 'gps_missing') return 'red';
    if (status === 'partial' || status === 'leave_pending') return 'amber';
    return 'gray';
};

const formatDistance = (meters: number | null): string => {
    if (meters === null || Number.isNaN(meters)) return 'Jarak belum dihitung';
    return meters >= 1000 ? `${(meters / 1000).toFixed(2)} km` : `${Math.round(meters)} m`;
};

const distanceMeters = (fromLat: number, fromLng: number, toLat: number, toLng: number): number => {
    const r = 6371000;
    const dLat = ((toLat - fromLat) * Math.PI) / 180;
    const dLng = ((toLng - fromLng) * Math.PI) / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos((fromLat * Math.PI) / 180) * Math.cos((toLat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;
    return r * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
};

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <span className="em-error">{message}</span>;
}

export default function Attendance({ shell, initialTab, employee, today, locations, records, leaveTypes, balances, leaveRequests }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const mapRef = useRef<HTMLDivElement>(null);
    const mapInstance = useRef<any>(null);
    const userMarker = useRef<any>(null);
    const distanceLine = useRef<any>(null);

    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [photoStatus, setPhotoStatus] = useState('Belum ada foto');
    const [photoReady, setPhotoReady] = useState(false);
    const [cameraOn, setCameraOn] = useState(false);
    const [coords, setCoords] = useState<{ lat: number; lng: number; accuracy: number } | null>(null);
    const [gpsStatus, setGpsStatus] = useState('Menunggu lokasi');
    const [mapStatus, setMapStatus] = useState('Menunggu lokasi');
    const [mapDistance, setMapDistance] = useState('Jarak belum dihitung');
    const [lightbox, setLightbox] = useState<{ url: string; title: string } | null>(null);
    const [tab, setTab] = useState<PresenceTab>(initialTab === 'cuti' ? 'cuti' : 'absensi');
    const [leaveOpen, setLeaveOpen] = useState(false);
    const [draggingTabs, setDraggingTabs] = useState(false);
    const [canTabLeft, setCanTabLeft] = useState(false);
    const [canTabRight, setCanTabRight] = useState(false);
    const tabsRef = useRef<HTMLDivElement>(null);
    const tabDrag = useRef<{ x: number; sl: number } | null>(null);
    const tabDragMoved = useRef(false);

    const leaveForm = useForm({ employee_leave_type_id: '', starts_at: '', ends_at: '', reason: '', employee_notes: '' });
    const todayISO = new Date().toISOString().slice(0, 10);
    const pendingLeaves = leaveRequests.filter((item) => ['draft', 'submitted', 'in_approval'].includes(item.status)).length;

    const updateTabArrows = () => {
        const el = tabsRef.current;
        if (!el) return;
        setCanTabLeft(el.scrollLeft > 4);
        setCanTabRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 4);
    };

    const scrollTabsBy = (dx: number) => {
        tabsRef.current?.scrollBy({ left: dx, behavior: 'smooth' });
    };

    const onTabsPointerDown = (event: React.PointerEvent<HTMLDivElement>) => {
        if (event.pointerType !== 'mouse' || event.button !== 0) return;
        const el = tabsRef.current;
        if (!el) return;
        tabDrag.current = { x: event.clientX, sl: el.scrollLeft };
        tabDragMoved.current = false;
    };

    const onTabsPointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
        const drag = tabDrag.current;
        const el = tabsRef.current;
        if (!drag || !el) return;
        const dx = event.clientX - drag.x;
        if (!tabDragMoved.current && Math.abs(dx) > 6) {
            tabDragMoved.current = true;
            setDraggingTabs(true);
        }
        if (tabDragMoved.current) el.scrollLeft = drag.sl - dx;
    };

    const endTabsDrag = () => {
        tabDrag.current = null;
        setDraggingTabs(false);
    };

    const onTabsClickCapture = (event: React.MouseEvent<HTMLDivElement>) => {
        if (tabDragMoved.current) {
            event.stopPropagation();
            event.preventDefault();
            tabDragMoved.current = false;
        }
    };

    const submitLeave = (event: React.FormEvent) => {
        event.preventDefault();
        leaveForm.post('/employee/leaves', {
            preserveScroll: true,
            onSuccess: () => { leaveForm.reset(); setLeaveOpen(false); },
        });
    };

    const form = useForm({ photo: null as File | null, latitude: '', longitude: '', accuracy: '' });

    const uploadPercent = form.progress?.percentage ?? 0;

    // ── Peta Leaflet ──
    useEffect(() => {
        if (!mapRef.current || mapInstance.current) return;
        const map = L.map(mapRef.current, { zoomControl: true, scrollWheelZoom: false }).setView([-6.2, 106.816666], 12);
        // Tanpa API key: primer OSM standar, cadangan Esri WorldStreetMap.
        const primaryTiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        const fallbackTiles = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics',
        });
        let fallbackLoaded = false;
        primaryTiles.on('tileerror', () => {
            if (fallbackLoaded) return;
            fallbackLoaded = true;
            primaryTiles.remove();
            fallbackTiles.addTo(map);
            window.setTimeout(() => map.invalidateSize(), 150);
        });

        const layers: any[] = [];
        locations.forEach((location) => {
            const point: any = [location.latitude, location.longitude];
            layers.push(L.circle(point, { radius: location.radius, color: '#2b67b7', weight: 2, fillColor: '#2b67b7', fillOpacity: 0.14 }).addTo(map));
            layers.push(L.circleMarker(point, { radius: 7, color: '#fff', weight: 2, fillColor: '#2b67b7', fillOpacity: 1 }).addTo(map).bindPopup(`${location.name}<br>Radius ${formatDistance(location.radius)}`));
        });
        if (layers.length > 0) {
            map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [24, 24] });
        }
        mapInstance.current = map;
        window.setTimeout(() => map.invalidateSize(), 400);
        return () => {
            map.remove();
            mapInstance.current = null;
        };
    }, [locations]);

    const paintUserOnMap = (lat: number, lng: number) => {
        const map = mapInstance.current;
        if (!map) return;
        userMarker.current?.remove();
        distanceLine.current?.remove();

        userMarker.current = L.circleMarker([lat, lng], { radius: 8, color: '#fff', weight: 2, fillColor: '#d69b31', fillOpacity: 1 })
            .addTo(map)
            .bindPopup('Posisi kamu');

        if (locations.length === 0) {
            setMapStatus('Belum ada lokasi kantor aktif');
            setMapDistance('Admin perlu menambahkan lokasi absensi.');
            map.setView([lat, lng], 16);
            return;
        }

        const nearest = locations
            .map((location) => ({ ...location, distance: distanceMeters(lat, lng, location.latitude, location.longitude) }))
            .sort((a, b) => a.distance - b.distance)[0];
        const inside = nearest.distance <= nearest.radius;

        distanceLine.current = L.polyline([[lat, lng], [nearest.latitude, nearest.longitude]], {
            color: inside ? '#23795c' : '#b3402f',
            weight: 3,
            dashArray: inside ? undefined : '8 8',
        }).addTo(map);

        setMapStatus(inside ? `Di dalam radius ${nearest.name}` : `Di luar radius ${nearest.name}`);
        setMapDistance(`Jarak ${formatDistance(nearest.distance)}, radius ${formatDistance(nearest.radius)}`);
        if (inside) {
            map.setView([lat, lng], 17);
        } else {
            map.fitBounds(L.latLngBounds([[lat, lng], [nearest.latitude, nearest.longitude]]).pad(0.25), { padding: [24, 24] });
        }
    };

    const locate = () => {
        if (!navigator.geolocation) {
            setGpsStatus('GPS tidak tersedia');
            return;
        }
        setGpsStatus('Mengambil lokasi…');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = Number(position.coords.latitude.toFixed(7));
                const lng = Number(position.coords.longitude.toFixed(7));
                const accuracy = Math.round(position.coords.accuracy);
                setCoords({ lat, lng, accuracy });
                setGpsStatus(`Akurasi ${accuracy} meter`);
                paintUserOnMap(lat, lng);
            },
            () => {
                setGpsStatus('Izin lokasi ditolak atau gagal dibaca');
                setMapStatus('Izin lokasi ditolak atau gagal dibaca');
            },
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 },
        );
    };

    useEffect(() => {
        locate();
        return () => {
            streamRef.current?.getTracks().forEach((track) => track.stop());
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        updateTabArrows();
        window.addEventListener('resize', updateTabArrows);
        return () => window.removeEventListener('resize', updateTabArrows);
    }, []);

    useEffect(() => {
        tabsRef.current?.querySelector('.em-tab.active')?.scrollIntoView({ inline: 'nearest', block: 'nearest' });
        updateTabArrows();
    }, [tab]);

    // ── Kamera ──
    const startCamera = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                setCameraOn(true);
            }
        } catch {
            fileRef.current?.click();
        }
    };

    const stopCamera = () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setCameraOn(false);
    };

    const fileToWebp = (file: File): Promise<Blob> => new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const maxSize = 1280;
            const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d')?.drawImage(img, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Gagal konversi gambar.'))), 'image/webp', 0.78);
        };
        img.onerror = () => reject(new Error('Gagal membaca gambar.'));
        img.src = URL.createObjectURL(file);
    });

    const acceptPhotoBlob = async (blob: Blob) => {
        try {
            setPhotoStatus('Mengompres foto…');
            setPhotoReady(false);
            const webp = blob.type === 'image/webp' && blob.size < 1500000 ? blob : await fileToWebp(new File([blob], 'capture.jpg', { type: blob.type || 'image/jpeg' }));
            const file = new File([webp], `attendance-${Date.now()}.webp`, { type: 'image/webp' });
            setPhotoFile(file);
            setPhotoPreview(URL.createObjectURL(webp));
            setPhotoStatus('Foto siap dipakai.');
            setPhotoReady(true);
        } catch {
            setPhotoStatus('Gagal membaca foto. Coba pilih gambar lain.');
            setPhotoReady(false);
        }
    };

    const capturePhoto = () => {
        const video = videoRef.current;
        if (!video || !streamRef.current) {
            fileRef.current?.click();
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 960;
        canvas.height = video.videoHeight || 720;
        canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) {
                setPhotoStatus('Gagal mengambil foto. Coba ulangi.');
                return;
            }
            void acceptPhotoBlob(blob);
        }, 'image/webp', 0.78);
    };

    const onPickFile = async (file: File | null) => {
        if (!file) return;
        await acceptPhotoBlob(file);
    };

    const submitCapture = (mode: 'in' | 'out') => {
        if (!photoFile || !coords) return;
        form.setData({ photo: photoFile, latitude: String(coords.lat), longitude: String(coords.lng), accuracy: String(coords.accuracy) });
        form.post(`/employee/attendance/check-${mode}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setPhotoFile(null);
                setPhotoPreview(null);
                setPhotoReady(false);
                setPhotoStatus('Belum ada foto');
                stopCamera();
            },
        });
    };

    const canCheckIn = Boolean(!today.checkedIn && photoReady && coords);
    const canCheckOut = Boolean(today.checkedIn && !today.checkedOut && photoReady && coords);

    return <AttendanceConcept
        shell={shell}
        employee={employee}
        today={today}
        locations={locations}
        records={records}
        leaveTypes={leaveTypes}
        balances={balances}
        leaveRequests={leaveRequests}
        tab={tab}
        setTab={setTab}
        pendingLeaves={pendingLeaves}
        videoRef={videoRef}
        fileRef={fileRef}
        mapRef={mapRef}
        photoFile={photoFile}
        photoPreview={photoPreview}
        photoStatus={photoStatus}
        photoReady={photoReady}
        cameraOn={cameraOn}
        coords={coords}
        gpsStatus={gpsStatus}
        mapStatus={mapStatus}
        mapDistance={mapDistance}
        canCheckIn={canCheckIn}
        canCheckOut={canCheckOut}
        uploadPercent={uploadPercent}
        attendanceForm={form}
        leaveForm={leaveForm}
        leaveOpen={leaveOpen}
        setLeaveOpen={setLeaveOpen}
        lightbox={lightbox}
        setLightbox={setLightbox}
        startCamera={startCamera}
        stopCamera={stopCamera}
        capturePhoto={capturePhoto}
        onPickFile={onPickFile}
        locate={locate}
        submitCapture={submitCapture}
        submitLeave={submitLeave}
    />;

    /* Legacy layout kept below temporarily for rollback reference; the active page is AttendanceConcept. */
    /*
    return (
        <AdminShell shell={shell}>
            <Head title={`Kehadiran Saya · ${shell.appName}`} />
            <div className="em-root">
                <div className="em-stack">
                    <section className="em-hero">
                        <div className="em-hero-inner">
                            <div className="em-hero-copy">
                                <span className="em-hero-icon"><Clock3 size={26} /></span>
                                <div>
                                    <small>Kepegawaian saya</small>
                                    <h1>Kehadiran Saya</h1>
                                    <p>{employee.name}{employee.unitName ? ` · ${employee.unitName}` : ''} — absensi mandiri harian dan pengajuan cuti dalam satu pintu.</p>
                                    <div className="em-hero-pills">
                                        <span className="em-pill"><CalendarDays size={12} /> {today.dateLabel}</span>
                                        <span className="em-pill"><LogIn size={12} /> {today.checkInLabel}</span>
                                        <span className="em-pill"><LogOut size={12} /> {today.checkOutLabel}</span>
                                        {pendingLeaves > 0 && <span className="em-pill"><Sprout size={12} /> {pendingLeaves} cuti berproses</span>}
                                    </div>
                                </div>
                            </div>
                            <div className="em-hero-actions">
                                <div className="em-pill" style={{ fontSize: 13, padding: '10px 16px' }}>
                                    Masuk <b style={{ fontSize: 18 }}>{today.checkedIn ? today.checkInLabel : '-'}</b>
                                </div>
                                <div className="em-pill" style={{ fontSize: 13, padding: '10px 16px' }}>
                                    Keluar <b style={{ fontSize: 18 }}>{today.checkedOut ? today.checkOutLabel : '-'}</b>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div className="em-tabs-wrap">
                        {canTabLeft && (
                            <button type="button" className="em-tab-arrow left" aria-label="Geser tab ke kiri" onClick={() => scrollTabsBy(-220)}>
                                <ChevronLeft size={16} />
                            </button>
                        )}
                        <div
                            className={`em-tabs${draggingTabs ? ' dragging' : ''}`}
                            role="tablist"
                            aria-label="Navigasi kehadiran"
                            ref={tabsRef}
                            onScroll={updateTabArrows}
                            onPointerDown={onTabsPointerDown}
                            onPointerMove={onTabsPointerMove}
                            onPointerUp={endTabsDrag}
                            onPointerLeave={endTabsDrag}
                            onPointerCancel={endTabsDrag}
                            onClickCapture={onTabsClickCapture}
                        >
                            <button type="button" role="tab" aria-selected={tab === 'absensi'} className={`em-tab${tab === 'absensi' ? ' active' : ''}`} onClick={() => setTab('absensi')}>
                                <Camera size={15} /> Absensi
                            </button>
                            <button type="button" role="tab" aria-selected={tab === 'cuti'} className={`em-tab${tab === 'cuti' ? ' active' : ''}`} onClick={() => setTab('cuti')}>
                                <Sprout size={15} /> Cuti
                                {pendingLeaves > 0 && <span className="em-tab-count">{pendingLeaves}</span>}
                            </button>
                        </div>
                        {canTabRight && (
                            <button type="button" className="em-tab-arrow right" aria-label="Geser tab ke kanan" onClick={() => scrollTabsBy(220)}>
                                <ChevronRight size={16} />
                            </button>
                        )}
                    </div>

                    {tab === 'absensi' && (
                    <>
                    <div className="em-layout">
                        <section className="em-card">
                            <div className="em-card-head"><h2 className="em-card-title"><Camera size={16} /> Verifikasi kehadiran</h2></div>
                            <div className="em-card-body" style={{ display: 'grid', gap: 14 }}>
                                <div className="em-camera">
                                    {photoPreview ? (
                                        <img src={photoPreview} alt="Preview absensi" />
                                    ) : (
                                        <video ref={videoRef} playsInline autoPlay muted style={{ display: cameraOn ? 'block' : 'none' }} />
                                    )}
                                    {!photoPreview && !cameraOn && (
                                        <div className="em-camera-empty">
                                            <div>
                                                <Camera size={28} style={{ marginBottom: 8 }} />
                                                <div>Ambil foto atau pilih gambar untuk bukti absensi.</div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <input ref={fileRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={(e) => void onPickFile(e.target.files?.[0] ?? null)} />

                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                    {!cameraOn && (
                                        <button type="button" className="em-btn ghost sm" onClick={() => void startCamera()}>
                                            <Video size={14} /> Kamera
                                        </button>
                                    )}
                                    {cameraOn && (
                                        <button type="button" className="em-btn ghost sm" onClick={stopCamera}>
                                            <X size={14} /> Matikan
                                        </button>
                                    )}
                                    <button type="button" className="em-btn primary sm" onClick={capturePhoto}>
                                        <Camera size={14} /> Ambil foto
                                    </button>
                                    <button type="button" className="em-btn ghost sm" onClick={() => fileRef.current?.click()}>
                                        <Upload size={14} /> Upload
                                    </button>
                                </div>

                                <div className="em-balance">
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 }}>
                                        <div>
                                            <small className="em-hint" style={{ fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em' }}>Foto absensi</small>
                                            <b>{photoStatus}</b>
                                        </div>
                                        <span className={`em-badge ${photoReady ? 'green' : 'gray'}`}>{photoReady ? 'Siap' : 'Belum siap'}</span>
                                    </div>
                                    {(form.processing || uploadPercent > 0) && (
                                        <div style={{ marginTop: 10 }}>
                                            <div className="em-progress"><i style={{ width: `${uploadPercent}%` }} /></div>
                                            <small className="em-hint">{uploadPercent > 0 ? `${uploadPercent}%` : 'Menyiapkan…'}</small>
                                        </div>
                                    )}
                                    {!form.processing && <small className="em-hint" style={{ display: 'block', marginTop: 8 }}>Foto dikompres ke WebP sebelum dikirim.</small>}
                                </div>
                                <FieldError message={form.errors.photo} />

                                <div className="em-balance">
                                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, alignItems: 'flex-start' }}>
                                        <div>
                                            <small className="em-hint" style={{ fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em' }}>GPS</small>
                                            <b>{gpsStatus}</b>
                                            <small className="em-hint">{coords ? `${coords.lat}, ${coords.lng}` : 'Koordinat belum tersedia'}</small>
                                        </div>
                                        <button type="button" className="em-icon-btn" style={{ color: 'var(--em-brand)' }} aria-label="Perbarui lokasi" onClick={locate}>
                                            <LocateFixed size={15} />
                                        </button>
                                    </div>
                                </div>
                                <FieldError message={form.errors.latitude} />
                                <FieldError message={form.errors.longitude} />

                                <div className="em-balance">
                                    <small className="em-hint" style={{ fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em' }}>Peta radius absensi</small>
                                    <b>{mapStatus}</b>
                                    <small className="em-hint">{mapDistance} · {locations.length} lokasi</small>
                                    <div className="em-map" style={{ marginTop: 10 }} ref={mapRef} />
                                </div>

                                <div style={{ display: 'grid', gap: 8 }}>
                                    <button type="button" className="em-btn primary" disabled={!canCheckIn || form.processing} onClick={() => submitCapture('in')}>
                                        <LogIn size={15} /> {form.processing ? 'Mengirim…' : 'Check-in'}
                                    </button>
                                    <button type="button" className="em-btn ghost" disabled={!canCheckOut || form.processing} onClick={() => submitCapture('out')}>
                                        <LogOut size={15} /> Check-out
                                    </button>
                                    {!coords && <div className="em-note info"><Info size={15} /><span>Aktifkan izin lokasi agar tombol absensi menyala.</span></div>}
                                </div>
                            </div>
                        </section>

                        <div className="em-stack">
                            <section className="em-card">
                                <div className="em-card-head"><h2 className="em-card-title"><Building2 size={16} /> Lokasi kantor aktif</h2></div>
                                <div className="em-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {locations.length === 0 && (
                                        <p className="em-hint" style={{ margin: 0 }}>Belum ada lokasi kantor aktif. Admin bisa menambahkan dari Kepegawaian &gt; Lokasi Absensi.</p>
                                    )}
                                    {locations.map((location) => (
                                        <div className="em-balance" key={location.id}>
                                            <b>{location.name}</b>
                                            <small className="em-hint">{location.latitude}, {location.longitude}</small>
                                            <div style={{ marginTop: 8 }}><span className="em-badge">{location.radius} meter</span></div>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="em-card">
                                <div className="em-card-head"><h2 className="em-card-title"><Clock3 size={16} /> Riwayat absensi</h2></div>
                                {records.length === 0 ? (
                                    <EmptyState icon={Clock3} iconSize={26} title="Belum ada riwayat" description="Riwayat 12 absensi terakhir akan tampil di sini." />
                                ) : (
                                    <div className="em-table-wrap">
                                        <table className="em-table">
                                            <thead>
                                                <tr><th>Tanggal</th><th>Status</th><th>Masuk</th><th>Keluar</th><th>Lokasi</th><th>Bukti</th><th>Durasi</th></tr>
                                            </thead>
                                            <tbody>
                                                {records.map((record) => (
                                                    <tr key={record.id}>
                                                        <td><b>{record.dateLabel}</b></td>
                                                        <td><span className={`em-badge ${statusTone(record.status)}`}>{record.statusLabel}</span></td>
                                                        <td className="num">{record.checkInLabel}</td>
                                                        <td className="num">{record.checkOutLabel}</td>
                                                        <td>
                                                            <div>{record.locationName}</div>
                                                            <small className="em-hint">{record.distanceLabel}</small>
                                                        </td>
                                                        <td>
                                                            <div style={{ display: 'flex', gap: 6 }}>
                                                                {record.checkInPhoto && (
                                                                    <button type="button" className="em-btn ghost sm" onClick={() => setLightbox({ url: record.checkInPhoto!, title: `Foto masuk · ${record.checkInTime ?? record.dateLabel}` })}>
                                                                        <Eye size={13} /> In
                                                                    </button>
                                                                )}
                                                                {record.checkOutPhoto && (
                                                                    <button type="button" className="em-btn ghost sm" onClick={() => setLightbox({ url: record.checkOutPhoto!, title: `Foto keluar · ${record.checkOutTime ?? record.dateLabel}` })}>
                                                                        <Eye size={13} /> Out
                                                                    </button>
                                                                )}
                                                                {!record.checkInPhoto && !record.checkOutPhoto && <span className="em-hint">-</span>}
                                                            </div>
                                                        </td>
                                                        <td className="num">{record.workMinutes !== null ? `${record.workMinutes} mnt` : '-'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </section>
                        </div>
                    </div>

                    <div className="em-note info"><Navigation size={15} /><span>Check-in dan check-out wajib di dalam radius lokasi kantor aktif dengan foto bukti dan GPS menyala.</span></div>
                    </>
                    )}

                    {tab === 'cuti' && (
                    <div className="em-layout">
                        <div className="em-stack">
                            <section className="em-card">
                                <div className="em-card-head">
                                    <h2 className="em-card-title"><Wallet size={16} /> Saldo cuti {new Date().getFullYear()}</h2>
                                    <span className="em-badge gray">{balances.length}</span>
                                </div>
                                <div className="em-card-body">
                                    {balances.length === 0 && (
                                        <p className="em-hint" style={{ margin: 0 }}>Saldo akan dibuat otomatis saat pengajuan cuti pertama.</p>
                                    )}
                                    {balances.map((balance) => (
                                        <div className="em-balance" key={balance.id}>
                                            <b>{balance.typeName}</b>
                                            <div className="em-balance-nums">
                                                <div><strong>{balance.available}</strong><small>Tersedia</small></div>
                                                <div><strong>{balance.used}</strong><small>Terpakai</small></div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            {leaveOpen && (
                                <section className="em-card">
                                    <div className="em-card-head"><h2 className="em-card-title"><Send size={16} /> Pengajuan baru</h2></div>
                                    <form onSubmit={submitLeave}>
                                        <div className="em-card-body" style={{ display: 'grid', gap: 14 }}>
                                            <div className="em-field">
                                                <label>Jenis cuti <i>*</i></label>
                                                <select className="em-select" value={leaveForm.data.employee_leave_type_id} onChange={(e) => leaveForm.setData('employee_leave_type_id', e.target.value)}>
                                                    <option value="">Pilih jenis cuti</option>
                                                    {leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                                                </select>
                                                <FieldError message={leaveForm.errors.employee_leave_type_id} />
                                            </div>
                                            <div className="em-grid-2">
                                                <div className="em-field">
                                                    <label>Mulai <i>*</i></label>
                                                    <input className="em-input" type="date" min={todayISO} value={leaveForm.data.starts_at} onChange={(e) => leaveForm.setData('starts_at', e.target.value)} />
                                                    <FieldError message={leaveForm.errors.starts_at} />
                                                </div>
                                                <div className="em-field">
                                                    <label>Selesai <i>*</i></label>
                                                    <input className="em-input" type="date" min={leaveForm.data.starts_at || todayISO} value={leaveForm.data.ends_at} onChange={(e) => leaveForm.setData('ends_at', e.target.value)} />
                                                    <FieldError message={leaveForm.errors.ends_at} />
                                                </div>
                                            </div>
                                            <div className="em-field">
                                                <label>Alasan <i>*</i></label>
                                                <textarea className="em-textarea" rows={3} value={leaveForm.data.reason} onChange={(e) => leaveForm.setData('reason', e.target.value)} placeholder="Keperluan cuti" />
                                                <FieldError message={leaveForm.errors.reason} />
                                                <FieldError message={(leaveForm.errors as Record<string, string | undefined>).balance} />
                                            </div>
                                            <div className="em-field">
                                                <label>Catatan</label>
                                                <textarea className="em-textarea" rows={2} value={leaveForm.data.employee_notes} onChange={(e) => leaveForm.setData('employee_notes', e.target.value)} placeholder="Opsional" />
                                                <FieldError message={leaveForm.errors.employee_notes} />
                                            </div>
                                            <div className="em-note info"><Info size={15} /><span>Pengajuan langsung dikirim ke alur approval. Saldo harus mencukupi total hari yang diajukan.</span></div>
                                            <div className="em-form-actions">
                                                <button type="button" className="em-btn ghost" onClick={() => setLeaveOpen(false)}>Batal</button>
                                                <button type="submit" className="em-btn primary" disabled={leaveForm.processing}>
                                                    <Send size={15} /> {leaveForm.processing ? 'Mengirim…' : 'Kirim pengajuan'}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </section>
                            )}
                        </div>

                        <section className="em-card">
                            <div className="em-card-head">
                                <h2 className="em-card-title"><Clock3 size={16} /> Riwayat pengajuan</h2>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                    <span className="em-badge gray">{leaveRequests.length}</span>
                                    <button type="button" className="em-btn primary sm" onClick={() => setLeaveOpen(!leaveOpen)}>
                                        {leaveOpen ? 'Tutup form' : <><Send size={13} /> Ajukan cuti</>}
                                    </button>
                                </div>
                            </div>
                            {leaveRequests.length === 0 ? (
                                <EmptyState icon={CalendarDays} iconSize={26} title="Belum ada pengajuan" description="Riwayat pengajuan cuti Anda akan tampil di sini." />
                            ) : (
                                <div className="em-table-wrap">
                                    <table className="em-table">
                                        <thead>
                                            <tr><th>Nomor</th><th>Jenis</th><th>Periode</th><th>Hari</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            {leaveRequests.map((item) => {
                                                const meta = leaveStatusMeta(item.status);
                                                return (
                                                    <tr key={item.id}>
                                                        <td><b>{item.requestNumber}</b></td>
                                                        <td>{item.typeName}</td>
                                                        <td>{item.periodLabel}</td>
                                                        <td className="num">{item.totalDays}</td>
                                                        <td><span className={`em-badge ${meta.tone}`}><CircleCheck size={11} /> {meta.label}</span></td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </div>
                    )}
                </div>
            </div>

            {lightbox && (
                <div className="as-palette-backdrop" onMouseDown={(e) => { if (e.target === e.currentTarget) setLightbox(null); }}>
                    <div className="as-palette" style={{ width: 'min(40rem, 100%)' }} role="dialog" aria-label={lightbox.title}>
                        <div className="as-palette-input">
                            <MapPin size={15} />
                            <input value={lightbox.title} readOnly aria-label="Judul foto" style={{ border: 0 }} />
                            <button type="button" className="as-burger" style={{ display: 'grid' }} aria-label="Tutup foto" onClick={() => setLightbox(null)}>
                                <X size={15} />
                            </button>
                        </div>
                        <img src={lightbox.url} alt={lightbox.title} style={{ display: 'block', width: '100%' }} />
                        <div className="as-palette-foot">
                            <a href={lightbox.url} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--as-primary)', fontWeight: 700, textDecoration: 'none' }}>
                                Buka foto di tab baru
                            </a>
                        </div>
                    </div>
                </div>
            )}
        </AdminShell>
    );
    */
}
