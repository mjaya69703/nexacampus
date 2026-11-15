// Grafik tren IPS & IPK per semester — SVG ringan, tanpa dependency chart.
import { LineChart } from 'lucide-react';
import { useState } from 'react';

export type GpaTrendPoint = {
    semester: number;
    ips: number | null;
    ipk: number | null;
};

type Props = {
    points: GpaTrendPoint[];
};

const WIDTH = 720;
const HEIGHT = 248;
const PAD = { top: 14, right: 16, bottom: 30, left: 36 };
const MAX_GPA = 4;
const TICKS = [0, 1, 2, 3, 4];

const clamp = (value: number): number => Math.min(Math.max(value, 0), MAX_GPA);
const formatScore = (value: number | null): string => (value === null ? '–' : value.toFixed(2));

export function GpaTrendChart({ points }: Props) {
    const [hovered, setHovered] = useState<number | null>(null);
    const [selected, setSelected] = useState<number | null>(null);

    const plotWidth = WIDTH - PAD.left - PAD.right;
    const plotHeight = HEIGHT - PAD.top - PAD.bottom;

    const x = (index: number): number =>
        points.length <= 1
            ? PAD.left + plotWidth / 2
            : PAD.left + (index / (points.length - 1)) * plotWidth;

    const y = (value: number): number =>
        PAD.top + (1 - clamp(value) / MAX_GPA) * plotHeight;

    const buildPath = (key: 'ips' | 'ipk'): string =>
        points
            .map((point, index) => ({ index, value: point[key] }))
            .filter((point): point is { index: number; value: number } => point.value !== null)
            .map((point, index) => `${index === 0 ? 'M' : 'L'} ${x(point.index).toFixed(2)} ${y(point.value).toFixed(2)}`)
            .join(' ');

    const series = [
        { key: 'ips' as const, label: 'IPS' },
        { key: 'ipk' as const, label: 'IPK' },
    ];

    const filled = points.filter((point) => point.ips !== null || point.ipk !== null).length;
    const labelStep = points.length > 16 ? Math.ceil(points.length / 12) : 1;
    const step = points.length > 1 ? plotWidth / (points.length - 1) : plotWidth;

    const activeIndex = hovered ?? selected;
    const activePoint = activeIndex !== null ? points[activeIndex] ?? null : null;

    const toggleSelected = (index: number): void =>
        setSelected((current) => (current === index ? null : index));

    const describe = (point: GpaTrendPoint): string =>
        `Semester ${point.semester}. IPS ${formatScore(point.ips)}. IPK ${formatScore(point.ipk)}.`;

    if (filled === 0) {
        return (
            <div className="db-chart-empty">
                <LineChart size={26} />
                <div>
                    <b>Belum ada hasil studi.</b>
                    <small>Grafik IPS dan IPK muncul setelah nilai semester difinalisasi.</small>
                </div>
            </div>
        );
    }

    return (
        <div className="db-chart">
            <div className="db-chart-legend">
                {series.map((item) => (
                    <span className="db-chart-legend-item" key={item.key}>
                        <span className={`db-chart-swatch ${item.key}`} />
                        {item.label}
                    </span>
                ))}
                <span className="db-hint" style={{ marginLeft: 'auto' }}>Skala 0 – 4,00</span>
            </div>

            <div className="db-chart-readout" aria-live="polite">
                {activePoint ? (
                    <>
                        <b>Semester {activePoint.semester}</b>{' '}
                        <span className="db-chart-readout-item ips">IPS {formatScore(activePoint.ips)}</span>{' '}
                        <span className="db-chart-readout-item ipk">IPK {formatScore(activePoint.ipk)}</span>{' '}
                        {activePoint.ips === null && activePoint.ipk === null && (
                            <span className="db-chart-readout-empty">Belum ada nilai pada semester ini.</span>
                        )}
                    </>
                ) : (
                    <span className="db-chart-readout-empty">
                        Arahkan kursor atau klik titik/semester untuk melihat detail.
                    </span>
                )}
            </div>

            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                role="img"
                aria-label="Grafik tren IPS dan IPK per semester"
            >
                {TICKS.map((tick) => (
                    <g key={tick}>
                        <line
                            className="db-chart-grid"
                            x1={PAD.left}
                            x2={PAD.left + plotWidth}
                            y1={y(tick)}
                            y2={y(tick)}
                        />
                        <text
                            className="db-chart-axis"
                            x={PAD.left - 8}
                            y={y(tick) + 3}
                            textAnchor="end"
                        >
                            {tick.toFixed(1)}
                        </text>
                    </g>
                ))}

                {activeIndex !== null && (
                    <line
                        className="db-chart-guide"
                        x1={x(activeIndex)}
                        x2={x(activeIndex)}
                        y1={PAD.top}
                        y2={PAD.top + plotHeight}
                    />
                )}

                {series.map((item) => (
                    <path
                        key={item.key}
                        className={`db-chart-line ${item.key}`}
                        d={buildPath(item.key)}
                    />
                ))}

                {series.flatMap((item) =>
                    points.map((point, index) => {
                        const value = point[item.key];

                        if (value === null) {
                            return null;
                        }

                        const isActive = activeIndex === index;

                        return (
                            <circle
                                key={`${item.key}-${point.semester}`}
                                className={`db-chart-point ${item.key}${isActive ? ' is-active' : ''}`}
                                cx={x(index)}
                                cy={y(value)}
                                r={isActive ? 5 : 3.4}
                            />
                        );
                    }),
                )}

                {points.map((point, index) => {
                    const showLabel =
                        index === 0 ||
                        index === points.length - 1 ||
                        index % labelStep === 0;

                    if (!showLabel) {
                        return null;
                    }

                    return (
                        <text
                            key={`label-${point.semester}`}
                            className="db-chart-axis"
                            x={x(index)}
                            y={HEIGHT - 9}
                            textAnchor="middle"
                        >
                            {point.semester}
                        </text>
                    );
                })}

                {points.map((point, index) => {
                    const start = Math.max(PAD.left, x(index) - step / 2);
                    const end = Math.min(PAD.left + plotWidth, x(index) + step / 2);

                    return (
                        <rect
                            key={`hit-${point.semester}`}
                            className="db-chart-hit"
                            x={start}
                            y={PAD.top}
                            width={Math.max(1, end - start)}
                            height={plotHeight}
                            tabIndex={0}
                            role="button"
                            aria-label={describe(point)}
                            aria-pressed={selected === index}
                            onMouseEnter={() => setHovered(index)}
                            onMouseLeave={() => setHovered(null)}
                            onFocus={() => setHovered(index)}
                            onBlur={() => setHovered(null)}
                            onClick={() => toggleSelected(index)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' || event.key === ' ') {
                                    event.preventDefault();
                                    toggleSelected(index);
                                }
                            }}
                        />
                    );
                })}
            </svg>

            <p className="db-hint" style={{ margin: '6px 0 0' }}>
                IPS mengikuti nilai tiap semester; IPK dihitung kumulatif sampai semester tersebut.
            </p>
        </div>
    );
}
