// Detail registrasi mahasiswa — read-only + approval.
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCheck, ClipboardCheck, Pencil, XCircle } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    registration: {
        id: number; student: string; nim: string | null; program: string | null;
        year: string | null; semester: number | null;
        registrationStatus: string; registrationTone: string;
        academicStatus: string | null; isActive: boolean;
        notes: string | null; submittedAt: string | null;
        approvedAt: string | null; approvedBy: string | null;
        createdAt: string | null; canApprove: boolean;
    };
    canUpdate: boolean;
    urls: { index: string; edit: string; approve: string };
};

export default function StudentRegistrationShow({ shell, registration, canUpdate, urls }: Props) {
    const [approving, setApproving] = useState<'Approved' | 'Rejected' | null>(null);
    const approveForm = useForm({ status: 'Approved', notes: '' });

    const openApprove = (status: 'Approved' | 'Rejected') => {
        approveForm.setData({ status, notes: '' });
        approveForm.clearErrors();
        setApproving(status);
    };

    const confirmApprove = () => {
        approveForm.post(urls.approve, {
            preserveScroll: true,
            onSuccess: () => setApproving(null),
        });
    };

    const rows: [string, string][] = [
        ['Mahasiswa', `${registration.student}${registration.nim ? ` · ${registration.nim}` : ''}`],
        ['Program studi', registration.program ?? '-'],
        ['Tahun akademik', `${registration.year ?? '-'}${registration.semester ? ` · Smt ${registration.semester}` : ''}`],
        ['Status akademik', registration.academicStatus ?? '-'],
        ['Aktif', registration.isActive ? 'Ya' : 'Tidak'],
        ['Diajukan', registration.submittedAt ?? '-'],
        ['Disetujui', registration.approvedAt ?? '-'],
        ['Penyetuju', registration.approvedBy ?? '-'],
        ['Dibuat', registration.createdAt ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Registrasi ${registration.student} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardCheck}
                        eyebrow="Akademik"
                        title={registration.student}
                        description={`${registration.nim ?? ''} · ${registration.year ?? ''}`}
                        badges={[registration.registrationStatus, registration.academicStatus ?? '-']}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {canUpdate && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>}
                                {canUpdate && registration.canApprove && (
                                    <>
                                        <button className="db-btn light" type="button" onClick={() => openApprove('Approved')}><CheckCheck size={15} /> Setujui</button>
                                        <button className="db-btn light" type="button" onClick={() => openApprove('Rejected')}><XCircle size={15} /> Tolak</button>
                                    </>
                                )}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Ringkasan registrasi</h2>
                            <span className={`db-badge ${registration.registrationTone}`}>{registration.registrationStatus}</span>
                        </div>
                        <div className="db-card-body tight">
                            {rows.map(([label, value]) => (
                                <div className="db-summary" key={label}>
                                    <div>
                                        <b>{label}</b>
                                        <small>{value}</small>
                                    </div>
                                </div>
                            ))}
                            {registration.notes && (
                                <div className="db-summary">
                                    <div>
                                        <b>Catatan</b>
                                        <small>{registration.notes}</small>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                {approving && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setApproving(null)} role="dialog" aria-modal="true" aria-label="Approval registrasi">
                            <div className="db-modal" style={{ width: 'min(28rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>{approving === 'Approved' ? 'Setujui' : 'Tolak'} registrasi?</h3>
                                <p>Menyetujui mendorong semester ke profil mahasiswa.</p>
                                <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                                    <TextareaField
                                        label="Catatan (opsional)"
                                        value={approveForm.data.notes}
                                        onChange={(e) => approveForm.setData('notes', e.target.value)}
                                        error={(approveForm.errors as Record<string, string>).notes}
                                    />
                                </div>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setApproving(null)}>Batal</button>
                                    <button
                                        className={`db-btn ${approving === 'Approved' ? 'primary' : 'danger'}`}
                                        type="button"
                                        onClick={confirmApprove}
                                        disabled={approveForm.processing}
                                    >
                                        {approveForm.processing ? 'Memproses…' : approving === 'Approved' ? 'Setujui' : 'Tolak'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminShell>
    );
}
