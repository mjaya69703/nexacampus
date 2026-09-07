// Public digital student ID verification page.
import { BadgeCheck, ShieldCheck } from 'lucide-react';
import { Campus } from '../../components/Home/PublicShell';
import '../../../css/admission-public.css';

type Props = { campus: Campus; student: { name: string; nim: string; academicStatus: string; programName: string; facultyName: string; isActive: boolean } };

export default function DigitalIdVerify({ campus, student }: Props) {
    return <div className="adm-root">
        <div className="adm-verify-wrap">
            <article className="adm-verify">
                <div className="adm-verify-head">
                    <span className="adm-verify-mark"><BadgeCheck size={28} /></span>
                    <h1>Kartu Mahasiswa Valid</h1>
                    <p>Data ini cocok dengan profil mahasiswa aktif di {campus.name}.</p>
                </div>
                <div className="adm-verify-data">
                    <div className="wide"><small>Nama</small><strong>{student.name}</strong></div>
                    <div><small>NIM</small><strong>{student.nim}</strong></div>
                    <div><small>Status Akademik</small><strong>{student.academicStatus}</strong></div>
                    <div className="wide"><small>Program Studi</small><strong>{student.programName}</strong></div>
                    <div className="wide"><small>Fakultas</small><strong>{student.facultyName}</strong></div>
                </div>
                <div className="adm-verify-foot">
                    <span><ShieldCheck size={13} /> Verifikasi resmi {campus.name}</span>
                    <span className={`adm-badge ${student.isActive ? 'green' : 'red'}`}>{student.isActive ? 'Aktif' : 'Tidak Aktif'}</span>
                </div>
            </article>
        </div>
    </div>;
}
