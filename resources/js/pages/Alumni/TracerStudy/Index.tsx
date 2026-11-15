// Tracer study alumni — daftar kampanye survei (Inertia React).
import { Head } from '@inertiajs/react';
import {
    CalendarDays, CheckCircle2, ClipboardList, Clock3, GraduationCap,
    Inbox, UserRoundX,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Campaign = {
    id: number;
    title: string;
    description: string | null;
    academicYear: string | null;
    startDate: string | null;
    endDate: string | null;
    questionsCount: number;
    hasResponded: boolean;
    isOpen: boolean;
    statusLabel: string;
    showUrl: string;
    fillUrl: string | null;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    campaigns: Campaign[];
    urls: { index: string; profile: string };
};

export default function TracerStudyIndex({ shell, hasProfile, campaigns, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Tracer Study · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Portal alumni"
                        title="Tracer Study"
                        description="Bantu kampus meningkatkan mutu pendidikan dengan berbagi pengalaman karir dan studi lanjutmu."
                        badges={hasProfile ? [`${campaigns.length} kampanye`] : undefined}
                    />

                    {!hasProfile ? (
                        <section className="db-card">
                            <div className="db-card-body" style={{ display: 'grid', justifyItems: 'center', gap: 10, padding: '48px 24px', textAlign: 'center' }}>
                                <span className="db-stat-icon red"><UserRoundX size={22} /></span>
                                <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 18 }}>Profil Alumni Belum Tersedia</h2>
                                <p className="db-hint" style={{ margin: 0, maxWidth: 420 }}>
                                    Hubungi administrator atau bagian alumni untuk mengaktifkan profil alumni kamu.
                                </p>
                                <a className="db-btn ghost" href={urls.profile}>Buka halaman profil</a>
                            </div>
                        </section>
                    ) : campaigns.length === 0 ? (
                        <section className="db-card">
                            <div className="db-card-body" style={{ display: 'grid', justifyItems: 'center', gap: 10, padding: '48px 24px', textAlign: 'center' }}>
                                <span className="db-stat-icon"><Inbox size={22} /></span>
                                <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 18 }}>Belum Ada Survei untuk Kamu</h2>
                                <p className="db-hint" style={{ margin: 0, maxWidth: 460 }}>
                                    Saat ini belum ada kampanye tracer study yang aktif dan sesuai dengan tahun lulus atau program studimu.
                                </p>
                            </div>
                        </section>
                    ) : (
                        <div className="db-stack">
                            {campaigns.map((campaign) => (
                                <section key={campaign.id} className="db-card">
                                    <div className="db-card-body" style={{ display: 'flex', flexWrap: 'wrap', gap: 18, alignItems: 'flex-start' }}>
                                        <div style={{ flex: '1 1 320px', minWidth: 240 }}>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 8 }}>
                                                <span className={`db-badge ${campaign.hasResponded ? 'green' : campaign.isOpen ? 'amber' : 'gray'}`}>
                                                    {campaign.hasResponded ? <CheckCircle2 size={11} /> : <Clock3 size={11} />}
                                                    {campaign.statusLabel}
                                                </span>
                                                <span className="db-badge gray">{campaign.questionsCount} pertanyaan</span>
                                            </div>
                                            <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 16 }}>{campaign.title}</h2>
                                            {campaign.description && (
                                                <p className="db-hint" style={{ margin: '6px 0 0' }}>{campaign.description}</p>
                                            )}
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginTop: 8 }}>
                                                <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                    <CalendarDays size={12} /> {campaign.startDate ?? '-'} - {campaign.endDate ?? '-'}
                                                </span>
                                                {campaign.academicYear && (
                                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                        <GraduationCap size={12} /> {campaign.academicYear}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <div style={{ display: 'grid', gap: 8, justifyItems: 'end' }}>
                                            {campaign.fillUrl ? (
                                                <>
                                                    <a className="db-btn primary" href={campaign.fillUrl}>Isi survei</a>
                                                    <a className="db-btn ghost sm" href={campaign.showUrl}>Lihat detail</a>
                                                </>
                                            ) : (
                                                <a className="db-btn ghost" href={campaign.showUrl}>Lihat detail</a>
                                            )}
                                        </div>
                                    </div>
                                </section>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
