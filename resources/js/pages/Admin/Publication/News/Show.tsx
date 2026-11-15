// Pratinjau berita (read-only) — pola Show academic.
import { Head, router } from '@inertiajs/react';
import { Image, Newspaper, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type News = {
    id: number; title: string; slug: string; content: string; excerpt: string | null;
    category: string | null; isPublished: boolean; publishedAt: string | null;
    imageUrl: string | null; creatorName: string; createdAt: string | null; updatedAt: string | null;
};

type Props = {
    shell: ShellProps;
    news: News;
    can: { update: boolean; delete: boolean };
    urls: { index: string; edit: string; destroy: string };
};

export default function NewsShow({ shell, news, can, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <AdminShell shell={shell}>
            <Head title={`${news.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Newspaper}
                        eyebrow="Publikasi · Pratinjau"
                        title={news.title}
                        description={`${news.category ?? 'Tanpa kategori'} · oleh ${news.creatorName}`}
                        badges={[news.slug, news.isPublished ? 'Published' : 'Draft']}
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
                            <h2 className="db-card-title">Isi Berita</h2>
                            {can.delete && (
                                <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 size={14} /> Hapus
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            {news.imageUrl && (
                                <img src={news.imageUrl} alt={news.title} style={{ maxWidth: '100%', borderRadius: 12, marginBottom: 16 }} />
                            )}
                            {news.excerpt && (
                                <p className="db-hint" style={{ fontSize: 13.5 }}>{news.excerpt}</p>
                            )}
                            <div dangerouslySetInnerHTML={{ __html: news.content }} />

                            <div className="crud-grid" style={{ marginTop: 18 }}>
                                <div>
                                    <span className="crud-label">Kategori</span>
                                    <div>{news.category ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Dipublikasi</span>
                                    <div>{news.publishedAt ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Status sampul</span>
                                    <div>{news.imageUrl ? (<span className="db-badge green"><Image size={11} /> Ada</span>) : (<span className="db-hint">—</span>)}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Dibuat · Diubah</span>
                                    <div className="db-hint">{news.createdAt ?? '-'} · {news.updatedAt ?? '-'}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus berita?"
                    message={`"${news.title}" dipindah ke sampah. Gambar sampul ikut terhapus.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={() => {
                        setProcessing(true);
                        router.delete(urls.destroy, { onFinish: () => { setProcessing(false); setConfirmDelete(false); } });
                    }}
                    onCancel={() => setConfirmDelete(false)}
                />
            </div>
        </AdminShell>
    );
}
