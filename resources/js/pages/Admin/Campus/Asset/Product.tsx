// Detail produk aset: ringkasan + tabel unit per nomor inventaris.
import { Head, router, useForm } from '@inertiajs/react';
import { Boxes, Eye, Pencil, Trash2, Wrench } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Unit = {
    id: number; assetCode: string; serialNumber: string | null;
    room: string | null; building: string | null;
    condition: string; conditionLabel: string; status: string; statusLabel: string;
    funding: string | null; fundingDetail: string | null; purchaseDate: string | null;
    editUrl: string; showUrl: string; deleteUrl: string; reportUrl: string;
};

type Product = {
    name: string | null; brand: string | null; model: string | null;
    category: string | null; categoryLabel: string | null;
    qty: number; good: number; damaged: number; totalValue: number;
    rooms: string[]; units: Unit[];
};

type Props = {
    shell: ShellProps;
    product: Product;
    can: { update: boolean; delete: boolean; report: boolean };
    urls: { index: string };
};

export default function AssetProduct({ shell, product, can, urls }: Props) {
    const [pendingDelete, setPendingDelete] = useState<Unit | null>(null);
    const [pendingReport, setPendingReport] = useState<Unit | null>(null);
    const [processing, setProcessing] = useState(false);
    const reportForm = useForm({ condition: 'major_damage', priority: 'high', notes: '' });

    const title = product.name ?? '(Tanpa nama)';

    return (
        <AdminShell shell={shell}>
            <Head title={`${title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Boxes}
                        eyebrow="Kampus · Detail Produk"
                        title={title}
                        description={[product.brand, product.model, product.categoryLabel].filter(Boolean).join(' · ') || 'Tanpa identitas tambahan'}
                        badges={[`${product.qty} unit`, `${product.good} baik`, `${product.damaged} rusak`]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar Unit ({product.units.length})</h2>
                            {product.rooms.length > 0 && (
                                <small className="db-hint">Tersebar di: {product.rooms.join(', ')}</small>
                            )}
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>No. Inventaris</th>
                                            <th>Serial</th>
                                            <th>Ruangan</th>
                                            <th>Kondisi</th>
                                            <th>Status</th>
                                            <th>Sumber Dana</th>
                                            <th style={{ textAlign: 'right' }}>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {product.units.length === 0 && (
                                            <tr><td colSpan={7} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">Belum ada unit.</span></td></tr>
                                        )}
                                        {product.units.map((unit) => (
                                            <tr key={unit.id}>
                                                <td><b style={{ color: 'var(--db-heading)' }}>{unit.assetCode}</b></td>
                                                <td>{unit.serialNumber ?? <span className="db-hint">—</span>}</td>
                                                <td>{unit.room ?? <span className="db-hint">Gudang</span>}</td>
                                                <td><span className="db-badge gray">{unit.conditionLabel}</span></td>
                                                <td><span className={`db-badge ${unit.status === 'active' ? 'green' : 'gray'}`}>{unit.statusLabel}</span></td>
                                                <td>{unit.funding ?? <span className="db-hint">—</span>}</td>
                                                <td>
                                                    <div className="crud-row-actions">
                                                        <a className="db-btn ghost sm" href={unit.showUrl} title="Detail unit">
                                                            <Eye size={13} />
                                                        </a>
                                                        {can.update && (
                                                            <a className="db-btn ghost sm" href={unit.editUrl} title="Ubah unit">
                                                                <Pencil size={13} />
                                                            </a>
                                                        )}
                                                        {can.report && (
                                                            <button className="db-btn ghost sm" type="button" title="Laporkan rusak" onClick={() => setPendingReport(unit)}>
                                                                <Wrench size={13} />
                                                            </button>
                                                        )}
                                                        {can.delete && (
                                                            <button className="db-btn danger sm" type="button" title="Hapus unit" onClick={() => setPendingDelete(unit)}>
                                                                <Trash2 size={13} />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pendingDelete !== null}
                    title="Hapus unit?"
                    message={pendingDelete ? `"${pendingDelete.assetCode}" dipindah ke sampah dan bisa dipulihkan.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={() => {
                        if (!pendingDelete) return;
                        setProcessing(true);
                        router.delete(pendingDelete.deleteUrl, { preserveScroll: true, onFinish: () => { setProcessing(false); setPendingDelete(null); } });
                    }}
                    onCancel={() => setPendingDelete(null)}
                />

                <ConfirmModal
                    open={pendingReport !== null}
                    title="Laporkan rusak?"
                    message={pendingReport ? `Unit ${pendingReport.assetCode} masuk maintenance + tiket dibuat. Kondisi awal unit:` : ''}
                    confirmLabel="Ya, laporkan"
                    danger
                    processing={processing || reportForm.processing}
                    onConfirm={() => {
                        if (!pendingReport) return;
                        setProcessing(true);
                        reportForm.post(pendingReport.reportUrl, {
                            preserveScroll: true,
                            onFinish: () => {
                                setProcessing(false);
                                setPendingReport(null);
                                reportForm.reset('notes');
                            },
                        });
                    }}
                    onCancel={() => setPendingReport(null)}
                >
                    <div style={{ marginTop: 12, textAlign: 'left', display: 'grid', gap: 12 }}>
                        <SelectField
                            label="Kondisi ditemukan"
                            required
                            value={reportForm.data.condition}
                            onChange={(e) => reportForm.setData('condition', e.target.value)}
                            error={reportForm.errors.condition}
                        >
                            <option value="minor_damage">Rusak Ringan</option>
                            <option value="major_damage">Rusak Berat</option>
                        </SelectField>
                        <SelectField
                            label="Prioritas tiket"
                            required
                            value={reportForm.data.priority}
                            onChange={(e) => reportForm.setData('priority', e.target.value)}
                            error={reportForm.errors.priority}
                        >
                            <option value="low">Rendah</option>
                            <option value="medium">Sedang</option>
                            <option value="high">Tinggi</option>
                            <option value="urgent">Sangat Mendesak</option>
                        </SelectField>
                        <TextareaField
                            label="Catatan kerusakan"
                            rows={3}
                            value={reportForm.data.notes}
                            onChange={(e) => reportForm.setData('notes', e.target.value)}
                            error={reportForm.errors.notes}
                        />
                    </div>
                </ConfirmModal>
            </div>
        </AdminShell>
    );
}
