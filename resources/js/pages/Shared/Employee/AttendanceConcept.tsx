import { Head } from '@inertiajs/react';
import {
    Activity, AlertTriangle, ArrowRight, BadgeCheck, Building2, Camera, Check, CheckCircle2, CircleDot,
    Clock3, FileCheck2, FileText, Gauge, LocateFixed, LogIn, LogOut, MapPin, Navigation, RefreshCw,
    Send, ShieldCheck, Sparkles, Sprout, Upload, UserRound, Video, X,
} from 'lucide-react';
import { Dispatch, RefObject, SetStateAction } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';

type OfficeLocation = { id: number; name: string; address: string | null; latitude: number; longitude: number; radius: number };
type HistoryRecord = {
    kind?: string; id: number | string; dateLabel: string; status: string; statusLabel: string;
    checkInLabel: string; checkOutLabel: string; locationName: string; distanceLabel: string;
    checkInPhoto: string | null; checkOutPhoto: string | null;
    checkInTime: string | null; checkOutTime: string | null; workMinutes: number | null;
};
type Balance = { id: number; typeName: string; available: number; used: number };
type LeaveType = { id: number; name: string };
type LeaveRequest = { id: number; requestNumber: string; typeName: string; periodLabel: string; totalDays: number; status: string };
type Tab = 'absensi' | 'cuti';
type Lightbox = { url: string; title: string } | null;

type Props = {
    shell: ShellProps;
    employee: { name: string; unitName: string | null };
    today: { dateLabel: string; checkedIn: boolean; checkedOut: boolean; checkInLabel: string; checkOutLabel: string };
    locations: OfficeLocation[];
    records: HistoryRecord[];
    leaveTypes: LeaveType[];
    balances: Balance[];
    leaveRequests: LeaveRequest[];
    tab: Tab;
    setTab: Dispatch<SetStateAction<Tab>>;
    pendingLeaves: number;
    videoRef: RefObject<HTMLVideoElement | null>;
    fileRef: RefObject<HTMLInputElement | null>;
    mapRef: RefObject<HTMLDivElement | null>;
    photoFile: File | null;
    photoPreview: string | null;
    photoStatus: string;
    photoReady: boolean;
    cameraOn: boolean;
    coords: { lat: number; lng: number; accuracy: number } | null;
    gpsStatus: string;
    mapStatus: string;
    mapDistance: string;
    canCheckIn: boolean;
    canCheckOut: boolean;
    uploadPercent: number;
    attendanceForm: any;
    leaveForm: any;
    leaveOpen: boolean;
    setLeaveOpen: Dispatch<SetStateAction<boolean>>;
    lightbox: Lightbox;
    setLightbox: Dispatch<SetStateAction<Lightbox>>;
    startCamera: () => Promise<void>;
    stopCamera: () => void;
    capturePhoto: () => void;
    onPickFile: (file: File | null) => Promise<void>;
    locate: () => void;
    submitCapture: (mode: 'in' | 'out') => void;
    submitLeave: (event: React.FormEvent) => void;
};

const formatMinutes = (minutes: number | null) => {
    if (minutes === null) return 'Belum selesai';
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return hours > 0 ? `${hours}j ${rest}m` : `${rest} menit`;
};

const statusTone = (status: string) => {
    if (status === 'inside_radius') return 'green';
    if (status === 'outside_radius' || status === 'gps_missing') return 'red';
    if (status === 'partial') return 'amber';
    return 'gray';
};

const leaveStatus = (status: string) => {
    if (status === 'approved') return { label: 'Disetujui', tone: 'green' };
    if (status === 'rejected') return { label: 'Ditolak', tone: 'red' };
    if (status === 'cancelled') return { label: 'Dibatalkan', tone: 'gray' };
    return { label: 'Menunggu', tone: 'amber' };
};

export default function AttendanceConcept({
    shell, employee, today, locations, records, leaveTypes, balances, leaveRequests, tab, setTab, pendingLeaves,
    videoRef, fileRef, mapRef, photoPreview, photoStatus, photoReady, cameraOn, coords, gpsStatus, mapStatus,
    mapDistance, canCheckIn, canCheckOut, uploadPercent, attendanceForm, leaveForm, leaveOpen, setLeaveOpen,
    lightbox, setLightbox, startCamera, stopCamera, capturePhoto, onPickFile, locate, submitCapture, submitLeave,
}: Props) {
    const isComplete = today.checkedIn && today.checkedOut;
    const dayStatus = isComplete ? 'Hari kerja selesai' : today.checkedIn ? 'Sedang bekerja' : 'Belum mulai';
    const latest = records[0];
    const checkedDays = records.filter((record) => record.checkInLabel !== '-').length;

    return <AdminShell shell={shell}>
        <Head title={`Kehadiran Saya · ${shell.appName}`} />
        <div className="em-root em-pulse-root">
            <div className="em-pulse-stack">
                <section className="em-pulse-hero">
                    <div className="em-pulse-hero-copy">
                        <span className="em-pulse-kicker"><Activity size={13} /> WORKDAY PULSE</span>
                        <h1>Atur ritme kerja Anda hari ini.</h1>
                        <p>{employee.name}{employee.unitName ? ` · ${employee.unitName}` : ''}. Satu ruang untuk memulai hari, memantau posisi kerja, dan menutup aktivitas dengan rapi.</p>
                        <div className="em-pulse-hero-meta"><span><CalendarIcon /> {today.dateLabel}</span><span><CircleDot size={13} /> {dayStatus}</span></div>
                    </div>
                    <div className={`em-pulse-orbit ${isComplete ? 'complete' : today.checkedIn ? 'working' : ''}`}><div><strong>{today.checkedIn ? today.checkInLabel : '—'}</strong><small>mulai kerja</small></div><span className="em-pulse-orbit-line" /><div><strong>{today.checkedOut ? today.checkOutLabel : '—'}</strong><small>selesai</small></div></div>
                </section>

                <div className="em-pulse-metrics">
                    <Metric icon={<Gauge size={18} />} label="Status hari ini" value={dayStatus} tone={today.checkedIn ? 'green' : 'gold'} detail={today.checkedOut ? 'Semua checkpoint selesai' : 'Selesaikan checkpoint berikutnya'} />
                    <Metric icon={<Clock3 size={18} />} label="Checkpoint" value={`${today.checkedIn ? 1 : 0}/2`} tone="blue" detail={today.checkedOut ? 'Masuk dan keluar tercatat' : 'Dua momen dalam satu hari'} />
                    <Metric icon={<BadgeCheck size={18} />} label="Riwayat aktif" value={`${checkedDays} hari`} tone="violet" detail="Data kehadiran tersimpan" />
                    <Metric icon={<Sprout size={18} />} label="Cuti berproses" value={String(pendingLeaves)} tone="amber" detail="Pengajuan membutuhkan perhatian" />
                </div>

                <div className="em-pulse-switcher" role="tablist" aria-label="Ruang kerja pegawai">
                    <button type="button" className={tab === 'absensi' ? 'active' : ''} onClick={() => setTab('absensi')}><Clock3 size={16} /><span><b>Ritme hari ini</b><small>Absensi & lokasi kerja</small></span></button>
                    <button type="button" className={tab === 'cuti' ? 'active' : ''} onClick={() => setTab('cuti')}><Sprout size={16} /><span><b>Ruang jeda</b><small>Saldo & pengajuan cuti</small></span>{pendingLeaves > 0 && <i>{pendingLeaves}</i>}</button>
                </div>

                {tab === 'absensi' && <>
                    <div className="em-pulse-grid">
                        <section className="em-pulse-card em-checkpoint-card">
                            <div className="em-pulse-card-head"><div><span className="em-pulse-label">CHECKPOINT BERIKUTNYA</span><h2>{today.checkedOut ? 'Hari kerja selesai.' : today.checkedIn ? 'Tutup hari dengan check-out.' : 'Mulai hari dengan check-in.'}</h2></div><span className={`em-pulse-state ${isComplete ? 'done' : today.checkedIn ? 'working' : 'waiting'}`}>{isComplete ? <CheckCircle2 size={14} /> : <CircleDot size={14} />} {isComplete ? 'Selesai' : today.checkedIn ? 'Berjalan' : 'Menunggu'}</span></div>
                            <p className="em-pulse-copy">{today.checkedOut ? 'Bukti kehadiran hari ini sudah lengkap. Anda bisa melihat detailnya di riwayat.' : today.checkedIn ? 'Ambil foto dan pastikan GPS aktif untuk mencatat waktu pulang Anda.' : 'Siapkan foto dan lokasi. Sistem akan memvalidasi bahwa Anda berada di radius kantor.'}</p>
                            <div className="em-pulse-steps"><PulseStep icon={<LogIn size={15} />} title="Check-in" value={today.checkedIn ? today.checkInLabel : 'Belum tercatat'} active={!today.checkedIn} done={today.checkedIn} /><span className="em-pulse-step-line" /><PulseStep icon={<LogOut size={15} />} title="Check-out" value={today.checkedOut ? today.checkOutLabel : 'Menunggu check-in'} active={today.checkedIn && !today.checkedOut} done={today.checkedOut} /></div>
                            {!isComplete && <div className="em-pulse-capture">
                                <div className="em-pulse-camera-frame">{photoPreview ? <img src={photoPreview} alt="Preview bukti absensi" /> : <><video ref={videoRef} playsInline autoPlay muted style={{ display: cameraOn ? 'block' : 'none' }} /><div className="em-pulse-camera-empty" style={{ display: cameraOn ? 'none' : 'grid' }}><Camera size={25} /><span>Foto bukti belum disiapkan</span><small>Gunakan kamera atau unggah foto dari perangkat</small></div></>}</div>
                                <input ref={fileRef} type="file" accept="image/*" hidden onChange={(event) => void onPickFile(event.target.files?.[0] ?? null)} />
                                <div className="em-pulse-capture-actions">{!cameraOn ? <button type="button" className="em-pulse-btn secondary" onClick={() => void startCamera()}><Video size={15} /> Kamera</button> : <button type="button" className="em-pulse-btn secondary" onClick={stopCamera}><X size={15} /> Matikan</button>}<button type="button" className="em-pulse-btn secondary" onClick={capturePhoto}><Camera size={15} /> Ambil foto</button><button type="button" className="em-pulse-btn secondary" onClick={() => fileRef.current?.click()}><Upload size={15} /> Unggah</button></div>
                                <div className="em-pulse-readiness"><span className={photoReady ? 'ready' : ''}><FileCheck2 size={15} /> {photoReady ? 'Bukti foto siap' : photoStatus}</span>{coords ? <span className="ready"><LocateFixed size={15} /> GPS akurat {coords.accuracy} m</span> : <span><LocateFixed size={15} /> GPS belum siap</span>}</div>
                                {uploadPercent > 0 && <div className="em-progress"><i style={{ width: `${uploadPercent}%` }} /></div>}
                                {(attendanceForm.errors.photo || attendanceForm.errors.latitude || attendanceForm.errors.longitude) && <div className="em-pulse-error"><AlertTriangle size={14} /> {attendanceForm.errors.photo || attendanceForm.errors.latitude || attendanceForm.errors.longitude}</div>}
                                <div className="em-pulse-action-row"><button type="button" className="em-pulse-btn primary" disabled={!canCheckIn || attendanceForm.processing} onClick={() => submitCapture('in')}><LogIn size={15} /> {attendanceForm.processing ? 'Mengirim…' : 'Catat check-in'}</button><button type="button" className="em-pulse-btn outline" disabled={!canCheckOut || attendanceForm.processing} onClick={() => submitCapture('out')}><LogOut size={15} /> Catat check-out</button></div>
                            </div>}
                            {isComplete && <div className="em-pulse-complete"><CheckCircle2 size={20} /><div><b>Checkpoint hari ini lengkap.</b><small>Terima kasih sudah menutup aktivitas kerja dengan tertib.</small></div></div>}
                        </section>

                        <div className="em-pulse-side-stack">
                            <section className="em-pulse-card em-location-card"><div className="em-pulse-card-head"><div><span className="em-pulse-label">GEOFENCE</span><h2>Posisi kerja</h2></div><button type="button" className="em-pulse-icon-btn" onClick={locate} aria-label="Perbarui lokasi"><RefreshCw size={15} /></button></div><div className="em-pulse-location-status"><span className={coords ? 'ready' : ''}><LocateFixed size={17} /><b>{coords ? gpsStatus : 'Lokasi belum terbaca'}</b></span><small>{coords ? `${coords.lat}, ${coords.lng}` : 'Izinkan akses GPS untuk melanjutkan absensi.'}</small></div><div className="em-pulse-map" ref={mapRef} /><div className="em-pulse-map-caption"><span><Navigation size={13} /> {mapStatus}</span><b>{mapDistance}</b></div></section>
                            <section className="em-pulse-card em-office-card"><div className="em-pulse-card-head"><div><span className="em-pulse-label">AREA KERJA</span><h2>Lokasi yang diizinkan</h2></div><Building2 size={19} /></div>{locations.length === 0 ? <div className="em-pulse-empty-mini"><AlertTriangle size={16} /> Belum ada lokasi absensi aktif.</div> : <div className="em-office-list">{locations.map((location) => <div key={location.id}><span><MapPin size={14} /><b>{location.name}</b></span><small>{location.address || 'Alamat belum tersedia'} · radius {location.radius} m</small></div>)}</div>}</section>
                        </div>
                    </div>

                    <section className="em-pulse-card em-history-card"><div className="em-pulse-card-head"><div><span className="em-pulse-label">JEJAK KEHADIRAN</span><h2>Ritme kerja terakhir</h2><p>Rekap aktivitas yang tercatat di sistem.</p></div><span className="em-pulse-count">{records.length} catatan</span></div>{records.length === 0 ? <EmptyState icon={Clock3} iconSize={26} title="Belum ada riwayat" description="Riwayat kehadiran akan tampil setelah Anda melakukan absensi." /> : <div className="em-history-list">{records.map((record, index) => <article className="em-history-item" key={record.id}><div className="em-history-date"><b>{record.dateLabel.split(' ')[0]}</b><small>{record.dateLabel.split(' ').slice(1).join(' ')}</small></div><span className={`em-history-dot ${statusTone(record.status)}`} />{index < records.length - 1 && <span className="em-history-line" />}<div className="em-history-main"><div><b>{record.locationName}</b><span className={`em-badge ${statusTone(record.status)}`}>{record.statusLabel}</span></div><p><span><LogIn size={13} /> {record.checkInLabel}</span><span><LogOut size={13} /> {record.checkOutLabel}</span><span><Clock3 size={13} /> {formatMinutes(record.workMinutes)}</span><span><Navigation size={13} /> {record.distanceLabel}</span></p></div><div className="em-history-proof">{record.checkInPhoto && <button type="button" onClick={() => setLightbox({ url: record.checkInPhoto!, title: `Foto masuk · ${record.checkInTime ?? record.dateLabel}` })}><Camera size={13} /> Masuk</button>}{record.checkOutPhoto && <button type="button" onClick={() => setLightbox({ url: record.checkOutPhoto!, title: `Foto keluar · ${record.checkOutTime ?? record.dateLabel}` })}><Camera size={13} /> Keluar</button>}</div></article>)}</div>}</section>
                    <div className="em-pulse-guidance"><ShieldCheck size={16} /><span><b>Validasi otomatis aktif.</b> Absensi membutuhkan bukti foto, GPS aktif, dan posisi di dalam radius lokasi kantor.</span></div>
                </>}

                {tab === 'cuti' && <LeaveWorkspace balances={balances} leaveTypes={leaveTypes} leaveRequests={leaveRequests} leaveForm={leaveForm} leaveOpen={leaveOpen} setLeaveOpen={setLeaveOpen} submitLeave={submitLeave} />}
            </div>
        </div>

        {lightbox && <div className="as-palette-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) setLightbox(null); }}><div className="as-palette" style={{ width: 'min(40rem, 100%)' }} role="dialog" aria-label={lightbox.title}><div className="as-palette-input"><Camera size={15} /><input value={lightbox.title} readOnly aria-label="Judul foto" style={{ border: 0 }} /><button type="button" className="as-burger" style={{ display: 'grid' }} aria-label="Tutup foto" onClick={() => setLightbox(null)}><X size={15} /></button></div><img src={lightbox.url} alt={lightbox.title} style={{ display: 'block', width: '100%' }} /><div className="as-palette-foot"><a href={lightbox.url} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--as-primary)', fontWeight: 700, textDecoration: 'none' }}>Buka foto di tab baru</a></div></div></div>}
    </AdminShell>;
}

function Metric({ icon, label, value, detail, tone }: { icon: React.ReactNode; label: string; value: string; detail: string; tone: string }) {
    return <div className={`em-pulse-metric ${tone}`}><span>{icon}</span><div><small>{label}</small><b>{value}</b><em>{detail}</em></div></div>;
}

function PulseStep({ icon, title, value, active, done }: { icon: React.ReactNode; title: string; value: string; active: boolean; done: boolean }) {
    return <div className={`em-pulse-step${active ? ' active' : ''}${done ? ' done' : ''}`}><span>{done ? <Check size={15} /> : icon}</span><p><b>{title}</b><small>{value}</small></p></div>;
}

function LeaveWorkspace({ balances, leaveTypes, leaveRequests, leaveForm, leaveOpen, setLeaveOpen, submitLeave }: { balances: Balance[]; leaveTypes: LeaveType[]; leaveRequests: LeaveRequest[]; leaveForm: any; leaveOpen: boolean; setLeaveOpen: Dispatch<SetStateAction<boolean>>; submitLeave: (event: React.FormEvent) => void }) {
    return <div className="em-pulse-leave">
        <section className="em-pulse-leave-intro"><div><span className="em-pulse-kicker"><Sprout size={13} /> RUANG JEDA</span><h2>Istirahat juga bagian dari ritme kerja.</h2><p>Rencanakan waktu jeda, lihat saldo yang tersedia, dan kirim pengajuan melalui alur persetujuan yang terhubung.</p></div><span className="em-pulse-leave-art"><Sprout size={42} /></span></section>
        <div className="em-pulse-leave-grid"><section className="em-pulse-card"><div className="em-pulse-card-head"><div><span className="em-pulse-label">SALDO TAHUN INI</span><h2>Ruang jeda tersedia</h2></div><span className="em-pulse-count">{balances.length} jenis</span></div>{balances.length === 0 ? <div className="em-pulse-empty-mini"><AlertTriangle size={16} /> Saldo cuti belum dibuat untuk tahun ini.</div> : <div className="em-pulse-balance-list">{balances.map((balance) => <div key={balance.id}><span className="em-pulse-balance-icon"><Sprout size={16} /></span><div><b>{balance.typeName}</b><small>{balance.used} hari sudah digunakan</small></div><strong>{balance.available}<small>tersedia</small></strong></div>)}</div>}</section>
            <section className="em-pulse-card"><div className="em-pulse-card-head"><div><span className="em-pulse-label">AKSI</span><h2>Ajukan waktu jeda</h2></div><FileText size={19} /></div>{!leaveOpen ? <div className="em-pulse-leave-cta"><div><b>Butuh waktu untuk recharge?</b><p>Isi pengajuan baru dan pantau prosesnya di daftar riwayat.</p></div><button type="button" className="em-pulse-btn primary" onClick={() => setLeaveOpen(true)}><Send size={15} /> Ajukan cuti</button></div> : <form className="em-pulse-leave-form" onSubmit={submitLeave}><Field label="Jenis cuti"><select className="em-input" value={leaveForm.data.employee_leave_type_id} onChange={(event) => leaveForm.setData('employee_leave_type_id', event.target.value)}><option value="">Pilih jenis cuti</option>{leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}</select><ErrorText message={leaveForm.errors.employee_leave_type_id} /></Field><div className="em-pulse-form-grid"><Field label="Mulai"><input className="em-input" type="date" value={leaveForm.data.starts_at} onChange={(event) => leaveForm.setData('starts_at', event.target.value)} /><ErrorText message={leaveForm.errors.starts_at} /></Field><Field label="Selesai"><input className="em-input" type="date" min={leaveForm.data.starts_at || undefined} value={leaveForm.data.ends_at} onChange={(event) => leaveForm.setData('ends_at', event.target.value)} /><ErrorText message={leaveForm.errors.ends_at} /></Field></div><Field label="Alasan"><textarea className="em-textarea" rows={3} value={leaveForm.data.reason} onChange={(event) => leaveForm.setData('reason', event.target.value)} placeholder="Keperluan cuti" /><ErrorText message={leaveForm.errors.reason} /><ErrorText message={leaveForm.errors.balance} /></Field><Field label="Catatan tambahan"><textarea className="em-textarea" rows={2} value={leaveForm.data.employee_notes} onChange={(event) => leaveForm.setData('employee_notes', event.target.value)} placeholder="Opsional" /><ErrorText message={leaveForm.errors.employee_notes} /></Field><div className="em-pulse-form-actions"><button type="button" className="em-pulse-btn secondary" onClick={() => setLeaveOpen(false)}>Batal</button><button type="submit" className="em-pulse-btn primary" disabled={leaveForm.processing}><Send size={15} /> {leaveForm.processing ? 'Mengirim…' : 'Kirim pengajuan'}</button></div></form>}</section></div>
        <section className="em-pulse-card em-history-card"><div className="em-pulse-card-head"><div><span className="em-pulse-label">RIWAYAT PENGAJUAN</span><h2>Jadwal jeda Anda</h2><p>Semua pengajuan yang masuk ke alur persetujuan.</p></div><span className="em-pulse-count">{leaveRequests.length} pengajuan</span></div>{leaveRequests.length === 0 ? <EmptyState icon={Sprout} iconSize={26} title="Belum ada pengajuan" description="Pengajuan cuti Anda akan tampil di sini." /> : <div className="em-leave-history">{leaveRequests.map((item) => { const meta = leaveStatus(item.status); return <article key={item.id}><span className={`em-leave-icon ${meta.tone}`}><FileText size={16} /></span><div><b>{item.typeName}</b><small>{item.requestNumber} · {item.periodLabel}</small></div><strong>{item.totalDays}<small>hari</small></strong><span className={`em-badge ${meta.tone}`}>{meta.label}</span></article>; })}</div>}</section>
    </div>;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) { return <label className="em-field"><span>{label}</span>{children}</label>; }
function ErrorText({ message }: { message?: string }) { return message ? <small className="em-error">{message}</small> : null; }
function CalendarIcon() { return <span className="em-calendar-icon">▦</span>; }
