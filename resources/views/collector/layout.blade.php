<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <style>
        :root { font-family: system-ui, sans-serif; color: #0f172a; background: #f8fafc; }
        body { margin: 0; padding: 2rem; max-width: 1200px; }
        h1 { margin-top: 0; }
        h2 { margin-top: 0; font-size: 1.15rem; }
        nav { margin-bottom: 1.25rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        nav a { color: #2563eb; text-decoration: none; font-weight: 600; }
        nav a.active { color: #0f172a; text-decoration: underline; }
        .app-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #f8fafc;
            padding-bottom: 0.75rem;
            margin: -2rem -2rem 1.25rem;
            padding: 1rem 2rem 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .app-header h1 { margin-bottom: 0.65rem; font-size: 1.35rem; }
        .app-header h1 a { color: inherit; text-decoration: none; }
        .app-header h1 a:hover { color: #2563eb; }
        .app-header nav { margin-bottom: 0; }
        .page-breadcrumb { margin: 0 0 1rem; font-size: 0.925rem; }
        .page-breadcrumb a { color: #2563eb; font-weight: 600; text-decoration: none; }
        .page-breadcrumb a:hover { text-decoration: underline; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.925rem; }
        th, td { text-align: left; padding: 0.5rem 0.35rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .data-table-card { padding: 0; overflow: hidden; }
        .data-table-card .data-table-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .data-table-card > .muted { padding: 1rem 1.25rem; margin: 0; }
        .data-table-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; }
        .data-table-toolbar-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-left: auto; justify-content: flex-end; }
        .data-table-toolbar-actions .toolbar-inline-label { display: inline; margin: 0; font-weight: 600; font-size: 0.875rem; white-space: nowrap; }
        .data-table-toolbar-actions .toolbar-search-input { width: 18rem; max-width: min(40vw, 22rem); min-width: 10rem; flex: 0 1 auto; margin: 0; }
        .data-table-toolbar-actions button,
        .data-table-toolbar-actions .btn { flex-shrink: 0; white-space: nowrap; margin: 0; }
        .data-table-scroll { overflow-x: auto; max-height: min(70vh, 640px); overflow-y: auto; }
        table.data-table { margin: 0; }
        table.data-table thead th { position: sticky; top: 0; z-index: 1; background: #f1f5f9; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.02em; color: #475569; white-space: nowrap; padding: 0.65rem 0.75rem; }
        .sortable-th { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 0.2rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; font-size: 0.8rem; white-space: nowrap; }
        .sortable-th:hover { color: #2563eb; }
        .sortable-th.is-active { color: #1d4ed8; }
        .sort-indicator { font-size: 0.7rem; line-height: 1; opacity: 0.95; }
        .sort-indicator-idle { opacity: 0.35; font-weight: 400; }
        .sortable-th:hover .sort-indicator-idle { opacity: 0.65; }
        table.data-table tbody td { padding: 0.55rem 0.75rem; white-space: nowrap; }
        table.data-table tbody tr:nth-child(even) { background: #fafafa; }
        table.data-table tbody tr:hover { background: #eff6ff; }
        table.data-table td.num { color: #64748b; font-size: 0.85rem; width: 2.5rem; }
        .data-table-footer { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.75rem 1.25rem; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .data-table-footer-start { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
        .data-table-per-page { margin: 0; display: flex; align-items: center; }
        .data-table-per-page-label { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.875rem; color: #64748b; font-weight: 500; margin: 0; }
        .data-table-per-page select { width: auto; min-width: 4.5rem; padding: 0.35rem 0.5rem; font-size: 0.875rem; }
        .data-table-info { margin: 0; font-size: 0.875rem; }
        .data-table-pagination { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
        .data-table-pagination .page-link { display: inline-block; padding: 0.35rem 0.65rem; border-radius: 6px; border: 1px solid #e2e8f0; text-decoration: none; color: #0f172a; font-size: 0.875rem; background: #fff; }
        .data-table-pagination .page-link:hover:not(.disabled):not(.current) { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
        .data-table-pagination .page-link.current { background: #2563eb; color: #fff; border-color: #2563eb; font-weight: 600; }
        .data-table-pagination .page-link.disabled { color: #94a3b8; background: #f1f5f9; cursor: default; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 999px; font-size: 0.75rem; background: #e2e8f0; }
        .badge.ok { background: #dcfce7; color: #166534; }
        .badge.offline { background: #fee2e2; color: #991b1b; }
        .badge.checking { background: #e0e7ff; color: #3730a3; }
        .badge.disabled { background: #f1f5f9; color: #64748b; }
        .badge.warn { background: #fef9c3; color: #854d0e; }
        .badge.port { background: #ffedd5; color: #9a3412; }
        .flash { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .flash.success { background: #ecfdf5; color: #065f46; }
        .flash.warning { background: #fffbeb; color: #92400e; }
        .flash.error { background: #fef2f2; color: #991b1b; }
        .enroll-result { padding: 1rem 1.15rem; border-radius: 8px; margin-bottom: 1rem; border: 2px solid transparent; }
        .enroll-result-title { margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700; }
        .enroll-result-message { margin: 0; font-size: 0.95rem; line-height: 1.45; }
        .enroll-result-detail { margin: 0.5rem 0 0; font-size: 0.875rem; font-weight: 500; }
        .enroll-result-success { background: #ecfdf5; border-color: #34d399; color: #065f46; }
        .enroll-result-warning { background: #fffbeb; border-color: #fbbf24; color: #92400e; }
        .enroll-result-error { background: #fef2f2; border-color: #f87171; color: #991b1b; }
        .enroll-progress { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; font-weight: 600; }
        button:disabled { opacity: 0.75; cursor: wait; }
        .finger-picker-wrap { margin-bottom: 1.25rem; }
        .finger-picker-label { display: block; font-weight: 600; margin-bottom: 0.65rem; font-size: 0.875rem; }
        .finger-hands { display: flex; flex-wrap: wrap; gap: 2rem; justify-content: center; align-items: flex-end; padding: 0.5rem 0 0.25rem; }
        .finger-hand { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .finger-hand-title { font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .finger-hand-row { display: flex; align-items: flex-end; gap: 0.35rem; padding: 0.75rem 1rem 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px 12px 8px 8px; min-height: 7.5rem; }
        .finger-hand-row.left { border-bottom-left-radius: 28px; border-bottom-right-radius: 10px; }
        .finger-hand-row.right { border-bottom-right-radius: 28px; border-bottom-left-radius: 10px; }
        .finger-palm { width: 100%; height: 0.45rem; background: #e2e8f0; border-radius: 0 0 6px 6px; margin-top: -0.15rem; }
        .finger-segment { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 0.25rem; padding: 0; border: 2px solid #cbd5e1; border-radius: 999px 999px 6px 6px; background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%); cursor: pointer; font: inherit; color: #334155; transition: background 0.15s, border-color 0.15s, transform 0.1s; min-width: 2.1rem; }
        .finger-segment:hover { border-color: #93c5fd; background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%); }
        .finger-segment:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .finger-segment.is-selected { border-color: #2563eb; background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%); color: #fff; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35); }
        .finger-segment.is-selected .finger-segment-num { color: #dbeafe; }
        .finger-segment-inner { width: 100%; border-radius: inherit; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; padding: 0.35rem 0.2rem 0.45rem; }
        .finger-segment-num { font-size: 0.65rem; font-weight: 700; color: #64748b; line-height: 1; }
        .finger-segment-name { font-size: 0.6rem; font-weight: 600; line-height: 1.1; text-align: center; max-width: 2.5rem; word-break: break-word; }
        .finger-segment.h-pinky { height: 3.25rem; }
        .finger-segment.h-ring { height: 4rem; }
        .finger-segment.h-middle { height: 4.65rem; }
        .finger-segment.h-index { height: 4.15rem; }
        .finger-segment.h-thumb { height: 2.85rem; min-width: 2.35rem; border-radius: 40% 40% 8px 8px; }
        .finger-picker-summary { margin: 0.75rem 0 0; font-size: 0.925rem; font-weight: 600; color: #0f172a; }
        .finger-picker-summary span { color: #2563eb; }
        .finger-picker-visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        button, .btn { background: #2563eb; color: #fff; border: 0; border-radius: 6px; padding: 0.55rem 1rem; cursor: pointer; font: inherit; text-decoration: none; display: inline-block; }
        button:hover, .btn:hover { background: #1d4ed8; color: #fff; }
        button.secondary, .btn.secondary { background: #475569; }
        button.secondary:hover { background: #334155; }
        button.danger { background: #dc2626; font-size: 0.8rem; padding: 0.35rem 0.65rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.35rem; font-size: 0.875rem; }
        input[type="text"], input[type="search"], input[type="number"], input[type="date"], select { width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; box-sizing: border-box; background: #fff; }
        input[type="date"] { min-height: 2.5rem; color: #0f172a; }
        input[type="search"] { -webkit-appearance: none; appearance: none; }
        .logs-filter-form { display: block; }
        .logs-filter-grid { display: grid; grid-template-columns: minmax(11rem, 1fr) minmax(17rem, 1.15fr) minmax(16rem, 1.35fr); gap: 1rem 1.25rem; align-items: end; }
        @media (max-width: 960px) {
            .logs-filter-grid { grid-template-columns: 1fr 1fr; }
            .logs-filter-search-block { grid-column: 1 / -1; }
        }
        @media (max-width: 560px) {
            .logs-filter-grid { grid-template-columns: 1fr; }
        }
        .logs-filter-grid .field { min-width: 0; margin: 0; }
        .logs-filter-daterange { border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.6rem 0.85rem 0.75rem; margin: 0; background: #f8fafc; min-width: 0; }
        .logs-filter-daterange-legend { font-size: 0.875rem; font-weight: 600; color: #0f172a; padding: 0 0.25rem; margin-bottom: 0.35rem; display: block; }
        .logs-filter-daterange-inputs { display: flex; align-items: flex-end; gap: 0.45rem; flex-wrap: wrap; }
        .logs-filter-daterange-inputs .field-date-inline { flex: 1 1 7.5rem; min-width: 7rem; margin: 0; }
        .logs-filter-daterange-inputs .field-date-inline label { font-size: 0.8125rem; color: #64748b; margin-bottom: 0.25rem; }
        .logs-filter-daterange-sep { flex: 0 0 auto; color: #64748b; font-size: 0.8125rem; font-weight: 500; padding-bottom: 0.55rem; }
        .logs-filter-search-block { min-width: 0; }
        .logs-filter-search-row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; }
        .logs-filter-search-row .field-search { flex: 1 1 12rem; min-width: 10rem; margin: 0; }
        .logs-filter-search-row .field-search label { font-size: 0.875rem; }
        .logs-filter-search-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; flex: 0 0 auto; padding-bottom: 0.1rem; }
        .data-table-toolbar .field-device { flex: 0 1 16rem; min-width: 12rem; max-width: 18rem; }
        .field-row { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
        .field { flex: 1; min-width: 10rem; }
        .field-sm { max-width: 8rem; }
        .errors { color: #b91c1c; font-size: 0.875rem; margin: 0.25rem 0 0; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 0.75rem; }
        .pagination { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; align-items: center; }
        .pagination a, .pagination span { padding: 0.35rem 0.65rem; border-radius: 6px; border: 1px solid #e2e8f0; text-decoration: none; color: #0f172a; font-size: 0.875rem; }
        .pagination span.current { background: #2563eb; color: #fff; border-color: #2563eb; }
        .icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #334155; text-decoration: none; vertical-align: middle; }
        .icon-btn:hover { background: #f1f5f9; color: #2563eb; border-color: #93c5fd; }
        .icon-btn svg { width: 1.15rem; height: 1.15rem; }
        .actions-cell { display: flex; gap: 0.35rem; align-items: center; flex-wrap: nowrap; }
        .device-collect-error-msg { display: block; color: #b91c1c; font-size: 0.8125rem; max-width: 22rem; word-break: break-word; line-height: 1.35; }
        .device-collect-error-none { color: #94a3b8; font-size: 0.8125rem; }
        .devices-table-wrap { overflow-x: auto; margin-top: 0.75rem; }
        .devices-table-wrap table { min-width: 52rem; }
        .devices-table-wrap th.actions-col,
        .devices-table-wrap td.actions-cell {
            position: sticky;
            right: 0;
            z-index: 2;
            background: #fff;
            box-shadow: -4px 0 8px -2px rgba(15, 23, 42, 0.08);
            white-space: nowrap;
        }
        .devices-table-wrap thead th.actions-col { background: #fff; z-index: 3; }
        .device-name-link { color: #2563eb; font-weight: 600; text-decoration: none; }
        .device-name-link:hover { text-decoration: underline; }
        .view-logs-link { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8125rem; font-weight: 600; color: #2563eb; text-decoration: none; white-space: nowrap; }
        .view-logs-link:hover { text-decoration: underline; }
        .view-logs-link svg { width: 1rem; height: 1rem; flex-shrink: 0; }
        .app-header-top { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .app-version { font-size: 0.875rem; font-weight: 600; color: #64748b; white-space: nowrap; }
        .desktop-update-overlay {
            position: fixed; inset: 0; z-index: 200; display: flex; align-items: center; justify-content: center;
            background: rgba(15, 23, 42, 0.72); padding: 1rem;
        }
        .desktop-update-card {
            width: 100%; max-width: 26rem; background: #fff; border: 1px solid #fde68a; border-radius: 12px;
            padding: 1.35rem 1.5rem; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
        }
        .desktop-update-card h2 { margin: 0 0 0.5rem; font-size: 1.15rem; }
        .desktop-update-card p { margin: 0; font-size: 0.925rem; line-height: 1.45; color: #334155; }
        .desktop-update-progress {
            margin-top: 1rem; padding: 0.85rem 0.9rem; border-radius: 8px;
            background: #f8fafc; border: 1px solid #e2e8f0;
        }
        .desktop-update-progress-label {
            display: flex; justify-content: space-between; gap: 0.75rem; align-items: baseline;
            font-size: 0.875rem; color: #334155; margin-bottom: 0.55rem;
        }
        .desktop-update-progress-track {
            height: 0.65rem; border-radius: 999px; background: #e2e8f0; overflow: hidden;
        }
        .desktop-update-progress-fill {
            height: 100%; width: 0%; border-radius: inherit; background: #2563eb;
            transition: width 0.15s ease-out;
        }
        .desktop-update-progress-fill.is-indeterminate {
            width: 35% !important;
            animation: desktop-update-indeterminate 1.1s ease-in-out infinite;
        }
        .desktop-update-progress-meta { margin: 0.55rem 0 0; font-size: 0.75rem; }
        .collect-progress {
            margin-top: 1rem; padding: 0.85rem 0.9rem; border-radius: 8px;
            background: #eff6ff; border: 1px solid #93c5fd;
        }
        .collect-progress-label {
            display: flex; justify-content: space-between; gap: 0.75rem; align-items: baseline;
            font-size: 0.875rem; color: #1e40af; margin-bottom: 0.55rem; font-weight: 600;
        }
        .collect-progress-track {
            height: 0.65rem; border-radius: 999px; background: #dbeafe; overflow: hidden;
        }
        .collect-progress-fill {
            height: 100%; width: 0%; border-radius: inherit; background: #2563eb;
            transition: width 0.2s ease-out;
        }
        .collect-progress-fill.is-indeterminate {
            width: 40% !important;
            animation: desktop-update-indeterminate 1.1s ease-in-out infinite;
        }
        .collect-progress-meta { margin: 0.55rem 0 0; font-size: 0.8rem; color: #1e40af; }
        .btn[disabled], a.btn.is-disabled {
            opacity: 0.65; pointer-events: none; cursor: wait;
        }
        @keyframes desktop-update-indeterminate {
            0% { transform: translateX(-120%); }
            100% { transform: translateX(320%); }
        }
        .flash-confirm-overlay {
            position: fixed; inset: 0; z-index: 190; display: flex; align-items: center; justify-content: center;
            background: rgba(15, 23, 42, 0.55); padding: 1rem;
        }
        .flash-confirm-card {
            width: 100%; max-width: 24rem; background: #fff; border-radius: 12px;
            padding: 1.35rem 1.5rem; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
            border: 1px solid #e2e8f0;
        }
        .flash-confirm-card.flash-confirm-success { border-color: #86efac; }
        .flash-confirm-card.flash-confirm-error { border-color: #fca5a5; }
        .flash-confirm-card.flash-confirm-warning { border-color: #fde68a; }
        .flash-confirm-card h2 { margin: 0 0 0.5rem; font-size: 1.15rem; }
        .flash-confirm-card.flash-confirm-success h2 { color: #166534; }
        .flash-confirm-card.flash-confirm-error h2 { color: #991b1b; }
        .flash-confirm-card.flash-confirm-warning h2 { color: #92400e; }
        .flash-confirm-card p { margin: 0; font-size: 0.95rem; line-height: 1.45; color: #334155; }
        .flash-confirm-actions { margin-top: 1.15rem; display: flex; justify-content: flex-end; }
        .collector-loader {
            position: fixed; inset: 0; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            background: rgba(248, 250, 252, 0.86);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .collector-loader.is-hidden { display: none; }
        .collector-loader-card { text-align: center; padding: 1.5rem 1.75rem; max-width: 22rem; }
        .collector-loader-spinner {
            width: 2.75rem; height: 2.75rem; margin: 0 auto 1rem;
            border: 4px solid #dbeafe; border-top-color: #2563eb;
            border-radius: 50%; animation: collector-loader-spin 0.8s linear infinite;
        }
        @keyframes collector-loader-spin {
            to { transform: rotate(360deg); }
        }
        #collector-loader-text {
            margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a;
        }
        .collector-loader-hint { margin: 0.45rem 0 0; font-size: 0.875rem; color: #64748b; }
    </style>
</head>
<body>
    <div
        id="collector-full-screen-loader"
        class="collector-loader"
        aria-live="polite"
        role="status"
        aria-hidden="false"
    >
        <div class="collector-loader-card">
            <div class="collector-loader-spinner" aria-hidden="true"></div>
            <p id="collector-loader-text">Loading…</p>
            <p class="collector-loader-hint">Please wait. Do not close the app.</p>
        </div>
    </div>
    <script>
        (function () {
            const el = document.getElementById('collector-full-screen-loader');
            const textEl = document.getElementById('collector-loader-text');
            const defaultText = 'Loading…';
            let count = 0;
            let sticky = false;
            let pageReady = false;

            const render = () => {
                if (! el) {
                    return;
                }
                const on = ! pageReady || sticky || count > 0;
                el.classList.toggle('is-hidden', ! on);
                el.setAttribute('aria-hidden', on ? 'false' : 'true');
            };

            const show = (text, options = {}) => {
                if (text && textEl) {
                    textEl.textContent = text;
                }
                if (options.sticky) {
                    sticky = true;
                } else {
                    count += 1;
                }
                render();
            };

            const hide = (options = {}) => {
                if (options.force) {
                    sticky = false;
                    count = 0;
                } else if (options.sticky) {
                    sticky = false;
                } else {
                    count = Math.max(0, count - 1);
                }
                if (! sticky && count === 0 && textEl) {
                    textEl.textContent = defaultText;
                }
                render();
            };

            window.CollectorLoader = { show, hide };

            const markPageReady = () => {
                pageReady = true;
                render();
            };

            window.addEventListener('load', () => {
                markPageReady();
                hide({ force: true });
            });
            window.addEventListener('pageshow', (event) => {
                hide({ force: true });
                pageReady = true;
                render();
            });
            if (document.readyState === 'complete') {
                markPageReady();
                hide({ force: true });
            }

            const sameOriginUrl = (value) => {
                try {
                    return new URL(value, window.location.origin);
                } catch (_error) {
                    return null;
                }
            };

            const shouldSkipBackgroundUrl = (value) => {
                const url = sameOriginUrl(value);
                if (! url) {
                    return true;
                }
                const path = url.pathname;

                return /\/devices\/status\/?$/.test(path)
                    || /\/collect\/status\/?$/.test(path)
                    || /\/collect\/auto\/?$/.test(path)
                    || /\/collect\/now\/?$/.test(path)
                    || /\/desktop\/updater\/status\/?$/.test(path)
                    || /\/desktop\/updater\/check\/?$/.test(path)
                    || /\/desktop\/updater\/install\/?$/.test(path)
                    || /\/desktop\/update\//.test(path);
            };

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');
                if (! link || link.dataset.noLoader !== undefined) {
                    return;
                }
                if (link.hasAttribute('data-desktop-installer-download')) {
                    return;
                }
                if (link.target === '_blank' || link.hasAttribute('download')) {
                    return;
                }

                const href = link.getAttribute('href') || '';
                if (href.startsWith('#') || href.startsWith('javascript:')) {
                    return;
                }

                const url = sameOriginUrl(link.href);
                if (! url || url.origin !== window.location.origin) {
                    return;
                }
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                if (url.pathname === window.location.pathname && url.search === window.location.search) {
                    return;
                }

                show('Loading…');
            });

            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (! (form instanceof HTMLFormElement) || form.dataset.noLoader !== undefined) {
                    return;
                }
                if (event.defaultPrevented) {
                    return;
                }
                if (form.target === '_blank') {
                    return;
                }
                show('Saving…');
            });

            const originalFetch = window.fetch.bind(window);
            window.fetch = function (input, init) {
                const url = typeof input === 'string'
                    ? input
                    : (input && typeof input.url === 'string' ? input.url : '');
                const skip = shouldSkipBackgroundUrl(url);
                if (! skip) {
                    show('Loading…');
                }

                return originalFetch(input, init).finally(() => {
                    if (! skip) {
                        hide();
                    }
                });
            };
        })();
    </script>
    @php
        $desktopAppVersion = ltrim((string) config('nativephp.version', '0.0.0'), 'vV');
    @endphp
    <header class="app-header">
        <div class="app-header-top">
            <h1><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></h1>
            <span class="app-version" title="Installed desktop app version">v{{ $desktopAppVersion }}</span>
        </div>
        <nav aria-label="Main">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
            <a href="{{ route('logs.index') }}" @class(['active' => request()->routeIs('logs.*')])>Collected logs</a>
            <a href="{{ route('dtr.index') }}" @class(['active' => request()->routeIs('dtr.*')])>Download DTR</a>
            <a href="{{ route('archives.index') }}" @class(['active' => request()->routeIs('archives.index') || request()->routeIs('archives.download')])>Deleted log backups</a>
            <a href="{{ route('archives.upload') }}" @class(['active' => request()->routeIs('archives.upload*')])>Upload backup JSON</a>
            <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>Device users</a>
        </nav>
    </header>

    @yield('content')

    @include('collector.partials.flash-confirm-modal')
    @include('collector.partials.desktop-updater-overlay')
    @include('collector.partials.desktop-installer-update-modal')

    <script>
        (function () {
            const flashModal = document.querySelector('[data-flash-confirm]');
            if (flashModal) {
                const closeFlash = () => flashModal.remove();
                flashModal.querySelectorAll('[data-flash-confirm-close]').forEach((btn) => {
                    btn.addEventListener('click', closeFlash);
                });
                flashModal.addEventListener('click', (event) => {
                    if (event.target === flashModal) {
                        closeFlash();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeFlash();
                    }
                }, { once: true });
                flashModal.querySelector('[data-flash-confirm-close]')?.focus();
            }

            const formatBytes = (bytes) => {
                if (! Number.isFinite(bytes) || bytes < 0) {
                    return '';
                }
                const units = ['B', 'KB', 'MB', 'GB'];
                let value = bytes;
                let unit = 0;
                while (value >= 1024 && unit < units.length - 1) {
                    value /= 1024;
                    unit += 1;
                }
                return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
            };

            const setDownloadProgress = ({
                visible = true,
                label = 'Downloading…',
                percent = null,
                meta = '',
            } = {}) => {
                const box = document.querySelector('[data-desktop-installer-progress]');
                const labelEl = document.querySelector('[data-desktop-installer-progress-label]');
                const pctEl = document.querySelector('[data-desktop-installer-progress-pct]');
                const fillEl = document.querySelector('[data-desktop-installer-progress-fill]');
                const barEl = document.querySelector('[data-desktop-installer-progress-bar]');
                const metaEl = document.querySelector('[data-desktop-installer-progress-meta]');

                if (! box) {
                    return;
                }

                box.hidden = ! visible;
                if (labelEl) {
                    labelEl.textContent = label;
                }
                if (metaEl) {
                    metaEl.textContent = meta;
                }

                if (percent === null || ! Number.isFinite(percent)) {
                    if (pctEl) {
                        pctEl.textContent = '';
                    }
                    if (fillEl) {
                        fillEl.classList.add('is-indeterminate');
                        fillEl.style.width = '35%';
                    }
                    if (barEl) {
                        barEl.removeAttribute('aria-valuenow');
                    }
                    return;
                }

                const clamped = Math.max(0, Math.min(100, Math.round(percent)));
                if (pctEl) {
                    pctEl.textContent = `${clamped}%`;
                }
                if (fillEl) {
                    fillEl.classList.remove('is-indeterminate');
                    fillEl.style.width = `${clamped}%`;
                }
                if (barEl) {
                    barEl.setAttribute('aria-valuenow', String(clamped));
                }
            };

            const saveBlob = (blob, filename) => {
                const objectUrl = URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = objectUrl;
                anchor.download = filename;
                anchor.rel = 'noopener';
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 15000);
            };

            const downloadWithProgress = (url, filename) => new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.responseType = 'blob';
                xhr.withCredentials = url.startsWith('/') || url.includes(window.location.host);

                xhr.onprogress = (event) => {
                    if (event.lengthComputable && event.total > 0) {
                        const percent = (event.loaded / event.total) * 100;
                        setDownloadProgress({
                            label: `Downloading ${filename}…`,
                            percent,
                            meta: `${formatBytes(event.loaded)} / ${formatBytes(event.total)}`,
                        });
                    } else {
                        setDownloadProgress({
                            label: `Downloading ${filename}…`,
                            percent: null,
                            meta: event.loaded ? `${formatBytes(event.loaded)} downloaded` : 'Working…',
                        });
                    }
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300 && xhr.response) {
                        resolve(xhr.response);
                        return;
                    }
                    reject(new Error(`Download failed (HTTP ${xhr.status}).`));
                };

                xhr.onerror = () => reject(new Error('Network error while downloading the installer.'));
                xhr.onabort = () => reject(new Error('Download was cancelled.'));
                xhr.send();
            });

            const openExternalDownload = (url, filename) => {
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = filename;
                anchor.rel = 'noopener';
                anchor.target = '_blank';
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
            };

            const isSameOriginUrl = (url) => {
                try {
                    const parsed = new URL(url, window.location.origin);
                    return parsed.origin === window.location.origin;
                } catch (_error) {
                    return false;
                }
            };

            const startDesktopInstallerDownload = async (trigger) => {
                const href = trigger.getAttribute('href') || '';
                const filename = trigger.dataset.desktopInstallerFilename || 'Pulse-installer';
                const statusEl = document.querySelector('[data-desktop-installer-download-status]');

                if (! href || trigger.classList.contains('is-disabled')) {
                    return;
                }

                trigger.classList.add('is-disabled');
                trigger.setAttribute('aria-disabled', 'true');
                trigger.textContent = 'Downloading…';

                if (statusEl) {
                    statusEl.hidden = false;
                    statusEl.textContent = 'Preparing download…';
                }

                setDownloadProgress({
                    visible: true,
                    label: 'Preparing download…',
                    percent: 0,
                    meta: '',
                });

                try {
                    const response = await fetch(href, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (! response.ok || ! payload.ok || ! payload.url) {
                        throw new Error(payload.message || 'Unable to prepare the installer download.');
                    }

                    const downloadName = payload.filename || filename;
                    const useStream = Boolean(payload.stream) && isSameOriginUrl(payload.url);

                    setDownloadProgress({
                        label: `Downloading ${downloadName}…`,
                        percent: useStream ? 0 : null,
                        meta: useStream ? '' : 'Browser download started — check your Downloads folder',
                    });
                    if (statusEl) {
                        statusEl.textContent = useStream
                            ? `Downloading ${downloadName}…`
                            : `Downloading ${downloadName}… Check your Downloads folder, then quit this app and run the installer.`;
                    }

                    if (useStream) {
                        const blob = await downloadWithProgress(payload.url, downloadName);
                        saveBlob(blob, downloadName);
                        setDownloadProgress({
                            label: 'Download complete',
                            percent: 100,
                            meta: `${formatBytes(blob.size)} saved to Downloads`,
                        });
                    } else {
                        // Direct S3 pre-signed URL — do not buffer ~150MB in Electron memory.
                        openExternalDownload(payload.url, downloadName);
                        setDownloadProgress({
                            label: 'Download started',
                            percent: 100,
                            meta: 'Check your Downloads folder for the installer',
                        });
                    }

                    if (statusEl) {
                        statusEl.textContent = 'Download complete. Quit this app, then run the installer from Downloads.';
                    }
                    trigger.textContent = 'Download again';
                } catch (error) {
                    setDownloadProgress({
                        label: 'Download failed',
                        percent: 0,
                        meta: error?.message || 'Unable to download the installer.',
                    });
                    if (statusEl) {
                        statusEl.textContent = error?.message || 'Download failed.';
                    }
                    window.alert(error?.message || 'Unable to start the installer download.');
                    trigger.textContent = 'Download update';
                } finally {
                    trigger.classList.remove('is-disabled');
                    trigger.removeAttribute('aria-disabled');
                }
            };

            document.addEventListener('click', (event) => {
                const installerDownload = event.target.closest('[data-desktop-installer-download]');
                if (installerDownload) {
                    event.preventDefault();
                    startDesktopInstallerDownload(installerDownload);
                }
            });
        })();
    </script>

</body>
</html>
