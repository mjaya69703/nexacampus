import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Megaphone } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Att = { name: string; type: string; size: string; url: string } | null;
type Ann = { id: number; title: string; content: string; priority: string | null; priorityLabel: string; isPinned: boolean; creator: string; publishedAt: string; attachment: Att };

type Props = { shell: ShellProps; announcement: Ann; urls: { index: string; edit: string | null } };

export default function AnnouncementsShow({ shell, announcement, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Pengumuman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Megaphone} eyebrow="Publikasi" title={announcement.title} description="Detail pengumuman" badges={[announcement.priorityLabel]} />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <div><b>Prioritas:</b> {announcement.priorityLabel}</div>
                            <div><b>Pembuat:</b> {announcement.creator}</div>
                            <div><b>Dipublikasikan:</b> {announcement.publishedAt}</div>
                            {announcement.isPinned && <div>📌 Disematkan</div>}
                            <div style={{ marginTop: 8 }} dangerouslySetInnerHTML={{ __html: announcement.content }} />
                            {announcement.attachment && (
                                <a href={announcement.attachment.url} target="_blank" className="db-btn ghost sm">
                                    Lampiran: {announcement.attachment.name}
                                </a>
                            )}
                            {urls.edit && <Link href={urls.edit} className="db-btn primary sm">Edit</Link>}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}