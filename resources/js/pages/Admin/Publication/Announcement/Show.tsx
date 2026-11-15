// Pratinjau pengumuman (read-only) — pola Show academic.
import { Head, router } from '@inertiajs/react';
import { Download, Megaphone, Paperclip, Pencil, Send, Trash2, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Announcement = {
    id: number; title: string; content: string;
    targetType: string; targetLabel: string; targetDetail: string | null;
    priority: string; priorityLabel: string;
    isPinned: boolean; isPublished: boolean; isScheduled: boolean;
    publishedAt: string | null; scheduledAt: string | null;
    attachmentName: string | null; attachmentType: string | null; attachmentSize: string | null; attachmentUrl: string | null;
    readsCount: number; creatorName: string; createdAt: string | null; updatedAt: string | null;
};

type Props = {
    shell: ShellProps;
    announcement: Announcement;
    can: { update: boolean; delete: boolean; publish: boolean };
    urls: { index: string; edit: string; publish: string; unpublish: string; destroy: string };
};

const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'];

function isImageAttachment(type: string | null, name: string | null) {
    if (type && IMAGE_EXTENSIONS.includes(type.toLowerCase())) return true;
    const ext = name?.split('.').pop()?.toLowerCase();
    return !!ext && IMAGE_EXTENSIONS.includes(ext);
}

export default function AnnouncementShow({ shell, announcement, can, urls }: Props) {
    const [pending, setPending] = useState<'publish' | 'unpublish' | 'delete' | null>(null);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending === 'publish') {
            router.post(urls.publish, {}, { preserveScroll: true, onFinish: done });
        } else if (pending === 'unpublish') {
            router.post(urls.unpublish, {}, { preserveScroll: true, onFinish: done });
        } else {
            router.delete(urls.destroy, { onFinish: done });
        }
    };

    const copy = {
        publish: { title: 'Publish pengumuman?', message: `"${announcement.title}" langsung terlihat penerima sesuai target.`, confirm: 'Ya, publish', danger: false },
        unpublish: { title: 'Batalkan publish?', message: `"${announcement.title}" kembali ke draft.`, confirm: 'Ya, batalkan', danger: true },
        delete: { title: 'Hapus pengumuman?', message: `"${announcement.title}" dipindah ke sampah. Lampiran ikut terhapus.`, confirm: 'Ya, hapus', danger: true },
    }[pending ?? 'publish'];

    return (
        <AdminShell shell={shell}>
            <Head title={`${announcement.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Megaphone}
                        eyebrow="Publikasi · Pratinjau"
                        title={announcement.title}
                        description={`${announcement.targetLabel}${announcement.targetDetail ? ` — ${announcement.targetDetail}` : ''} · oleh ${announcement.creatorName}`}
                        badges={[
                            announcement.priorityLabel,
                            announcement.isPublished ? 'Published' : 'Draft',
                            ...(announcement.isPinned ? ['Di-pin'] : []),
                            ...(announcement.isScheduled ? ['Terjadwal'] : []),
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar</a>
                                {can.update && (
                                    <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>
                                )}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Isi Pengumuman</h2>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                {can.publish && !announcement.isPublished && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('publish')}>
                                        <Send size={14} /> Publish
                                    </button>
                                )}
                                {can.publish && announcement.isPublished && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('unpublish')}>
                                        <Undo2 size={14} /> Batalkan publish
                                    </button>
                                )}
                                {can.delete && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('delete')}>
                                        <Trash2 size={14} /> Hapus
                                    </button>
                                )}
                            </div>
                        </div>
                        <div className="db-card-body">
                            <div dangerouslySetInnerHTML={{ __html: announcement.content }} />

                            <div className="crud-grid" style={{ marginTop: 18 }}>
                                <div>
                                    <span className="crud-label">Target</span>
                                    <div>{announcement.targetLabel}{announcement.targetDetail ? ` — ${announcement.targetDetail}` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Prioritas</span>
                                    <div><span className="db-badge gray">{announcement.priorityLabel}</span></div>
                                </div>
                                <div>
                                    <span className="crud-label">Dipublikasi</span>
                                    <div>{announcement.publishedAt ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Terjadwal</span>
                                    <div>{announcement.scheduledAt ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Dibaca</span>
                                    <div><span className="db-badge gray">{announcement.readsCount}x</span></div>
                                </div>
                                <div>
                                    <span className="crud-label">Dibuat · Diubah</span>
                                    <div className="db-hint">{announcement.createdAt ?? '-'} · {announcement.updatedAt ?? '-'}</div>
                                </div>
                            </div>

                            {announcement.attachmentUrl && (
                                <div style={{ marginTop: 16 }}>
                                    {isImageAttachment(announcement.attachmentType, announcement.attachmentName) ? (
                                        <img src={announcement.attachmentUrl} alt={announcement.attachmentName ?? 'Lampiran'} style={{ maxWidth: '100%', borderRadius: 12 }} />
                                    ) : null}
                                    <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                                        <Paperclip size={14} />
                                        <span style={{ flex: 1 }}>{announcement.attachmentName} ({announcement.attachmentSize})</span>
                                        <a className="db-btn ghost sm" href={announcement.attachmentUrl} target="_blank" rel="noreferrer">
                                            <Download size={14} /> Unduh
                                        </a>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy.title}
                    message={copy.message}
                    confirmLabel={copy.confirm}
                    danger={copy.danger}
                    processing={processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
