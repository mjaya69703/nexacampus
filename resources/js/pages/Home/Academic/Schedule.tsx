// Public academic schedule page.
import { useMemo, useState } from 'react';
import { CalendarClock, MapPin, Monitor, UserRound } from 'lucide-react';
import { AcademicBaseProps, AcademicShell, AcademicStat } from '../../../components/Home/Academic/AcademicShell';

type ScheduleItem = { id: number; day: string; courseCode: string; courseName: string; program: string; lecturer: string; startTime: string; endTime: string; room: string; deliveryMode: string; sessionType: string };
type Props = AcademicBaseProps & { yearName: string | null; schedules: ScheduleItem[] };
const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const dayNames: Record<string, string> = { Monday: 'Senin', Tuesday: 'Selasa', Wednesday: 'Rabu', Thursday: 'Kamis', Friday: 'Jumat', Saturday: 'Sabtu', Sunday: 'Minggu' };
const modeNames: Record<string, string> = { offline: 'Tatap muka', online: 'Daring', hybrid: 'Hybrid' };

export default function Schedule({ campus, links, user, yearName, schedules }: Props) {
    const availableDays = useMemo(() => dayOrder.filter((day) => schedules.some((item) => item.day === day)), [schedules]);
    const [activeDay, setActiveDay] = useState(availableDays[0] ?? 'Monday');
    const visibleSchedules = schedules.filter((item) => item.day === activeDay);

    return <AcademicShell campus={campus} links={links} user={user} activeTab="Jadwal Kuliah" eyebrow="Jadwal kuliah" title="Lihat ritme perkuliahan minggu ini." description="Jadwal kuliah umum yang dipublikasikan untuk periode akademik berjalan. Mahasiswa tetap perlu memeriksa jadwal personal di portal akademik." icon={CalendarClock}>
        <div className="academic-overview"><div><h2>Jadwal kuliah umum</h2><p>Tahun akademik {yearName ?? 'belum diatur'} · Pilih hari untuk melihat sesi yang tersedia.</p></div><div className="academic-stats"><AcademicStat value={schedules.length} label="Sesi aktif" /><AcademicStat value={availableDays.length} label="Hari kuliah" /></div></div>
        {schedules.length ? <div className="schedule-layout"><div className="day-list">{availableDays.map((day) => <button type="button" className={`day-tab ${activeDay === day ? 'active' : ''}`} key={day} onClick={() => setActiveDay(day)}>{dayNames[day]} <span>{schedules.filter((item) => item.day === day).length}</span></button>)}</div><div><div className="schedule-day-heading"><h2>{dayNames[activeDay]}</h2><span>{visibleSchedules.length} sesi terjadwal</span></div><div className="schedule-cards">{visibleSchedules.map((item) => <article className="schedule-card" key={item.id}><div className="schedule-time"><strong>{item.startTime}</strong><small>sampai {item.endTime}</small><span className="mode-pill">{modeNames[item.deliveryMode] ?? item.deliveryMode}</span></div><div><h3>{item.courseName}</h3><div className="schedule-meta"><span><b>{item.courseCode}</b> · {item.program}</span><span><UserRound size={12} /> {item.lecturer}</span><span><MapPin size={12} /> {item.room}</span></div></div></article>)}</div></div></div> : <div className="academic-panel academic-empty"><span className="academic-empty-icon"><CalendarClock size={25} /></span><h3>Jadwal belum tersedia</h3><p>Belum ada sesi perkuliahan aktif yang dipublikasikan untuk periode ini.</p></div>}
        <div className="schedule-note"><Monitor size={15} /> <span>Jadwal di halaman ini adalah jadwal umum. Untuk jadwal sesuai KRS, silakan masuk ke portal mahasiswa.</span></div>
    </AcademicShell>;
}
