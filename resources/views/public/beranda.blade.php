@extends('layouts.public')

@section('title', 'Beranda · Perumahan Lumin Park')

@push('styles')
<style>
    /* ── Keyframes ── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(32px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes floatSlow {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50%       { transform: translateY(-18px) rotate(3deg); }
    }
    @keyframes floatMed {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50%       { transform: translateY(-10px) rotate(-2deg); }
    }
    @keyframes pulseRing {
        0%   { transform: scale(1);   opacity: 0.4; }
        100% { transform: scale(1.9); opacity: 0; }
    }
    @keyframes shimmer {
        0%   { background-position: -200% 0; }
        100% { background-position:  200% 0; }
    }
    @keyframes countUp {
        from { opacity: 0; transform: scale(0.7); }
        to   { opacity: 1; transform: scale(1); }
    }

    /* ── Scroll Reveal ── */
    .reveal {
        opacity: 0;
        transform: translateY(28px);
        transition: opacity 0.65s cubic-bezier(.22,.61,.36,1), transform 0.65s cubic-bezier(.22,.61,.36,1);
    }
    .reveal.visible {
        opacity: 1;
        transform: translateY(0);
    }
    .reveal-left {
        opacity: 0;
        transform: translateX(-28px);
        transition: opacity 0.65s cubic-bezier(.22,.61,.36,1), transform 0.65s cubic-bezier(.22,.61,.36,1);
    }
    .reveal-left.visible { opacity: 1; transform: translateX(0); }
    .reveal-right {
        opacity: 0;
        transform: translateX(28px);
        transition: opacity 0.65s cubic-bezier(.22,.61,.36,1), transform 0.65s cubic-bezier(.22,.61,.36,1);
    }
    .reveal-right.visible { opacity: 1; transform: translateX(0); }

    /* ── Hero ── */
    .hero-bg {
        background: linear-gradient(135deg, #0d1f17 0%, #123024 40%, #1c4d39 70%, #0d1f17 100%);
        position: relative;
        overflow: hidden;
    }
    .hero-bg::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 60% 50% at 80% 20%, rgba(47,125,91,0.35) 0%, transparent 70%),
            radial-gradient(ellipse 40% 60% at 10% 80%, rgba(27,77,57,0.4) 0%, transparent 60%);
    }
    .hero-grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 60px 60px;
    }
    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(47,125,91,0.25);
        border: 1px solid rgba(47,125,91,0.5);
        border-radius: 999px;
        padding: 6px 16px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #b8d9c8;
        backdrop-filter: blur(8px);
        animation: fadeUp 0.6s ease both;
    }
    .hero-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-weight: 800;
        line-height: 1.1;
        animation: fadeUp 0.6s 0.15s ease both;
    }
    .hero-title span.accent {
        color: #8bbfaa;
    }
    .hero-tagline {
        animation: fadeUp 0.6s 0.3s ease both;
    }
    .hero-buttons {
        animation: fadeUp 0.6s 0.45s ease both;
    }
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        color: #123024;
        font-weight: 700;
        font-size: 0.875rem;
        padding: 14px 28px;
        border-radius: 14px;
        transition: all 0.25s cubic-bezier(.22,.61,.36,1);
        box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    }
    .btn-primary:hover {
        background: #f5ede0;
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.35);
    }
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.08);
        color: #ffffff;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 14px 28px;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.25);
        transition: all 0.25s cubic-bezier(.22,.61,.36,1);
        backdrop-filter: blur(8px);
    }
    .btn-secondary:hover {
        background: rgba(255,255,255,0.18);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-2px);
    }

    /* ── Floating shapes ── */
    .shape {
        position: absolute;
        border-radius: 999px;
        pointer-events: none;
        mix-blend-mode: screen;
    }
    .shape-1 {
        width: 350px; height: 350px;
        background: radial-gradient(circle, rgba(47,125,91,0.18) 0%, transparent 70%);
        top: -80px; right: -80px;
        animation: floatSlow 8s ease-in-out infinite;
    }
    .shape-2 {
        width: 200px; height: 200px;
        background: radial-gradient(circle, rgba(139,191,170,0.12) 0%, transparent 70%);
        bottom: 40px; left: 10%;
        animation: floatMed 6s ease-in-out infinite 1s;
    }

    /* ── Stats bar ── */
    .stats-bar {
        background: linear-gradient(135deg, #ffffff 0%, #faf6ee 100%);
        border-top: 3px solid #2f7d5b;
    }
    .stat-item {
        position: relative;
    }
    .stat-item:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0; top: 20%; height: 60%;
        width: 1px;
        background: linear-gradient(to bottom, transparent, #cfc0a0, transparent);
    }
    .stat-number {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 2.25rem;
        font-weight: 800;
        color: #123024;
        line-height: 1;
    }
    .stat-label { color: #6b7280; font-size: 0.8rem; font-weight: 500; }
    .stat-dot {
        width: 8px; height: 8px;
        background: #2f7d5b;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
        position: relative;
    }
    .stat-dot::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid #2f7d5b;
        animation: pulseRing 2s ease infinite;
    }

    /* ── Section headers ── */
    .section-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #dcebe2;
        color: #256349;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 5px 14px;
        border-radius: 999px;
    }
    .section-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 2rem;
        font-weight: 800;
        color: #0d0f0e;
        line-height: 1.2;
        margin-top: 10px;
    }
    .section-divider {
        width: 48px; height: 4px;
        background: linear-gradient(90deg, #2f7d5b, #8bbfaa);
        border-radius: 2px;
        margin-top: 14px;
    }

    /* ── Unit Cards ── */
    .unit-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 20px;
        background: #ffffff;
        border: 1px solid #ede3d1;
        box-shadow: 0 2px 12px rgba(18,48,36,0.07);
        transition: all 0.3s cubic-bezier(.22,.61,.36,1);
        text-decoration: none;
        color: inherit;
    }
    .unit-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 50px rgba(18,48,36,0.15);
        border-color: #2f7d5b;
    }
    .unit-card-img {
        height: 180px;
        width: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }
    .unit-card:hover .unit-card-img { transform: scale(1.05); }
    .unit-card-placeholder {
        height: 180px;
        background: linear-gradient(135deg, #f2f7f4 0%, #dcebe2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 600;
        color: #2f7d5b;
    }
    .unit-card-body { padding: 20px; display: flex; flex-direction: column; flex: 1; gap: 10px; }
    .unit-badge {
        display: inline-block;
        background: #dcebe2;
        color: #256349;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 3px 10px;
        border-radius: 999px;
    }
    .unit-price {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 1.3rem;
        font-weight: 800;
        color: #123024;
    }
    .unit-cta {
        margin-top: auto;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.8rem;
        font-weight: 700;
        color: #2f7d5b;
        padding-top: 10px;
        border-top: 1px solid #f5ede0;
        transition: gap 0.2s;
    }
    .unit-card:hover .unit-cta { gap: 8px; }

    /* ── Scheme Cards ── */
    .scheme-card {
        background: linear-gradient(160deg, #ffffff 0%, #faf6ee 100%);
        border: 1px solid #ede3d1;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 2px 12px rgba(18,48,36,0.06);
        transition: all 0.3s cubic-bezier(.22,.61,.36,1);
        position: relative;
        overflow: hidden;
    }
    .scheme-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2f7d5b, #8bbfaa);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }
    .scheme-card:hover { transform: translateY(-5px); box-shadow: 0 20px 45px rgba(18,48,36,0.12); }
    .scheme-card:hover::before { transform: scaleX(1); }

    /* ── Map section ── */
    .map-wrap {
        border-radius: 24px;
        overflow: hidden;
        border: 2px solid #dcebe2;
        box-shadow: 0 8px 40px rgba(18,48,36,0.12);
    }

    /* ── News & Gallery Cards ── */
    .news-card {
        background: #ffffff;
        border: 1px solid #ede3d1;
        border-radius: 20px;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(18,48,36,0.06);
        transition: all 0.3s ease;
    }
    .news-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(18,48,36,0.12); border-color: #b8d9c8; }
    .gallery-figure {
        overflow: hidden;
        border-radius: 18px;
        border: 1px solid #ede3d1;
        box-shadow: 0 2px 10px rgba(18,48,36,0.06);
        background: #ffffff;
        transition: all 0.3s ease;
    }
    .gallery-figure:hover { transform: scale(1.02); box-shadow: 0 12px 32px rgba(18,48,36,0.14); }
    .gallery-figure img { transition: transform 0.4s ease; }
    .gallery-figure:hover img { transform: scale(1.08); }

    /* ── CTA / Sales section ── */
    .cta-section {
        background: linear-gradient(135deg, #123024 0%, #1c4d39 50%, #2f7d5b 100%);
        border-radius: 28px;
        position: relative;
        overflow: hidden;
    }
    .cta-section::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 50% 70% at 90% 10%, rgba(139,191,170,0.2) 0%, transparent 60%),
            radial-gradient(ellipse 30% 50% at 5% 90%, rgba(47,125,91,0.3) 0%, transparent 60%);
    }
    .cta-grid-bg {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
        background-size: 40px 40px;
    }
    .sales-contact-card {
        display: flex;
        align-items: center;
        gap: 14px;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 18px;
        padding: 18px;
        text-decoration: none;
        backdrop-filter: blur(8px);
        transition: all 0.3s ease;
    }
    .sales-contact-card:hover {
        background: rgba(255,255,255,0.2);
        border-color: rgba(255,255,255,0.4);
        transform: translateY(-3px);
    }

    /* ── Shimmer loading ── */
    .shimmer {
        background: linear-gradient(90deg, #f5ede0 25%, #faf6ee 50%, #f5ede0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 12px;
    }

    /* ── Scroll-to-top ── */
    #scroll-top {
        position: fixed;
        bottom: 32px;
        right: 32px;
        z-index: 50;
        width: 48px; height: 48px;
        background: #2f7d5b;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 20px rgba(18,48,36,0.3);
        cursor: pointer;
        opacity: 0;
        pointer-events: none;
        transition: all 0.3s ease;
        border: none;
    }
    #scroll-top.show { opacity: 1; pointer-events: auto; }
    #scroll-top:hover { background: #123024; transform: translateY(-3px); }
</style>
@endpush

@section('content')

{{-- ═══════════════════════════════════════════════
     HERO
═══════════════════════════════════════════════ --}}
<section class="hero-bg text-white min-h-[85vh] flex items-center">
    <div class="hero-grid"></div>
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <div class="relative mx-auto max-w-6xl px-4 py-24 sm:py-32 w-full">
        <div class="max-w-4xl">
            <span id="hero-eyebrow" class="hero-eyebrow">
                <span class="stat-dot"></span>
                Hunian Minimalis, Asri &amp; Islami
            </span>

            <h1 class="hero-title mt-6 text-4xl sm:text-5xl lg:text-6xl text-white">
                <span id="hero-title" data-setting="estate_name">Perumahan<br>
                <span class="accent">Lumin Park</span></span>
            </h1>

            <p id="hero-tagline" class="hero-tagline mt-6 max-w-2xl text-base sm:text-lg leading-relaxed text-brand-100/85">
                Jelajahi unit rumah yang tersedia, bandingkan skema pembayaran, dan hubungi tim sales kami tanpa perlu membuat akun.
            </p>

            <div class="hero-buttons mt-10 flex flex-wrap gap-4">
                <a href="/unit" class="btn-primary">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Lihat Daftar Unit
                </a>
                <a href="/kontak" class="btn-secondary">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 00-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.68 10.32 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>
                    Hubungi Sales
                </a>
            </div>

            {{-- trust badges --}}
            <div class="hero-buttons mt-10 flex flex-wrap items-center gap-6 text-xs text-brand-100/60">
                <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-brand-300" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>Izin Resmi</span>
                <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-brand-300" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Hak Kepemilikan Jelas</span>
                <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-brand-300" viewBox="0 0 24 24" fill="currentColor"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>Tim Profesional</span>
            </div>
        </div>
    </div>

    {{-- bottom wave --}}
    <div class="absolute bottom-0 left-0 right-0 overflow-hidden leading-none">
        <svg viewBox="0 0 1440 56" xmlns="http://www.w3.org/2000/svg" class="fill-cream-50 w-full"><path d="M0,32L60,26.7C120,21,240,11,360,10.7C480,11,600,21,720,26.7C840,32,960,32,1080,26.7C1200,21,1320,11,1380,5.3L1440,0L1440,56L1380,56C1320,56,1200,56,1080,56C960,56,840,56,720,56C600,56,480,56,360,56C240,56,120,56,60,56L0,56Z"/></svg>
    </div>

    <img id="hero-bg" alt="" class="absolute inset-0 hidden h-full w-full object-cover opacity-20">
</section>

{{-- ═══════════════════════════════════════════════
     STATS BAR
═══════════════════════════════════════════════ --}}
<div class="stats-bar shadow-sm">
    <div class="mx-auto max-w-6xl px-4">
        <div class="grid grid-cols-2 sm:grid-cols-4">
            <div class="stat-item px-6 py-7 text-center reveal">
                <div class="stat-number">50<span class="text-brand-500 text-2xl">+</span></div>
                <div class="stat-label mt-1">Unit Tersedia</div>
            </div>
            <div class="stat-item px-6 py-7 text-center reveal" style="transition-delay:.1s">
                <div class="stat-number">3<span class="text-brand-500 text-2xl">+</span></div>
                <div class="stat-label mt-1">Klaster Aktif</div>
            </div>
            <div class="stat-item px-6 py-7 text-center reveal" style="transition-delay:.2s">
                <div class="stat-number">5<span class="text-brand-500 text-2xl">★</span></div>
                <div class="stat-label mt-1">Rating Layanan</div>
            </div>
            <div class="stat-item px-6 py-7 text-center reveal" style="transition-delay:.3s">
                <div class="stat-number">100<span class="text-brand-500 text-2xl">%</span></div>
                <div class="stat-label mt-1">Izin Resmi</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════════════ --}}
<main class="bg-cream-50">

    {{-- ── Unit Unggulan ── --}}
    <section data-seksi="unggulan" class="mx-auto max-w-6xl px-4 py-20">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="reveal-left">
                <span class="section-tag">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    Unit Unggulan
                </span>
                <h2 class="section-title">Rumah Pilihan<br><span class="text-brand-600">Terbaik Kami</span></h2>
                <div class="section-divider"></div>
                <p class="mt-4 text-sm text-gray-500 max-w-sm">Beberapa unit rumah berkualitas tinggi yang sedang kami tawarkan untuk Anda.</p>
            </div>
            <a href="/unit" class="reveal-right flex items-center gap-2 text-sm font-bold text-brand-600 hover:text-brand-900 transition-colors group">
                Lihat Semua Unit
                <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div id="beranda-units" class="mt-10 reveal"></div>
    </section>

    {{-- ── Divider ── --}}
    <div class="mx-auto max-w-6xl px-4"><div class="border-t border-cream-300"></div></div>

    {{-- ── Skema Pembayaran ── --}}
    <section data-seksi="skema" class="mx-auto max-w-6xl px-4 py-20">
        <div class="text-center mb-12 reveal">
            <span class="section-tag">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/></svg>
                Skema Pembayaran
            </span>
            <h2 class="section-title">Pilih Skema yang<br><span class="text-brand-600">Sesuai Anda</span></h2>
            <div class="section-divider mx-auto"></div>
            <p class="mt-4 text-sm text-gray-500 max-w-md mx-auto">Pilih metode pembiayaan yang paling sesuai dengan kebutuhan dan kemampuan Anda.</p>
        </div>
        <div id="beranda-schemes" class="reveal"></div>
    </section>

    {{-- ── Lokasi Proyek ── --}}
    <section data-seksi="peta" class="bg-white py-20">
        <div class="mx-auto max-w-6xl px-4">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
                <div class="reveal-left">
                    <span class="section-tag">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        Lokasi Proyek
                    </span>
                    <h2 class="section-title">Temukan Kami di<br><span class="text-brand-600">Lokasi Strategis</span></h2>
                    <div class="section-divider"></div>
                    <p class="mt-4 text-sm text-gray-500 max-w-sm">Klik penanda pada peta untuk melihat detail jalan di sekitar proyek dan dapatkan rute menuju lokasi.</p>
                </div>
            </div>

            <div class="map-wrap reveal">
                <div id="beranda-map" class="h-[440px] bg-cream-100"></div>
            </div>
            <div id="beranda-rute" class="mt-5 flex flex-wrap items-center gap-3 reveal"></div>
            <div id="beranda-map-list" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 reveal"></div>
        </div>
    </section>

    {{-- ── Kabar Terbaru & Galeri ── --}}
    <section class="mx-auto max-w-6xl px-4 py-20">
        <div class="grid gap-14 lg:grid-cols-2">
            <div data-seksi="news">
                <div class="reveal-left">
                    <span class="section-tag">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                        Kabar Terbaru
                    </span>
                    <h2 class="section-title">Berita &amp;<br><span class="text-brand-600">Informasi</span></h2>
                    <div class="section-divider"></div>
                </div>
                <div id="beranda-news" class="mt-8 space-y-5 reveal"></div>
            </div>

            <div data-seksi="gallery">
                <div class="reveal-right">
                    <span class="section-tag">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                        Galeri
                    </span>
                    <h2 class="section-title">Galeri<br><span class="text-brand-600">Perumahan</span></h2>
                    <div class="section-divider"></div>
                </div>
                <div id="beranda-gallery" class="mt-8 reveal"></div>
            </div>
        </div>
    </section>

    {{-- ── CTA / Hubungi Sales ── --}}
    <section data-seksi="sales" class="mx-auto max-w-6xl px-4 pb-24">
        <div class="cta-section reveal">
            <div class="cta-grid-bg"></div>
            <div class="relative p-10 sm:p-14">
                <div class="grid gap-10 lg:grid-cols-2 items-center">
                    <div>
                        <span class="inline-flex items-center gap-2 bg-white/15 border border-white/25 text-white text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-full backdrop-blur">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 00-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.68 10.32 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>
                            Tim Sales Siap Membantu
                        </span>
                        <h2 class="font-serif font-bold text-3xl sm:text-4xl text-white mt-5 leading-tight" style="font-family:'Playfair Display',serif">
                            Siap Memilih<br>Unit Impian Anda?
                        </h2>
                        <p class="mt-4 text-brand-100/80 text-sm leading-relaxed max-w-sm">
                            Kontak WhatsApp sales kami siap membantu Anda menemukan unit yang paling sesuai dengan kebutuhan dan anggaran.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-4">
                            <a href="/kontak" class="inline-flex items-center gap-2 bg-white text-brand-900 font-bold text-sm px-7 py-3.5 rounded-2xl hover:bg-cream-200 transition-all hover:-translate-y-0.5 shadow-lg">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .18h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.72 6.72l1.21-1.21a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7a2 2 0 011.72 2.03z"/></svg>
                                Hubungi Kami
                            </a>
                            <a href="/unit" class="inline-flex items-center gap-2 bg-white/15 border border-white/30 text-white font-semibold text-sm px-7 py-3.5 rounded-2xl hover:bg-white/25 transition-all hover:-translate-y-0.5 backdrop-blur">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                Jelajahi Unit
                            </a>
                        </div>
                    </div>

                    <div>
                        <div id="beranda-sales" class="grid gap-3 sm:grid-cols-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

{{-- ── Scroll to top button ── --}}
<button id="scroll-top" aria-label="Kembali ke atas" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 15l-6-6-6 6"/></svg>
</button>

@endsection

@push('scripts')
<script>
    // ── Scroll-to-top ──────────────────────────────────────
    window.addEventListener('scroll', function () {
        const btn = document.getElementById('scroll-top');
        if (btn) btn.classList.toggle('show', window.scrollY > 400);
    }, { passive: true });

    // ── Scroll Reveal ──────────────────────────────────────
    (function () {
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(function (el) {
            io.observe(el);
        });
    })();

    // ── Shared helpers ─────────────────────────────────────
    const ANON = { anonymous: true };

    function pct(value) {
        const n = Number(value || 0);
        return (n % 1 === 0 ? n : n.toFixed(1)) + '%';
    }

    // ── Unit card ──────────────────────────────────────────
    function unitCard(u) {
        const imgHtml = u.image_url
            ? '<img src="' + LP.esc(u.image_url) + '" alt="' + LP.esc(u.unit_type) + '" loading="lazy" class="unit-card-img">'
            : '<div class="unit-card-placeholder"><svg class="h-10 w-10 text-brand-400 opacity-60" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></div>';

        return '<a href="/unit/' + LP.esc(u.id) + '" class="unit-card">'
            + '<div style="overflow:hidden">' + imgHtml + '</div>'
            + '<div class="unit-card-body">'
            + '<div class="flex items-center justify-between gap-2">'
            + '<span class="unit-badge">' + LP.esc(u.unit_code) + '</span>'
            + '<span class="text-xs text-gray-400 font-medium">' + LP.esc(u.cluster) + '</span>'
            + '</div>'
            + '<h3 class="text-base font-bold text-ink-900">' + LP.esc(u.unit_type) + '</h3>'
            + '<p class="text-xs text-gray-500">LT ' + LP.number(u.land_size) + ' m&sup2; &middot; LB ' + LP.number(u.building_size) + ' m&sup2;</p>'
            + '<div class="unit-price">' + LP.money(u.price) + '</div>'
            + '<span class="unit-cta">Lihat detail <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>'
            + '</div></a>';
    }

    async function loadUnits() {
        const mount = document.getElementById('beranda-units');
        mount.innerHTML = '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">'
            + '<div class="shimmer h-72"></div><div class="shimmer h-72"></div><div class="shimmer h-72"></div>'
            + '</div>';
        try {
            const rows = await LP.fetchJson('/public/featured-units', ANON);
            const list = rows || [];
            mount.innerHTML = list.length
                ? '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">' + list.map(unitCard).join('') + '</div>'
                : '<p class="rounded-2xl border border-cream-300 bg-white p-8 text-sm text-gray-500 text-center">Belum ada unit yang tersedia saat ini.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat daftar unit.');
        }
    }

    // ── Scheme card ────────────────────────────────────────
    function schemeCard(s) {
        return '<div class="scheme-card">'
            + '<p class="text-xs font-bold uppercase tracking-widest text-brand-500 mb-1">' + LP.esc(s.scheme_type) + '</p>'
            + '<h3 class="text-lg font-bold text-ink-900">' + LP.esc(s.scheme_name) + '</h3>'
            + '<dl class="mt-5 space-y-3 text-sm">'
            + '<div class="flex items-center justify-between py-2 border-b border-cream-200">'
            + '<dt class="text-gray-500 font-medium flex items-center gap-1.5"><svg class="h-3.5 w-3.5 text-brand-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>Uang Muka</dt>'
            + '<dd class="font-bold text-brand-700 text-base">' + pct(s.down_payment_percent) + '</dd></div>'
            + '<div class="flex items-center justify-between py-2 border-b border-cream-200">'
            + '<dt class="text-gray-500 font-medium">Tenor</dt>'
            + '<dd class="font-bold text-ink-900">' + LP.number(s.installment_months) + ' bulan</dd></div>'
            + '<div class="flex items-center justify-between py-2">'
            + '<dt class="text-gray-500 font-medium">Suku Bunga</dt>'
            + '<dd class="font-bold text-ink-900">' + pct(s.interest_rate) + '</dd></div>'
            + '</dl>'
            + (s.description ? '<p class="mt-4 text-xs leading-relaxed text-gray-400 border-t border-cream-200 pt-4">' + LP.esc(s.description) + '</p>' : '')
            + '</div>';
    }

    async function loadSchemes() {
        const mount = document.getElementById('beranda-schemes');
        mount.innerHTML = '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">'
            + '<div class="shimmer h-56"></div><div class="shimmer h-56"></div>'
            + '<div class="shimmer h-56"></div><div class="shimmer h-56"></div>'
            + '</div>';
        try {
            const rows = await LP.fetchJson('/public/payment-schemes', ANON);
            const list = (rows || []).slice(0, 4);
            mount.innerHTML = list.length
                ? '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">' + list.map(schemeCard).join('') + '</div>'
                : '<p class="rounded-2xl border border-cream-300 bg-white p-8 text-sm text-gray-500 text-center">Skema pembayaran belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat skema pembayaran.');
        }
    }

    // ── News & Gallery ─────────────────────────────────────
    function renderNews(news, mount) {
        mount.innerHTML = news.length
            ? news.map(function (n) {
                return '<article class="news-card">'
                    + '<p class="text-xs font-bold uppercase tracking-widest text-brand-500">' + LP.date(n.date) + '</p>'
                    + '<h3 class="mt-2 text-base font-bold text-ink-900 leading-snug">' + LP.esc(n.title) + '</h3>'
                    + (n.image_url ? '<img src="' + LP.esc(n.image_url) + '" alt="' + LP.esc(n.title) + '" loading="lazy" class="mt-3 h-36 w-full rounded-xl object-cover">' : '')
                    + '<p class="mt-3 text-sm leading-relaxed text-gray-500">' + LP.esc(n.content) + '</p>'
                    + '</article>';
            }).join('')
            : '<p class="rounded-2xl border border-cream-300 bg-white p-8 text-sm text-gray-500 text-center">Belum ada kabar terbaru.</p>';
    }

    function renderGallery(gallery, mount) {
        mount.innerHTML = gallery.length
            ? '<div class="grid gap-4 sm:grid-cols-2">' + gallery.map(function (g) {
                return '<figure class="gallery-figure">'
                    + '<div style="overflow:hidden"><img src="' + LP.esc(g.image_url) + '" alt="' + LP.esc(g.title) + '" loading="lazy" class="h-44 w-full object-cover"></div>'
                    + '<figcaption class="px-4 py-3 text-sm font-semibold text-gray-700 bg-white">' + LP.esc(g.title) + '</figcaption>'
                    + '</figure>';
            }).join('') + '</div>'
            : '<p class="rounded-2xl border border-cream-300 bg-white p-8 text-sm text-gray-500 text-center">Galeri belum tersedia.</p>';
    }

    async function loadNewsAndGallery(aktif) {
        const newsMount    = document.getElementById('beranda-news');
        const galleryMount = document.getElementById('beranda-gallery');
        if (aktif.news)    LP.loading(newsMount,    'Memuat kabar terbaru...');
        if (aktif.gallery) LP.loading(galleryMount, 'Memuat galeri...');
        try {
            const data    = await LP.fetchJson('/public/news-gallery', ANON);
            const news    = (data && data.news)    || [];
            const gallery = (data && data.gallery) || [];
            if (aktif.news)    renderNews(news,       newsMount);
            if (aktif.gallery) renderGallery(gallery, galleryMount);
        } catch (e) {
            if (aktif.news)    LP.errorBox(newsMount,    e.message || 'Gagal memuat kabar terbaru.');
            if (aktif.gallery) LP.errorBox(galleryMount, 'Galeri gagal dimuat.');
        }
    }

    // ── Sales contacts ─────────────────────────────────────
    async function loadSales() {
        const mount = document.getElementById('beranda-sales');
        LP.loading(mount, 'Memuat kontak sales...');
        try {
            const rows = await LP.fetchJson('/public/sales-contacts', ANON);
            mount.innerHTML = (rows || []).length
                ? rows.map(function (s) {
                    const avatarHtml = s.avatar_url
                        ? LP.fotoSales(s.avatar_url, s.name, 'h-12 w-12 rounded-full object-cover border-2 border-white/40')
                        : '<span class="grid h-12 w-12 place-items-center rounded-full bg-white/20 text-white border border-white/30">'
                          + '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .2-3.2-.7-2.7-1.1-4.4-3.8-4.6-4-.1-.2-1-1.4-1-2.6s.6-1.8.8-2c.2-.2.4-.3.6-.3h.5c.2 0 .4 0 .5.4l.8 2c.1.2 0 .3-.1.5l-.4.6c-.2.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.4 0 .6-.1l.7-.9c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.4.3.1.2.1.7-.1 1z"/></svg>'
                          + '</span>';
                    return '<a href="' + LP.esc(s.whatsapp_link) + '" target="_blank" rel="noopener" class="sales-contact-card">'
                        + avatarHtml
                        + '<span>'
                        + '<span class="block text-sm font-bold text-white">' + LP.esc(s.name) + '</span>'
                        + '<span class="block text-xs text-brand-200 mt-0.5">' + LP.esc(s.sales_code) + '</span>'
                        + '</span>'
                        + '<svg class="h-4 w-4 text-white/50 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'
                        + '</a>';
                }).join('')
                : '<p class="text-sm text-brand-100/60 p-4">Kontak sales belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat kontak sales.');
        }
    }

    // ── Map ────────────────────────────────────────────────
    function muatLeaflet() {
        if (window.L) return Promise.resolve();
        return new Promise(function (resolve, reject) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(css);
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = resolve;
            script.onerror = function () { reject(new Error('Peta tidak dapat dimuat.')); };
            document.head.appendChild(script);
        });
    }

    const ZOOM_JALAN   = 17;
    const LAYANAN_RUTE = 'https://router.project-osrm.org/route/v1/driving/';
    let petaBeranda = null, lapisanRute = null, titikSaya = null, proyekTerpilih = null;

    const ruteGoogle = (r) => 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' + r.latitude + ',' + r.longitude;

    function popupProyek(r) {
        return '<strong>' + LP.esc(r.name) + '</strong><br>'
            + LP.esc(r.address || 'Alamat belum diisi') + '<br>'
            + LP.number(r.units_available) + ' unit tersedia<br>'
            + '<a href="/unit?proyek=' + encodeURIComponent(r.name) + '">Lihat unit proyek ini</a><br>'
            + '<a href="' + LP.esc(ruteGoogle(r)) + '" target="_blank" rel="noopener">Rute menuju lokasi</a>';
    }

    function petaInfo(rows) {
        document.getElementById('beranda-map-list').innerHTML = rows.map(function (r) {
            return '<div class="rounded-2xl border border-cream-300 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">'
                + '<div class="flex items-start gap-3">'
                + '<div class="mt-0.5 h-9 w-9 flex-shrink-0 rounded-xl bg-brand-50 flex items-center justify-center">'
                + '<svg class="h-5 w-5 text-brand-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>'
                + '</div>'
                + '<div class="flex-1 min-w-0">'
                + '<p class="text-sm font-bold text-ink-900">' + LP.esc(r.name) + '</p>'
                + '<p class="mt-0.5 text-xs text-gray-500 truncate">' + LP.esc(r.address || 'Alamat belum diisi') + '</p>'
                + '<p class="mt-1.5 text-xs font-bold text-brand-600">' + LP.number(r.units_available) + ' unit tersedia</p>'
                + '</div></div>'
                + '<div class="mt-4 flex flex-wrap gap-2">'
                + '<a href="/unit?proyek=' + encodeURIComponent(r.name) + '" class="rounded-xl border border-cream-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-brand-500 hover:text-brand-700 transition-colors">Lihat unit</a>'
                + '<a href="' + LP.esc(ruteGoogle(r)) + '" target="_blank" rel="noopener" class="rounded-xl bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 transition-colors">Rute &rarr;</a>'
                + '</div></div>';
        }).join('');
    }

    function pilihProyekPeta(r) {
        proyekTerpilih = r;
        const tombol = document.getElementById('beranda-rute-saya');
        const buka   = document.getElementById('beranda-rute-buka');
        if (tombol) tombol.textContent = 'Rute dari lokasi saya ke ' + r.name;
        if (buka)   buka.href = ruteGoogle(r);
    }

    function ruteInfo(pesan) {
        const el = document.getElementById('beranda-rute-info');
        if (el) el.textContent = pesan;
    }

    function posisiPengguna() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) { reject(new Error('Peramban ini tidak mendukung pengambilan lokasi.')); return; }
            navigator.geolocation.getCurrentPosition(
                function (pos) { resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }); },
                function () { reject(new Error('Izin lokasi tidak diberikan.')); },
                { enableHighAccuracy: true, timeout: 12000 });
        });
    }

    async function gambarRute() {
        if (!petaBeranda || !proyekTerpilih) return;
        ruteInfo('Mengambil lokasi Anda...');
        try {
            const asal   = await posisiPengguna();
            const tautan = LAYANAN_RUTE + asal.longitude + ',' + asal.latitude + ';'
                + proyekTerpilih.longitude + ',' + proyekTerpilih.latitude + '?overview=full&geometries=geojson';
            const jawaban = await fetch(tautan);
            const data    = await jawaban.json();
            const rute    = data && data.routes ? data.routes[0] : null;
            if (!rute) throw new Error('Rute berkendara tidak ditemukan.');

            if (lapisanRute) petaBeranda.removeLayer(lapisanRute);
            lapisanRute = L.geoJSON(rute.geometry, { style: { color: '#2f7d5b', weight: 5, opacity: 0.8 } }).addTo(petaBeranda);

            if (titikSaya) petaBeranda.removeLayer(titikSaya);
            titikSaya = L.circleMarker([asal.latitude, asal.longitude], {
                radius: 7, color: '#123024', fillColor: '#2f7d5b', fillOpacity: 0.9,
            }).addTo(petaBeranda).bindPopup('Posisi Anda');

            petaBeranda.fitBounds(lapisanRute.getBounds().pad(0.15), { maxZoom: ZOOM_JALAN });
            ruteInfo('Rute ke ' + proyekTerpilih.name + ': ' + (rute.distance / 1000).toFixed(1)
                + ' km, estimasi ' + Math.max(1, Math.round(rute.duration / 60)) + ' menit.');
        } catch (e) {
            ruteInfo((e && e.message ? e.message : 'Rute gagal dibuat.') + ' Gunakan tombol Google Maps di samping.');
        }
    }

    async function loadMap() {
        const mount = document.getElementById('beranda-map');
        LP.loading(mount, 'Memuat peta lokasi proyek...');
        try {
            const rows = (await LP.fetchJson('/public/project-locations', ANON)) || [];
            if (!rows.length) {
                mount.innerHTML = '<div class="flex h-full items-center justify-center p-8 text-center text-sm text-gray-500">'
                    + 'Belum ada proyek dengan koordinat lokasi.</div>';
                document.getElementById('beranda-map-list').innerHTML = '';
                document.getElementById('beranda-rute').innerHTML = '';
                return;
            }

            await muatLeaflet();
            mount.innerHTML = '';

            petaBeranda = L.map(mount, { scrollWheelZoom: false, maxZoom: 19 });
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; kontributor OpenStreetMap',
            }).addTo(petaBeranda);

            const penanda = rows.map(function (r) {
                const marker = L.marker([r.latitude, r.longitude]).addTo(petaBeranda).bindPopup(popupProyek(r));
                marker.on('click', function () {
                    pilihProyekPeta(r);
                    petaBeranda.flyTo([r.latitude, r.longitude], ZOOM_JALAN);
                });
                return marker;
            });

            if (penanda.length === 1) {
                petaBeranda.setView([rows[0].latitude, rows[0].longitude], 15);
            } else {
                petaBeranda.fitBounds(L.featureGroup(penanda).getBounds().pad(0.3), { maxZoom: ZOOM_JALAN });
            }

            document.getElementById('beranda-rute').innerHTML =
                '<button id="beranda-rute-saya" type="button" class="inline-flex items-center gap-2 rounded-2xl border border-brand-500 bg-white px-5 py-2.5 text-sm font-bold text-brand-700 shadow-sm transition hover:bg-brand-50 hover:-translate-y-0.5">'
                + '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>'
                + 'Gambar rute dari lokasi saya</button>'
                + '<a id="beranda-rute-buka" href="#" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-2xl bg-ink-900 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-ink-800 hover:-translate-y-0.5" style="background:#0d0f0e">'
                + '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/></svg>'
                + 'Buka di Google Maps</a>'
                + '<p id="beranda-rute-info" class="text-sm text-gray-500 self-center">Klik penanda pada peta untuk memperbesar.</p>';

            document.getElementById('beranda-rute-saya').addEventListener('click', function (event) {
                LP.withBusy(event.currentTarget, gambarRute);
            });
            pilihProyekPeta(rows[0]);
            petaInfo(rows);
        } catch (e) {
            LP.errorBox(mount, e.message || 'Peta lokasi gagal dimuat.');
        }
    }

    // ── Settings & init ────────────────────────────────────
    function terapkanTampilan(nilai) {
        if (nilai.public_hero_eyebrow) document.getElementById('hero-eyebrow').innerHTML =
            '<span class="stat-dot"></span>' + nilai.public_hero_eyebrow;
        if (nilai.public_hero_title)   document.getElementById('hero-title').textContent   = nilai.public_hero_title;
        if (nilai.public_hero_tagline) document.getElementById('hero-tagline').textContent = nilai.public_hero_tagline;

        if (nilai.public_hero_image_url) {
            const bg = document.getElementById('hero-bg');
            bg.src = nilai.public_hero_image_url;
            bg.classList.remove('hidden');
        }

        const aktif = {};
        ['unggulan', 'skema', 'peta', 'news', 'gallery', 'sales'].forEach(function (nama) {
            const node = document.querySelector('[data-seksi="' + nama + '"]');
            if (!node) return;
            const nyala = String(nilai['public_show_' + nama] === '0' ? '0' : '1') === '1';
            node.classList.toggle('hidden', !nyala);
            aktif[nama] = nyala;
        });
        return aktif;
    }

    LP.fillSettings().then(function (nilai) {
        const aktif = terapkanTampilan(nilai || {});
        if (aktif.unggulan) loadUnits();
        if (aktif.skema)    loadSchemes();
        if (aktif.peta)     loadMap();
        if (aktif.news || aktif.gallery) loadNewsAndGallery(aktif);
        if (aktif.sales)    loadSales();
    }).catch(function () {
        loadUnits();
        loadSchemes();
        loadMap();
        loadNewsAndGallery({ news: true, gallery: true });
        loadSales();
    });
</script>
@endpush
