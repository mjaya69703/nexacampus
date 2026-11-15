// Form input absensi pegawai manual (admin kepegawaian).
import { Head, useForm } from '@inertiajs/react';
import { Fingerprint, Info, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type Props = {
    shell: ShellProps;
    defaults: {
        attendance_date: string;
        status: string;
        check_in_at: string;
        check_out_at: string;
    };
    options: {
        statuses: Option[];
        workUnits: Option[];
    };
    urls: { index: string; submit: string; searchEmployees: string };
};

export default function EmployeeAttendanceRecordForm({ shell, defaults, options, urls }: Props) {
    const form = useForm({
        employee_profile_id: null as number | null,
        attendance_date: defaults.attendance_date,
        status: defaults.status,
        check_in_at: defaults.check_in_at,
        check_out_at: defaults.check_out_at,
        work_unit_id: '',
        notes: '',
    });

    const selectedEmployee: AsyncOption | null = form.data.employee_profile_id
        ? { id: form.data.employee_profile_id, label: 'Pegawai terpilih' }
        : null;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Input Absensi Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Fingerprint}
                        eyebrow="Kepegawaian"
                        title="Input Absensi Pegawai"
                        description="Catat absensi pegawai secara manual untuk koreksi, dinas luar, atau pencatatan susulan. Data akan ditandai sebagai sumber Manual Admin."
                        badges={['Input manual admin']}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><UserRound size={16} /> Pegawai & Tanggal</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <AsyncSelect
                                                label="Pegawai"
                                                required
                                                fetchUrl={urls.searchEmployees}
                                                value={selectedEmployee}
                                                onChange={(option) => form.setData('employee_profile_id', option ? Number(option.id) : null)}
                                                error={form.errors.employee_profile_id}
                                                hint="Hanya pegawai aktif yang dapat dicatat absensinya."
                                                placeholder="Cari nama, email, atau nomor pegawai…"
                                            />
                                        </div>
                                        <TextField
                                            label="Tanggal absensi"
                                            type="date"
                                            required
                                            value={form.data.attendance_date}
                                            onChange={(event) => form.setData('attendance_date', event.target.value)}
                                            error={form.errors.attendance_date}
                                        />
                                        <SelectField
                                            label="Status kehadiran"
                                            required
                                            value={form.data.status}
                                            onChange={(event) => form.setData('status', event.target.value)}
                                            error={form.errors.status}
                                        >
                                            {options.statuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </SelectField>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Fingerprint size={16} /> Waktu & Unit Kerja</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <TextField
                                            label="Jam check-in"
                                            type="datetime-local"
                                            value={form.data.check_in_at}
                                            onChange={(event) => form.setData('check_in_at', event.target.value)}
                                            error={form.errors.check_in_at}
                                            hint="Kosongkan bila pegawai tidak melakukan check-in."
                                        />
                                        <TextField
                                            label="Jam check-out"
                                            type="datetime-local"
                                            value={form.data.check_out_at}
                                            onChange={(event) => form.setData('check_out_at', event.target.value)}
                                            error={form.errors.check_out_at}
                                            hint="Harus sama atau setelah waktu check-in."
                                        />
                                        <SelectField
                                            label="Unit kerja"
                                            value={form.data.work_unit_id}
                                            onChange={(event) => form.setData('work_unit_id', event.target.value)}
                                            error={form.errors.work_unit_id}
                                            hint="Kosongkan untuk memakai unit kerja utama pegawai."
                                        >
                                            <option value="">Ikuti unit kerja utama pegawai</option>
                                            {options.workUnits.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </SelectField>
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan absensi"
                                                rows={3}
                                                placeholder="Alasan koreksi, keterangan dinas luar, atau informasi pendukung lainnya…"
                                                value={form.data.notes}
                                                onChange={(event) => form.setData('notes', event.target.value)}
                                                error={form.errors.notes}
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
                                            Durasi kerja dihitung otomatis dari selisih check-in dan check-out. Absensi manual tidak memiliki data geofence maupun bukti foto.
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel="Simpan Absensi"
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
