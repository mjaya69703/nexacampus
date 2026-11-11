// Grafik tren IPS & IPK per semester — SVG ringan, tanpa dependency chart.
import { LineChart } from 'lucide-react';

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

export function GpaTrendChart({ points }: Props) {
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

                {series.map((item) => (
                    <path
                        key={item.key}
                        className={`db-chart-line ${item.key}`}
                        d={buildPath(item.key)}
                    />
                ))}

                {series.flatMap((item) =>
                    points
                        .map((point, index) => ({ point, index, value: point[item.key] }))
                        .filter((entry): entry is { point: GpaTrendPoint; index: number; value: number } => entry.value !== null)
                        .map((entry) => (
                            <circle
                                key={`${item.key}-${entry.point.semester}`}
                                className={`db-chart-point ${item.key}`}
                                cx={x(entry.index)}
                                cy={y(entry.value)}
                                r={3.4}
                            >
                                <title>{`Semester ${entry.point.semester} · ${item.label} ${entry.value.toFixed(2)}`}</title>
                            </circle>
                        )),
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
            </svg>

            <p className="db-hint" style={{ margin: '6px 0 0' }}>
                IPS mengikuti nilai tiap semester; IPK dihitung kumulatif sampai semester tersebut.
            </p>
        </div>
    );
}
