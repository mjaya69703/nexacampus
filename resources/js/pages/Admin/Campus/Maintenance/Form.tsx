// Form tambah/ubah laporan kerusakan.
import { Head, useForm } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type TicketPayload = {
    id?: number;
    ticket_number?: string;
    title: string;
    description: string;
    room_id: number | null;
    room_label: string | null;
    campus_asset_id: number | null;
    asset_label: string | null;
    reported_by_user_id: number;
    reporter_label: string;
    assigned_to_user_id: number | null;
    assignee_label: string | null;
    priority: string;
    status?: string;
    resolution_notes?: string;
    repair_cost?: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    ticket: TicketPayload;
    priorities: Record<string, string>;
    statuses?: Record<string, string>;
    urls: { index: string; submit: string; searchUsers: string; searchRooms: string; searchAssets: string };
};

export default function MaintenanceForm({ shell, mode, ticket, priorities, statuses, urls }: Props) {
    const isCreate = mode === 'create';
    const [roomOption, setRoomOption] = useState<AsyncOption | null>(
        ticket.room_id && ticket.room_label ? { id: ticket.room_id, label: ticket.room_label } : null,
    );
    const [assetOption, setAssetOption] = useState<AsyncOption | null>(
        ticket.campus_asset_id && ticket.asset_label ? { id: ticket.campus_asset_id, label: ticket.asset_label } : null,
    );
    const [reporterOption, setReporterOption] = useState<AsyncOption | null>(
        ticket.reported_by_user_id ? { id: ticket.reported_by_user_id, label: ticket.reporter_label } : null,
    );
    const [assigneeOption, setAssigneeOption] = useState<AsyncOption | null>(
        ticket.assigned_to_user_id && ticket.assignee_label
            ? { id: ticket.assigned_to_user_id, label: ticket.assignee_label }
            : null,
    );

    const form = useForm({
        title: ticket.title,
        description: ticket.description,
        room_id: ticket.room_id as number | null,
        campus_asset_id: ticket.campus_asset_id as number | null,
        reported_by_user_id: ticket.reported_by_user_id,
        assigned_to_user_id: ticket.assigned_to_user_id as number | null,
        priority: ticket.priority,
        status: ticket.status ?? 'open',
        resolution_notes: ticket.resolution_notes ?? '',
        repair_cost: ticket.repair_cost ?? '',
    });

    const onAssetChange = (option: AsyncOption | null) => {
        setAssetOption(option);
        form.setData('campus_asset_id', option ? Number(option.id) : null);
        // Ikutkan ruangan aset bila ruangan masih kosong.
        const assetRoomId = option?.meta?.room_id;
        const assetRoomLabel = option?.meta?.room_label;
        if (option && form.data.room_id === null && typeof assetRoomId === 'number') {
            setRoomOption({ id: assetRoomId, label: typeof assetRoomLabel === 'string' ? assetRoomLabel : `Ruangan #${assetRoomId}` });
            form.setData('room_id', assetRoomId);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Buat' : 'Edit'} Laporan Kerusakan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Wrench}
                        eyebrow="Kampus"
                        title={isCreate ? 'Buat Laporan' : `Edit ${ticket.ticket_number ?? ''}`}
                        description={isCreate
                            ? 'Laporan baru selalu berstatus terbuka dan menunggu penugasan teknisi.'
                            : 'Keluar dari status selesai mengosongkan waktu penyelesaian otomatis.'}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Wrench size={16} /> Form Laporan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Judul kerusakan"
                                            required
                                            placeholder="Contoh: Proyektor tidak menyala"
                                            value={form.data.title}
                                            onChange={(e) => form.setData('title', e.target.value)}
                                            error={form.errors.title}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Rincian kerusakan"
                                            required
                                            rows={4}
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                    <AsyncSelect
                                        label="Ruangan"
                                        fetchUrl={urls.searchRooms}
                                        value={roomOption}
                                        onChange={(option) => {
                                            setRoomOption(option);
                                            form.setData('room_id', option ? Number(option.id) : null);
                                        }}
                                        error={form.errors.room_id}
                                        hint="Ketik untuk mencari (hanya ruangan aktif)."
                                    />
                                    <AsyncSelect
                                        label="Aset terkait"
                                        fetchUrl={urls.searchAssets}
                                        value={assetOption}
                                        onChange={onAssetChange}
                                        error={form.errors.campus_asset_id}
                                        hint="Ketik nama/kode. Memilih aset mengisi ruangan otomatis bila kosong."
                                    />
                                    <AsyncSelect
                                        label="Pelapor"
                                        required
                                        fetchUrl={urls.searchUsers}
                                        value={reporterOption}
                                        onChange={(option) => {
                                            setReporterOption(option);
                                            form.setData('reported_by_user_id', option ? Number(option.id) : 0);
                                        }}
                                        error={form.errors.reported_by_user_id}
                                    />
                                    <AsyncSelect
                                        label="Teknisi penanggungjawab"
                                        fetchUrl={urls.searchUsers}
                                        value={assigneeOption}
                                        onChange={(option) => {
                                            setAssigneeOption(option);
                                            form.setData('assigned_to_user_id', option ? Number(option.id) : null);
                                        }}
                                        error={form.errors.assigned_to_user_id}
                                        hint="Kosongkan bila belum ditugaskan."
                                    />
                                    <SelectField
                                        label="Prioritas"
                                        required
                                        value={form.data.priority}
                                        onChange={(e) => form.setData('priority', e.target.value)}
                                        error={form.errors.priority}
                                    >
                                        {Object.entries(priorities).map(([id, name]) => (
                                            <option key={id} value={id}>{name}</option>
                                        ))}
                                    </SelectField>
                                    {!isCreate && statuses && (
                                        <SelectField
                                            label="Status"
                                            required
                                            value={form.data.status}
                                            onChange={(e) => form.setData('status', e.target.value)}
                                            error={form.errors.status}
                                        >
                                            {Object.entries(statuses).map(([id, name]) => (
                                                <option key={id} value={id}>{name}</option>
                                            ))}
                                        </SelectField>
                                    )}
                                    {!isCreate && (
                                        <>
                                            <div style={{ gridColumn: '1 / -1' }}>
                                                <TextareaField
                                                    label="Catatan penyelesaian"
                                                    rows={3}
                                                    value={form.data.resolution_notes}
                                                    onChange={(e) => form.setData('resolution_notes', e.target.value)}
                                                    error={form.errors.resolution_notes}
                                                    hint="Wajib bila menyelesaikan lewat tombol di detail; opsional di sini."
                                                />
                                            </div>
                                            <TextField
                                                label="Biaya perbaikan (Rp)"
                                                type="number"
                                                min={0}
                                                value={form.data.repair_cost}
                                                onChange={(e) => form.setData('repair_cost', e.target.value)}
                                                error={form.errors.repair_cost}
                                            />
                                        </>
                                    )}
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Buat Laporan' : 'Simpan Perubahan'}
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
