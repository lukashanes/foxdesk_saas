<?php
/**
 * Public FoxDesk Cloud website.
 *
 * This is the public FoxDesk Cloud surface. Operational/customer data is
 * intentionally kept out of this page; it belongs behind platform admin login.
 */

$page_title = 'FoxDesk Cloud';
$cloud_regular_price = billing_format_money(billing_cloud_base_price_cents());
$cloud_launch_price = billing_currency() === 'CZK' ? '249 Kc' : 'EUR 9.90';
$cloud_launch_until = 'May 31, 2026';
$included_storage = billing_included_storage_bytes() === 1073741824
    ? '1 GB'
    : preg_replace('/\.00\s+/', ' ', format_file_size(billing_included_storage_bytes()));
?>
<!DOCTYPE html>
<html lang="en" class="selection:bg-[#2563eb]/30 selection:text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> - Managed helpdesk hosting</title>
    <meta name="description" content="FoxDesk Cloud is managed hosting for FoxDesk. Unlimited users, clients, agents, and tickets with simple storage-based scaling.">
    <link rel="icon" type="image/png" href="assets/public/logo.png">
    <link rel="stylesheet" href="tailwind.min.css">
    <script>
        (function () {
            var saved = localStorage.getItem('foxdesk-cloud-theme');
            var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        @font-face {
            font-family: Inter;
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url(assets/fonts/inter-latin.woff2) format("woff2");
        }
        :root {
            --fd-blue: #2563eb;
            --fd-blue-dark: #3243bd;
            --fd-ink: #111827;
            --fd-muted: #4b5563;
            --fd-line: #e5e7eb;
            --fd-bg: #f9fafb;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            margin: 0;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--fd-bg);
            color: var(--fd-ink);
        }
        .fd-glow-top {
            position: fixed;
            inset: -240px auto auto 50%;
            width: 740px;
            height: 740px;
            transform: translateX(-50%);
            background: radial-gradient(circle, rgba(37, 99, 235, .14), transparent 62%);
            pointer-events: none;
            z-index: -1;
        }
        .fd-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            border-bottom: 1px solid rgba(229, 231, 235, .9);
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(16px);
        }
        .fd-header-inner {
            max-width: 1280px;
            height: 64px;
            margin: 0 auto;
            padding: 0 clamp(20px, 4vw, 44px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }
        .fd-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #111827;
            text-decoration: none;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .fd-brand img {
            width: 32px;
            height: 32px;
            border-radius: 8px;
        }
        .fd-nav {
            display: flex;
            align-items: center;
            gap: 22px;
        }
        .fd-nav a {
            color: #4b5563;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .fd-nav a:hover {
            color: #111827;
        }
        .fd-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 750;
            text-decoration: none;
            transition: .18s ease;
        }
        .fd-btn.primary {
            background: var(--fd-blue);
            color: #fff;
            box-shadow: 0 0 30px rgba(37, 99, 235, .24);
        }
        .fd-btn.primary:hover {
            background: var(--fd-blue-dark);
            box-shadow: 0 0 40px rgba(37, 99, 235, .34);
            transform: translateY(-1px);
        }
        .fd-btn.secondary {
            border: 1px solid var(--fd-line);
            background: #fff;
            color: #111827;
        }
        .fd-main {
            padding-top: 112px;
        }
        .fd-section {
            max-width: 1280px;
            margin: 0 auto;
            padding-left: clamp(20px, 4vw, 44px);
            padding-right: clamp(20px, 4vw, 44px);
        }
        .fd-hero {
            display: grid;
            grid-template-columns: minmax(0, .92fr) minmax(560px, 1.08fr);
            gap: 52px;
            align-items: center;
            padding: 48px 0 84px;
        }
        .fd-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid rgba(37, 99, 235, .2);
            background: rgba(37, 99, 235, .08);
            color: #2563eb;
            font-size: 14px;
            font-weight: 700;
        }
        .fd-hero-copy {
            min-width: 0;
        }
        .fd-pulse {
            width: 8px;
            height: 8px;
            border-radius: 99px;
            background: #60a5fa;
            box-shadow: 0 0 0 6px rgba(96, 165, 250, .16);
        }
        .fd-hero h1 {
            max-width: 720px;
            margin: 0;
            color: #111827;
            font-size: clamp(3rem, 5.4vw, 5.35rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
            font-weight: 900;
        }
        .fd-hero h1 span {
            color: var(--fd-blue);
        }
        .fd-hero p {
            max-width: 620px;
            margin: 24px 0 0;
            color: #4b5563;
            font-size: clamp(1.05rem, 1.8vw, 1.24rem);
            line-height: 1.7;
        }
        .fd-hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            gap: 14px;
            margin-top: 32px;
        }
        .fd-hero-proof {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            max-width: 620px;
            margin-top: 30px;
        }
        .fd-proof-item {
            padding: 14px;
            border: 1px solid rgba(229, 231, 235, .92);
            border-radius: 16px;
            background: rgba(255, 255, 255, .76);
        }
        .fd-proof-item strong {
            display: block;
            color: #111827;
            font-size: 16px;
            font-weight: 850;
        }
        .fd-proof-item span {
            display: block;
            margin-top: 4px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.45;
        }
        .fd-hero-product {
            min-width: 0;
            position: relative;
        }
        .fd-product-frame {
            position: relative;
            border: 1px solid rgba(229, 231, 235, .95);
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 34px 90px rgba(17, 24, 39, .16);
            overflow: hidden;
        }
        .fd-product-toolbar {
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 16px;
            border-bottom: 1px solid rgba(229, 231, 235, .9);
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .fd-window-dots {
            display: flex;
            gap: 7px;
        }
        .fd-window-dots span {
            width: 10px;
            height: 10px;
            border-radius: 99px;
            background: #d1d5db;
        }
        .fd-window-dots span:first-child {
            background: #f87171;
        }
        .fd-window-dots span:nth-child(2) {
            background: #fbbf24;
        }
        .fd-window-dots span:nth-child(3) {
            background: #34d399;
        }
        .fd-product-url {
            flex: 1;
            max-width: 420px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #64748b;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        .fd-product-frame img {
            display: block;
            width: 100%;
            height: auto;
        }
        .fd-floating-card {
            position: absolute;
            right: 24px;
            bottom: -24px;
            width: 260px;
            padding: 18px;
            border: 1px solid rgba(229, 231, 235, .95);
            border-radius: 20px;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 24px 60px rgba(17, 24, 39, .16);
            backdrop-filter: blur(14px);
        }
        .fd-floating-card span {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 750;
        }
        .fd-floating-card strong {
            display: block;
            margin-top: 4px;
            color: #111827;
            font-size: 28px;
            line-height: 1.1;
            letter-spacing: -0.04em;
        }
        .fd-floating-card p {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }
        .fd-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }
        .fd-card {
            border: 1px solid var(--fd-line);
            border-radius: 24px;
            background: rgba(255, 255, 255, .82);
            padding: 28px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .5);
        }
        .fd-card h3 {
            margin: 0 0 10px;
            color: #111827;
            font-size: 20px;
            font-weight: 850;
            letter-spacing: -0.02em;
        }
        .fd-card p {
            margin: 0;
            color: #4b5563;
            line-height: 1.65;
        }
        .fd-heading {
            max-width: 760px;
            margin: 0 auto 36px;
            text-align: center;
        }
        .fd-heading h2 {
            margin: 0;
            color: #111827;
            font-size: clamp(2.2rem, 4vw, 3.7rem);
            line-height: 1.08;
            letter-spacing: -0.04em;
            font-weight: 900;
        }
        .fd-heading p {
            margin: 16px 0 0;
            color: #4b5563;
            font-size: 18px;
            line-height: 1.65;
        }
        .fd-band {
            padding: 72px 0;
        }
        .fd-pricing {
            display: grid;
            grid-template-columns: minmax(360px, .82fr) minmax(0, 1.18fr);
            gap: 22px;
            align-items: start;
        }
        .fd-price-card {
            position: relative;
            border: 2px solid rgba(37, 99, 235, .22);
            border-radius: 22px;
            background: #fff;
            padding: 26px;
            box-shadow: 0 25px 60px rgba(37, 99, 235, .12);
        }
        .fd-price-card h3 {
            margin: 0;
            font-size: 26px;
            font-weight: 850;
        }
        .fd-price {
            display: flex;
            align-items: baseline;
            gap: 10px;
            margin: 14px 0 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--fd-line);
        }
        .fd-price strong {
            font-size: 48px;
            line-height: 1;
            letter-spacing: -0.05em;
        }
        .fd-price span {
            color: #6b7280;
            font-weight: 700;
        }
        .fd-old-price {
            color: #9ca3af;
            font-size: 18px;
            font-weight: 800;
            text-decoration: line-through;
        }
        .fd-price-note {
            display: inline-flex;
            margin-bottom: 18px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 850;
        }
        .fd-list {
            display: grid;
            gap: 10px;
            margin: 0 0 20px;
            padding: 0;
            list-style: none;
        }
        .fd-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #374151;
            line-height: 1.5;
        }
        .fd-check {
            flex: 0 0 auto;
            width: 21px;
            height: 21px;
            border-radius: 99px;
            display: grid;
            place-items: center;
            background: rgba(37, 99, 235, .12);
            color: var(--fd-blue);
            font-size: 13px;
            font-weight: 900;
        }
        .fd-soon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 800;
        }
        .fd-preview-stack {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            align-items: start;
        }
        .fd-preview-stack img {
            width: 100%;
            border-radius: 0;
            border: 1px solid var(--fd-line);
            background: #fff;
            padding: 8px;
            box-shadow: 0 14px 34px rgba(17, 24, 39, .08);
        }
        .fd-migration {
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            gap: 28px;
            align-items: start;
            padding: 36px;
            border: 1px solid rgba(37, 99, 235, .18);
            border-radius: 28px;
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            box-shadow: 0 24px 70px rgba(17, 24, 39, .08);
        }
        .fd-migration h2 {
            margin: 0;
            color: #111827;
            font-size: clamp(2rem, 4vw, 3.15rem);
            line-height: 1.08;
            letter-spacing: -0.04em;
            font-weight: 900;
        }
        .fd-migration p {
            margin: 16px 0 0;
            color: #4b5563;
            font-size: 17px;
            line-height: 1.7;
        }
        .fd-steps {
            display: grid;
            gap: 12px;
        }
        .fd-step {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: 14px;
            padding: 16px;
            border: 1px solid var(--fd-line);
            border-radius: 18px;
            background: #fff;
        }
        .fd-step-number {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(37, 99, 235, .1);
            color: #2563eb;
            font-weight: 900;
        }
        .fd-step strong {
            display: block;
            color: #111827;
            font-size: 15px;
            font-weight: 850;
        }
        .fd-step span {
            display: block;
            margin-top: 4px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.55;
        }
        .fd-footer {
            border-top: 1px solid var(--fd-line);
            background: #fff;
            padding: 42px 0;
            color: #6b7280;
            font-size: 14px;
        }
        .fd-footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding-left: clamp(20px, 4vw, 44px);
            padding-right: clamp(20px, 4vw, 44px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        @media (max-width: 920px) {
            .fd-nav {
                display: none;
            }
            .fd-grid,
            .fd-pricing,
            .fd-migration {
                grid-template-columns: 1fr;
            }
            .fd-preview-stack {
                grid-template-columns: 1fr;
            }
            .fd-main {
                padding-top: 86px;
            }
            .fd-hero {
                grid-template-columns: 1fr;
                gap: 34px;
                padding-top: 36px;
                text-align: left;
            }
            .fd-hero-proof {
                grid-template-columns: 1fr;
            }
            .fd-floating-card {
                position: static;
                width: auto;
                margin-top: 14px;
            }
            .fd-footer-inner {
                align-items: flex-start;
                flex-direction: column;
            }
        }
        @media (max-width: 560px) {
            .fd-header-inner,
            .fd-section,
            .fd-footer-inner {
                padding-left: 16px;
                padding-right: 16px;
            }
            .fd-header-inner {
                height: auto;
                min-height: 68px;
            }
            .fd-brand span {
                font-size: 18px;
            }
            .fd-btn {
                min-height: 40px;
                padding: 0 13px;
            }
            .fd-hero-actions {
                align-items: stretch;
                flex-direction: column;
            }
            .fd-card,
            .fd-price-card {
                border-radius: 20px;
                padding: 22px;
            }
        }
        [data-theme="dark"] body { background: #030712; color: #f8fafc; }
        [data-theme="dark"] .fd-header { background: rgba(3, 7, 18, .78); border-color: rgba(255,255,255,.08); }
        [data-theme="dark"] .fd-brand,
        [data-theme="dark"] .fd-nav a:hover,
        [data-theme="dark"] .fd-hero h1,
        [data-theme="dark"] .fd-proof-item strong,
        [data-theme="dark"] .fd-floating-card strong,
        [data-theme="dark"] .fd-card h3,
        [data-theme="dark"] .fd-heading h2,
        [data-theme="dark"] .fd-feature-copy h3,
        [data-theme="dark"] .fd-feature-list strong,
        [data-theme="dark"] .fd-capability h4,
        [data-theme="dark"] .fd-price-card h3,
        [data-theme="dark"] .fd-migration h2,
        [data-theme="dark"] .fd-step strong { color: #f8fafc; }
        [data-theme="dark"] .fd-nav a,
        [data-theme="dark"] .fd-hero p,
        [data-theme="dark"] .fd-proof-item span,
        [data-theme="dark"] .fd-floating-card span,
        [data-theme="dark"] .fd-floating-card p,
        [data-theme="dark"] .fd-card p,
        [data-theme="dark"] .fd-heading p,
        [data-theme="dark"] .fd-feature-copy p,
        [data-theme="dark"] .fd-feature-list li,
        [data-theme="dark"] .fd-capability p,
        [data-theme="dark"] .fd-list li,
        [data-theme="dark"] .fd-migration p,
        [data-theme="dark"] .fd-step span,
        [data-theme="dark"] .fd-footer { color: #94a3b8; }
        [data-theme="dark"] .fd-btn.secondary,
        [data-theme="dark"] .fd-theme-toggle,
        [data-theme="dark"] .fd-product-frame,
        [data-theme="dark"] .fd-card,
        [data-theme="dark"] .fd-proof-item,
        [data-theme="dark"] .fd-floating-card,
        [data-theme="dark"] .fd-feature-media,
        [data-theme="dark"] .fd-capability,
        [data-theme="dark"] .fd-price-card,
        [data-theme="dark"] .fd-step,
        [data-theme="dark"] .fd-footer { background: rgba(15, 23, 42, .82); border-color: rgba(255,255,255,.10); color: #f8fafc; }
        [data-theme="dark"] .fd-product-toolbar { background: linear-gradient(180deg, #111827, #0f172a); border-color: rgba(255,255,255,.10); }
        [data-theme="dark"] .fd-product-url { background: rgba(255,255,255,.06); color: #94a3b8; }
        [data-theme="dark"] .fd-migration { background: linear-gradient(180deg, rgba(15,23,42,.92), rgba(2,6,23,.88)); border-color: rgba(37,99,235,.28); }
        .fd-dark-img { display: none !important; }
        [data-theme="dark"] .fd-light-img { display: none !important; }
        [data-theme="dark"] .fd-dark-img { display: block !important; }
        .fd-theme-toggle {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 1px solid var(--fd-line);
            background: #fff;
            color: #111827;
            display: inline-grid;
            place-items: center;
            font-weight: 900;
            cursor: pointer;
        }
        .fd-feature-section {
            display: grid;
            grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
            gap: 56px;
            align-items: center;
            padding: 72px 0;
        }
        .fd-feature-section.reverse .fd-feature-copy { order: 2; }
        .fd-feature-section.reverse .fd-feature-media { order: 1; }
        .fd-feature-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            border: 1px solid rgba(37, 99, 235, .22);
            background: rgba(37, 99, 235, .10);
            color: #2563eb;
            display: grid;
            place-items: center;
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 22px;
        }
        .fd-feature-copy h3 {
            margin: 0;
            color: #111827;
            font-size: clamp(2rem, 4vw, 3.25rem);
            line-height: 1.08;
            letter-spacing: -0.04em;
            font-weight: 900;
        }
        .fd-feature-copy p {
            margin: 18px 0 0;
            color: #4b5563;
            font-size: 18px;
            line-height: 1.7;
        }
        .fd-feature-list {
            display: grid;
            gap: 13px;
            margin: 24px 0 0;
            padding: 0;
            list-style: none;
        }
        .fd-feature-list li {
            display: flex;
            gap: 12px;
            color: #4b5563;
            line-height: 1.55;
        }
        .fd-feature-list strong { color: #111827; }
        .fd-feature-media {
            border: 1px solid var(--fd-line);
            border-radius: 18px;
            overflow: visible;
            padding: 10px;
            background: #fff;
            box-shadow: 0 28px 70px rgba(17, 24, 39, .16);
        }
        .fd-feature-media img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 0;
        }
        .fd-capability-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }
        .fd-capability {
            min-height: 184px;
            padding: 22px;
            border: 1px solid var(--fd-line);
            border-radius: 22px;
            background: rgba(255,255,255,.84);
        }
        .fd-capability span {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            margin-bottom: 18px;
            border-radius: 12px;
            background: rgba(37, 99, 235, .10);
            color: #2563eb;
            font-weight: 900;
        }
        .fd-capability h4 { margin: 0 0 8px; color: #111827; font-size: 17px; font-weight: 850; }
        .fd-capability p { margin: 0; color: #4b5563; font-size: 14px; line-height: 1.6; }
        @media (max-width: 920px) {
            .fd-feature-section,
            .fd-feature-section.reverse { grid-template-columns: 1fr; }
            .fd-feature-section.reverse .fd-feature-copy,
            .fd-feature-section.reverse .fd-feature-media { order: initial; }
            .fd-capability-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .fd-capability-grid { grid-template-columns: 1fr; }
            .fd-feature-section { padding: 48px 0; gap: 28px; }
        }

        /* FoxDesk.org-inspired glass polish */
        body {
            background:
                radial-gradient(circle at 50% -8%, rgba(37, 99, 235, .16), transparent 34rem),
                radial-gradient(circle at 86% 18%, rgba(96, 165, 250, .10), transparent 28rem),
                linear-gradient(180deg, #f9fafb 0%, #f8fafc 44%, #eef4ff 100%);
        }
        [data-theme="dark"] body {
            background:
                radial-gradient(circle at 50% -10%, rgba(37, 99, 235, .22), transparent 36rem),
                radial-gradient(circle at 85% 18%, rgba(96, 165, 250, .12), transparent 28rem),
                linear-gradient(180deg, #030712 0%, #050b18 56%, #07101f 100%);
        }
        .fd-glow-top {
            width: 980px;
            height: 980px;
            background: radial-gradient(circle, rgba(37, 99, 235, .18), rgba(96, 165, 250, .08) 34%, transparent 64%);
            filter: blur(4px);
        }
        .fd-header {
            background: rgba(255, 255, 255, .68);
            box-shadow: 0 1px 0 rgba(255, 255, 255, .7) inset;
        }
        [data-theme="dark"] .fd-header {
            background: rgba(3, 7, 18, .58);
            box-shadow: 0 1px 0 rgba(255, 255, 255, .06) inset;
        }
        .fd-btn,
        .fd-theme-toggle,
        .fd-pill,
        .fd-proof-item,
        .fd-card,
        .fd-product-frame,
        .fd-floating-card,
        .fd-feature-media,
        .fd-capability,
        .fd-price-card,
        .fd-migration,
        .fd-step {
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }
        .fd-btn.primary {
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 18px 45px rgba(37, 99, 235, .26), inset 0 1px 0 rgba(255,255,255,.22);
        }
        .fd-btn.secondary,
        .fd-theme-toggle {
            background: rgba(255, 255, 255, .62);
            border-color: rgba(17, 24, 39, .09);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.74);
        }
        .fd-hero {
            padding-top: 58px;
        }
        .fd-hero h1 {
            max-width: 820px;
            font-size: clamp(3rem, 5.15vw, 5.25rem);
            letter-spacing: -0.032em;
        }
        .fd-hero p {
            color: #5f6b7c;
        }
        .fd-pill {
            background: rgba(37, 99, 235, .08);
            border-color: rgba(37, 99, 235, .20);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.72), 0 10px 30px rgba(37,99,235,.08);
        }
        .fd-proof-item,
        .fd-card,
        .fd-capability {
            background: rgba(255, 255, 255, .56);
            border-color: rgba(17, 24, 39, .06);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.82), 0 20px 50px rgba(17,24,39,.045);
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .fd-proof-item:hover,
        .fd-card:hover,
        .fd-capability:hover {
            transform: translateY(-4px);
            border-color: rgba(37, 99, 235, .20);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.9), 0 28px 70px rgba(37,99,235,.10);
        }
        .fd-product-frame,
        .fd-feature-media {
            background: rgba(255, 255, 255, .64);
            border-color: rgba(17, 24, 39, .08);
            box-shadow: 0 34px 90px rgba(37, 99, 235, .12), 0 16px 50px rgba(17, 24, 39, .10);
        }
        .fd-product-toolbar {
            background: rgba(255,255,255,.58);
            border-bottom-color: rgba(17, 24, 39, .06);
        }
        .fd-product-url {
            background: rgba(241, 245, 249, .72);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.74);
        }
        .fd-floating-card {
            background: rgba(255, 255, 255, .72);
            border-color: rgba(17, 24, 39, .08);
            box-shadow: 0 24px 70px rgba(17, 24, 39, .14), inset 0 1px 0 rgba(255,255,255,.82);
        }
        .fd-heading {
            margin-bottom: 42px;
        }
        .fd-heading h2,
        .fd-feature-copy h3,
        .fd-migration h2 {
            letter-spacing: -0.055em;
        }
        .fd-feature-icon,
        .fd-capability span,
        .fd-step-number {
            background: linear-gradient(135deg, rgba(37,99,235,.16), rgba(37,99,235,.045));
            border: 1px solid rgba(37,99,235,.18);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.75);
        }
        .fd-price-card,
        .fd-migration {
            background: rgba(255, 255, 255, .66);
            border-color: rgba(37, 99, 235, .18);
            box-shadow: 0 34px 90px rgba(37, 99, 235, .10), inset 0 1px 0 rgba(255,255,255,.78);
        }
        .fd-step {
            background: rgba(255,255,255,.58);
            border-color: rgba(17,24,39,.06);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.74);
        }
        [data-theme="dark"] .fd-btn.secondary,
        [data-theme="dark"] .fd-theme-toggle,
        [data-theme="dark"] .fd-proof-item,
        [data-theme="dark"] .fd-card,
        [data-theme="dark"] .fd-capability,
        [data-theme="dark"] .fd-product-frame,
        [data-theme="dark"] .fd-feature-media,
        [data-theme="dark"] .fd-floating-card,
        [data-theme="dark"] .fd-price-card,
        [data-theme="dark"] .fd-migration,
        [data-theme="dark"] .fd-step {
            background: rgba(15, 23, 42, .54);
            border-color: rgba(255,255,255,.09);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.06), 0 28px 80px rgba(0,0,0,.22);
        }
        [data-theme="dark"] .fd-product-toolbar {
            background: rgba(15, 23, 42, .70);
            border-bottom-color: rgba(255,255,255,.08);
        }
        [data-theme="dark"] .fd-product-url {
            background: rgba(255,255,255,.06);
        }
        [data-theme="dark"] .fd-hero p,
        [data-theme="dark"] .fd-feature-copy p,
        [data-theme="dark"] .fd-heading p,
        [data-theme="dark"] .fd-card p,
        [data-theme="dark"] .fd-capability p {
            color: #a8b3c5;
        }
        @media (max-width: 920px) {
            .fd-hero {
                padding-top: 36px;
                padding-bottom: 64px;
            }
            .fd-hero h1 {
                max-width: 12ch;
                font-size: clamp(3.4rem, 9.2vw, 5.1rem);
                line-height: 1.02;
                letter-spacing: -0.03em;
            }
            .fd-hero p {
                max-width: 720px;
                font-size: clamp(1.1rem, 3.1vw, 1.45rem);
            }
        }

        @media (min-width: 921px) and (max-width: 1280px) {
            .fd-hero {
                grid-template-columns: minmax(0, 1fr);
                gap: 38px;
            }
            .fd-hero h1 {
                max-width: 13ch;
                font-size: clamp(4.2rem, 7.2vw, 5.35rem);
                line-height: 1.03;
            }
            .fd-hero p,
            .fd-hero-proof {
                max-width: 860px;
            }
        }

        /* Liquid/glass design pass based on material hierarchy: content layer + elevated translucent controls. */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -2;
            pointer-events: none;
            background:
                linear-gradient(115deg, transparent 0 18%, rgba(37,99,235,.13) 34%, transparent 52%),
                linear-gradient(245deg, transparent 0 22%, rgba(125,211,252,.14) 40%, transparent 62%);
            opacity: .9;
        }
        [data-theme="dark"] body::before {
            background:
                linear-gradient(115deg, transparent 0 18%, rgba(37,99,235,.18) 34%, transparent 56%),
                linear-gradient(245deg, transparent 0 26%, rgba(14,165,233,.12) 42%, transparent 66%);
            opacity: 1;
        }
        .fd-header,
        .fd-proof-item,
        .fd-card,
        .fd-product-frame,
        .fd-floating-card,
        .fd-feature-media,
        .fd-capability,
        .fd-price-card,
        .fd-migration,
        .fd-step {
            position: relative;
            overflow: hidden;
        }
        .fd-proof-item::before,
        .fd-card::before,
        .fd-product-frame::before,
        .fd-floating-card::before,
        .fd-feature-media::before,
        .fd-capability::before,
        .fd-price-card::before,
        .fd-migration::before,
        .fd-step::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            border-radius: inherit;
            background:
                linear-gradient(135deg, rgba(255,255,255,.72), rgba(255,255,255,.18) 34%, rgba(255,255,255,0) 58%),
                linear-gradient(315deg, rgba(37,99,235,.08), rgba(255,255,255,0) 46%);
            opacity: .72;
            mix-blend-mode: soft-light;
        }
        [data-theme="dark"] .fd-proof-item::before,
        [data-theme="dark"] .fd-card::before,
        [data-theme="dark"] .fd-product-frame::before,
        [data-theme="dark"] .fd-floating-card::before,
        [data-theme="dark"] .fd-feature-media::before,
        [data-theme="dark"] .fd-capability::before,
        [data-theme="dark"] .fd-price-card::before,
        [data-theme="dark"] .fd-migration::before,
        [data-theme="dark"] .fd-step::before {
            background:
                linear-gradient(135deg, rgba(255,255,255,.18), rgba(255,255,255,.05) 34%, rgba(255,255,255,0) 58%),
                linear-gradient(315deg, rgba(37,99,235,.16), rgba(255,255,255,0) 46%);
            opacity: .9;
        }
        .fd-proof-item > *,
        .fd-card > *,
        .fd-product-frame > *,
        .fd-floating-card > *,
        .fd-feature-media > *,
        .fd-capability > *,
        .fd-price-card > *,
        .fd-migration > *,
        .fd-step > * {
            position: relative;
            z-index: 1;
        }
        .fd-proof-item,
        .fd-card,
        .fd-capability,
        .fd-step {
            background: rgba(255,255,255,.46);
            border: 1px solid rgba(255,255,255,.66);
            box-shadow:
                0 18px 55px rgba(15,23,42,.07),
                inset 0 1px 0 rgba(255,255,255,.88),
                inset 0 -1px 0 rgba(255,255,255,.28);
        }
        .fd-product-frame,
        .fd-feature-media,
        .fd-price-card,
        .fd-migration {
            background: rgba(255,255,255,.52);
            border: 1px solid rgba(255,255,255,.72);
            box-shadow:
                0 34px 100px rgba(37,99,235,.14),
                0 12px 36px rgba(15,23,42,.08),
                inset 0 1px 0 rgba(255,255,255,.92),
                inset 0 -1px 0 rgba(255,255,255,.34);
        }
        .fd-floating-card,
        .fd-header {
            background: rgba(255,255,255,.58);
            border-color: rgba(255,255,255,.72);
            box-shadow:
                0 18px 60px rgba(15,23,42,.10),
                inset 0 1px 0 rgba(255,255,255,.88);
        }
        [data-theme="dark"] .fd-proof-item,
        [data-theme="dark"] .fd-card,
        [data-theme="dark"] .fd-capability,
        [data-theme="dark"] .fd-step {
            background: rgba(15,23,42,.42);
            border-color: rgba(255,255,255,.12);
            box-shadow:
                0 22px 70px rgba(0,0,0,.26),
                inset 0 1px 0 rgba(255,255,255,.09),
                inset 0 -1px 0 rgba(255,255,255,.03);
        }
        [data-theme="dark"] .fd-product-frame,
        [data-theme="dark"] .fd-feature-media,
        [data-theme="dark"] .fd-price-card,
        [data-theme="dark"] .fd-migration,
        [data-theme="dark"] .fd-floating-card {
            background: rgba(15,23,42,.50);
            border-color: rgba(255,255,255,.13);
            box-shadow:
                0 34px 100px rgba(0,0,0,.34),
                inset 0 1px 0 rgba(255,255,255,.10),
                inset 0 -1px 0 rgba(255,255,255,.03);
        }
        .fd-price-card {
            display: flex;
            flex-direction: column;
        }
        .fd-price-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }
        .fd-offer-strip {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            padding: 7px 9px;
            border-radius: 12px;
            background: rgba(255,255,255,.58);
            border: 1px solid rgba(37,99,235,.14);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
        }
        .fd-offer-strip span {
            color: #2563eb;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .fd-offer-strip strong {
            color: #0f172a;
            font-size: 14px;
            font-weight: 900;
        }
        .fd-offer-strip s {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 750;
            text-decoration-thickness: 2px;
        }
        .fd-discount-chip {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(37,99,235,.12);
            color: #1d4ed8;
            border: 1px solid rgba(37,99,235,.18);
            font-size: 12px;
            font-weight: 900;
        }
        .fd-price {
            margin-top: 0;
        }
        [data-theme="dark"] .fd-offer-strip {
            background: rgba(15,23,42,.54);
            border-color: rgba(96,165,250,.18);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
        }
        [data-theme="dark"] .fd-offer-strip strong {
            color: #f8fafc;
        }
        [data-theme="dark"] .fd-discount-chip {
            background: rgba(37,99,235,.20);
            color: #bfdbfe;
            border-color: rgba(96,165,250,.22);
        }
        @media (max-width: 560px) {
            .fd-price-top {
                flex-direction: column;
            }
        }
        @media (max-width: 560px) {
            .fd-header-inner {
                gap: 10px;
                min-height: 64px;
            }
            .fd-header-inner > .flex {
                flex: 0 0 auto;
                gap: 7px;
            }
            .fd-brand {
                gap: 8px;
                font-size: 18px;
                min-width: 0;
            }
            .fd-brand img {
                width: 30px;
                height: 30px;
            }
            .fd-btn {
                flex: 0 0 auto;
                min-height: 38px;
                padding: 0 11px;
                font-size: 13px;
                line-height: 1;
                white-space: nowrap;
            }
            .fd-theme-toggle {
                flex: 0 0 38px;
                width: 38px;
                height: 38px;
            }
        }
        @media (max-width: 380px) {
            .fd-brand span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="fd-glow-top"></div>

    <header class="fd-header">
        <div class="fd-header-inner">
            <a href="<?php echo e(url('cloud')); ?>" class="fd-brand">
                <picture>
                    <source srcset="assets/public/logo.webp" type="image/webp">
                    <img src="assets/public/logo.png" alt="FoxDesk">
                </picture>
                <span>FoxDesk</span>
            </a>
            <nav class="fd-nav" aria-label="Public navigation">
                <a href="#cloud">Cloud</a>
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#migration">Migration</a>
                <a href="<?php echo e(url('legal', ['type' => 'privacy'])); ?>">Privacy</a>
                <a href="https://foxdesk.org" target="_blank" rel="noopener">Open-source</a>
            </nav>
            <div class="flex items-center gap-3">
                <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">Client login</a>
                <a href="#pricing" class="fd-btn primary">View plan</a>
                <button type="button" class="fd-theme-toggle" onclick="toggleCloudTheme()" aria-label="Toggle color mode">◐</button>
            </div>
        </div>
    </header>

    <main class="fd-main">
        <section class="fd-section fd-hero" id="cloud">
            <div class="fd-hero-copy">
                <h1>Run customer support from one managed FoxDesk.</h1>
                <p>FoxDesk Cloud hosts your helpdesk, time tracking, clients, tickets, attachments, email delivery, and updates so your team can start working without managing PHP hosting.</p>
                <div class="fd-hero-actions">
                    <a href="#pricing" class="fd-btn primary">See Cloud plan</a>
                    <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">Sign in to app</a>
                </div>
                <div class="fd-hero-proof">
                    <div class="fd-proof-item"><strong>Unlimited</strong><span>Users, clients, agents, and tickets</span></div>
                    <div class="fd-proof-item"><strong><?php echo e($included_storage); ?></strong><span>Storage included at launch</span></div>
                    <div class="fd-proof-item"><strong>Managed</strong><span>Updates, storage, email, and backups</span></div>
                </div>
            </div>

            <div class="fd-hero-product" aria-label="FoxDesk Cloud product preview">
                <div class="fd-product-frame">
                    <div class="fd-product-toolbar">
                        <div class="fd-window-dots"><span></span><span></span><span></span></div>
                        <div class="fd-product-url">app.foxdesk.net / dashboard</div>
                    </div>
                    <img class="fd-light-img" src="assets/public/dashboard-light.webp" alt="FoxDesk dashboard preview">
                    <img class="fd-dark-img" src="assets/public/dashboard-dark.webp" alt="FoxDesk dashboard preview in dark mode">
                </div>
                <div class="fd-floating-card">
                    <span>Cloud workspace</span>
                    <strong>Ready in minutes</strong>
                    <p>Hosted app login, managed updates, reliable email delivery, and attachment storage.</p>
                </div>
            </div>
        </section>

        <section class="fd-section fd-band" id="features">
            <div class="fd-heading">
                <h2>Everything FoxDesk can do, managed for you.</h2>
                <p>The SaaS version keeps the full FoxDesk feature set and removes the hosting work: app stack, email, storage, updates, backups, and monitoring.</p>
            </div>
            <div class="fd-grid">
                <article class="fd-card">
                    <h3>Ready workspace</h3>
                    <p>Each customer gets their own FoxDesk workspace with isolated users, clients, tickets, reports, and files.</p>
                </article>
                <article class="fd-card">
                    <h3>Unlimited team</h3>
                    <p>No per-agent pricing. Invite admins, agents, clients, and collaborators without fighting seat limits.</p>
                </article>
                <article class="fd-card">
                    <h3>Managed operations</h3>
                    <p>The hosted version is prepared for managed updates, email delivery, attachment storage, backups, and monitoring.</p>
                </article>
            </div>
        </section>

        <section class="fd-section">
            <div class="fd-feature-section">
                <div class="fd-feature-copy">
                    <div class="fd-feature-icon">T</div>
                    <h3>Ticket lifecycle management.</h3>
                    <p>Run daily support from one workspace: ticket statuses, priorities, assignments, comments, attachments, client visibility, and shared public links.</p>
                    <ul class="fd-feature-list">
                        <li><span class="fd-check">✓</span><span><strong>Email piping:</strong> create and update tickets from support inboxes.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Granular notifications:</strong> keep teams and clients informed without noise.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Attachments:</strong> store and download files without managing hosting disk space yourself.</span></li>
                    </ul>
                </div>
                <div class="fd-feature-media">
                    <img class="fd-light-img" src="assets/public/ticket-detail-light.webp" alt="FoxDesk ticket detail">
                    <img class="fd-dark-img" src="assets/public/ticket-detail-dark.webp" alt="FoxDesk ticket detail in dark mode">
                </div>
            </div>

            <div class="fd-feature-section reverse">
                <div class="fd-feature-copy">
                    <div class="fd-feature-icon">⏱</div>
                    <h3>Time tracking and client reports.</h3>
                    <p>Track billable work directly on tickets, review time by client, and prepare reports without exporting support data into a second tool.</p>
                    <ul class="fd-feature-list">
                        <li><span class="fd-check">✓</span><span><strong>Work logs:</strong> manual entries, timers, summaries, and internal notes.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Reports:</strong> client reports, time totals, snapshots, and shareable links.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Quick timer controls:</strong> start, pause, resume, and stop from the app.</span></li>
                    </ul>
                </div>
                <div class="fd-feature-media">
                    <img class="fd-light-img" src="assets/public/time-report-light.webp" alt="FoxDesk time reporting">
                    <img class="fd-dark-img" src="assets/public/time-report-dark.webp" alt="FoxDesk time reporting in dark mode">
                </div>
            </div>

            <div class="fd-heading">
                <h2>Built for support teams, agencies, and automation.</h2>
                <p>FoxDesk Cloud keeps the practical admin surface from the self-hosted edition and adds a managed SaaS operating layer.</p>
            </div>
            <div class="fd-capability-grid">
                <article class="fd-capability"><span>A</span><h4>AI Agent API</h4><p>Give AI agents or internal tools API access to create tickets, post updates, and log work.</p></article>
                <article class="fd-capability"><span>✉</span><h4>Custom client emails</h4><p>Send branded support notifications from your FoxDesk workspace without configuring SMTP yourself.</p></article>
                <article class="fd-capability"><span>O</span><h4>Organizations</h4><p>Group clients, tickets, time, reports, and permissions around real customer accounts.</p></article>
                <article class="fd-capability"><span>🔒</span><h4>Security controls</h4><p>Admin permissions, 2FA support, CSRF protection, audit/security logs, and impersonation controls.</p></article>
                <article class="fd-capability"><span>🌐</span><h4>Multilingual UI</h4><p>Use FoxDesk across languages with localized app labels and customer-facing text.</p></article>
                <article class="fd-capability"><span>🔔</span><h4>Notifications</h4><p>Email, in-app, and push notification building blocks for ticket activity and reminders.</p></article>
                <article class="fd-capability"><span>📎</span><h4>File storage</h4><p>Attachments are stored outside the app container so the workspace can grow safely.</p></article>
                <article class="fd-capability"><span>⚙</span><h4>Admin settings</h4><p>Branding, ticket statuses, priorities, types, users, clients, reports, and recurring tasks.</p></article>
            </div>
        </section>

        <section class="fd-section fd-band" id="pricing">
            <div class="fd-heading">
                <h2>One simple Cloud plan.</h2>
                <p>Create a workspace, start the Cloud subscription with Stripe Checkout, and manage billing from your FoxDesk account.</p>
            </div>
            <div class="fd-pricing">
                <div class="fd-price-card">
                    <div class="fd-price-top">
                        <div>
                            <span class="fd-soon">Stripe checkout ready</span>
                            <h3 class="mt-5">FoxDesk Cloud</h3>
                            <div class="fd-offer-strip mt-4">
                                <span>50% launch discount</span>
                                <s>Regular <?php echo e($cloud_regular_price); ?>/month</s>
                            </div>
                        </div>
                        <div class="fd-discount-chip">Valid until <?php echo e($cloud_launch_until); ?></div>
                    </div>
                    <div class="fd-price">
                        <strong><?php echo e($cloud_launch_price); ?></strong>
                        <span>/ month</span>
                    </div>
                    <ul class="fd-list">
                        <li><span class="fd-check">✓</span><span>Unlimited users, agents, clients, organizations, and tickets</span></li>
                        <li><span class="fd-check">✓</span><span><?php echo e($included_storage); ?> file storage included</span></li>
                        <li><span class="fd-check">✓</span><span>Hosted FoxDesk app on app.foxdesk.net</span></li>
                        <li><span class="fd-check">✓</span><span>Managed email sending and attachment storage</span></li>
                        <li><span class="fd-check">✓</span><span>Updates and production deployment prepared for managed hosting</span></li>
                    </ul>
                    <a href="<?php echo e(url('signup')); ?>" class="fd-btn primary w-full">Create workspace</a>
                    <p class="mt-4 text-sm text-gray-500">Subscription checkout opens after signup so the workspace and billing customer stay linked.</p>
                </div>
                <div class="fd-preview-stack">
                    <img class="fd-light-img" src="assets/public/dashboard-light.webp" alt="FoxDesk dashboard">
                    <img class="fd-dark-img" src="assets/public/dashboard-dark.webp" alt="FoxDesk dashboard in dark mode">
                    <img class="fd-light-img" src="assets/public/ticket-detail-light.webp" alt="FoxDesk ticket detail">
                    <img class="fd-dark-img" src="assets/public/ticket-detail-dark.webp" alt="FoxDesk ticket detail in dark mode">
                </div>
            </div>
        </section>

        <section class="fd-section fd-band" id="migration">
            <div class="fd-migration">
                <div>
                    <h2>Move your existing FoxDesk safely.</h2>
                    <p>If you already run FoxDesk on Vas-Hosting or another PHP hosting, the clean migration path is backup first, restore second, then DNS switch only after testing.</p>
                    <div class="fd-hero-actions justify-start">
                        <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">App login</a>
                        <a href="mailto:hanes.lukas@gmail.com?subject=FoxDesk%20migration" class="fd-btn primary">Plan migration</a>
                    </div>
                </div>
                <div class="fd-steps">
                    <div class="fd-step">
                        <div class="fd-step-number">1</div>
                        <div><strong>Export current installation</strong><span>Database dump, uploaded files, config, cron/email settings, and current FoxDesk version.</span></div>
                    </div>
                    <div class="fd-step">
                        <div class="fd-step-number">2</div>
                        <div><strong>Restore on the new server</strong><span>Import DB, copy files to the managed stack, configure email delivery and attachment storage.</span></div>
                    </div>
                    <div class="fd-step">
                        <div class="fd-step-number">3</div>
                        <div><strong>Test before switching DNS</strong><span>Login, tickets, attachments, outbound email, inbound email, cron, health endpoint, and admin permissions.</span></div>
                    </div>
                    <div class="fd-step">
                        <div class="fd-step-number">4</div>
                        <div><strong>Switch production traffic</strong><span>Lower DNS TTL, point the app domain to the new server, monitor logs, then keep the old host as rollback for a short period.</span></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="fd-footer">
        <div class="fd-footer-inner">
            <div class="flex items-center gap-2">
                <img src="assets/public/logo.webp" alt="" width="28" height="28" class="rounded-lg">
                <strong class="text-gray-900">FoxDesk</strong>
            </div>
            <div>Open-source FoxDesk remains available at <a href="https://foxdesk.org" class="text-[#2563eb]" target="_blank" rel="noopener">foxdesk.org</a>. FoxDesk Cloud runs at <strong>app.foxdesk.net</strong>.</div>
            <div class="flex items-center gap-4 flex-wrap text-sm">
                <a href="<?php echo e(url('legal', ['type' => 'privacy'])); ?>">Privacy</a>
                <a href="<?php echo e(url('legal', ['type' => 'terms'])); ?>">Terms</a>
                <a href="<?php echo e(url('legal', ['type' => 'dpa'])); ?>">DPA</a>
                <a href="<?php echo e(url('legal', ['type' => 'security'])); ?>">Security</a>
            </div>
        </div>
    </footer>
    <script>
        function toggleCloudTheme() {
            var current = document.documentElement.getAttribute('data-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('foxdesk-cloud-theme', next);
        }
    </script>
</body>
</html>
