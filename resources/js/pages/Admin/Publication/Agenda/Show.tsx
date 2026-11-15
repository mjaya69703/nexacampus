// Pratinjau agenda (read-only) — pola Show academic.
import { Head, router } from '@inertiajs/react';
import { CalendarDays, MapPin, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Agenda = {
    id: number; title: string; slug: string; description: string | null;
    category: string | null; location: string | null;
    eventDate: string | null; eventTime: string | null; eventEndDate: string | null;
    isUpcoming: boolean; isPublished: boolean; publishedAt: string | null;
    creatorName: string; createdAt: string | null; updatedAt: string | null;
};

type Props = {
    shell: ShellProps;
    agenda: Agenda;
    can: { update: boolean; delete: boolean };
    urls: { index: string; edit: string; destroy: string };
};

export default function AgendaShow({ shell, agenda, can, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <AdminShell shell={shell}>
            <Head title={`${agenda.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Publikasi · Pratinjau"
                        title={agenda.title}
                        description={`${agenda.eventDate ?? '-'}${agenda.eventTime ? ` · ${agenda.eventTime}` : ''}${agenda.location ? ` · ${agenda.location}` : ''}`}
                        badges={[
                            agenda.category ?? 'Tanpa kategori',
                            agenda.isUpcoming ? 'Akan datang' : 'Terlewat',
                            agenda.isPublished ? 'Published' : 'Draft',
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
                            <h2 className="db-card-title">Detail Agenda</h2>
                            {can.delete && (
                                <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 size={14} /> Hapus
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            {agenda.description ? (
                                <p style={{ whiteSpace: 'pre-wrap' }}>{agenda.description}</p>
                            ) : (
                                <p className="db-hint">Tanpa deskripsi.</p>
                            )}

                            <div className="crud-grid" style={{ marginTop: 18 }}>
                                <div>
                                    <span className="crud-label">Tanggal acara</span>
                                    <div>{agenda.eventDate ?? '-'}{agenda.eventEndDate ? ` — ${agenda.eventEndDate}` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Jam</span>
                                    <div>{agenda.eventTime ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Lokasi</span>
                                    <div>{agenda.location ? (<span><MapPin size={12} style={{ display: 'inline' }} /> {agenda.location}</span>) : '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Kategori</span>
                                    <div>{agenda.category ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Dipublikasi</span>
                                    <div>{agenda.publishedAt ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Dibuat · Diubah</span>
                                    <div className="db-hint">{agenda.createdAt ?? '-'} · {agenda.updatedAt ?? '-'}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus agenda?"
                    message={`"${agenda.title}" dipindah ke sampah dan bisa dipulihkan.`}
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
