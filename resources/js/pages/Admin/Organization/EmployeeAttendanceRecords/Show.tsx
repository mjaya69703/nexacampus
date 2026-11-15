// Detail catatan absensi pegawai: geofence, peta, dan bukti foto.
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Clock3,
    Fingerprint,
    ImageOff,
    Info,
    MapPin,
    ShieldCheck,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Location = {
    name: string;
    latitude: number;
    longitude: number;
    radius: number;
};

type Point = {
    locationName: string | null;
    latitude: number | null;
    longitude: number | null;
    distance: number | null;
    accuracy: number | null;
    photoUrl: string | null;
    time: string | null;
    location: Location | null;
};

type RecordPayload = {
    id: number;
    employeeName: string;
    employeeNumber: string | null;
    employeeEmail: string | null;
    attendanceDate: string | null;
    status: string;
    statusLabel: string;
    workUnit: string | null;
    checkInAt: string | null;
    checkOutAt: string | null;
    workMinutes: number;
    source: string;
    notes: string | null;
    locationStatus: string | null;
    locationStatusLabel: string;
    checkIn: Point;
    checkOut: Point;
};

type Props = {
    shell: ShellProps;
    can: { delete: boolean };
    record: RecordPayload;
    urls: { index: string; delete: string };
};

function statusTone(status: string): string {
    if (status === 'present') return 'green';
    if (status === 'late') return 'gold';
    if (status === 'absent') return 'red';
    return 'gray';
}

function locationTone(status: string | null): string {
    if (status === 'inside_radius') return 'green';
    if (status === 'outside_radius' || status === 'gps_missing') return 'red';
    if (status === 'partial') return 'gold';
    return 'gray';
}

function formatMinutes(minutes: number): string {
    if (!minutes || minutes <= 0) return '-';
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) return `${rest} menit`;

    return `${hours} jam${rest > 0 ? ` ${rest} menit` : ''}`;
}

function formatDistance(meters: number | null): string {
    if (meters === null || meters === undefined || Number.isNaN(Number(meters))) return 'jarak belum tersedia';

    const value = Number(meters);

    return value >= 1000 ? `${(value / 1000).toFixed(2)} km` : `${Math.round(value)} m`;
}

function distanceMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const earth = 6371000;
    const toRad = (value: number) => (value * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;

    return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export default function EmployeeAttendanceRecordShow({ shell, can, record, urls }: Props) {
    const mapRef = useRef<HTMLDivElement | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const element = mapRef.current;
        const L = (window as unknown as { L?: any }).L;

        if (!element || !L) return;

        const map = L.map(element, { zoomControl: true, scrollWheelZoom: false }).setView([-6.2, 106.816666], 12);

        const primaryTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        }).addTo(map);

        const fallbackTiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        });

        let fallbackLoaded = false;
        primaryTiles.on('tileerror', () => {
            if (fallbackLoaded) return;
            fallbackLoaded = true;
            primaryTiles.remove();
            fallbackTiles.addTo(map);
            window.setTimeout(() => map.invalidateSize(), 150);
        });

        const points = [
            { ...record.checkIn, label: 'Check-In' },
            { ...record.checkOut, label: 'Check-Out' },
        ];

        const layers: any[] = [];
        const renderedLocations = new Set<string>();

        points.forEach((point) => {
            if (point.location && !renderedLocations.has(point.location.name)) {
                renderedLocations.add(point.location.name);

                const officePoint: [number, number] = [point.location.latitude, point.location.longitude];

                layers.push(L.circle(officePoint, {
                    radius: point.location.radius,
                    color: '#4f46e5',
                    weight: 2,
                    fillColor: '#6366f1',
                    fillOpacity: 0.14,
                }).addTo(map));

                layers.push(L.marker(officePoint).addTo(map)
                    .bindPopup(`${point.location.name}<br>Radius ${formatDistance(point.location.radius)}`));
            }

            if (point.latitude === null || point.longitude === null) return;

            const employeePoint: [number, number] = [point.latitude, point.longitude];

            layers.push(L.marker(employeePoint).addTo(map)
                .bindPopup(`${point.label}<br>${point.time ?? '-'}<br>${point.latitude}, ${point.longitude}`));

            if (!point.location) return;

            const officePoint: [number, number] = [point.location.latitude, point.location.longitude];
            const measured = point.distance ?? distanceMeters(point.latitude, point.longitude, point.location.latitude, point.location.longitude);
            const inside = measured <= point.location.radius;

            layers.push(L.polyline([employeePoint, officePoint], {
                color: inside ? '#16a34a' : '#dc2626',
                weight: 3,
                dashArray: inside ? null : '8 8',
            }).addTo(map));

            layers.push(L.marker([
                (point.latitude + point.location.latitude) / 2,
                (point.longitude + point.location.longitude) / 2,
            ], {
                interactive: false,
                icon: L.divIcon({
                    className: 'attendance-distance-label',
                    html: `${point.label}: ${formatDistance(measured)}`,
                }),
            }).addTo(map));
        });

        if (layers.length > 0) {
            const group = L.featureGroup(layers);
            const hasOutside = points.some((point) => point.location && point.distance !== null && point.distance > point.location.radius);
            map.fitBounds(group.getBounds(), { padding: [30, 30], maxZoom: hasOutside ? 15 : 17 });
        }

        const timers = [150, 500, 1000].map((delay) => window.setTimeout(() => map.invalidateSize(), delay));

        return () => {
            timers.forEach((timer) => window.clearTimeout(timer));
            map.remove();
        };
    }, [record]);

    const remove = () => {
        setProcessing(true);
        router.delete(urls.delete, {
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });
    };

    const points = [
        { key: 'in', label: 'Check-In', point: record.checkIn },
        { key: 'out', label: 'Check-Out', point: record.checkOut },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Absensi ${record.employeeName} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Fingerprint}
                        eyebrow="Kepegawaian"
                        title={`Absensi ${record.employeeName}`}
                        description={`${record.attendanceDate ?? '-'} · ${record.workUnit ?? 'Tanpa unit kerja'} · Sumber: ${record.source}`}
                        badges={[record.statusLabel, record.locationStatusLabel]}
                        actions={(
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={14} /> Kembali</a>
                                {can.delete && (
                                    <button className="db-btn danger" type="button" onClick={() => setConfirming(true)}>
                                        <Trash2 size={14} /> Hapus
                                    </button>
                                )}
                            </div>
                        )}
                    />

                    <section className="db-stats" aria-label="Ringkasan absensi">
                        <div className="db-stat">
                            <span className="db-stat-icon"><UserRound size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{record.employeeName}</span>
                                <span className="db-stat-label">{record.employeeNumber ?? 'Tanpa nomor pegawai'}</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className={`db-stat-icon ${statusTone(record.status) === 'green' ? 'green' : statusTone(record.status) === 'red' ? 'red' : 'gold'}`}><CalendarClock size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{record.statusLabel}</span>
                                <span className="db-stat-label">Status Kehadiran</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Clock3 size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{record.checkIn?.time ?? '-'} - {record.checkOut?.time ?? '-'}</span>
                                <span className="db-stat-label">Check-In / Check-Out</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Clock3 size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{formatMinutes(record.workMinutes)}</span>
                                <span className="db-stat-label">Durasi Kerja</span>
                            </div>
                        </div>
                    </section>

                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 2fr) minmax(280px, 1fr)', gap: 16, alignItems: 'start' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Info size={16} /> Informasi Absensi</h2>
                                    <span className={`db-badge ${locationTone(record.locationStatus)}`}>{record.locationStatusLabel}</span>
                                </div>
                                <div className="db-card-body">
                                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                                        <tbody>
                                            {[
                                                ['Nama Pegawai', record.employeeName],
                                                ['Nomor Pegawai', record.employeeNumber ?? '-'],
                                                ['Email', record.employeeEmail ?? '-'],
                                                ['Tanggal', record.attendanceDate ?? '-'],
                                                ['Unit Kerja', record.workUnit ?? '-'],
                                                ['Sumber Data', record.source],
                                                ['Check-In', record.checkInAt ?? '-'],
                                                ['Check-Out', record.checkOutAt ?? '-'],
                                                ['Durasi Kerja', formatMinutes(record.workMinutes)],
                                            ].map(([label, value]) => (
                                                <tr key={label} style={{ borderBottom: '1px solid var(--db-line)' }}>
                                                    <th style={{ textAlign: 'left', padding: '10px 0', color: 'var(--db-muted)', fontWeight: 500, width: 180 }}>{label}</th>
                                                    <td style={{ padding: '10px 0', color: 'var(--db-ink)' }}>{value}</td>
                                                </tr>
                                            ))}
                                            {record.notes && (
                                                <tr>
                                                    <th style={{ textAlign: 'left', padding: '10px 0', color: 'var(--db-muted)', fontWeight: 500 }}>Catatan</th>
                                                    <td style={{ padding: '10px 0', color: 'var(--db-ink)', whiteSpace: 'pre-line' }}>{record.notes}</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><MapPin size={16} /> Posisi & Radius Geofence</h2>
                                </div>
                                <div className="db-card-body">
                                    <div
                                        ref={mapRef}
                                        style={{
                                            width: '100%',
                                            height: 340,
                                            borderRadius: 14,
                                            overflow: 'hidden',
                                            border: '1px solid var(--db-line)',
                                            background: 'var(--db-card)',
                                        }}
                                    />
                                    <div className="db-hint" style={{ marginTop: 10 }}>
                                        Peta memvisualisasikan titik check-in/out terhadap radius toleransi geofence kantor.
                                    </div>
                                </div>
                            </section>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                                {points.map(({ key, label, point }) => (
                                    <section className="db-card" key={key}>
                                        <div className="db-card-head">
                                            <h2 className="db-card-title"><ImageOff size={16} /> Bukti Foto {label}</h2>
                                        </div>
                                        <div className="db-card-body">
                                            {point.photoUrl ? (
                                                <a href={point.photoUrl} target="_blank" rel="noopener noreferrer" style={{ display: 'block' }}>
                                                    <img
                                                        src={point.photoUrl}
                                                        alt={`Bukti foto ${label}`}
                                                        style={{ width: '100%', maxHeight: 260, objectFit: 'cover', borderRadius: 12, border: '1px solid var(--db-line)' }}
                                                    />
                                                </a>
                                            ) : (
                                                <div style={{ textAlign: 'center', padding: '24px 0', border: '1px dashed var(--db-line)', borderRadius: 12 }}>
                                                    <ImageOff size={28} style={{ color: 'var(--db-muted)', marginBottom: 8 }} />
                                                    <div className="db-hint" style={{ marginTop: 0 }}>Belum ada bukti foto.</div>
                                                </div>
                                            )}
                                            <div className="db-hint" style={{ marginTop: 8 }}>{point.time ? `Diambil pukul ${point.time}` : 'Waktu tidak tersedia'}</div>
                                        </div>
                                    </section>
                                ))}
                            </div>
                        </div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {points.map(({ key, label, point }) => (
                                <section className="db-card" key={key}>
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><ShieldCheck size={16} /> {label}</h2>
                                        <span className={`db-badge ${point.latitude !== null ? 'green' : 'gray'}`}>
                                            {point.latitude !== null ? 'Terverifikasi GPS' : 'Tanpa GPS'}
                                        </span>
                                    </div>
                                    <div className="db-card-body">
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
                                            <div>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Lokasi Kantor</span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)' }}>{point.location?.name ?? point.locationName ?? '-'}</b>
                                            </div>
                                            <div>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Jarak ke Pusat Geofence</span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)' }}>{formatDistance(point.distance)}</b>
                                            </div>
                                            <div>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Radius Toleransi</span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)' }}>{point.location ? formatDistance(point.location.radius) : '-'}</b>
                                            </div>
                                            <div>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Akurasi GPS</span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)' }}>{formatDistance(point.accuracy)}</b>
                                            </div>
                                            <div>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Koordinat</span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)' }}>
                                                    {point.latitude !== null && point.longitude !== null ? `${point.latitude}, ${point.longitude}` : '-'}
                                                </b>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            ))}
                        </div>
                    </div>
                </div>

                <ConfirmModal
                    open={confirming}
                    title="Hapus catatan absensi?"
                    message={`Catatan absensi ${record.employeeName} tanggal ${record.attendanceDate ?? '-'} akan dipindahkan ke tempat sampah.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={remove}
                    onCancel={() => setConfirming(false)}
                />
            </div>
        </AdminShell>
    );
}
