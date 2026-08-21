import './bootstrap';
import flatpickr from "flatpickr";
import 'flatpickr/dist/flatpickr.min.css';
import L from 'leaflet';
import Lenis from 'lenis';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import './../../vendor/power-components/livewire-powergrid/dist/powergrid'

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

window.L = L;

// ============================================================
// Smooth Scrolling (Lenis) — global, semua halaman.
// Nonaktif otomatis bila user memilih reduced motion (aksesibilitas).
// ============================================================
window.lenis = null;

if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    window.lenis = new Lenis({
        duration: 1.05,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        smoothWheel: true,
        touchMultiplier: 1.6,
    });

    function raf(time) {
        window.lenis.raf(time);
        requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

    // Overlay dengan scroll internal tidak boleh dikendalikan Lenis.
    function markInnerScrollables() {
        document.querySelectorAll('.offcanvas-body, .modal-body').forEach((el) => {
            el.setAttribute('data-lenis-prevent', '');
        });
    }
    markInnerScrollables();

    // Livewire morph/navigation: pastikan dimensi konten dihitung ulang.
    document.addEventListener('livewire:navigated', () => {
        markInnerScrollables();
        window.lenis.resize();
        window.scrollTo({ top: window.scrollY });
    });

    Livewire.hook('morphed', ({ el }) => {
        if (el.hasAttribute && el.hasAttribute('wire:id')) {
            markInnerScrollables();
            window.lenis.resize();
        }
    });
}

// ============================================================
// ApexCharts Engine (declarative) — merender semua [data-app-chart].
// Aman terhadap lazy-loading & dedupe script Livewire karena init
// dipicu global setiap ada morph, idempoten per elemen.
// ============================================================
window.appDashboardCharts = {
    loading: false,
    queue: [],
    scanTimer: null,

    ensureApex(callback) {
        if (typeof ApexCharts !== 'undefined') {
            callback();
            return;
        }

        this.queue.push(callback);

        if (this.loading) return;
        this.loading = true;

        const script = document.createElement('script');
        script.src = '/assets/libs/apexcharts/dist/apexcharts.min.js';
        script.onload = () => {
            this.queue.forEach((cb) => cb());
            this.queue = [];
        };
        script.onerror = () => console.warn('[appDashboardCharts] Gagal memuat apexcharts.min.js');
        document.head.appendChild(script);
    },

    theme() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    },

    buildOptions(config) {
        const isDark = this.theme() === 'dark';

        if (config.type === 'donut' || config.type === 'pie') {
            return {
                chart: { type: config.type, height: config.height },
                labels: config.labels || [],
                series: config.series || [],
                legend: { position: 'bottom', fontSize: '11px' },
                colors: ['#206bc4', '#2fb344', '#4299e1', '#f59f00', '#d63939', '#ae3ec9'],
                tooltip: { theme: isDark ? 'dark' : 'light' },
            };
        }

        return {
            chart: { type: config.type, height: config.height, toolbar: { show: false }, animations: { speed: 450 } },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', horizontal: false } },
            dataLabels: { enabled: false },
            series: config.series || [],
            xaxis: {
                categories: config.categories || [],
                labels: { style: { colors: isDark ? '#b8b3c9' : '#64748b' } },
            },
            yaxis: { labels: { style: { colors: isDark ? '#b8b3c9' : '#64748b' } } },
            grid: { borderColor: isDark ? 'rgba(167,139,255,0.15)' : '#e6e9f0' },
            colors: ['#6842f4'],
            stroke: { curve: 'smooth', width: config.type === 'line' ? 3 : 0 },
            tooltip: { theme: isDark ? 'dark' : 'light' },
        };
    },

    render(config) {
        const el = document.getElementById(config.elId);
        if (!el) return;

        if (el.__apexChart) {
            el.__apexChart.destroy();
            el.__apexChart = null;
        }

        const chart = new ApexCharts(el, this.buildOptions(config));
        el.__apexChart = chart;
        chart.render();
    },

    init(root = document) {
        this.ensureApex(() => {
            root.querySelectorAll('[data-app-chart]').forEach((el) => {
                if (!el.id || el.__apexChart) return;

                try {
                    this.render(JSON.parse(el.dataset.appChart));
                } catch (error) {
                    console.warn('[appDashboardCharts] Render gagal untuk #' + el.id, error);
                }
            });
        });
    },

    // Debounced scan — dipanggil setiap morph Livewire.
    requestScan() {
        clearTimeout(this.scanTimer);
        this.scanTimer = setTimeout(() => this.init(document), 120);
    },
};

document.addEventListener('DOMContentLoaded', () => {
    window.appDashboardCharts.init();
});

// Pindai ulang chart setiap komponen Livewire selesai morph
// (termasuk saat child dashboard selesai lazy-load).
function wireChartScanHooks() {
    if (!window.Livewire) return false;

    Livewire.hook('morphed', ({ el }) => {
        if (el.hasAttribute && el.hasAttribute('wire:id')) {
            window.appDashboardCharts.requestScan();
        }
    });

    return true;
}

if (!wireChartScanHooks()) {
    window.addEventListener('livewire:init', wireChartScanHooks);
}

window.previewImage = function (input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById(previewId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
};

window.initLivewireTomSelect = function ({
    selectId,
    property,
    placeholder = '',
    removeButtonTitle = 'Hapus item',
}) {
    const selectElement = document.getElementById(selectId);

    if (!selectElement || selectElement.tomselect || typeof window.TomSelect === 'undefined') {
        return;
    }

    const tomSelect = new window.TomSelect(selectElement, {
        plugins: {
            remove_button: {
                title: removeButtonTitle,
            },
        },
        persist: false,
        create: false,
        hidePlaceholder: true,
        placeholder,
    });

    tomSelect.on('change', () => {
        const selectedValues = tomSelect.getValue();
        const values = Array.isArray(selectedValues)
            ? selectedValues
            : (selectedValues ? selectedValues.split(',') : []);

        const componentElement = selectElement.closest('[wire\\:id]');
        if (!componentElement) {
            return;
        }

        const component = window.Livewire?.find(componentElement.getAttribute('wire:id'));
        if (!component) {
            return;
        }

        component.set(property, values);
    });
};
