// Detail blokir — ringkasan tunggakan + lepas / dispensasi (paritas Blade).
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Clock, Lock } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Hold = {
    id: number; student: string; nim: string | null; program: string | null;
    holdType: string; holdText: string; invoice: string | null; invoiceType: string | null;
    invoiceUrl: string | null; outstanding: string | null; reason: string | null;
    status: string; statusTone: string; statusText: string;
    blockedAt: string | null; waivedUntil: string | null;
    releasedAt: string | null; releasedBy: string | null; releaseNotes: string | null;
    policyMode: string | null; isHandleable: boolean;
};

type Props = {
    shell: ShellProps;
    hold: Hold;
    can: { handle: boolean };
    urls: { index: string; release: string; waive: string };
};

export default function FinancialHoldShow({ shell, hold, can, urls }: Props) {
    const [confirmRelease, setConfirmRelease] = useState(false);
    const [processing, setProcessing] = useState(false);
    const form = useForm({ release_notes: '', waived_until: hold.waivedUntil ?? '' });

    const submitRelease = () => {
        setProcessing(true);
        form.post(urls.release, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirmRelease(false);
            },
        });
    };

    const submitWaive = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(urls.waive, { preserveScroll: true });
    };

    const errors = form.errors as Record<string, string>;

    const summary: [string, React.ReactNode][] = [
        ['Mahasiswa', <b key="s">{hold.student} {hold.nim ? `(${hold.nim})` : ''}</b>],
        ['Prodi', hold.program ?? '-'],
        ['Target Blokir', <b key="t">{hold.holdText}</b>],
        ['Tagihan', hold.invoiceUrl ? <a key="i" href={hold.invoiceUrl}>{hold.invoice}</a> : (hold.invoice ?? '-')],
        ['Tunggakan', <b key="o">{hold.outstanding ?? '-'}</b>],
        ['Alasan', hold.reason ?? '-'],
        ['Status', <span key="st" className={`db-badge ${hold.statusTone}`}>{hold.statusText}</span>],
        ['Diblokir', hold.blockedAt ?? '-'],
        ['Mode Kebijakan', hold.policyMode ?? '-'],
        ['Dilepas', hold.releasedAt ? `${hold.releasedAt}${hold.releasedBy ? ` oleh ${hold.releasedBy}` : ''}` : '-'],
        ['Catatan Rilis', hold.releaseNotes ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Blokir ${hold.holdText} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Lock}
                        eyebrow="Keuangan"
                        title={`Blokir · ${hold.holdText}`}
                        description={`${hold.student} — ${hold.statusText}`}
                        actions={
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {can.handle && hold.isHandleable && (
                                    <button type="button" className="db-btn light" disabled={processing} onClick={() => setConfirmRelease(true)}>
                                        <CheckCircle2 size={15} /> Lepas Blokir
                                    </button>
                                )}
                            </>
                        }
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Ringkasan</h2>
                        </div>
                        <div className="db-card-body tight">
                            {summary.map(([label, value]) => (
                                <div className="db-summary" key={label}>
                                    <span className="db-hint">{label}</span>
                                    <span>{value}</span>
                                </div>
                            ))}
                        </div>
                    </section>

                    {can.handle && hold.isHandleable && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Clock size={16} /> Dispensasi Sementara</h2>
                            </div>
                            <div className="db-card-body">
                                <form onSubmit={submitWaive}>
                                    <div className="crud-grid">
                                        <TextField
                                            label="Berlaku Sampai"
                                            required
                                            type="date"
                                            value={form.data.waived_until}
                                            onChange={(e) => form.setData('waived_until', e.target.value)}
                                            error={errors.waived_until}
                                        />
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.release_notes}
                                            onChange={(e) => form.setData('release_notes', e.target.value)}
                                            error={errors.release_notes}
                                        />
                                    </div>
                                    <div style={{ marginTop: 12 }}>
                                        <button type="submit" className="db-btn primary sm" disabled={form.processing}>
                                            Beri Dispensasi
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    )}
                </div>

                <ConfirmModal
                    open={confirmRelease}
                    title="Lepas blokir?"
                    message="Mahasiswa kembali bisa mengakses layanan yang diblokir."
                    confirmLabel="Ya, lepas"
                    danger={false}
                    processing={processing}
                    onConfirm={submitRelease}
                    onCancel={() => setConfirmRelease(false)}
                />
            </div>
        </AdminShell>
    );
}
