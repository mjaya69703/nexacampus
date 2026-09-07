// Public applicant portal.
import { Head, Link, useForm } from '@inertiajs/react';
import { BadgeCheck, CalendarCheck, CalendarDays, CalendarX2, CheckCircle2, Clock3, Eye, FileText, FolderOpen, Hourglass, IdCard, MapPin, Star, Upload, Video, XCircle } from 'lucide-react';
import { AdmissionBaseProps, AdmissionShell } from '../../../components/Home/Admission/AdmissionShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StatStrip } from '../../../components/Shared/StatStrip';

type Requirement = { id: number; documentType: string; label: string; isRequired: boolean; allowedExtensions: string; maxSizeKb: number };
type DocumentItem = { id: number; documentType: string; fileName: string; fileSizeKb: string; verificationStatus: string; verificationNotes: string | null; previewUrl: string; isImage: boolean; canPreview: boolean };
type ExamSession = { id: number; title: string | null; examType: string; examDate: string | null; examTime: string | null; venue: string; meetingLink: string | null; attendanceStatus: string };
type Score = { id: number; type: string; weight: number | null; score: number | null };
type TimelineItem = { id: number; fromStatus: string; toStatus: string; notes: string | null; createdAt: string | null };
type Props = AdmissionBaseProps & {
    application: { applicationNumber: string; fullName: string; status: string; statusRaw: string; email: string; phone: string; classType: string; periodName: string | null; programName: string; finalScore: number | null; nim: string | null; convertedAt: string | null };
    requirements: Requirement[];
    documents: DocumentItem[];
    examSessions: ExamSession[];
    scores: Score[];
    timeline: TimelineItem[];
    stats: { verifiedDocuments: number; uploadedDocuments: number; completion: number; timelineItems: number; assignedSessions: number };
};

const documentFor = (documents: DocumentItem[], documentType: string) => documents.find((document) => document.documentType === documentType) ?? null;

export default function Portal({ campus, links, user, application, requirements, documents, examSessions, scores, timeline, stats }: Props) {
    const uploadForm = useForm<{ document: File | null; requirementId: number }>({ document: null, requirementId: 0 });
    const uploadUrl = `/admission/applications/${application.applicationNumber}/documents`;

    const pickDocument = (requirement: Requirement, file: File | null) => {
        uploadForm.setData('document', file);
        uploadForm.setData('requirementId', requirement.id);
        if (!file) return;
        uploadForm.clearErrors();
        uploadForm.post(uploadUrl, { forceFormData: true });
    };

    const documentError = (documentType: string) =>
        (uploadForm.errors as Record<string, string | undefined>)[`document.${documentType}`] ?? uploadForm.errors.document;

    return <AdmissionShell campus={campus} links={links} user={user} activeTab="Cek Status" eyebrow="Portal Pendaftar" title={`Selamat datang, ${application.fullName}.`} description="Lacak progres seleksi, unggah kelengkapan berkas, pantau jadwal ujian, dan lihat pengumuman resmi langsung dari dashboard terpadu ini." icon={IdCard}
        action={<Link className="adm-btn light" href="/admission/apply">Pendaftaran Lainnya</Link>}>
        <Head title={`Portal Pendaftar · ${application.applicationNumber}`} />

        <section className="adm-card" id="ringkasan" style={{ background: 'linear-gradient(118deg, var(--adm-brand-deep), #1d4d82 60%, #2b69ab)', border: 0, color: '#ffffff' }}>
            <div className="adm-card-body" style={{ display: 'grid', gap: 18 }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'flex-start', gap: 14 }}>
                    <div>
                        <small style={{ display: 'block', color: 'rgba(255,255,255,.72)', fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.08em' }}>Nomor Pendaftaran</small>
                        <strong style={{ display: 'block', margin: '4px 0 0', color: '#ffffff', font: '700 26px "Source Serif 4", Georgia, serif' }}>{application.applicationNumber}</strong>
                        <span style={{ display: 'block', margin: '6px 0 0', color: 'rgba(255,255,255,.78)', fontSize: 12 }}>{application.programName} · {application.classType || '-'} · {application.periodName ?? '-'}</span>
                    </div>
                    <span className="adm-badge" style={{ background: '#ffffff', color: 'var(--adm-brand-deep)' }}>{application.status}</span>
                </div>
                <div style={{ display: 'grid', gap: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', color: 'rgba(255,255,255,.78)', fontSize: 11.5, fontWeight: 700 }}><span>Kelengkapan Berkas Wajib</span><span>{stats.completion}%</span></div>
                    <div className="adm-progress"><i style={{ width: `${stats.completion}%` }} /></div>
                </div>
            </div>
        </section>

        <StatStrip
            items={[
                { icon: FolderOpen, value: `${stats.verifiedDocuments} / ${stats.uploadedDocuments}`, label: 'Dokumen Terverifikasi', tone: 'green' },
                { icon: CalendarCheck, value: stats.assignedSessions, label: 'Sesi Ujian Ditugaskan' },
                { icon: Star, value: application.finalScore ?? '-', label: 'Skor Akhir / Kelulusan', tone: 'gold' },
                { icon: Hourglass, value: stats.timelineItems, label: 'Riwayat Status' },
            ]}
        />

        <div className="adm-layout">
            <div className="adm-stack">
                <section className="adm-card" id="documents">
                    <div className="adm-card-head"><h2 className="adm-card-title">Kelengkapan Berkas Persyaratan</h2><span className="adm-badge">Progres: {stats.completion}%</span></div>
                    <div className="adm-card-body">
                        <div className="adm-grid">
                            {requirements.map((requirement) => {
                                const document = documentFor(documents, requirement.documentType);
                                return <article key={requirement.id} className={`adm-doc${document?.verificationStatus === 'verified' ? ' ok' : document?.verificationStatus === 'rejected' ? ' bad' : ''}`}>
                                    <div className="adm-doc-top">
                                        <h4>{requirement.label} {requirement.isRequired && <span className="adm-badge red">Wajib</span>}</h4>
                                        {document && (document.verificationStatus === 'verified'
                                            ? <span className="adm-badge green"><CheckCircle2 size={12} /> Verified</span>
                                            : document.verificationStatus === 'rejected'
                                                ? <span className="adm-badge red"><XCircle size={12} /> Ditolak (Perbaiki)</span>
                                                : <span className="adm-badge amber"><Clock3 size={12} /> Menunggu Verifikasi</span>)}
                                    </div>

                                    {document
                                        ? <>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                <small className="adm-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 7 }}><FileText size={13} />{document.fileName} ({document.fileSizeKb} KB)</small>
                                                {document.canPreview && <a href={document.previewUrl} target="_blank" rel="noopener noreferrer" className="adm-btn ghost sm"><Eye size={13} /> Buka File</a>}
                                            </div>
                                            {document.verificationNotes && <div className="adm-note red"><XCircle size={14} /><span><b>Catatan Verifikator:</b> {document.verificationNotes}</span></div>}
                                        </>
                                        : <small className="adm-hint">Belum diunggah.</small>}

                                    {(!document || document.verificationStatus !== 'verified') && <div className="adm-upload">
                                        <label className="adm-file-label"><Upload size={13} />{uploadForm.processing && uploadForm.data.requirementId === requirement.id ? 'Memproses…' : 'Pilih berkas untuk diunggah…'}<input type="file" accept={requirement.allowedExtensions.split(',').map((extension) => '.' + extension.trim().toLowerCase()).join(',')} disabled={uploadForm.processing} onChange={(event) => pickDocument(requirement, event.target.files?.[0] ?? null)} /></label>
                                        <small className="adm-hint" style={{ flex: '0 1 auto' }}>Maks. {requirement.maxSizeKb} KB · {requirement.allowedExtensions}</small>
                                    </div>}
                                    {(documentError(requirement.documentType) ?? uploadForm.errors.document) && <div className="adm-note red"><XCircle size={14} /><span>{documentError(requirement.documentType) ?? uploadForm.errors.document}</span></div>}
                                </article>;
                            })}
                            {requirements.length === 0 && <EmptyState icon={FolderOpen} iconSize={26} title="Belum Ada Persyaratan" description="Gelombang ini belum memiliki daftar persyaratan dokumen." />}
                        </div>
                    </div>
                </section>

                <section className="adm-card" id="selection">
                    <div className="adm-card-head"><h2 className="adm-card-title">Jadwal Ujian &amp; Wawancara</h2><span className="adm-badge">{stats.assignedSessions} Sesi Ditugaskan</span></div>
                    <div className="adm-card-body">
                        {examSessions.length === 0 && <EmptyState icon={CalendarX2} iconSize={26} title="Belum Ada Jadwal" description="Belum ada jadwal ujian atau wawancara yang ditetapkan untuk Anda." />}
                        {examSessions.length > 0 && <div className="adm-grid">
                            {examSessions.map((session) => <article className="adm-card" key={session.id}>
                                <div className="adm-card-body" style={{ display: 'grid', gap: 13 }}>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'flex-start', gap: 10 }}>
                                        <div style={{ display: 'grid', gap: 7 }}>
                                            <h4 style={{ margin: 0, color: 'var(--adm-heading)', font: '700 15px "Source Serif 4", Georgia, serif' }}>{session.title}</h4>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 10 }}>
                                                <span className="adm-badge">{session.examType}</span>
                                                <span className="adm-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><CalendarDays size={13} />{session.examDate}</span>
                                                <span className="adm-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><Clock3 size={13} />{session.examTime}</span>
                                            </div>
                                        </div>
                                        <span className={`adm-badge ${session.attendanceStatus.toLowerCase() === 'present' ? 'green' : session.attendanceStatus.toLowerCase() === 'absent' ? 'red' : ''}`}>Kehadiran: {session.attendanceStatus}</span>
                                    </div>
                                    <div className="adm-list">
                                        <div className="adm-list-row"><small>Lokasi / Ruang</small><b style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><MapPin size={13} />{session.venue}</b></div>
                                        <div className="adm-list-row"><small>Tautan Virtual Meeting</small>{session.meetingLink ? <a className="adm-btn primary sm" style={{ justifySelf: 'start' }} href={session.meetingLink} target="_blank" rel="noopener noreferrer"><Video size={13} /> Buka Ruang Virtual</a> : <b style={{ color: 'var(--adm-muted)', fontWeight: 500 }}>Belum tersedia</b>}</div>
                                    </div>
                                </div>
                            </article>)}
                        </div>}
                    </div>
                </section>
            </div>

            <aside className="adm-stack">
                <section className="adm-card">
                    <div className="adm-card-head"><h3 className="adm-card-title">Profil Pendaftaran</h3></div>
                    <div className="adm-card-body tight" style={{ display: 'grid', gap: 14 }}>
                        <div className="adm-list">
                            {[['Nama Lengkap', application.fullName], ['Gelombang', application.periodName ?? '-'], ['Program Studi / Kelas', `${application.programName} (${application.classType || '-'})`], ['Email / No. HP', `${application.email} · ${application.phone}`]].map(([label, value]) => <div className="adm-list-row" key={label}><small>{label}</small><b>{value}</b></div>)}
                        </div>
                        {application.convertedAt && <div style={{ display: 'grid', gap: 3, padding: '13px 15px', borderRadius: 12, background: 'var(--adm-green-soft)' }}>
                            <b style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--adm-green)', fontSize: 12.5 }}><BadgeCheck size={14} /> Pendaftaran Diterima</b>
                            <small style={{ margin: '5px 0 0', color: 'var(--adm-muted)', fontSize: 11, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.07em' }}>Nomor Induk Mahasiswa (NIM)</small>
                            <strong style={{ color: 'var(--adm-heading)', font: '700 23px "Source Serif 4", Georgia, serif' }}>{application.nim ?? '-'}</strong>
                            <small className="adm-hint">Dikonversi pada: {application.convertedAt}</small>
                        </div>}
                    </div>
                </section>

                <section className="adm-card">
                    <div className="adm-card-head"><h3 className="adm-card-title">Penilaian Kelulusan</h3><Star size={15} color="var(--adm-gold)" /></div>
                    <div className="adm-card-body tight">
                        {scores.length === 0 && <EmptyState icon={Star} iconSize={24} title="Skor Belum Tersedia" description="Skor ujian dan penilaian belum dipublikasikan oleh panitia." style={{ minHeight: 0, padding: '22px 18px' }} />}
                        {scores.length > 0 && <div style={{ display: 'grid', gap: 11 }}>
                            {scores.map((score) => <div key={score.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, paddingBottom: 10, borderBottom: '1px solid var(--adm-line)' }}>
                                <div><b style={{ color: 'var(--adm-ink)', fontSize: 13 }}>{score.type}</b>{score.weight !== null && <small className="adm-hint" style={{ display: 'block' }}>Bobot: {score.weight}</small>}</div>
                                <span className="adm-badge">{score.score}</span>
                            </div>)}
                            <div style={{ marginTop: 3, padding: 14, borderRadius: 12, background: 'var(--adm-brand-soft)', textAlign: 'center' }}>
                                <small style={{ color: 'var(--adm-brand)', fontSize: 11, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.07em' }}>Skor Akhir / Final</small>
                                <strong style={{ display: 'block', color: 'var(--adm-heading)', font: '700 26px "Source Serif 4", Georgia, serif' }}>{application.finalScore ?? '-'}</strong>
                            </div>
                        </div>}
                    </div>
                </section>

                <section className="adm-card" id="timeline">
                    <div className="adm-card-head"><h3 className="adm-card-title">Riwayat Status</h3><span className="adm-badge gray">{stats.timelineItems}</span></div>
                    <div className="adm-card-body tight">
                        {timeline.length === 0 && <EmptyState icon={Hourglass} iconSize={24} title="Belum Ada Riwayat" description="Belum ada riwayat perubahan status." style={{ minHeight: 0, padding: '22px 18px' }} />}
                        {timeline.length > 0 && <div className="adm-timeline">
                            {timeline.map((item) => <div className="adm-timeline-item" key={item.id}>
                                <span className="adm-timeline-dot" />
                                <h4><span className="adm-badge gray">{item.fromStatus}</span><span aria-hidden>→</span><span className="adm-badge">{item.toStatus}</span></h4>
                                <time>{item.createdAt}</time>
                                {item.notes && <div className="adm-timeline-note">“{item.notes}”</div>}
                            </div>)}
                        </div>}
                    </div>
                </section>
            </aside>
        </div>
    </AdmissionShell>;
}
