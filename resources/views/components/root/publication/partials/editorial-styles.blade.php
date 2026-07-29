<style>
/* Modern Publication Design System for NexaCampus */
@keyframes pulse-green {
    0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(74, 222, 128, 0); }
    100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
}

.pub-shell {
    min-height: calc(100vh - 80px);
}

.pub-wrap {
    width: 100%;
    max-width: 1320px;
    padding-right: var(--tblr-gutter-x, 1.5rem);
    padding-left: var(--tblr-gutter-x, 1.5rem);
    margin-right: auto;
    margin-left: auto;
    padding-top: 1.5rem;
    padding-bottom: 3.5rem;
}

/* Glassmorphism Hero Section */
.pub-hero {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #1e1b4b 100%);
    border-radius: 24px;
    color: #fff;
    padding: 3rem 2.5rem;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.35);
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(260px, .65fr);
    gap: 1.5rem;
    align-items: center;
    margin-bottom: 2.25rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.pub-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, rgba(139, 92, 246, 0.1) 50%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.pub-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.pub-hero > * {
    position: relative;
    z-index: 2;
}

.pub-kicker {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: .75rem;
    padding: .35rem .85rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    color: rgba(255, 255, 255, 0.95);
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.pub-kicker::before {
    content: '';
    width: 9px;
    height: 9px;
    background-color: #4ade80;
    border-radius: 50%;
    display: inline-block;
    animation: pulse-green 2s infinite;
}

.pub-title {
    margin: 0 0 1rem;
    color: #fff;
    font-size: clamp(1.6rem, 2.5vw, 2.25rem);
    font-weight: 850;
    line-height: 1.15;
    letter-spacing: -0.025em;
    max-width: 44rem;
}

.pub-title .text-accent {
    background: linear-gradient(135deg, #60a5fa, #a78bfa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.pub-lede {
    color: rgba(255, 255, 255, 0.82);
    font-size: 1.02rem;
    line-height: 1.65;
    max-width: 44rem;
    margin: 0;
}

.pub-hero-panel {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 1.75rem;
    backdrop-filter: blur(16px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.pub-hero-panel__label {
    color: rgba(255, 255, 255, .6);
    font-size: .76rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: .35rem;
}

.pub-hero-panel__number {
    color: #fff;
    font-size: 2.5rem;
    line-height: 1;
    font-weight: 900;
    letter-spacing: -0.03em;
}

.pub-hero-panel__note {
    color: rgba(255, 255, 255, .7);
    line-height: 1.6;
    margin: .75rem 0 0;
    font-size: .86rem;
}

/* Layout Grids */
.pub-layout,
.pub-detail {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 1.75rem;
    align-items: start;
}

.pub-layout--reverse {
    grid-template-columns: 340px minmax(0, 1fr);
}

.pub-main,
.pub-detail-main {
    min-width: 0;
}

.pub-side {
    min-width: 0;
    display: grid;
    gap: 1.25rem;
}

/* Card & Surface Containers */
.pub-card,
.pub-filter,
.pub-sidebox,
.pub-empty {
    background: var(--tblr-bg-surface, #ffffff);
    border: 1px solid var(--tblr-border-color, rgba(0, 0, 0, 0.08));
    border-radius: 1.15rem;
    box-shadow: 0 .125rem .375rem rgba(0, 0, 0, .04);
}

.pub-filter,
.pub-sidebox,
.pub-empty {
    padding: 1.35rem;
}

.pub-section-title {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin: 0 0 1.1rem;
    color: var(--tblr-body-color, #1e293b);
    font-size: .85rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.pub-section-title::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

/* Featured Hero Showcase Card */
.pub-feature {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr);
    overflow: hidden;
    margin-bottom: 2rem;
    text-decoration: none;
    color: inherit;
    border-radius: 1.25rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
    transition: transform .25 ease, box-shadow .25s ease, border-color .25s ease;
}

.pub-feature:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(59, 130, 246, 0.12);
    border-color: rgba(59, 130, 246, 0.4);
    color: inherit;
}

.pub-feature__body {
    padding: 2.25rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.pub-feature__media {
    min-height: 320px;
    background: var(--tblr-bg-surface-secondary, #f8fafc);
    overflow: hidden;
    position: relative;
}

.pub-feature__media img,
.pub-thumb img,
.pub-gallery-card img,
.pub-detail-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .4s ease;
}

.pub-feature:hover .pub-feature__media img,
.pub-card:hover .pub-thumb img,
.pub-gallery-card:hover img {
    transform: scale(1.05);
}

.pub-feature__placeholder,
.pub-thumb__placeholder,
.pub-gallery-placeholder {
    width: 100%;
    height: 100%;
    min-height: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(139, 92, 246, 0.08));
    color: var(--tblr-secondary, #64748b);
}

/* Badges & Tags */
.pub-badge {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    padding: .35rem .75rem;
    border-radius: 999px;
    background: rgba(59, 130, 246, .1);
    color: #2563eb;
    font-size: .73rem;
    font-weight: 750;
    line-height: 1;
    white-space: nowrap;
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.pub-badge--dark {
    background: rgba(15, 23, 42, .08);
    color: var(--tblr-body-color, #1e293b);
    border-color: rgba(15, 23, 42, .12);
}

.pub-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .85rem;
    color: var(--tblr-secondary, #64748b);
    font-size: .84rem;
    font-weight: 500;
}

.pub-feature h2 {
    margin: .8rem 0 .65rem;
    color: var(--tblr-body-color, #0f172a);
    font-size: clamp(1.25rem, 2vw, 1.65rem);
    line-height: 1.28;
    font-weight: 850;
    letter-spacing: -0.02em;
}

/* Grids */
.pub-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.25rem;
}

.pub-grid--three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

/* Standard Cards */
.pub-card {
    display: flex;
    flex-direction: column;
    min-height: 100%;
    overflow: hidden;
    color: inherit;
    text-decoration: none;
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
}

.pub-card:hover {
    transform: translateY(-4px);
    border-color: rgba(59, 130, 246, .4);
    box-shadow: 0 14px 32px rgba(0,0,0,.08);
    color: inherit;
}

.pub-card:focus-visible,
.pub-chip:focus-visible,
.pub-button:focus-visible,
.pub-lightbox-button:focus-visible,
.pub-gallery-card:focus-visible {
    outline: 3px solid rgba(59, 130, 246, .4);
    outline-offset: 3px;
}

.pub-card:active,
.pub-button:active,
.pub-chip:active,
.pub-gallery-card:active {
    transform: translateY(-1px);
}

.pub-thumb {
    aspect-ratio: 16 / 10;
    background: var(--tblr-bg-surface-secondary, #f8fafc);
    overflow: hidden;
    position: relative;
}

.pub-card__body {
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: .75rem;
    padding: 1.35rem;
}

.pub-card__title {
    margin: 0;
    color: var(--tblr-body-color, #0f172a);
    font-size: 1.05rem;
    line-height: 1.4;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.pub-card__excerpt {
    margin: 0;
    color: var(--tblr-secondary, #64748b);
    font-size: .875rem;
    line-height: 1.6;
}

.pub-card__footer {
    margin-top: auto;
    padding-top: .85rem;
    border-top: 1px solid var(--tblr-border-color, rgba(0, 0, 0, 0.08));
}

/* Forms & Inputs */
.pub-form-row {
    display: grid;
    gap: .85rem;
}

.pub-input,
.pub-select {
    min-height: 46px;
    width: 100%;
    border: 1.5px solid var(--tblr-border-color, #e2e8f0);
    border-radius: 14px;
    background: var(--tblr-bg-surface, #ffffff);
    color: var(--tblr-body-color, #0f172a);
    padding: .75rem 1rem;
    outline: 0;
    font-size: .9rem;
    transition: border-color .2s ease, box-shadow .2s ease;
}

.pub-input:focus,
.pub-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, .15);
}

/* Buttons & Chips */
.pub-button,
.pub-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    min-height: 42px;
    border: 1px solid var(--tblr-border-color, #e2e8f0);
    border-radius: 999px;
    background: var(--tblr-bg-surface, #ffffff);
    color: var(--tblr-body-color, #334155);
    padding: .55rem 1.15rem;
    font-weight: 700;
    font-size: .85rem;
    line-height: 1;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition: transform .2s ease, background .2s ease, border-color .2s ease, color .2s ease, box-shadow .2s ease;
}

.pub-button:hover,
.pub-chip:hover {
    background: var(--tblr-bg-surface-secondary, #f8fafc);
    border-color: rgba(59, 130, 246, .4);
    color: #2563eb;
}

.pub-button--accent,
.pub-chip--active {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff !important;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
}

.pub-button--accent:hover,
.pub-chip--active:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff !important;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45);
}

.pub-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
}

.pub-side-list {
    display: grid;
    gap: .6rem;
}

.pub-side-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: .85rem;
    align-items: center;
    padding: .75rem;
    border-radius: .85rem;
    color: inherit;
    text-decoration: none;
    transition: background .2s ease, transform .2s ease;
}

.pub-side-item:hover {
    background: var(--tblr-bg-surface-secondary, #f8fafc);
    color: inherit;
    transform: translateX(3px);
}

/* Date Tile Badge */
.pub-date-tile {
    width: 52px;
    min-height: 52px;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
    text-align: center;
    line-height: 1;
    box-shadow: 0 4px 14px rgba(59, 130, 246, .3);
    flex-shrink: 0;
}

.pub-date-tile strong {
    font-size: 1.25rem;
    line-height: 1;
    font-weight: 850;
}

.pub-date-tile span {
    margin-top: .18rem;
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    opacity: 0.9;
}

/* Agenda Card Special Layout */
.pub-agenda-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 1.15rem;
    padding: 1.35rem;
}

.pub-agenda-card .pub-card__body {
    padding: 0;
}

.pub-back {
    margin-bottom: 1.35rem;
}

/* Detail Pages */
.pub-detail-head {
    padding: 0 0 1.35rem;
    border-bottom: 1px solid var(--tblr-border-color, rgba(0, 0, 0, 0.08));
}

.pub-detail-title {
    margin: .85rem 0;
    color: var(--tblr-body-color, #0f172a);
    font-size: clamp(1.6rem, 3vw, 2.35rem);
    line-height: 1.2;
    font-weight: 850;
    letter-spacing: -.025em;
}

.pub-detail-media {
    margin: 1.5rem 0;
    aspect-ratio: 16 / 9;
    border-radius: 1.15rem;
    overflow: hidden;
    background: var(--tblr-bg-surface-secondary, #f8fafc);
    box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .08);
}

.pub-prose {
    color: var(--tblr-body-color, #334155);
    font-size: 1.03rem;
    line-height: 1.85;
}

.pub-prose p,
.pub-prose ul,
.pub-prose ol {
    margin-bottom: 1.25rem;
}

.pub-prose img {
    max-width: 100%;
    height: auto;
    border-radius: .85rem;
    margin: 1.25rem 0;
}

.pub-prose h2,
.pub-prose h3,
.pub-prose h4 {
    margin: 2rem 0 .85rem;
    color: var(--tblr-body-color, #0f172a);
    font-weight: 850;
    line-height: 1.25;
    letter-spacing: -0.015em;
}

.pub-prose blockquote {
    margin: 1.75rem 0;
    padding: 1.15rem 1.5rem;
    border-left: 4px solid #3b82f6;
    background: var(--tblr-bg-surface-secondary, #f8fafc);
    border-radius: 0 1rem 1rem 0;
    font-style: italic;
    color: var(--tblr-body-color, #1e293b);
}

/* Event Detail Hero Banner */
.pub-event-hero {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 1.75rem;
    align-items: center;
    margin-bottom: 1.75rem;
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #fff;
    border-radius: 24px;
    padding: 2.25rem;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.pub-event-hero .pub-detail-title {
    color: #fff;
}

.pub-event-hero .pub-meta {
    color: rgba(255, 255, 255, .8);
}

.pub-event-date {
    width: 110px;
    aspect-ratio: 1;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .25);
    color: #fff;
    text-align: center;
    backdrop-filter: blur(12px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.pub-event-date strong {
    display: block;
    font-size: 3.2rem;
    line-height: .9;
    font-weight: 900;
}

.pub-event-date span {
    display: block;
    margin-top: .5rem;
    color: rgba(255, 255, 255, .8);
    font-size: .72rem;
    font-weight: 750;
    letter-spacing: .08em;
    text-transform: uppercase;
}

/* Gallery Grid & Cards */
.pub-gallery-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.5rem;
}

.pub-gallery-card {
    position: relative;
    min-height: 330px;
    border-radius: 1.25rem;
    overflow: hidden;
    background: var(--tblr-bg-surface, #ffffff);
    color: #fff;
    box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .06);
    cursor: pointer;
    transition: transform .25s ease, box-shadow .25s ease;
}

.pub-gallery-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.16);
}

.pub-gallery-card__media {
    position: absolute;
    inset: 0;
    background: var(--tblr-bg-surface-secondary, #f8fafc);
}

.pub-gallery-card__media::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(15,23,42,.05) 0%, rgba(15,23,42,.85) 100%);
    transition: opacity .25s ease;
}

.pub-gallery-card:hover .pub-gallery-card__media::after {
    background: linear-gradient(180deg, rgba(15,23,42,.1) 0%, rgba(15,23,42,.92) 100%);
}

.pub-gallery-card__body {
    position: relative;
    z-index: 1;
    min-height: 330px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 1.5rem;
}

.pub-gallery-card h2 {
    margin: .85rem 0 .4rem;
    color: #fff;
    font-size: 1.2rem;
    line-height: 1.3;
    font-weight: 850;
}

.pub-gallery-card p {
    margin: 0;
    color: rgba(255,255,255,.82);
    line-height: 1.55;
    font-size: .875rem;
}

/* Lightbox Modal */
.pub-lightbox {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.75rem;
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(12px);
    animation: fadeIn .2s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.pub-lightbox__frame {
    width: min(1150px, 100%);
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.pub-lightbox__image {
    display: flex;
    align-items: center;
    justify-content: center;
    max-height: 74vh;
    width: 100%;
}

.pub-lightbox__image img {
    max-width: 100%;
    max-height: 74vh;
    object-fit: contain;
    border-radius: 1.15rem;
    box-shadow: 0 25px 70px rgba(0,0,0,.6);
}

.pub-lightbox-button {
    position: fixed;
    z-index: 10000;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 50%;
    background: rgba(255,255,255,.15);
    color: #fff;
    cursor: pointer;
    transition: background .2s ease, transform .2s ease;
    backdrop-filter: blur(8px);
}

.pub-lightbox-button:hover {
    background: rgba(255,255,255,.3);
    transform: scale(1.08);
}

.pub-lightbox-button--close {
    top: 24px;
    right: 24px;
}

.pub-lightbox-button--prev {
    top: 50%;
    left: 24px;
    transform: translateY(-50%);
}

.pub-lightbox-button--prev:hover {
    transform: translateY(-50%) scale(1.08);
}

.pub-lightbox-button--next {
    top: 50%;
    right: 24px;
    transform: translateY(-50%);
}

.pub-lightbox-button--next:hover {
    transform: translateY(-50%) scale(1.08);
}

.pub-lightbox-caption {
    color: #fff;
    text-align: center;
    margin-top: 1.25rem;
    max-width: 680px;
}

.pub-muted {
    color: var(--tblr-secondary, #64748b);
}

.pub-line-clamp-2,
.pub-line-clamp-3 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pub-line-clamp-2 { -webkit-line-clamp: 2; }
.pub-line-clamp-3 { -webkit-line-clamp: 3; }

/* Comprehensive Dark Mode Overrides */
[data-bs-theme=dark] .pub-card,
body[data-bs-theme=dark] .pub-card,
[data-bs-theme=dark] .pub-filter,
body[data-bs-theme=dark] .pub-filter,
[data-bs-theme=dark] .pub-sidebox,
body[data-bs-theme=dark] .pub-sidebox,
[data-bs-theme=dark] .pub-empty,
body[data-bs-theme=dark] .pub-empty,
[data-bs-theme=dark] .pub-feature,
body[data-bs-theme=dark] .pub-feature {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}

[data-bs-theme=dark] .pub-input,
body[data-bs-theme=dark] .pub-input,
[data-bs-theme=dark] .pub-select,
body[data-bs-theme=dark] .pub-select {
    background-color: #0f172a !important;
    border-color: #475569 !important;
    color: #f8fafc !important;
}

[data-bs-theme=dark] .pub-button,
body[data-bs-theme=dark] .pub-button,
[data-bs-theme=dark] .pub-chip,
body[data-bs-theme=dark] .pub-chip {
    background-color: #0f172a !important;
    border-color: #334155 !important;
    color: #cbd5e1 !important;
}

[data-bs-theme=dark] .pub-button:hover,
body[data-bs-theme=dark] .pub-button:hover,
[data-bs-theme=dark] .pub-chip:hover,
body[data-bs-theme=dark] .pub-chip:hover {
    background-color: #334155 !important;
    color: #60a5fa !important;
}

[data-bs-theme=dark] .pub-side-item:hover,
body[data-bs-theme=dark] .pub-side-item:hover {
    background-color: #334155 !important;
}

[data-bs-theme=dark] .pub-badge--dark,
body[data-bs-theme=dark] .pub-badge--dark {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: #e2e8f0 !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
}

/* Responsiveness */
@media (prefers-reduced-motion: reduce) {
    .pub-card,
    .pub-button,
    .pub-chip,
    .pub-gallery-card,
    .pub-feature__media img,
    .pub-thumb img {
        transition-duration: .12s !important;
    }
}

@media (max-width: 991.98px) {
    .pub-hero,
    .pub-layout,
    .pub-layout--reverse,
    .pub-detail,
    .pub-feature,
    .pub-event-hero {
        grid-template-columns: 1fr;
    }

    .pub-layout--reverse .pub-side {
        order: 2;
    }

    .pub-layout--reverse .pub-main {
        order: 1;
    }

    .pub-side {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .pub-gallery-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .pub-wrap {
        padding-top: 1rem;
        padding-right: 1rem;
        padding-left: 1rem;
    }

    .pub-hero {
        padding: 2rem 1.5rem;
    }

    .pub-grid,
    .pub-grid--three,
    .pub-side,
    .pub-gallery-grid {
        grid-template-columns: 1fr;
    }

    .pub-feature__media {
        min-height: 220px;
    }

    .pub-agenda-card {
        grid-template-columns: 1fr;
    }

    .pub-date-tile {
        width: 100%;
        min-height: 46px;
        flex-direction: row;
        gap: .5rem;
    }

    .pub-date-tile span {
        margin-top: 0;
    }

    .pub-event-date {
        width: 100%;
        aspect-ratio: auto;
        min-height: 100px;
    }

    .pub-gallery-card,
    .pub-gallery-card__body {
        min-height: 280px;
    }

    .pub-lightbox-button--prev,
    .pub-lightbox-button--next {
        top: auto;
        bottom: 24px;
        transform: none;
    }
}
</style>
