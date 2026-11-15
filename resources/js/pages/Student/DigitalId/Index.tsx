// Kartu Mahasiswa digital (Inertia React) — kartu + QR verifikasi.
// QR memakai token HMAC sehingga bisa diverifikasi lewat halaman publik.
import { Head } from '@inertiajs/react';
import {
    BadgeCheck, Building2, CalendarDays, GraduationCap, IdCard, Printer,
    QrCode, ShieldCheck, TriangleAlert,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Card = {
    name: string;
    nim: string;
    studyProgram: string;
    faculty: string;
    entryYear: string | number;
    semester: string | number;
    status: string;
    active: boolean;
    photo: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    card: Card | null;
    verificationUrl: string | null;
    qrSvg: string | null;
};

export default function DigitalIdIndex({ shell, hasProfile, card, verificationUrl, qrSvg }: Props) {
    const print = (): void => {
        window.print();
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Kartu Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={IdCard}
                        eyebrow="Kemahasiswaan"
                        title="Kartu Mahasiswa"
                        description="Kartu identitas digital dengan QR verifikasi untuk layanan kampus."
                        badges={card ? [`NIM ${card.nim}`, card.status] : []}
                        actions={card ? (
                            <button type="button" className="db-btn ghost" onClick={print}>
                                <Printer size={15} /> Cetak
                            </button>
                        ) : undefined}
                    />

                    {!hasProfile || card === null ? (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Kartu mahasiswa tidak bisa ditampilkan.</span>
                        </div>
                    ) : (
                        <div className="db-grid-2" style={{ alignItems: 'start' }}>
                            <section className="db-card">
                                <div
                                    style={{
                                        borderRadius: 18,
                                        padding: 22,
                                        color: '#fff',
                                        background: 'linear-gradient(140deg, #6d4bd8 0%, #4b2fa8 55%, #2f1a73 100%)',
                                        boxShadow: '0 18px 40px rgba(76, 47, 168, .28)',
                                    }}
                                >
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
                                        <div>
                                            <div style={{ fontSize: 11, letterSpacing: '.16em', textTransform: 'uppercase', opacity: .8 }}>
                                                {shell.campusName}
                                            </div>
                                            <div style={{ fontSize: 18, fontWeight: 700, marginTop: 2 }}>Kartu Mahasiswa</div>
                                        </div>
                                        <BadgeCheck size={26} style={{ opacity: .9 }} />
                                    </div>

                                    <div style={{ display: 'flex', gap: 16, marginTop: 20, alignItems: 'center' }}>
                                        <img
                                            src={card.photo}
                                            alt={card.name}
                                            style={{
                                                width: 92,
                                                height: 92,
                                                objectFit: 'cover',
                                                borderRadius: 14,
                                                border: '2px solid rgba(255,255,255,.35)',
                                                background: 'rgba(255,255,255,.15)',
                                            }}
                                        />
                                        <div style={{ minWidth: 0 }}>
                                            <div style={{ fontSize: 18, fontWeight: 700, lineHeight: 1.3 }}>{card.name}</div>
                                            <div style={{ opacity: .9, fontSize: 13, marginTop: 2 }}>{card.nim}</div>
                                            <div style={{ opacity: .85, fontSize: 12, marginTop: 6 }}>{card.studyProgram}</div>
                                            <div style={{ opacity: .7, fontSize: 11.5 }}>{card.faculty}</div>
                                        </div>
                                    </div>

                                    <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap', marginTop: 22, fontSize: 12 }}>
                                        <div>
                                            <div style={{ opacity: .7, fontSize: 10.5, textTransform: 'uppercase', letterSpacing: '.08em' }}>Angkatan</div>
                                            <div style={{ fontWeight: 700 }}>{card.entryYear}</div>
                                        </div>
                                        <div>
                                            <div style={{ opacity: .7, fontSize: 10.5, textTransform: 'uppercase', letterSpacing: '.08em' }}>Semester</div>
                                            <div style={{ fontWeight: 700 }}>{card.semester}</div>
                                        </div>
                                        <div>
                                            <div style={{ opacity: .7, fontSize: 10.5, textTransform: 'uppercase', letterSpacing: '.08em' }}>Status</div>
                                            <div style={{ fontWeight: 700 }}>{card.active ? card.status : `${card.status} (non-aktif)`}</div>
                                        </div>
                                    </div>
                                </div>

                                <div className="db-card-body" style={{ paddingTop: 14 }}>
                                    <div className="db-note muted" style={{ margin: 0 }}>
                                        <ShieldCheck size={15} />
                                        <span>
                                            Kartu ini hanya berlaku selama status mahasiswa aktif. Tunjukkan bersama identitas lain
                                            saat meminta layanan di kampus.
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><QrCode size={16} /> QR Verifikasi</h2>
                                    {card.active
                                        ? <span className="db-badge green">Aktif</span>
                                        : <span className="db-badge red">Non-aktif</span>}
                                </div>
                                <div className="db-card-body">
                                    {qrSvg ? (
                                        <div
                                            style={{
                                                display: 'grid',
                                                placeItems: 'center',
                                                padding: 18,
                                                borderRadius: 16,
                                                background: '#fff',
                                                border: '1px solid var(--db-line)',
                                            }}
                                            // SVG dihasilkan library QR dari data server, bukan input pengguna.
                                            dangerouslySetInnerHTML={{ __html: qrSvg }}
                                        />
                                    ) : (
                                        <div className="db-empty-calm">
                                            <QrCode size={20} />
                                            <div><b>QR belum tersedia</b><small>Hubungi admin akademik bila kartu tidak memuat QR.</small></div>
                                        </div>
                                    )}

                                    {verificationUrl && (
                                        <div style={{ marginTop: 12 }}>
                                            <div className="db-hint">Tautan verifikasi:</div>
                                            <a
                                                href={verificationUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="db-hint"
                                                style={{ wordBreak: 'break-all', color: 'var(--db-brand)' }}
                                            >
                                                {verificationUrl}
                                            </a>
                                        </div>
                                    )}

                                    <div style={{ display: 'grid', gap: 8, marginTop: 16 }}>
                                        <div className="db-summary">
                                            <span className="db-summary-icon"><GraduationCap size={17} /></span>
                                            <div><b>{card.studyProgram}</b><small>Program studi</small></div>
                                        </div>
                                        <div className="db-summary">
                                            <span className="db-summary-icon"><Building2 size={17} /></span>
                                            <div><b>{card.faculty}</b><small>Fakultas</small></div>
                                        </div>
                                        <div className="db-summary">
                                            <span className="db-summary-icon gold"><CalendarDays size={17} /></span>
                                            <div><b>Angkatan {card.entryYear}</b><small>Semester {card.semester}</small></div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
