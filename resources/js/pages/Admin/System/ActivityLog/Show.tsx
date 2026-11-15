// Detail log aktivitas — properti JSON (Inertia React).
import { Head } from '@inertiajs/react';
import { ArrowLeft, History } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    activity: {
        id: number; log: string | null; description: string; event: string | null;
        subject: string; causer: string; createdAt: string | null;
        properties: Record<string, unknown>; prettyJson: string;
    };
    urls: { index: string };
};

export default function ActivityLogShow({ shell, activity, urls }: Props) {
    const rows: [string, string][] = [
        ['Log', activity.log ?? '-'],
        ['Aksi', activity.description],
        ['Event', activity.event ?? '-'],
        ['Subjek', activity.subject],
        ['Pelaku', activity.causer],
        ['Waktu', activity.createdAt ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Log #${activity.id} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={History}
                        eyebrow="Sistem · Log aktivitas"
                        title={`Log #${activity.id}`}
                        description={activity.description}
                        badges={[activity.log ?? '-', activity.causer]}
                        actions={(
                            <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali ke daftar</a>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Ringkasan</h2>
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
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Properti (JSON)</h2>
                        </div>
                        <div className="db-card-body">
                            <pre
                                style={{
                                    margin: 0, padding: 16, borderRadius: 12, overflowX: 'auto',
                                    background: 'var(--db-soft)', color: 'var(--db-ink)',
                                    fontFamily: "ui-monospace, 'Cascadia Code', Consolas, monospace",
                                    fontSize: 12, lineHeight: 1.7,
                                }}
                            >
                                {activity.prettyJson}
                            </pre>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
