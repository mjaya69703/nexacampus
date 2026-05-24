@once
    @push('styles')
        <style>
            .assignment-shell {
                display: grid;
                gap: 1rem;
            }

            .assignment-card {
                background: #fff;
                border: 0;
                border-radius: 18px;
                box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08);
            }

            .assignment-hero {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                overflow: hidden;
                position: relative;
            }

            .assignment-hero::before {
                content: "";
                position: absolute;
                inset: -45% -35% auto auto;
                width: 28rem;
                height: 28rem;
                background: radial-gradient(circle, rgba(255,255,255,.18), transparent 68%);
            }

            .assignment-icon {
                align-items: center;
                border-radius: 16px;
                display: inline-flex;
                flex: 0 0 auto;
                height: 3.5rem;
                justify-content: center;
                width: 3.5rem;
            }

            .assignment-pill {
                align-items: center;
                background: #f1f5f9;
                border-radius: 999px;
                color: #475569;
                display: inline-flex;
                font-size: .8rem;
                font-weight: 700;
                gap: .35rem;
                padding: .45rem .75rem;
            }

            .assignment-info-pill {
                background: rgba(255,255,255,.17);
                backdrop-filter: blur(10px);
                color: #fff;
            }

            .assignment-action {
                align-items: center;
                border: 0;
                border-radius: 12px;
                display: inline-flex;
                font-weight: 800;
                gap: .45rem;
                justify-content: center;
                min-height: 2.75rem;
                padding: .75rem 1rem;
                text-decoration: none;
            }

            .assignment-panel {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 1rem;
            }

            .assignment-list-item {
                background: #fff;
                border: 1px solid #eef2ff;
                border-radius: 16px;
                padding: 1rem;
                transition: transform .2s ease, box-shadow .2s ease;
            }

            .assignment-list-item:hover {
                box-shadow: 0 12px 30px rgba(102, 126, 234, .12);
                transform: translateY(-2px);
            }

            .assignment-progress {
                background: #e2e8f0;
                border-radius: 999px;
                height: .55rem;
                overflow: hidden;
            }

            .assignment-progress > span {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: block;
                height: 100%;
            }

            .assignment-dropzone {
                background: #f8fafc;
                border: 2px dashed #c7d2fe;
                border-radius: 16px;
                padding: 1rem;
            }

            .assignment-attachment {
                align-items: center;
                background: #eef2ff;
                border-radius: 999px;
                color: #4338ca;
                display: inline-flex;
                font-weight: 700;
                gap: .4rem;
                max-width: 100%;
                padding: .45rem .75rem;
                text-decoration: none;
            }
        </style>
    @endpush
@endonce
