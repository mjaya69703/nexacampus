// Form pengajuan cuti pegawai (admin kepegawaian).
import { Head, useForm } from '@inertiajs/react';
import { CalendarPlus, Info, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type LeaveTypeOption = { id: string; label: string; defaultDays: number };

type Props = {
    shell: ShellProps;
    leaveTypes: LeaveTypeOption[];
    defaults: { starts_at: string; ends_at: string };
    urls: { index: string; submit: string; searchEmployees: string };
};

function countDays(start: string, end: string): number {
    if (!start || !end) return 0;

    const from = new Date(`${start}T00:00:00`);
    const to = new Date(`${end}T00:00:00`);

    if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime()) || to < from) return 0;

    return Math.round((to.getTime() - from.getTime()) / 86400000) + 1;
}

export default function EmployeeLeaveRequestForm({ shell, leaveTypes, defaults, urls }: Props) {
    const form = useForm({
        employee_profile_id: null as number | null,
        employee_leave_type_id: '',
        starts_at: defaults.starts_at,
        ends_at: defaults.ends_at,
        reason: '',
        employee_notes: '',
    });

    const selectedEmployee: AsyncOption | null = form.data.employee_profile_id
        ? { id: form.data.employee_profile_id, label: 'Pegawai terpilih' }
        : null;

    const selectedType = leaveTypes.find((type) => type.id === form.data.employee_leave_type_id);
    const totalDays = countDays(form.data.starts_at, form.data.ends_at);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Cuti Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarPlus}
                        eyebrow="Kepegawaian"
                        title="Ajukan Cuti Pegawai"
                        description="Buat pengajuan cuti atas nama pegawai. Sistem menghitung durasi, memotong saldo pending, dan langsung mengirim pengajuan ke alur persetujuan kepegawaian."
                        badges={['Pengajuan atas nama pegawai']}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><UserRound size={16} /> Pegawai & Jenis Cuti</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <AsyncSelect
                                                label="Pegawai pengaju cuti"
                                                required
                                                fetchUrl={urls.searchEmployees}
                                                value={selectedEmployee}
                                                onChange={(option) => form.setData('employee_profile_id', option ? Number(option.id) : null)}
                                                error={form.errors.employee_profile_id}
                                                hint="Hanya pegawai aktif yang dapat mengajukan cuti."
                                                placeholder="Cari nama, email, atau nomor pegawai…"
                                            />
                                        </div>
                                        <SelectField
                                            label="Jenis cuti"
                                            required
                                            value={form.data.employee_leave_type_id}
                                            onChange={(event) => form.setData('employee_leave_type_id', event.target.value)}
                                            error={form.errors.employee_leave_type_id}
                                            hint={selectedType ? `Kuota default ${selectedType.defaultDays.toFixed(1)} hari per tahun.` : 'Pilih jenis cuti yang sesuai.'}
                                        >
                                            <option value="">-- Pilih jenis cuti --</option>
                                            {leaveTypes.map((type) => (
                                                <option key={type.id} value={type.id}>{type.label}</option>
                                            ))}
                                        </SelectField>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarPlus size={16} /> Periode & Alasan</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <TextField
                                            label="Tanggal mulai"
                                            type="date"
                                            required
                                            value={form.data.starts_at}
                                            onChange={(event) => form.setData('starts_at', event.target.value)}
                                            error={form.errors.starts_at}
                                        />
                                        <TextField
                                            label="Tanggal berakhir"
                                            type="date"
                                            required
                                            value={form.data.ends_at}
                                            onChange={(event) => form.setData('ends_at', event.target.value)}
                                            error={form.errors.ends_at}
                                            hint={totalDays > 0 ? `Durasi pengajuan: ${totalDays} hari kalender.` : 'Tanggal berakhir harus sama atau setelah tanggal mulai.'}
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Alasan pengajuan"
                                                required
                                                rows={4}
                                                placeholder="Tuliskan alasan cuti secara jelas, termasuk keperluan atau keterangan pendukung…"
                                                value={form.data.reason}
                                                onChange={(event) => form.setData('reason', event.target.value)}
                                                error={form.errors.reason}
                                            />
                                        </div>
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan pegawai (opsional)"
                                                rows={3}
                                                placeholder="Catatan tambahan dari pegawai, misalnya informasi kontak selama cuti…"
                                                value={form.data.employee_notes}
                                                onChange={(event) => form.setData('employee_notes', event.target.value)}
                                                error={form.errors.employee_notes}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-body">
                                    <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                                        <Info size={16} style={{ color: 'var(--db-brand)', marginTop: 2, flexShrink: 0 }} />
                                        <div className="db-hint" style={{ marginTop: 0 }}>
                                            Saldo cuti tahun berjalan otomatis dibuat bila belum tersedia. Pengajuan langsung berstatus diajukan dan menunggu keputusan approver pada alur persetujuan.
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel="Ajukan Cuti"
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
