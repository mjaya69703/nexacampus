// Detail organisasi mahasiswa: profil, keanggotaan, dan prestasi.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, AtSign, CalendarDays, CheckCircle2, ExternalLink, Eye,
    Mail, Pencil, Phone, Trash2, Trophy, UserCheck, UserPlus, Users,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import { initialsFor, StudentAffairsModal, StudentAffairsStats, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Member = {
    id: number;
    userId: number;
    name: string;
    nim: string | null;
    program: string | null;
    position: string;
    positionLabel: string;
    positionTone: string;
    period: string;
    joinedAt: string | null;
    leftAt: string | null;
    joinedAtIso: string | null;
    leftAtIso: string | null;
    isActive: boolean;
    notes: string | null;
    updateUrl: string;
    deleteUrl: string;
};

type Achievement = {
    id: number;
    title: string;
    student: string;
    categoryLabel: string;
    levelLabel: string;
    rankLabel: string;
    achievedAt: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; manageMembers: boolean; createAchievement: boolean };
    organization: {
        id: number;
        name: string;
        type: string;
        typeLabel: string;
        typeShort: string;
        typeTone: string;
        faculty: string | null;
        studyProgram: string | null;
        advisor: string | null;
        description: string | null;
        foundedAt: string | null;
        contactEmail: string | null;
        contactPhone: string | null;
        instagramUrl: string | null;
        isActive: boolean;
        logoUrl: string | null;
    };
    stats: { members: number; activeMembers: number; achievements: number };
    members: Member[];
    achievements: Achievement[];
    options: { positions: Record<string, string> };
    urls: {
        index: string;
        edit: string;
        delete: string;
        memberStore: string;
        searchStudents: string;
        achievementCreate: string;
    };
};

function defaultPeriod(): string {
    const year = new Date().getFullYear();

    return `${year}/${year + 1}`;
}

function MemberModal({ member, positions, urls, onClose }: {
    member: Member | null;
    positions: Record<string, string>;
    urls: Props['urls'];
    onClose: () => void;
}) {
    const form = useForm({
        user_id: member ? String(member.userId) : '',
        position: member?.position ?? 'member',
        period: member?.period ?? defaultPeriod(),
        joined_at: member?.joinedAtIso ?? '',
        left_at: member?.leftAtIso ?? '',
        is_active: member?.isActive ?? true,
        notes: member?.notes ?? '',
    });

    const [student, setStudent] = useState<AsyncOption | null>(member ? {
        id: member.userId,
        label: `${member.name} · ${member.nim ?? '-'}`,
    } : null);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onClose };

        if (member) {
            form.put(member.updateUrl, options);
            return;
        }

        form.post(urls.memberStore, options);
    };

    return (
        <StudentAffairsModal
            title={member ? 'Edit Anggota Organisasi' : 'Tambah Anggota Organisasi'}
            description="Pilih mahasiswa aktif, tentukan jabatan, dan isi periode kepengurusan."
            onClose={onClose}
        >
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <AsyncSelect
                    label="Mahasiswa"
                    required
                    fetchUrl={urls.searchStudents}
                    value={student}
                    onChange={(option) => {
                        setStudent(option);
                        form.setData('user_id', option ? String(option.id) : '');
                    }}
                    error={form.errors.user_id}
                    placeholder="Cari nama atau NIM…"
                />
                <div className="crud-grid">
                    <div>
                        <label className="crud-label" htmlFor="member-position">Jabatan <span>*</span></label>
                        <select
                            id="member-position"
                            className="db-input"
                            value={form.data.position}
                            onChange={(event) => form.setData('position', event.target.value)}
                        >
                            {Object.entries(positions).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                        {form.errors.position && <div className="db-error">{form.errors.position}</div>}
                    </div>
                    <TextField
                        label="Periode"
                        required
                        value={form.data.period}
                        onChange={(event) => form.setData('period', event.target.value)}
                        error={form.errors.period}
                        placeholder="2026/2027"
                        hint="Format tahun ajaran: 2026/2027."
                    />
                    <TextField
                        label="Tanggal bergabung"
                        type="date"
                        value={form.data.joined_at}
                        onChange={(event) => form.setData('joined_at', event.target.value)}
                        error={form.errors.joined_at}
                    />
                    <TextField
                        label="Tanggal keluar"
                        type="date"
                        value={form.data.left_at}
                        onChange={(event) => form.setData('left_at', event.target.value)}
                        error={form.errors.left_at}
                    />
                </div>
                <TextareaField
                    label="Catatan"
                    rows={2}
                    value={form.data.notes}
                    onChange={(event) => form.setData('notes', event.target.value)}
                    error={form.errors.notes}
                />
                <SwitchField
                    label="Status keanggotaan"
                    checked={form.data.is_active}
                    onChange={(value) => form.setData('is_active', value)}
                    onLabel="Aktif"
                    offLabel="Tidak aktif"
                />
                <div className="db-modal-actions">
                    <button type="button" className="db-btn ghost" onClick={onClose}>Batal</button>
                    <button type="submit" className="db-btn primary" disabled={form.processing}>
                        {member ? 'Simpan Perubahan' : 'Tambah Anggota'}
                    </button>
                </div>
            </form>
        </StudentAffairsModal>
    );
}

export default function OrganizationShow({ shell, can, organization, stats, members, achievements, options, urls }: Props) {
    const [memberModal, setMemberModal] = useState<{ member: Member | null } | null>(null);
    const [pendingDelete, setPendingDelete] = useState<Member | null>(null);
    const [processing, setProcessing] = useState(false);
    const [confirmOrganizationDelete, setConfirmOrganizationDelete] = useState(false);

    const confirmMemberDelete = () => {
        if (!pendingDelete) return;
        setProcessing(true);
        router.delete(pendingDelete.deleteUrl, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPendingDelete(null);
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${organization.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Kemahasiswaan"
                        title={organization.name}
                        description={organization.description || 'Profil organisasi mahasiswa.'}
                        badges={[
                            organization.typeLabel,
                            organization.faculty || 'Tingkat universitas',
                            organization.isActive ? 'Aktif' : 'Nonaktif',
                        ]}
                        actions={(
                            <>
                                <a className="db-btn ghost" href={urls.index}><ArrowLeft size={15} /> Daftar</a>
                                {can.createAchievement && (
                                    <a className="db-btn light" href={urls.achievementCreate}><Trophy size={15} /> Tambah Prestasi</a>
                                )}
                                {can.update && (
                                    <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Edit</a>
                                )}
                            </>
                        )}
                    />

                    <StudentAffairsStats
                        label="Statistik organisasi"
                        items={[
                            { label: 'Total anggota', value: stats.members, icon: Users },
                            { label: 'Anggota aktif', value: stats.activeMembers, icon: UserCheck, tone: 'green' },
                            { label: 'Prestasi organisasi', value: stats.achievements, icon: Trophy, tone: 'gold' },
                        ]}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Users size={15} /> Keanggotaan</h2>
                                    {can.manageMembers && (
                                        <button className="db-btn ghost sm" type="button" onClick={() => setMemberModal({ member: null })}>
                                            <UserPlus size={13} /> Tambah Anggota
                                        </button>
                                    )}
                                </div>
                                <div className="db-card-body">
                                    <div style={{ overflowX: 'auto' }}>
                                        <table className="crud-table">
                                            <thead>
                                                <tr>
                                                    <th>Mahasiswa</th>
                                                    <th>Jabatan</th>
                                                    <th>Periode</th>
                                                    <th>Status</th>
                                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {members.length === 0 && (
                                                    <tr>
                                                        <td colSpan={5} style={{ textAlign: 'center', padding: 24 }}>
                                                            <span className="db-hint">Belum ada anggota terdaftar.</span>
                                                        </td>
                                                    </tr>
                                                )}
                                                {members.map((member) => (
                                                    <tr key={member.id}>
                                                        <td>
                                                            <span style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                                                                <span className="db-stat-icon" style={{ width: 34, height: 34, borderRadius: 9, fontSize: 11, fontWeight: 800 }}>
                                                                    {initialsFor(member.name)}
                                                                </span>
                                                                <span>
                                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{member.name}</b>
                                                                    <small className="db-hint">
                                                                        {member.nim || 'Tanpa NIM'}{member.program ? ` · ${member.program}` : ''}
                                                                    </small>
                                                                </span>
                                                            </span>
                                                        </td>
                                                        <td><span className={`db-badge ${toneClass(member.positionTone)}`}>{member.positionLabel}</span></td>
                                                        <td>
                                                            <span>
                                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{member.period}</b>
                                                                <small className="db-hint">
                                                                    {member.joinedAt ? `Mulai ${member.joinedAt}` : '—'}
                                                                    {member.leftAt ? ` · Selesai ${member.leftAt}` : ''}
                                                                </small>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className={`db-badge ${member.isActive ? 'green' : 'gray'}`}>
                                                                {member.isActive ? 'Aktif' : 'Tidak aktif'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ display: 'flex', justifyContent: 'flex-end', gap: 6 }}>
                                                                {can.manageMembers && (
                                                                    <>
                                                                        <button className="db-btn ghost sm" type="button" title="Edit anggota" onClick={() => setMemberModal({ member })}>
                                                                            <Pencil size={13} />
                                                                        </button>
                                                                        <button className="db-btn ghost sm" type="button" title="Hapus anggota" onClick={() => setPendingDelete(member)}>
                                                                            <Trash2 size={13} />
                                                                        </button>
                                                                    </>
                                                                )}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Trophy size={15} /> Prestasi Organisasi</h2>
                                    <span className="db-badge gray">{achievements.length} tercatat</span>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ overflowX: 'auto' }}>
                                        <table className="crud-table">
                                            <thead>
                                                <tr>
                                                    <th>Prestasi</th>
                                                    <th>Mahasiswa</th>
                                                    <th>Kategori</th>
                                                    <th>Tingkat</th>
                                                    <th>Status</th>
                                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {achievements.length === 0 && (
                                                    <tr>
                                                        <td colSpan={6} style={{ textAlign: 'center', padding: 24 }}>
                                                            <span className="db-hint">Belum ada prestasi yang dikaitkan dengan organisasi ini.</span>
                                                        </td>
                                                    </tr>
                                                )}
                                                {achievements.map((achievement) => (
                                                    <tr key={achievement.id}>
                                                        <td>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{achievement.title}</b>
                                                            <small className="db-hint">{achievement.rankLabel} · {achievement.achievedAt || '—'}</small>
                                                        </td>
                                                        <td><span className="db-hint">{achievement.student}</span></td>
                                                        <td><span className="db-badge blue">{achievement.categoryLabel}</span></td>
                                                        <td><span className="db-badge amber">{achievement.levelLabel}</span></td>
                                                        <td><span className={`db-badge ${toneClass(achievement.statusTone)}`}>{achievement.statusLabel}</span></td>
                                                        <td>
                                                            <span style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                                                <a className="db-btn ghost sm" href={achievement.showUrl} title="Detail prestasi"><Eye size={13} /></a>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Profil Organisasi</h2>
                                    <span className={`db-badge ${toneClass(organization.typeTone)}`}>{organization.typeShort}</span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                                        {organization.logoUrl ? (
                                            <img
                                                src={organization.logoUrl}
                                                alt="Logo organisasi"
                                                style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 14, border: '1px solid var(--db-line)' }}
                                            />
                                        ) : (
                                            <span className="db-stat-icon" style={{ width: 64, height: 64, borderRadius: 14, fontWeight: 800 }}>
                                                {initialsFor(organization.name)}
                                            </span>
                                        )}
                                        <div>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 14 }}>{organization.name}</b>
                                            <small className="db-hint">{organization.typeLabel}</small>
                                        </div>
                                    </div>

                                    <p className="db-hint" style={{ margin: 0 }}>
                                        {organization.description || 'Belum ada deskripsi organisasi.'}
                                    </p>

                                    <div className="db-facts">
                                        <div className="db-fact">
                                            <span className="db-hint">Fakultas</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{organization.faculty || 'Universitas'}</b>
                                        </div>
                                        <div className="db-fact">
                                            <span className="db-hint">Program studi</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{organization.studyProgram || 'Lintas prodi'}</b>
                                        </div>
                                        <div className="db-fact">
                                            <span className="db-hint">Pembina</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{organization.advisor || '—'}</b>
                                        </div>
                                        <div className="db-fact">
                                            <span className="db-hint">Berdiri</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{organization.foundedAt || '—'}</b>
                                        </div>
                                    </div>

                                    <div style={{ display: 'grid', gap: 8 }}>
                                        {organization.contactEmail && (
                                            <a className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }} href={`mailto:${organization.contactEmail}`}>
                                                <Mail size={13} /> {organization.contactEmail}
                                            </a>
                                        )}
                                        {organization.contactPhone && (
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                                <Phone size={13} /> {organization.contactPhone}
                                            </span>
                                        )}
                                        {organization.instagramUrl && (
                                            <a className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }} href={organization.instagramUrl} target="_blank" rel="noopener noreferrer">
                                                <AtSign size={13} /> Instagram <ExternalLink size={11} />
                                            </a>
                                        )}
                                        {!organization.contactEmail && !organization.contactPhone && !organization.instagramUrl && (
                                            <span className="db-hint">Belum ada kontak organisasi.</span>
                                        )}
                                    </div>

                                    <span className={`db-badge ${organization.isActive ? 'green' : 'gray'}`} style={{ justifySelf: 'start' }}>
                                        {organization.isActive ? 'Organisasi aktif' : 'Organisasi nonaktif'}
                                    </span>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Informasi Sistem</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                        <CalendarDays size={13} /> Data anggota dikelola per periode kepengurusan.
                                    </span>
                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                        <CheckCircle2 size={13} /> Prestasi diverifikasi dari menu Prestasi Mahasiswa.
                                    </span>
                                </div>
                            </section>

                            {can.delete && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title">Zona Hati-hati</h2></div>
                                    <div className="db-card-body">
                                        <p className="db-hint" style={{ marginTop: 0 }}>
                                            Organisasi yang masih memiliki data prestasi tidak dapat dihapus.
                                        </p>
                                        <button className="db-btn danger" type="button" onClick={() => setConfirmOrganizationDelete(true)}>
                                            <Trash2 size={14} /> Hapus Organisasi
                                        </button>
                                    </div>
                                </section>
                            )}
                        </div>
                    </div>
                </div>

                {memberModal && (
                    <MemberModal
                        member={memberModal.member}
                        positions={options.positions}
                        urls={urls}
                        onClose={() => setMemberModal(null)}
                    />
                )}

                <ConfirmModal
                    open={pendingDelete !== null}
                    title="Hapus anggota?"
                    message={pendingDelete ? `${pendingDelete.name} akan dihapus dari kepengurusan organisasi.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmMemberDelete}
                    onCancel={() => setPendingDelete(null)}
                />

                <ConfirmModal
                    open={confirmOrganizationDelete}
                    title="Hapus organisasi?"
                    message={`"${organization.name}" akan dihapus beserta data keanggotaannya.`}
                    confirmLabel="Ya, hapus organisasi"
                    danger
                    onConfirm={() => router.delete(urls.delete)}
                    onCancel={() => setConfirmOrganizationDelete(false)}
                />
            </div>
        </AdminShell>
    );
}
