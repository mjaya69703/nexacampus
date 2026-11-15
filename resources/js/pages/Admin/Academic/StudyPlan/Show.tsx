// Detail KRS — read-only + total SKS.
import { Head } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Pencil } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    plan: {
        id: number; student: string; nim: string | null; program: string | null;
        year: string | null; registration: string; semester: number | null;
        status: string; statusTone: string; notes: string | null;
        submittedAt: string | null; approvedAt: string | null; approvedBy: string | null;
        createdAt: string | null;
    };
    details: {
        id: number; code: string | null; name: string | null; offeringLabel: string | null;
        credits: number | null; isRepeat: boolean; status: string; notes: string | null;
    }[];
    canUpdate: boolean;
    urls: { index: string; edit: string };
};

export default function StudyPlanShow({ shell, plan, details, canUpdate, urls }: Props) {
    const totalSks = details.reduce((sum, row) => sum + (row.credits ?? 0), 0);

    const rows: [string, string][] = [
        ['Mahasiswa', `${plan.student}${plan.nim ? ` · ${plan.nim}` : ''}`],
        ['Program studi', plan.program ?? '-'],
        ['Tahun akademik', `${plan.year ?? '-'}${plan.semester ? ` · Smt ${plan.semester}` : ''}`],
        ['Registrasi terkait', plan.registration],
        ['Diajukan', plan.submittedAt ?? '-'],
        ['Disetujui', plan.approvedAt ?? '-'],
        ['Penyetuju', plan.approvedBy ?? '-'],
        ['Dibuat', plan.createdAt ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`KRS ${plan.student} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik"
                        title={`KRS ${plan.student}`}
                        description={`${plan.nim ?? ''} · ${plan.year ?? ''}`}
                        badges={[plan.status, `${details.length} MK`, `${totalSks} SKS`]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {canUpdate && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Kelola</a>}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Ringkasan</h2>
                            <span className={`db-badge ${plan.statusTone}`}>{plan.status}</span>
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
                            {plan.notes && (
                                <div className="db-summary">
                                    <div>
                                        <b>Catatan</b>
                                        <small>{plan.notes}</small>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Mata Kuliah ({details.length} MK · {totalSks} SKS)</h2>
                        </div>
                        <div className="db-card-body tight">
                            {details.length === 0 && (
                                <p className="db-hint" style={{ margin: 0 }}>Belum ada MK dalam KRS ini.</p>
                            )}
                            {details.map((row) => (
                                <div className="db-summary" key={row.id}>
                                    <div>
                                        <b><span className="crud-name">{row.code ?? '-'}</span> · {row.name ?? '-'}</b>
                                        <small>{row.offeringLabel ?? ''}{row.notes ? ` · ${row.notes}` : ''}</small>
                                    </div>
                                    <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                        {row.isRepeat && <span className="db-badge amber">Mengulang</span>}
                                        <span className={`db-badge ${row.status === 'Taken' ? 'green' : row.status === 'Dropped' ? 'red' : 'gray'}`}>{row.status}</span>
                                        <span className="db-badge green">{row.credits ?? '-'} SKS</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
