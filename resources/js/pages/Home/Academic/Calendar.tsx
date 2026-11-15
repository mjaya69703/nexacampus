// Public academic calendar page.
import { useMemo, useState } from 'react';
import { CalendarDays, CheckCircle2, Clock3, List } from 'lucide-react';
import { AcademicBaseProps, AcademicShell } from '../../../components/Home/Academic/AcademicShell';
import '../../../../css/academic-calendar.css';

type Year = { name: string; semester: string; startDate: string | null; endDate: string | null; startIso: string | null; endIso: string | null } | null;
type Period = { id: number; name: string; type: string; startDate: string | null; endDate: string | null; startIso: string | null; endIso: string | null; isActive: boolean; description: string | null };
type Props = AcademicBaseProps & { year: Year; periods: Period[] };
type ViewMode = 'list' | 'calendar';
type Month = { key: string; label: string; year: number; month: number; days: number; offset: number; events: Period[] };

const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

function isoDate(date: Date) {
    return date.toISOString().slice(0, 10);
}

function makeMonths(year: Year, periods: Period[]): Month[] {
    if (!year?.startIso || !year.endIso) return [];
    const cursor = new Date(`${year.startIso}T00:00:00Z`);
    const end = new Date(`${year.endIso}T00:00:00Z`);
    cursor.setUTCDate(1);
    const months: Month[] = [];

    while (cursor <= end) {
        const currentYear = cursor.getUTCFullYear();
        const currentMonth = cursor.getUTCMonth();
        const monthStart = isoDate(new Date(Date.UTC(currentYear, currentMonth, 1)));
        const monthEnd = isoDate(new Date(Date.UTC(currentYear, currentMonth + 1, 0)));
        const monthEvents = periods.filter((period) => period.startIso && period.endIso && period.startIso <= monthEnd && period.endIso >= monthStart);

        months.push({
            key: `${currentYear}-${currentMonth}`,
            label: new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(cursor),
            year: currentYear,
            month: currentMonth,
            days: new Date(Date.UTC(currentYear, currentMonth + 1, 0)).getUTCDate(),
            offset: (new Date(Date.UTC(currentYear, currentMonth, 1)).getUTCDay() + 6) % 7,
            events: monthEvents,
        });
        cursor.setUTCMonth(currentMonth + 1);
    }

    return months;
}

function CalendarGrid({ year, periods }: { year: Year; periods: Period[] }) {
    const months = useMemo(() => makeMonths(year, periods), [year, periods]);
    const today = isoDate(new Date());

    if (!months.length) {
        return <div className="academic-panel academic-empty"><span className="academic-empty-icon"><CalendarDays size={25} /></span><h3>Kalender belum tersedia</h3><p>Bagian akademik belum menerbitkan tahun akademik aktif beserta periode kegiatannya.</p></div>;
    }

    return <div className="academic-calendar-board">
        <div className="calendar-board-head">
            <div>
                <span className="academic-breadcrumb">TAMPILAN BULANAN</span>
                <h2>Ritme akademik sepanjang tahun</h2>
            </div>
            <div className="calendar-legend">
                <span><i className="legend-dot active" />Periode berjalan</span>
                <span><i className="legend-dot" />Agenda lainnya</span>
            </div>
        </div>
        <div className="academic-month-grid">
            {months.map((month) => <section className="academic-month" key={month.key}>
                <div className="academic-month-heading">
                    <h3>{month.label}</h3>
                    <span>{month.events.length} agenda</span>
                </div>
                <div className="academic-weekdays">{weekdays.map((day) => <span key={day}>{day}</span>)}</div>
                <div className="academic-month-days">
                    {Array.from({ length: month.offset }).map((_, index) => <span className="month-day empty" key={`empty-${index}`} />)}
                    {Array.from({ length: month.days }).map((_, index) => {
                        const day = index + 1;
                        const date = isoDate(new Date(Date.UTC(month.year, month.month, day)));
                        const events = month.events.filter((period) => period.startIso && period.endIso && period.startIso <= date && period.endIso >= date);
                        return <span className={`month-day ${events.length ? 'has-event' : ''} ${events.some((event) => event.isActive) ? 'is-active' : ''} ${date === today ? 'is-today' : ''}`} title={events.map((event) => event.name).join(', ')} key={date}><b>{day}</b></span>;
                    })}
                </div>
                {month.events.length > 0 && <div className="month-events">
                    {month.events.slice(0, 3).map((event) => <span key={event.id}><i className={event.isActive ? 'active' : ''} />{event.name}</span>)}
                </div>}
            </section>)}
        </div>
    </div>;
}

export default function Calendar({ campus, links, user, year, periods }: Props) {
    const [viewMode, setViewMode] = useState<ViewMode>('list');
    const activeCount = periods.filter((p) => p.isActive).length;

    return <AcademicShell campus={campus} links={links} user={user} activeTab="Kalender" eyebrow="Kalender akademik" title="Susun langkah Anda sepanjang semester." description="Rangkaian tanggal penting untuk registrasi, perkuliahan, evaluasi, dan kegiatan akademik pada tahun berjalan." icon={CalendarDays}>

        {/* ── Hero Banner ─────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, #172c48 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'36px 38px', marginBottom:28,
        }}>
            <div style={{ position:'absolute', top:'-40%', right:'-8%', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'absolute', bottom:'-35%', left:'-5%', width:320, height:320, borderRadius:'50%', background:'radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 60%)', pointerEvents:'none' }} />

            <div style={{ position:'relative', zIndex:2, display:'flex', flexWrap:'wrap', alignItems:'flex-end', justifyContent:'space-between', gap:20 }}>
                <div style={{ maxWidth:620 }}>
                    <span style={{
                        display:'inline-flex', alignItems:'center', gap:8, marginBottom:14,
                        padding:'5px 14px', borderRadius:999, border:'1px solid rgba(255,255,255,.25)',
                        background:'rgba(255,255,255,.09)', color:'#dce8f5', fontSize:11, fontWeight:700,
                        letterSpacing:'.04em', textTransform:'uppercase',
                    }}>
                        <span style={{ width:8, height:8, borderRadius:'50%', background:'#4ade80', animation:'pulse-green 2s infinite' }} />
                        Panduan Perjalanan Akademik
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Inter', sans-serif", letterSpacing:'-.01em' }}>
                        Semua momen penting, tersusun dalam satu garis waktu.
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Gunakan kalender ini sebagai acuan perencanaan studi. Detail kegiatan dapat berubah mengikuti kebijakan akademik terbaru.
                    </p>
                </div>
                {year && <div style={{
                    padding:'18px 26px', border:'1px solid rgba(255,255,255,.22)', borderRadius:14,
                    background:'rgba(255,255,255,.08)', backdropFilter:'blur(8px)', textAlign:'right',
                }}>
                    <span style={{ display:'block', color:'rgba(255,255,255,.7)', fontSize:10, fontWeight:700, textTransform:'uppercase', letterSpacing:'.08em', marginBottom:4 }}>Tahun Akademik Aktif</span>
                    <strong style={{ display:'block', color:'#fff', fontSize:20, lineHeight:1.2 }}>{year.name}</strong>
                    <small style={{ display:'inline-block', marginTop:6, padding:'3px 10px', borderRadius:999, fontSize:11, fontWeight:700, color:'#fff', background:'rgba(255,255,255,.18)' }}>{year.semester}</small>
                </div>}
            </div>
        </section>

        <style>{`@keyframes pulse-green { 0%{box-shadow:0 0 0 0 rgba(74,222,128,.7)} 70%{box-shadow:0 0 0 8px rgba(74,222,128,0)} 100%{box-shadow:0 0 0 0 rgba(74,222,128,0)} }`}</style>

        {/* ── Stats + Controls ────────────────────────────────────── */}
        <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', justifyContent:'space-between', gap:16, marginBottom:24 }}>
            <div>
                <h2 style={{ margin:'0 0 4px', color:'var(--academic-navy)', fontSize:20, fontWeight:700, fontFamily:"'Inter', sans-serif" }}>Periode akademik</h2>
                <p style={{ margin:0, color:'var(--academic-muted)', fontSize:13 }}>{year ? `${year.startDate ?? '-'} hingga ${year.endDate ?? '-'}` : 'Belum ada tahun akademik yang sedang berjalan.'}</p>
            </div>
            <div style={{ display:'flex', alignItems:'center', gap:12 }}>
                <div style={{ display:'flex', gap:8 }}>
                    <div style={{ display:'flex', alignItems:'center', gap:10, padding:'10px 16px', border:'1px solid var(--academic-line)', borderRadius:10, background:'var(--surface)' }}>
                        <span style={{ display:'grid', placeItems:'center', width:32, height:32, borderRadius:8, color:'var(--academic-blue)', background:'var(--academic-soft)' }}><CalendarDays size={15} /></span>
                        <div>
                            <b style={{ display:'block', color:'var(--academic-navy)', fontSize:16, lineHeight:1.1 }}>{periods.length}</b>
                            <small style={{ color:'var(--academic-muted)', fontSize:10, fontWeight:700, textTransform:'uppercase', letterSpacing:'.06em' }}>Periode</small>
                        </div>
                    </div>
                    <div style={{ display:'flex', alignItems:'center', gap:10, padding:'10px 16px', border:'1px solid var(--academic-line)', borderRadius:10, background:'var(--surface)' }}>
                        <span style={{ display:'grid', placeItems:'center', width:32, height:32, borderRadius:8, color:'#32936f', background:'rgba(50,147,111,.1)' }}><CheckCircle2 size={15} /></span>
                        <div>
                            <b style={{ display:'block', color:'var(--academic-navy)', fontSize:16, lineHeight:1.1 }}>{activeCount}</b>
                            <small style={{ color:'var(--academic-muted)', fontSize:10, fontWeight:700, textTransform:'uppercase', letterSpacing:'.06em' }}>Berjalan</small>
                        </div>
                    </div>
                </div>
                <div className="calendar-view-switch" role="group" aria-label="Tampilan kalender">
                    <button type="button" className={viewMode === 'list' ? 'active' : ''} onClick={() => setViewMode('list')}><List size={14} /> List</button>
                    <button type="button" className={viewMode === 'calendar' ? 'active' : ''} onClick={() => setViewMode('calendar')}><CalendarDays size={14} /> Calendar</button>
                </div>
            </div>
        </div>

        {/* ── Content ─────────────────────────────────────────────── */}
        {viewMode === 'calendar'
            ? <CalendarGrid year={year} periods={periods} />
            : periods.length
                ? <div style={{ display:'grid', gap:10 }}>
                    {periods.map((period) => <article key={period.id} style={{
                        display:'grid', gridTemplateColumns:'4px 1fr auto', gap:0,
                        border:'1px solid var(--academic-line)', borderRadius:12, background:'var(--surface)',
                        overflow:'hidden', transition:'box-shadow .18s ease, border-color .18s ease',
                    }}
                    onMouseEnter={(e) => { e.currentTarget.style.boxShadow='0 2px 12px rgba(20,39,63,.06)'; e.currentTarget.style.borderColor=period.isActive ? '#32936f' : 'var(--academic-line)'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.boxShadow=''; e.currentTarget.style.borderColor='var(--academic-line)'; }}
                    >
                        <div style={{ background: period.isActive ? '#32936f' : 'var(--academic-line)' }} />
                        <div style={{ padding:'16px 20px', display:'grid', gap:4 }}>
                            <div style={{ display:'flex', alignItems:'center', gap:8 }}>
                                <h3 style={{ margin:0, color:'var(--academic-navy)', fontSize:14, fontWeight:700, fontFamily:"'Inter', sans-serif" }}>{period.name}</h3>
                                {period.isActive && <span style={{ display:'inline-flex', alignItems:'center', gap:4, padding:'2px 8px', borderRadius:999, fontSize:10, fontWeight:700, color:'#fff', background:'#32936f' }}><CheckCircle2 size={10} /> Aktif</span>}
                            </div>
                            <p style={{ margin:0, color:'var(--academic-muted)', fontSize:12.5 }}>{period.description ?? period.type}</p>
                        </div>
                        <div style={{ padding:'16px 20px', display:'flex', flexDirection:'column', alignItems:'flex-end', justifyContent:'center', gap:4, borderLeft:'1px solid var(--academic-line)', fontSize:12 }}>
                            <span style={{ display:'flex', alignItems:'center', gap:5, color:'var(--academic-muted)' }}><Clock3 size={11} /> Mulai <strong style={{ color:'var(--academic-navy)' }}>{period.startDate ?? '-'}</strong></span>
                            <span style={{ display:'flex', alignItems:'center', gap:5, color:'var(--academic-muted)' }}><Clock3 size={11} /> Selesai <strong style={{ color:'var(--academic-navy)' }}>{period.endDate ?? '-'}</strong></span>
                        </div>
                    </article>)}
                </div>
                : <div className="academic-panel academic-empty"><span className="academic-empty-icon"><CalendarDays size={25} /></span><h3>Kalender belum tersedia</h3><p>Bagian akademik belum menerbitkan tahun akademik aktif beserta periode kegiatannya.</p></div>
        }
    </AcademicShell>;
}
