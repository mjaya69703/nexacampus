// Public student achievements (prestasi mahasiswa) page.
import { Head } from '@inertiajs/react';
import {
    Award, CalendarDays, GraduationCap, HeartPulse, Medal, Star, Trophy, Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StatStrip } from '../../../components/Shared/StatStrip';

type Achievement = {
    id: number;
    title: string;
    student: string;
    nim: string | null;
    program: string | null;
    initials: string;
    organization: string | null;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    level: string;
    levelLabel: string;
    levelTone: string;
    rankLabel: string;
    rankTone: string;
    achievedAt: string | null;
    year: string | null;
    organizer: string | null;
    description: string | null;
    isFeatured: boolean;
};

type PrestasiProps = CommunityBaseProps & {
    achievements: Achievement[];
    stats: { total: number; featured: number; national: number; students: number };
    levels: Record<string, string>;
};

const iconToneFor = (tone: string): string => {
    switch (tone) {
        case 'green':
        case 'red':
            return tone;
        case 'amber':
            return 'gold';
        default:
            return '';
    }
};

const badgeFor = (tone: string): string => {
    switch (tone) {
        case 'green':
        case 'red':
        case 'amber':
        case 'gray':
            return tone;
        default:
            return '';
    }
};

export default function Prestasi({ campus, links, user, achievements, stats, levels }: PrestasiProps) {
    const [activeLevel, setActiveLevel] = useState('all');

    const levelEntries = Object.entries(levels);

    const filtered = useMemo(
        () => (activeLevel === 'all' ? achievements : achievements.filter((achievement) => achievement.level === activeLevel)),
        [activeLevel, achievements],
    );

    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Prestasi" eyebrow="Prestasi Mahasiswa" title="Prestasi & penghargaan mahasiswa." description="Mahasiswa NexaCampus terus mengukir prestasi gemilang di tingkat nasional maupun internasional. Jadilah bagian dari sejarah kami." icon={Trophy}>
        <Head title={`Prestasi Mahasiswa · ${campus.name}`} />

        <div className="adm-hero">
            <div className="public-container">
                <div className="adm-hero-inner">
                    <div>
                        <span className="adm-crumb"><Trophy size={14} /> {stats.total} Prestasi Terverifikasi</span>
                        <p className="adm-hero-desc" style={{ maxWidth: 560 }}>
                            Galeri capaian mahasiswa lintas kompetisi akademik, olahraga, seni, riset, dan kewirausahaan — dari tingkat lokal hingga internasional.
                        </p>
                    </div>
                    <div className="adm-hero-cta">
                        <a className="adm-btn outline" href="/kemahasiswaan/organisasi"><Users size={15} /> Organisasi Mahasiswa</a>
                        <a className="adm-btn light" href="/kemahasiswaan/layanan"><HeartPulse size={15} /> Layanan Mahasiswa</a>
                    </div>
                </div>
            </div>
        </div>

        <StatStrip
            items={[
                { icon: Trophy, value: stats.total, label: 'Prestasi Terverifikasi' },
                { icon: Medal, value: stats.national, label: 'Nasional & Internasional', tone: 'gold' },
                { icon: Star, value: stats.featured, label: 'Sorotan Kampus', tone: 'gold' },
                { icon: GraduationCap, value: stats.students, label: 'Mahasiswa Berprestasi', tone: 'green' },
            ]}
        />

        {achievements.length === 0 ? (
            <EmptyState
                icon={Award}
                iconSize={28}
                title="Belum Ada Prestasi Terverifikasi"
                description="Galeri prestasi akan ditampilkan di sini setelah admin memverifikasi capaian mahasiswa."
            />
        ) : (
            <>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    <button
                        type="button"
                        className={`adm-btn ${activeLevel === 'all' ? '' : 'ghost'} sm`}
                        onClick={() => setActiveLevel('all')}
                    >
                        Semua <span className="adm-badge gray">{achievements.length}</span>
                    </button>
                    {levelEntries.map(([value, label]) => {
                        const count = achievements.filter((achievement) => achievement.level === value).length;

                        if (count === 0) return null;

                        return (
                            <button
                                key={value}
                                type="button"
                                className={`adm-btn ${activeLevel === value ? '' : 'ghost'} sm`}
                                onClick={() => setActiveLevel(value)}
                            >
                                {label} <span className="adm-badge gray">{count}</span>
                            </button>
                        );
                    })}
                </div>

                {filtered.length === 0 ? (
                    <EmptyState
                        icon={Award}
                        iconSize={24}
                        title="Belum Ada Prestasi pada Tingkat Ini"
                        description="Pilih tingkat lain untuk melihat capaian mahasiswa yang tersedia."
                    />
                ) : (
                    <div className="adm-grid adm-cols-3">
                        {filtered.map((achievement) => (
                            <article className="adm-card" key={achievement.id}>
                                <div className="adm-card-body" style={{ display: 'grid', gap: 11, alignContent: 'start' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                                        <span className={`adm-stat-icon ${iconToneFor(achievement.levelTone)}`} style={{ width: 46, height: 46, borderRadius: 13, font: "700 14px 'Source Serif 4', Georgia, serif" }}>
                                            {achievement.initials || <Trophy size={20} />}
                                        </span>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-end', gap: 5 }}>
                                            {achievement.isFeatured && <span className="adm-badge amber"><Star size={10} /> Sorotan</span>}
                                            <span className={`adm-badge ${badgeFor(achievement.levelTone)}`.trim()}>{achievement.levelLabel}</span>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 15px/1.35 'Source Serif 4', Georgia, serif" }}>
                                            {achievement.title}
                                        </h3>
                                        <small className="adm-hint">
                                            {achievement.student}{achievement.program ? ` · ${achievement.program}` : ''}
                                        </small>
                                    </div>

                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                        <span className={`adm-badge ${badgeFor(achievement.rankTone)}`.trim()}>{achievement.rankLabel}</span>
                                        <span className={`adm-badge ${badgeFor(achievement.categoryTone)}`.trim()}>{achievement.categoryLabel}</span>
                                    </div>

                                    {achievement.description && (
                                        <p className="adm-hint" style={{ margin: 0 }}>{achievement.description}</p>
                                    )}

                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
                                        <span className="adm-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                            <CalendarDays size={11} /> {achievement.achievedAt || achievement.year || '—'}
                                        </span>
                                        {achievement.organizer && (
                                            <span className="adm-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                <Medal size={11} /> {achievement.organizer}
                                            </span>
                                        )}
                                    </div>

                                    {achievement.organization && (
                                        <small className="adm-hint">Melalui {achievement.organization}</small>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </>
        )}

        <div className="adm-note gold">
            <Star size={16} />
            <div>
                <b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)' }}>Kebanggaan bersama.</b>
                Setiap prestasi yang tercatat adalah hasil kerja keras mahasiswa, pembina, dan organisasi kemahasiswaan. Data diverifikasi oleh admin kemahasiswaan sebelum ditampilkan.
            </div>
        </div>
    </KemahasiswaanShell>;
}
