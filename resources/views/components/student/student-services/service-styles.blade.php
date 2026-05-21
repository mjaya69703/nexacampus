@push('styles')
    <style>
        .service-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            background: white;
            transition: all .3s ease;
        }
        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
        }
        .hero-gradient {
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero-gradient::before {
            content: '';
            position: absolute;
            inset: -60% -30% auto auto;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
        }
        .metric-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: .42rem .75rem;
            background: rgba(255,255,255,.18);
            border-radius: 8px;
            color: white;
            font-size: .84rem;
            backdrop-filter: blur(10px);
        }
        .request-card {
            border: 2px solid transparent;
            border-radius: 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.25rem;
            transition: all .3s ease;
        }
        .request-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(102,126,234,.15);
        }
        .form-shell {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 1.25rem;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border-radius: 10px;
            padding: .65rem 1.05rem;
            border: 0;
            font-weight: 700;
            text-decoration: none;
        }
        .detail-tile {
            min-height: 86px;
            border-radius: 14px;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
        }
        .timeline-dot {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            color: #4f46e5;
            background: #eef2ff;
        }
    </style>
@endpush
