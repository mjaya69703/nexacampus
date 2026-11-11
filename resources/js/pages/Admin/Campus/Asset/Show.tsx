// Pratinjau aset kampus (read-only) — pola Show academic.
import { Head, router } from '@inertiajs/react';
import { Boxes, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Ticket = { ticketNumber: string; title: string; status: string; priority: string };

type Asset = {
    id: number; assetCode: string; name: string; category: string;
    room: string | null; building: string | null; brand: string | null;
    modelNumber: string | null; serialNumber: string | null;
    condition: string; conditionLabel: string; status: string; statusLabel: string;
    purchaseDate: string | null; purchaseCost: string | null; isBorrowable: boolean;
    funding: string | null;
    notes: string | null; createdAt: string | null; updatedAt: string | null;
    tickets: Ticket[];
};

type Props = {
    shell: ShellProps;
    asset: Asset;
    can: { update: boolean; delete: boolean };
    urls: { index: string; edit: string; destroy: string };
};

export default function AssetShow({ shell, asset, can, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <AdminShell shell={shell}>
            <Head title={`${asset.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Boxes}
                        eyebrow="Kampus · Pratinjau"
                        title={asset.name}
                        description={`${asset.assetCode}${asset.room ? ` · ${asset.room}` : ' · Gudang'}`}
                        badges={[asset.conditionLabel, asset.statusLabel]}
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
                            <h2 className="db-card-title">Detail Aset</h2>
                            {can.delete && (
                                <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 size={14} /> Hapus
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div>
                                    <span className="crud-label">Kategori</span>
                                    <div>{asset.category}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Penempatan</span>
                                    <div>{asset.room ?? 'Gudang / belum ditempatkan'}{asset.building ? ` (${asset.building})` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Merk</span>
                                    <div>{asset.brand ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Model / Serial</span>
                                    <div>{asset.modelNumber ?? '-'}{asset.serialNumber ? ` · S/N ${asset.serialNumber}` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Pengadaan</span>
                                    <div>{asset.purchaseDate ?? '-'}{asset.purchaseCost ? ` · ${asset.purchaseCost}` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Sumber dana</span>
                                    <div>{asset.funding ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Dipinjamkan</span>
                                    <div>{asset.isBorrowable ? 'Ya' : 'Tidak'}</div>
                                </div>
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <span className="crud-label">Catatan</span>
                                    <div>{asset.notes ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Dibuat · Diubah</span>
                                    <div className="db-hint">{asset.createdAt ?? '-'} · {asset.updatedAt ?? '-'}</div>
                                </div>
                            </div>

                            {asset.tickets.length > 0 && (
                                <div style={{ marginTop: 18 }}>
                                    <span className="crud-label">Tiket maintenance terakhir ({asset.tickets.length})</span>
                                    <div style={{ display: 'grid', gap: 8, marginTop: 8 }}>
                                        {asset.tickets.map((ticket) => (
                                            <div key={ticket.ticketNumber} className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                <b style={{ flex: 1 }}>{ticket.title}</b>
                                                <span className="db-badge gray">{ticket.ticketNumber}</span>
                                                <span className="db-badge gray">{ticket.status}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus aset?"
                    message={`"${asset.name}" dipindah ke sampah dan bisa dipulihkan.`}
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
