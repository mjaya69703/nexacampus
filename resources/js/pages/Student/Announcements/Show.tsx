// Detail pengumuman mahasiswa (Inertia React).
// Konten ditulis admin redaksi, jadi dirender sebagai HTML seperti Blade lama.
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft, CalendarDays, Download, FileText, Megaphone, Paperclip,
    Pin, UserRound,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Attachment = {
    name: string;
    type: string;
    size: string;
    url: string;
};

type Announcement = {
    id: number;
    title: string;
    content: string;
    priority: string | null;
    priorityLabel: string;
    priorityTone: string;
    targetLabel: string;
    isPinned: boolean;
    creator: string;
    publishedAt: string;
    publishedHuman: string | null;
    attachment: Attachment | null;
};

type Props = {
    shell: ShellProps;
    announcement: Announcement;
    urls: { index: string };
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

export default function AnnouncementShow({ shell, announcement, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`${announcement.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Megaphone}
                        eyebrow="Publikasi"
                        title={announcement.title}
                        description={`${announcement.publishedAt} · ${announcement.creator}`}
                        badges={[
                            announcement.priorityLabel,
                            announcement.targetLabel,
                            ...(announcement.isPinned ? ['Disematkan'] : []),
                        ]}
                        actions={(
                            <Link href={urls.index} className="db-btn ghost">
                                <ArrowLeft size={15} /> Kembali
                            </Link>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head" style={{ flexWrap: 'wrap', gap: 8 }}>
                            <h2 className="db-card-title"><Megaphone size={16} /> Isi Pengumuman</h2>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                <span className={`db-badge ${toneClass(announcement.priorityTone)}`}>{announcement.priorityLabel}</span>
                                {announcement.isPinned && <span className="db-badge"><Pin size={11} /> Disematkan</span>}
                            </div>
                        </div>
                        <div className="db-card-body">
                            <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', marginBottom: 14 }} className="db-hint">
                                <span><CalendarDays size={12} /> {announcement.publishedAt}</span>
                                <span><UserRound size={12} /> {announcement.creator}</span>
                                {announcement.publishedHuman && <span>{announcement.publishedHuman}</span>}
                            </div>

                            <div className="db-richtext" dangerouslySetInnerHTML={{ __html: announcement.content }} />

                            {announcement.attachment && (
                                <div className="db-note info" style={{ marginTop: 18 }}>
                                    <Paperclip size={15} />
                                    <span style={{ flex: 1 }}>
                                        <b>{announcement.attachment.name}</b>
                                        <span className="db-hint" style={{ display: 'block' }}>
                                            <FileText size={11} /> {announcement.attachment.type} · {announcement.attachment.size}
                                        </span>
                                    </span>
                                    <a href={announcement.attachment.url} className="db-btn primary sm">
                                        <Download size={13} /> Unduh
                                    </a>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
