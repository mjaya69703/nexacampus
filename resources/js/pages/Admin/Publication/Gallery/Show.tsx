// Pratinjau album galeri (read-only) — pola Show academic.
import { Head, router } from '@inertiajs/react';
import { Images, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type AlbumImage = { id: number; url: string; caption: string | null };

type Album = {
    id: number; title: string; slug: string; description: string | null;
    category: string | null; isPublished: boolean; coverUrl: string | null;
    creatorName: string; createdAt: string | null; updatedAt: string | null;
    images: AlbumImage[];
};

type Props = {
    shell: ShellProps;
    album: Album;
    can: { update: boolean; delete: boolean };
    urls: { index: string; edit: string; destroy: string };
};

export default function GalleryShow({ shell, album, can, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <AdminShell shell={shell}>
            <Head title={`${album.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Images}
                        eyebrow="Publikasi · Pratinjau"
                        title={album.title}
                        description={`${album.category ?? 'Tanpa kategori'} · ${album.images.length} foto · oleh ${album.creatorName}`}
                        badges={[album.isPublished ? 'Published' : 'Draft']}
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
                            <h2 className="db-card-title">Isi Album</h2>
                            {can.delete && (
                                <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 size={14} /> Hapus
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            {album.coverUrl && (
                                <img src={album.coverUrl} alt={album.title} style={{ maxWidth: '100%', borderRadius: 12, marginBottom: 16 }} />
                            )}
                            {album.description ? (
                                <p style={{ whiteSpace: 'pre-wrap' }}>{album.description}</p>
                            ) : (
                                <p className="db-hint">Tanpa deskripsi.</p>
                            )}

                            {album.images.length > 0 && (
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 12, marginTop: 16 }}>
                                    {album.images.map((image) => (
                                        <figure key={image.id} style={{ margin: 0 }}>
                                            <img src={image.url} alt={image.caption ?? ''} style={{ width: '100%', height: 130, objectFit: 'cover', borderRadius: 10, display: 'block' }} />
                                            {image.caption && <figcaption className="db-hint" style={{ marginTop: 4 }}>{image.caption}</figcaption>}
                                        </figure>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus album?"
                    message={`"${album.title}" beserta semua fotonya dipindah ke sampah.`}
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
