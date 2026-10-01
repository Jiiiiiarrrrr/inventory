<?php
/**
 * POS Entry Point — Customer or Staff landing page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>POS — Brew & Co.</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    .cashier-void-btn {
      font-size: 0.8rem; padding: 0.3rem 0.7rem; color: #c0392b; border-color: #c0392b;
    }
    .cashier-collect-btn {
      font-size: 0.8rem; padding: 0.3rem 0.7rem; color: #fff; background: #2C7A1A; border-color: #2C7A1A;
    }

    /* ===== LANDING PAGE ===== */
    .landing-overlay {
      position: fixed; inset: 0;
      background: linear-gradient(160deg, rgba(58,36,27,.55) 0%, rgba(92,58,41,.4) 40%, rgba(122,79,56,.35) 70%, rgba(169,113,74,.45) 100%),
                  url('../coffee-bg.jpg') center/cover no-repeat;
      display: flex; align-items: center; justify-content: center;
      z-index: 9999; padding: 24px;
    }
    .landing-overlay.hidden { display: none; }
    .landing-card {
      background: #fff; border-radius: 24px; padding: 40px 36px;
      max-width: 600px; width: 100%; box-shadow: 0 24px 60px rgba(0,0,0,.3);
      text-align: center;
    }
    .landing-title { font-size: 28px; font-weight: 800; color: #33261d; margin-bottom: 4px; margin-top:14px; }
    .landing-subtitle { font-size: 14px; color: #7a6055; margin-bottom: 32px; margin-top:0; }
    .landing-options { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .landing-btn {
      display: flex; flex-direction: column; align-items: center; gap: 12px;
      padding: 32px 20px; border-radius: 18px; border: 2px solid #eaded0;
      background: #fff; cursor: pointer; transition: transform .2s, box-shadow .2s, border-color .2s;
    }
    .landing-btn:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(74,47,34,.15); border-color: #a9714a; }
    .landing-btn-icon {
      width: 80px; height: 80px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
    }
    .landing-btn.customer .landing-btn-icon { background: #e2f0e8; }
    .landing-btn.staff .landing-btn-icon { background: #efe3d3; }
    .landing-btn-label { font-size: 18px; font-weight: 800; color: #33261d; }
    .landing-btn-desc { font-size: 13px; color: #6f6055; line-height: 1.5; }
    .landing-back { margin-top: 24px; }
    .landing-back button {
      background: none; border: 1px solid #eaded0; border-radius: 10px;
      padding: 10px 20px; font-size: 14px; font-weight: 600; color: #6f6055; cursor: pointer;
    }
    .landing-back button:hover { background: #f6ecdd; color: #33261d; }
    .staff-login-form { text-align: left; }
    .staff-login-form .form-group { margin-bottom: 16px; }
    .staff-login-form label { display: block; font-size: 13px; font-weight: 700; color: #33261d; margin-bottom: 6px; }
    .staff-login-form input {
      width: 100%; padding: 12px 14px; border: 1.5px solid #eaded0; border-radius: 10px;
      font-size: 15px; background: #fff; color: #33261d; outline: none;
    }
    .staff-login-form input:focus { border-color: #a9714a; box-shadow: 0 0 0 3px rgba(169,113,74,.12); }
    .staff-login-form .btn-signin {
      width: 100%; padding: 14px; background: #a9714a; color: #fff;
      border: none; border-radius: 12px; font-size: 16px; font-weight: 800;
      cursor: pointer; margin-top: 8px;
    }
    .staff-login-form .btn-signin:hover { background: #8f5c39; }
    .staff-login-form .btn-signin:disabled { opacity: .6; cursor: not-allowed; }
    .staff-login-error {
      background: #fde8e8; color: #c0392b; border-radius: 10px; padding: 11px 14px;
      font-size: 13px; font-weight: 700; margin-bottom: 16px; display: none;
    }
    .staff-login-error.show { display: block; }
    @media (max-width: 500px) {
      .landing-options { grid-template-columns: 1fr; }
      .landing-card { padding: 28px 20px; }
      .landing-title { font-size: 22px; }
    }

    /* ===== SIDEBAR — LIGHT & FRIENDLY ===== */
    :root {
      --sidebar-w: 250px;
      --sidebar-bg: #faf6f0;
      --sidebar-border: #e8ddd0;
      --sidebar-text: #3b2313;
      --sidebar-muted: #8a6f4f;
      --sidebar-active: #a9714a;
      --sidebar-hover: rgba(169,113,74,.08);
      --body-bg: #faf6f0;
      --card-bg: #ffffff;
      --ink: #3b2313;
      --ink-soft: #7a6055;
      --border: #e8ddd0;
      --accent: #a9714a;
      --accent-dark: #8f5c39;
    }

    

    *{box-sizing:border-box;margin:0;padding:0}

    body {
      font-family: Georgia, "Iowan Old Style", "Segoe UI", serif;
      background: var(--body-bg);
      color: var(--ink);
    }

    .pos-sidebar {
      width: var(--sidebar-w);
      background: var(--sidebar-bg);
      color: var(--sidebar-text);
      display: flex;
      flex-direction: column;
      padding: 22px 16px;
      position: fixed;
      top: 0; left: 0;
      height: 100vh;
      z-index: 60;
      overflow-y: auto;
      overflow-x: hidden;
      border-right: 1px solid var(--sidebar-border);
      scrollbar-width: none;
    }
    .pos-sidebar:hover { scrollbar-width: thin; scrollbar-color: rgba(139,111,79,.25) transparent; }
    .pos-sidebar::-webkit-scrollbar { width: 5px; }
    .pos-sidebar::-webkit-scrollbar-thumb { background: transparent; border-radius: 3px; transition: background .2s ease; }
    .pos-sidebar:hover::-webkit-scrollbar-thumb { background: rgba(139,111,79,.25); }

    .logo {
      font-size: 30px;
      font-weight: 800;
      color: var(--accent);
      padding: 6px 10px 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      cursor: pointer;
    }
    .logo img { width: 60px; height: 60px; margin-bottom: 6px; }
    .logo span { font-size: 15px; font-weight: 800; letter-spacing: 0.02em; color: var(--ink); }

    .pos-nav { flex: 1; min-height: 0; overflow-y: auto; }

    .nav-section-label {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .12em;
      color: var(--sidebar-muted);
      padding: 14px 14px 6px;
      font-weight: 700;
      opacity: 0.7;
    }

    .pos-nav a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 14px;
      border-radius: 10px;
      color: var(--sidebar-text);
      font-weight: 500;
      font-size: 14px;
      margin-bottom: 2px;
      cursor: pointer;
      transition: all .15s ease;
      text-decoration: none;
    }
    .pos-nav a:hover { background: var(--sidebar-hover); color: var(--accent); }
    .pos-nav a.active { background: var(--accent); color: #fff; font-weight: 700; }

    .nav-divider { height: 1px; background: var(--sidebar-border); margin: 6px 14px; }

    /* ===== LIGHT MAIN CONTENT ===== */
    .pos-main {
      margin-left: var(--sidebar-w);
      min-height: 100vh;
      background: var(--body-bg);
      color: var(--ink);
    }

    /* Override dark style.css backgrounds for a lighter feel */
    .category-page { background: var(--body-bg); }
    .category-main { background: transparent; }
    .items-grid {}
    .items-grid .item-card {
      background: var(--card-bg) !important;
      border: 1px solid var(--border) !important;
      box-shadow: 0 2px 12px rgba(59,35,19,.04) !important;
      border-radius: 14px !important;
    }
    .items-grid .item-card:hover {
      box-shadow: 0 6px 20px rgba(59,35,19,.08) !important;
      border-color: var(--accent) !important;
    }
    .category-tabs { background: transparent !important; }
    .category-tabs .tab-btn {
      background: var(--card-bg) !important;
      border: 1.5px solid var(--border) !important;
      color: var(--ink) !important;
      font-weight: 600 !important;
    }
    .category-tabs .tab-btn.active {
      background: var(--accent) !important;
      color: #fff !important;
      border-color: var(--accent) !important;
    }
    .order-sidebar {
      background: var(--card-bg) !important;
      border: 1px solid var(--border) !important;
      border-radius: 14px !important;
      box-shadow: 0 2px 16px rgba(59,35,19,.05) !important;
    }
    .hero { background: transparent !important; }
    .hero h1 { color: var(--ink) !important; }
    .hero p { color: var(--ink-soft) !important; }
    .hero .eyebrow { color: var(--accent) !important; }
    .featured-section h2 { color: var(--accent) !important; }
    .featured-grid .featured-card {
      background: var(--card-bg) !important;
      border: 1px solid var(--border) !important;
      box-shadow: 0 2px 12px rgba(59,35,19,.04) !important;
      border-radius: 14px !important;
    }
    .featured-grid .featured-card:hover {
      box-shadow: 0 6px 20px rgba(59,35,19,.08) !important;
      border-color: var(--accent) !important;
    }
    .items-page-content { background: var(--body-bg) !important; }
    .items-full-grid .item-card {
      background: var(--card-bg) !important;
      border: 1px solid var(--border) !important;
      border-radius: 14px !important;
      box-shadow: 0 2px 12px rgba(59,35,19,.04) !important;
    }
    .btn-primary {
      background: var(--accent) !important;
      color: #fff !important;
      border-radius: 10px !important;
      font-weight: 700 !important;
    }
    .btn-primary:hover { background: var(--accent-dark) !important; }
    .btn-outline {
      background: transparent !important;
      color: var(--accent) !important;
      border: 1.5px solid var(--accent) !important;
      border-radius: 10px !important;
      font-weight: 700 !important;
    }
    .btn-place-order {
      background: var(--accent) !important;
      color: #fff !important;
      border-radius: 10px !important;
      font-weight: 700 !important;
    }

    /* Toggle button on mobile */
    .sidebar-toggle {
      display: none;
      position: fixed; top: 12px; left: 12px; z-index: 1001;
      width: 44px; height: 44px; border-radius: 10px;
      background: var(--card-bg); color: var(--accent);
      border: 1.5px solid var(--border); cursor: pointer; font-size: 20px;
      align-items: center; justify-content: center;
      box-shadow: 0 2px 10px rgba(59,35,19,.08);
    }
    .sidebar-backdrop {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.4);
      z-index: 55;
    }
    .sidebar-backdrop.show { display: block; }

    /* ===== MAIN CONTENT ===== */
    .pos-main {
      margin-left: 250px;
      min-height: 100vh;
      background: var(--cream);
    }

    @media (max-width: 820px) {
      .pos-sidebar { transform: translateX(-100%); transition: transform .3s; box-shadow: 4px 0 30px rgba(59,35,19,.1); }
      .pos-sidebar.open { transform: translateX(0); }
      .pos-main { margin-left: 0; }
      .sidebar-toggle { display: flex; }
    }

    /* Hide old navbar */
    .navbar { display: none !important; }

    /* Fix: Ensure page content is at the top */
    .page.active {
      display: block !important;
    }
    .cashier-page {
      margin-top: 0;
      padding-top: 1.5rem;
      max-width: none;
      margin-left: 0;
      margin-right: 0;
    }
    .cashier-closing {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border);
    }
    .cashier-closing-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .cashier-closing-header h3,
    .cashier-items-sold h4 { color: var(--ink); }
    .cashier-closing-header p { color: var(--ink-soft); font-size: .85rem; margin-top: .2rem; }
    .cashier-closing-summary {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
      margin-bottom: 15px;
    }
    .cashier-closing-summary .summary-card { min-width: 0; }
    .cashier-items-sold {
      margin-top: 1rem;
      padding: 1rem;
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
    }
    .cashier-items-sold h4 { margin-bottom: .75rem; }

    /* Receipt history uses complete, scannable cards instead of one compressed
       text row. The item chips keep a large order readable at a glance. */
    .cashier-receipt-list { display: grid; gap: 12px; }
    .cashier-receipt-card {
      background: #fff;
      border: 1px solid var(--border);
      border-left: 4px solid #4d8a67;
      border-radius: 12px;
      padding: 1rem 1.15rem;
      box-shadow: 0 2px 8px rgba(59,35,19,.04);
    }
    .cashier-receipt-card.is-pending { border-left-color: #c98a3d; }
    .cashier-receipt-card.is-voided { border-left-color: #bc5b54; opacity: .7; }
    .cashier-receipt-header, .cashier-receipt-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    .cashier-receipt-code { color: var(--ink); font-size: 1.05rem; font-weight: 800; letter-spacing: .02em; }
    .cashier-receipt-meta { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: .35rem; color: var(--ink-soft); font-size: .8rem; }
    .cashier-receipt-meta span + span::before { content: '•'; margin-right: .45rem; color: #c5aa8a; }
    .cashier-receipt-status { border-radius: 999px; padding: .3rem .65rem; font-size: .72rem; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; }
    .cashier-receipt-status.paid { color: #176341; background: #e6f5eb; }
    .cashier-receipt-status.pending { color: #8a5718; background: #fdf0d7; }
    .cashier-receipt-status.voided { color: #a23232; background: #fae6e5; }
    .cashier-receipt-items { margin: .9rem 0; padding: .8rem 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
    .cashier-receipt-items-label { margin-bottom: .55rem; color: var(--ink-soft); font-size: .69rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
    .cashier-receipt-item-list { display: flex; flex-wrap: wrap; gap: .45rem; }
    .cashier-receipt-item { display: inline-flex; align-items: center; gap: .35rem; max-width: 100%; padding: .35rem .55rem; border: 1px solid #ebded0; border-radius: 7px; background: #fffaf4; color: var(--ink); font-size: .79rem; line-height: 1.25; }
    .cashier-receipt-item strong { color: var(--accent-dark); white-space: nowrap; }
    .cashier-receipt-no-items { color: var(--ink-soft); font-size: .85rem; }
    .cashier-receipt-total-label { display: block; color: var(--ink-soft); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .cashier-receipt-total { display: block; margin-top: .12rem; color: var(--accent-dark); font-size: 1.12rem; font-weight: 800; }
    .cashier-receipt-actions { display: flex; align-items: center; gap: .6rem; }
    @media (max-width: 560px) {
      .cashier-receipt-card { padding: .9rem; }
      .cashier-receipt-header { align-items: flex-start; }
      .cashier-receipt-footer { align-items: flex-end; }
      .cashier-receipt-status { font-size: .66rem; }
    }

    .cashier-sold-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto auto auto;
      gap: 1rem;
      align-items: center;
      padding: .7rem 0;
      border-top: 1px solid var(--border);
      font-size: .9rem;
    }
    .cashier-sold-row span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cashier-sold-row strong { color: var(--accent-dark); white-space: nowrap; }
    .cashier-sold-row span:last-child { color: var(--ink-soft); white-space: nowrap; }
    /* Pending order queue — card style matching original cashier design */
    .cashier-queue {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
      margin-top: 1.25rem;
    }
    .cashier-order-card {
      background: var(--card-bg);
      border: 1.5px solid var(--border);
      border-radius: 14px;
      padding: 1.1rem 1.2rem;
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
      transition: border-color 0.15s, box-shadow 0.15s;
    }
    .cashier-order-card:hover {
      border-color: var(--accent);
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    .cashier-order-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .cashier-order-card-code {
      font-weight: 800;
      font-size: 1rem;
      color: var(--ink);
    }
    .cashier-order-card-type {
      font-size: 0.8rem;
      color: var(--ink-soft);
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2px 10px;
    }
    .cashier-order-card-items {
      font-size: 0.88rem;
      color: var(--ink-soft);
      line-height: 1.5;
    }
    .cashier-order-card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .7rem;
      margin-top: 0.25rem;
    }
    .cashier-order-actions { display: flex; gap: .5rem; align-items: center; }
    .cashier-order-actions button { white-space: nowrap; }
    .cashier-cancel-order-btn {
      border: 1.5px solid #c95d55;
      border-radius: 8px;
      background: #fff;
      color: #a63e37;
      font-weight: 700;
      cursor: pointer;
      padding: .43rem .68rem;
      font-size: .8rem;
    }
    .cashier-cancel-order-btn:hover { background: #fff0ee; }
    .cashier-collect-order-btn { font-size: .85rem !important; padding: .45rem .8rem !important; }
    @media (max-width: 380px) {
      .cashier-order-card-footer { align-items: flex-start; flex-direction: column; }
      .cashier-order-actions { width: 100%; }
      .cashier-order-actions button { flex: 1; }
    }
    .cashier-order-card-total {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--accent-dark);
    }
    .cashier-order-card-time {
      font-size: 0.78rem;
      color: var(--ink-soft);
      margin-bottom: 0.4rem;
    }
    @media (max-width: 650px) {
      .cashier-closing-summary { grid-template-columns: 1fr; }
      .cashier-sold-row { grid-template-columns: minmax(0, 1fr) auto; }
      .cashier-sold-row span:first-child { grid-column: 1 / -1; }
    }

    /* Payment counter: the order is checked on the left, while the cashier
       enters tendered cash and sees the live change on the right. */
    #payment-modal-overlay { padding: 20px; }
    .payment-counter-modal {
      width: min(920px, 100%);
      max-height: min(740px, calc(100vh - 40px));
      overflow: auto;
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, .9fr);
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      color: #2C1A0E;
      text-align: left;
    }
    .payment-order-panel { padding: 28px; border-right: 1px solid #eaded0; }
    .payment-entry-panel { padding: 28px; background: #fffaf2; }
    .payment-panel-heading { margin: 0; color: #2C1A0E; font-size: 1.35rem; }
    .payment-order-code { margin: 5px 0 22px; color: #7A5C3A; font-size: .92rem; font-weight: 700; }
    .payment-order-meta { display: flex; flex-wrap: wrap; gap: 8px; margin: -10px 0 18px; }
    .payment-order-meta span { border: 1px solid #e5d5bd; background: #fbf6ee; color: #704d2e; border-radius: 999px; padding: 5px 10px; font-size: .78rem; font-weight: 700; }
    .payment-detail-label { color: #7A5C3A; font-size: .76rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .payment-item-list { margin: 10px 0 18px; border-top: 1px solid #eaded0; }
    .payment-item-row { display: flex; justify-content: space-between; gap: 16px; padding: 11px 0; border-bottom: 1px solid #eaded0; color: #3c2b20; font-size: .94rem; }
    .payment-item-row span:first-child { min-width: 0; overflow-wrap: anywhere; }
    .payment-item-row strong { white-space: nowrap; color: #7A4C26; }
    .payment-empty-details { padding: 18px 0; color: #7A5C3A; font-size: .92rem; }
    .payment-total-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-top: 2px solid #2C1A0E; padding-top: 16px; font-size: 1.05rem; font-weight: 800; }
    .payment-total-row strong { font-size: 1.35rem; }
    .payment-cash-label { margin: 20px 0 8px; font-size: .92rem; font-weight: 800; }
    .payment-cash-display { background: #fff; border: 1.5px solid #D4BC8A; border-radius: 12px; padding: 14px 16px; font-size: 1.65rem; font-weight: 800; text-align: right; min-height: 2em; }
    .payment-change-card { margin: 14px 0 18px; padding: 13px 16px; border-radius: 12px; background: #e7f4e4; border: 1px solid #b7dcb0; }
    .payment-change-label { display: block; color: #34702a; font-size: .77rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .payment-change-value { display: block; margin-top: 2px; color: #245d1d; font-size: 1.6rem; font-weight: 800; text-align: right; }
    .payment-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; }
    .payment-keypad button { padding: .86rem 0; border-radius: 10px; border: none; background: #f0e8da; color: #2C1A0E; font-size: 1.1rem; font-weight: 800; cursor: pointer; }
    .payment-keypad button:hover { filter: brightness(.96); }
    .payment-keypad #payment-clear-btn { background: #e67e22; color: #fff; font-size: .92rem; }
    .payment-keypad #payment-backspace-btn { background: #c0392b; color: #fff; font-size: .92rem; }
    .payment-actions { display: flex; gap: 10px; margin-top: 20px; }
    .payment-actions button { flex: 1; padding: 11px 0; border-radius: 10px; border: none; color: #fff; font-weight: 800; cursor: pointer; }
    #payment-cancel-btn { background: #7A5C3A; }
    #payment-confirm-btn { background: #2C7A1A; }
    @media (max-width: 760px) {
      .payment-counter-modal { grid-template-columns: 1fr; max-width: 460px; }
      .payment-order-panel { border-right: 0; border-bottom: 1px solid #eaded0; padding: 22px; }
      .payment-entry-panel { padding: 22px; }
    }
  </style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../pos-responsive.css">
</head>
<body>

  <!-- ===== LANDING PAGE ===== -->
  <div class="landing-overlay" id="landingOverlay">
    <div class="landing-card" id="landingChoice" style="display:block">
      <img src="brewco-logo.svg" alt="Brew &amp; Co." style="width:90px;height:90px;filter:drop-shadow(0 4px 10px rgba(0,0,0,.15))">
      <div class="landing-title">Brew &amp; Co.</div>
      <div class="landing-subtitle">Coffee, Done Right</div>
      <div class="landing-options">
        <button class="landing-btn customer" onclick="goCustomer()">
          <div class="landing-btn-icon">
            <svg viewBox="0 0 24 24" style="width:36px;height:36px;fill:none;stroke:#256b4d;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          </div>
          <div class="landing-btn-label">Customer</div>
          <div class="landing-btn-desc">Browse the menu and place an order</div>
        </button>
        <button class="landing-btn staff" onclick="showStaffLogin()">
          <div class="landing-btn-icon">
            <svg viewBox="0 0 24 24" style="width:36px;height:36px;fill:none;stroke:#5c3a29;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          <div class="landing-btn-label">Staff</div>
          <div class="landing-btn-desc">Sign in to manage orders and sales</div>
        </button>
      </div>
    </div>
    <div class="landing-card" id="landingStaffLogin" style="display:none; max-width:440px;">
      <img src="brewco-logo.svg" alt="Brew &amp; Co." style="width:70px;height:70px;filter:drop-shadow(0 4px 10px rgba(0,0,0,.15))">
      <div class="landing-title">Staff Sign In</div>
      <div class="landing-subtitle">Cashier and Admin access only</div>
      <div class="staff-login-form">
        <div class="staff-login-error" id="staffLoginError"></div>
        <div class="form-group">
          <label for="staffEmail">Email</label>
          <input type="email" id="staffEmail" placeholder="Enter your email" autocomplete="username" />
        </div>
        <div class="form-group">
          <label for="staffPassword">Password</label>
          <div style="position:relative">
            <input type="password" id="staffPassword" placeholder="Enter your password" autocomplete="current-password" onkeydown="if(event.key==='Enter')attemptStaffLogin()" />
            <button type="button" onclick="toggleStaffPassword()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;color:#7a6055;padding:0;" title="Show/hide password"></button>
          </div>
        </div>
        <button class="btn-signin" id="staffSigninBtn" onclick="attemptStaffLogin()">Sign in</button>
      </div>
    </div>
  </div>

  <!-- ===== MOBILE SIDEBAR ===== -->
  <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
    <svg viewBox="0 0 24 24" style="width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
  </button>
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

  <!-- ===== SIDEBAR (EXACT INVENTORY STYLE) ===== -->
  <aside class="pos-sidebar" id="posSidebar">
    <a href="#" class="logo" onclick="goHome(); return false;">
      <img src="../brewco-logo.svg" alt="Brew & Co.">
      <span>Brew &amp; Co.</span>
    </a>

    <nav class="pos-nav" aria-label="Main navigation">

      <!-- OVERVIEW (always visible) -->
      <div class="nav-section-label">Overview</div>
      <a href="#" data-page="page-home" onclick="navTo('page-home'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Home
      </a>

      <!-- ACTIONS -->
      <div class="nav-section-label">Actions</div>
      <a href="#" data-page="page-category" onclick="navTo('page-category'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4zM6 1v3M10 1v3M14 1v3"/></svg>
        Menu
      </a>
      <a href="#" data-page="page-items" onclick="navTo('page-items'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
        Items
      </a>

      <!-- STAFF SECTIONS (hidden until staff login) -->
      <div class="nav-section-label" id="side-label-staff" style="display:none">Staff</div>
      <a href="#" data-page="page-pending" id="side-pending" style="display:none" onclick="navTo('page-pending'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        Pending orders <span id="side-pending-count" style="display:none;margin-left:4px;font-weight:800"></span>
      </a>
      <a href="#" data-page="page-cashier" id="side-cashier" style="display:none" onclick="navTo('page-cashier'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 14h4"/><path d="M14 14h4"/><path d="M14 18v4"/><path d="M10 18v4"/></svg>
        End-of-day sales
      </a>

      <div class="nav-section-label" id="side-label-manage" style="display:none">Manage</div>
      <a href="#" data-page="page-admin" id="side-admin" style="display:none" onclick="navTo('page-admin'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
        Admin
      </a>
      <a href="#" data-page="page-sales" id="side-sales" style="display:none" onclick="navTo('page-sales'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 4-5"/></svg>
        Sales
      </a>

      <!-- ACCOUNT (hidden for customers) -->
      <div class="nav-divider" id="side-divider" style="display:none"></div>
      <div class="nav-section-label" id="side-label-account" style="display:none">Account</div>
      <a href="#" data-page="page-account" id="side-account" style="display:none" onclick="navTo('page-account'); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
        My Profile
      </a>

      <!-- LOGOUT -->
      <div class="nav-divider"></div>
      <a href="#" onclick="logoutPos(); return false;">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Logout
      </a>
    </nav>
  </aside>

  <!-- ===== MAIN APP ===== -->
  <div class="pos-main" id="app" style="display:none;">

    <!-- HOME -->
    <div id="page-home" class="page">
      <div class="hero">
        <div class="eyebrow">Freshly Brewed Daily</div>
        <h1>Your Daily Dose of Comfort.</h1>
        <p>Serving warmth, comfort, and bold flavors in every cup.</p>
        <div class="hero-search">
          <input type="text" id="hero-search-input" placeholder="Search for a drink or food..." onkeydown="if(event.key==='Enter') handleHeroSearch()" />
          <button onclick="handleHeroSearch()">Search</button>
        </div>
        <div class="hero-buttons">
          <button class="btn-primary" onclick="navTo('page-category')">Order now</button>
          <button class="btn-outline" onclick="navTo('page-items')">View items</button>
        </div>
      </div>
      <div class="featured-section">
        <h2>Featured Items</h2>
        <div class="featured-grid" id="featured-grid"></div>
      </div>
    </div>

    <!-- MENU -->
    <div id="page-category" class="page">
      <div class="category-page">
        <div class="category-main">
          <div class="category-tabs" id="category-tabs"></div>
          <div class="items-grid" id="category-items-grid"></div>
        </div>
        <div class="order-sidebar">
          <h3>Current order</h3>
          <div class="order-items" id="order-items-list">
            <div class="empty-order">No items yet. Tap an item to add.</div>
          </div>
          <hr class="order-divider" />
          <div class="order-subtotal-row"><span>Subtotal</span><span id="order-subtotal"></span></div>
          <div class="order-vat-row"><span>VAT (12%)</span><span id="order-vat"></span></div>
          <div class="order-total"><span>Total</span><span id="order-total"></span></div>
          <div class="order-actions">
            <button class="btn-place-order" onclick="placeOrder()">Place order</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ITEMS -->
    <div id="page-items" class="page">
      <div class="items-page-content">
        <div class="items-filter">
          <div style="position:relative;display:flex;align-items:center;">
            <input id="items-search-input" type="text" placeholder="Search items..." oninput="handleCategoryFilter(document.getElementById('items-category-filter').value)" style="padding:0.5rem 2rem 0.5rem 0.85rem;border:1.5px solid #D4BC8A;border-radius:20px;font-size:0.9rem;outline:none;background:#fff;color:#2C1A0E;min-width:200px;" />
            <button onclick="document.getElementById('items-search-input').value='';handleCategoryFilter(document.getElementById('items-category-filter').value)" style="position:absolute;right:8px;background:none;border:none;cursor:pointer;color:#7A5C3A;font-size:1rem;line-height:1;padding:0;" title="Clear search">×</button>
          </div>
          <select id="items-category-filter" onchange="handleCategoryFilter(this.value)">
            <option value="">All categories</option>
          </select>
        </div>
        <div class="items-full-grid" id="items-full-grid"></div>
      </div>
    </div>

    <!-- SALES -->
    <div id="page-sales" class="page">
      <div class="sales-page-content">
        <div class="sales-header">
          <h2>Sales Dashboard</h2>
          <div class="sales-live-badge" id="sales-live-badge">● Live</div>
        </div>
        <div class="sales-summary-cards">
          <div class="summary-card"><div class="summary-label">Today's Sales</div><div class="summary-value" id="summary-today"></div><div class="summary-sub" id="summary-today-orders">0 orders</div></div>
          <div class="summary-card"><div class="summary-label">This Month</div><div class="summary-value" id="summary-month"></div><div class="summary-sub" id="summary-month-orders">0 orders</div></div>
          <div class="summary-card"><div class="summary-label">All Time</div><div class="summary-value" id="summary-all"></div><div class="summary-sub" id="summary-all-orders">0 orders</div></div>
        </div>
        <div class="sales-chart-card">
          <div class="chart-card-header">
            <span class="chart-title" id="chart-title">Daily Sales — This Month</span>
            <div class="chart-view-btns">
              <button class="chart-view-btn active" onclick="setChartView('month')">Month</button>
              <button class="chart-view-btn" onclick="setChartView('week')">Week</button>
            </div>
          </div>
          <div class="css-chart-wrap" id="sales-chart-wrap"></div>
        </div>
        <div class="sales-table-card">
          <div class="sales-search-row">
            <input type="text" id="sales-search" placeholder="Search by date or month…" oninput="filterSalesTable(this.value)" />
            <button class="btn-cancel" onclick="clearSalesSearch()">Clear</button>
          </div>
          <div class="sales-table-wrap">
            <table class="sales-table">
              <thead><tr><th>Order ID</th><th>Items</th><th>Total</th><th>Date</th></tr></thead>
              <tbody id="sales-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ADMIN -->
    <div id="page-admin" class="page">
      <div class="admin-page-content">
        <h2>Admin Panel</h2>
        <div class="admin-tabs">
          <button class="admin-tab-btn active" onclick="switchAdminTab('stock')"> Stock Manager</button>
          <button class="admin-tab-btn" onclick="switchAdminTab('menu')">🍽️ Menu Editor</button>
          <button class="admin-tab-btn" onclick="switchAdminTab('sales')">📊 Sales</button>
          <button class="admin-tab-btn" onclick="switchAdminTab('archive')">️ Archive</button>
          <button class="admin-tab-btn" onclick="switchAdminTab('staff')">👥 Staff</button>
        </div>
        <div id="admin-stock" class="admin-tab-panel active">
          <div class="admin-section-header">
            <h3>Stock Levels</h3>
            <p class="admin-section-sub">Adjust item quantities. Low stock = 5 or fewer units.</p>
          </div>
          <div class="stock-grid" id="stock-grid"></div>
        </div>
        <div id="admin-menu" class="admin-tab-panel">
          <div class="admin-section-header">
            <h3>Menu Items</h3>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
              <button class="btn-primary" onclick="openAddItem()">+ Add item</button>
              <button class="btn-outline" onclick="promptAddCategory()">+ Add category</button>
            </div>
          </div>
          <div class="menu-editor-grid" id="menu-editor-grid"></div>
        </div>
        <div id="admin-archive" class="admin-tab-panel">
          <div class="admin-section-header">
            <h3>🏷️ Archived Categories</h3>
            <p class="admin-section-sub">Categories you removed. Restore a category to bring it back.</p>
          </div>
          <div class="archive-grid" id="archived-categories-grid"><div class="archive-empty">Loading archived categories…</div></div>
          <div class="admin-section-header" style="margin-top:2rem;border-top:1.5px solid #D4BC8A;padding-top:1.5rem">
            <h3>🗂️ Archived Items</h3>
            <p class="admin-section-sub">Deleted items are stored here.</p>
          </div>
          <div class="archive-grid" id="archive-grid"><div class="archive-empty">Loading archived items…</div></div>
          <div class="admin-section-header" style="margin-top:2rem;border-top:1.5px solid #D4BC8A;padding-top:1.5rem">
            <h3>👥 Archived Staff</h3>
            <p class="admin-section-sub">Staff accounts that have been archived.</p>
          </div>
          <div class="archive-grid" id="archived-staff-grid"><div class="archive-empty">Loading archived staff…</div></div>
        </div>
        <div id="admin-staff" class="admin-tab-panel">
          <div class="admin-section-header">
            <h3>Staff Management</h3>
            <button class="btn-primary" onclick="openAddStaff()">+ Add staff</button>
          </div>
          <div class="staff-table-wrap">
            <table class="staff-table">
              <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
              <tbody id="staff-tbody"></tbody>
            </table>
          </div>
        </div>
        <div id="admin-sales" class="admin-tab-panel">
          <div class="sales-page-content" style="padding:0">
            <div class="sales-header">
              <h3>Sales Dashboard</h3>
              <div class="sales-live-badge" id="admin-sales-live-badge">● Live</div>
            </div>
            <div class="sales-summary-cards">
              <div class="summary-card"><div class="summary-label">Today's Sales</div><div class="summary-value" id="admin-summary-today"></div><div class="summary-sub" id="admin-summary-today-orders">0 orders</div></div>
              <div class="summary-card"><div class="summary-label">This Month</div><div class="summary-value" id="admin-summary-month"></div><div class="summary-sub" id="admin-summary-month-orders">0 orders</div></div>
              <div class="summary-card"><div class="summary-label">All Time</div><div class="summary-value" id="admin-summary-all"></div><div class="summary-sub" id="admin-summary-all-orders">0 orders</div></div>
            </div>
            <div class="sales-chart-card">
              <div class="chart-card-header">
                <span class="chart-title" id="admin-chart-title">Daily Sales — This Month</span>
                <div class="chart-view-btns">
                  <button class="chart-view-btn active" onclick="setAdminChartView('month')">Month</button>
                  <button class="chart-view-btn" onclick="setAdminChartView('week')">Week</button>
                </div>
              </div>
              <div class="css-chart-wrap" id="admin-sales-chart-wrap"></div>
            </div>
            <div class="sales-table-card">
              <div class="sales-search-row">
                <input type="text" id="admin-sales-search" placeholder="Search by date or month…" oninput="filterAdminSalesTable(this.value)" />
                <button class="btn-cancel" onclick="clearAdminSalesSearch()">Clear</button>
              </div>
              <div class="sales-table-wrap">
                <table class="sales-table">
                  <thead><tr><th>Order ID</th><th>Items</th><th>Total</th><th>Date</th></tr></thead>
                  <tbody id="admin-sales-tbody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ACCOUNT -->
    <div id="page-account" class="page">
      <div class="account-page-content">
        <h2>My account</h2>
        <div class="account-layout">
          <div class="account-avatar-section">
            <div class="avatar-circle" id="avatar-circle"></div>
            <div class="user-name" id="display-name">User</div>
            <div class="user-role" id="display-role">Staff</div>
            <button class="logout-btn-large" onclick="logoutPos()">Sign out</button>
          </div>
          <div class="account-form">
            <div class="form-group"><label for="account-name">Name</label><input type="text" id="account-name" placeholder="Enter name" /></div>
            <div class="form-group"><label for="account-role">Role</label><input type="text" id="account-role" readonly /></div>
            <div class="form-group full-width"><label for="account-email">Email</label><input type="email" id="account-email" placeholder="Enter email" /></div>
            <div class="form-group full-width"><label for="account-password">New password <span style="font-weight:400;color:var(--text-muted)">(leave blank to keep)</span></label>
              <div class="input-password-wrap">
                <input type="password" id="account-password" placeholder="Enter new password" />
                <button class="toggle-password" id="toggle-pass-btn" onclick="togglePasswordVisibility()"></button>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn-primary" onclick="saveAccountChanges()">Save changes</button>
              <button class="btn-cancel" onclick="navTo('page-home')">Cancel</button>
            </div>
            <div id="pin-section" style="display:none;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border)">
              <div style="font-weight:700;font-size:0.95rem;color:var(--text-dark);margin-bottom:0.75rem">🔐 Admin PIN</div>
              <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem">Your PIN is required before sensitive actions.</div>
              <div class="form-group full-width">
                <label for="account-pin">New PIN <span style="font-weight:400;color:var(--text-muted)">(4 digits)</span></label>
                <div class="input-password-wrap">
                  <input type="password" id="account-pin" placeholder="e.g. 1234" maxlength="4" inputmode="numeric" pattern="\d{4}" />
                  <button class="toggle-password" onclick="togglePinVisibility()" title="Show/hide PIN"></button>
                </div>
              </div>
              <div class="form-actions" style="margin-top:0.75rem">
                <button class="btn-primary" onclick="saveAdminPin()">Set PIN</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== CASHIER PAGE ===== -->
    <div id="page-cashier" class="page">
      <div class="cashier-page">
        <div class="cashier-header">
          <h2>📊 End-of-day sales</h2>
          <div style="display:flex;align-items:center;gap:0.75rem">
            <span class="sales-live-badge" id="cashier-live-badge">● Live</span>
            <button class="btn-outline" onclick="loadCashierDailySummary()" style="font-size:0.85rem;padding:0.35rem 0.85rem">↻ Refresh</button>
          </div>
        </div>
        <p class="cashier-sub">Today’s paid sales, direct product costs, and item quantities.</p>
          <section class="cashier-closing" aria-labelledby="cashier-closing-title">
            <div class="cashier-closing-header">
              <div>
                <h3 id="cashier-closing-title">End-of-day sales</h3>
                <p id="cashier-closing-date">Today</p>
              </div>
            </div>
            <div class="cashier-closing-summary">
              <div class="summary-card"><div class="summary-label">Paid sales</div><div class="summary-value" id="cashier-closing-revenue">₱0</div><div class="summary-sub" id="cashier-closing-orders">0 orders</div></div>
              <div class="summary-card"><div class="summary-label">Cost of goods sold</div><div class="summary-value" id="cashier-closing-cost">₱0</div><div class="summary-sub">Direct product cost</div></div>
              <div class="summary-card"><div class="summary-label">Gross gain / loss</div><div class="summary-value" id="cashier-closing-profit">₱0</div><div class="summary-sub" id="cashier-closing-profit-label">No sales yet</div></div>
            </div>

            <div class="category-tabs" id="cashier-view-tabs" style="margin-bottom:0.85rem">
              <button type="button" class="tab-btn active" onclick="selectCashierView('sold')" id="cashier-view-tab-sold">Items sold today</button>
              <button type="button" class="tab-btn" onclick="selectCashierView('receipts')" id="cashier-view-tab-receipts">Receipt history</button>
            </div>

            <div class="cashier-items-sold" id="cashier-panel-sold">
              <div id="cashier-items-sold-list"><div class="cashier-empty">Loading sales summary…</div></div>
            </div>
            <div class="cashier-items-sold" id="cashier-panel-receipts" style="display:none">
              <div id="cashier-receipts-list"><div class="cashier-empty">Loading receipts…</div></div>
            </div>
          </section>
      </div>
    </div>

    <!-- PENDING ORDERS (Kiosk-placed, awaiting payment at the Cashier) -->
    <div id="page-pending" class="page">
      <div class="cashier-page">
        <div class="cashier-header">
          <h2>🧾 Pending orders</h2>
          <div style="display:flex;align-items:center;gap:0.75rem">
            <span class="sales-live-badge" id="pending-live-badge">● Live</span>
            <button class="btn-outline" onclick="loadCashierPending()" style="font-size:0.85rem;padding:0.35rem 0.85rem">↻ Refresh</button>
          </div>
        </div>
        <p class="cashier-sub">Pending orders appear here. Click an order to collect payment.</p>
        <div class="cashier-queue" id="cashier-pending-list">
          <div class="cashier-empty">Loading pending orders…</div>
        </div>
      </div>
    </div>

  </div>

  <!-- MODAL -->
  <div class="modal-overlay" id="modal-overlay">
    <div class="modal-box" id="modal-box">
      <h3 id="modal-title">Confirm</h3>
      <p id="modal-message">Are you sure?</p>
      <div id="modal-body"></div>
      <div class="modal-actions" id="modal-actions">
        <button class="btn-primary" onclick="confirmModal()">Yes, confirm</button>
        <button class="btn-cancel" onclick="closeModal()">Cancel</button>
      </div>
    </div>
  </div>

  <!-- CHECKOUT MODAL: order type + cash-received numpad, in one step.
       Self-contained (own overlay + inline script) so it does not depend on
       app.js caching. window.openCheckoutModal(total) returns a Promise that
       resolves to { selectedType, cashResult } or null if cancelled. -->
  <div id="checkout-modal-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:10500;padding:20px;">
    <div style="background:#fff;border-radius:20px;padding:28px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center;">
      <h3 style="margin:0 0 16px;font-size:1.3rem;color:#2C1A0E">How will you have it?</h3>

      <div style="display:flex;gap:0.6rem;margin-bottom:1rem">
        <button type="button" id="checkout-type-takeout" style="flex:1;padding:0.85rem 0;border-radius:10px;border:1.5px solid #D4BC8A;background:#fff;color:#2C1A0E;font-weight:700;cursor:pointer">🥡 Take Out</button>
        <button type="button" id="checkout-type-dinein" style="flex:1;padding:0.85rem 0;border-radius:10px;border:1.5px solid #D4BC8A;background:#fff;color:#2C1A0E;font-weight:700;cursor:pointer">🍽️ Dine In</button>
      </div>

      <div style="display:flex;justify-content:space-between;font-size:1.05rem;font-weight:700;color:#2C1A0E;margin-bottom:1rem">
        <span>Total due</span><span id="checkout-total-display">₱0</span>
      </div>

      <div id="checkout-cash-section" style="text-align:left">
        <div style="font-size:1rem;font-weight:700;color:#2C1A0E;margin-bottom:0.6rem">Cash received</div>
        <div id="checkout-cash-display" style="background:#FBF6EE;border:1.5px solid #D4BC8A;border-radius:10px;padding:14px 16px;font-size:1.6rem;font-weight:700;color:#2C1A0E;text-align:right;margin-bottom:0.9rem;min-height:2em">₱0</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.6rem">
          <button type="button" class="checkout-digit-btn" data-digit="1" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">1</button>
          <button type="button" class="checkout-digit-btn" data-digit="2" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">2</button>
          <button type="button" class="checkout-digit-btn" data-digit="3" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">3</button>
          <button type="button" class="checkout-digit-btn" data-digit="4" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">4</button>
          <button type="button" class="checkout-digit-btn" data-digit="5" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">5</button>
          <button type="button" class="checkout-digit-btn" data-digit="6" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">6</button>
          <button type="button" class="checkout-digit-btn" data-digit="7" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">7</button>
          <button type="button" class="checkout-digit-btn" data-digit="8" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">8</button>
          <button type="button" class="checkout-digit-btn" data-digit="9" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">9</button>
          <button type="button" id="checkout-clear-btn" style="padding:0.95rem 0;border-radius:10px;border:none;background:#e67e22;color:#fff;font-weight:700;font-size:0.95rem;cursor:pointer">⌫ Clear</button>
          <button type="button" class="checkout-digit-btn" data-digit="0" style="padding:0.95rem 0;border-radius:10px;border:none;background:#f5efe4;font-size:1.2rem;font-weight:700;color:#2C1A0E;cursor:pointer">0</button>
          <button type="button" id="checkout-backspace-btn" style="padding:0.95rem 0;border-radius:10px;border:none;background:#c0392b;color:#fff;font-weight:700;font-size:0.95rem;cursor:pointer">← Back</button>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="button" id="checkout-cancel-btn" style="flex:1;padding:10px 0;border-radius:10px;border:none;background:#7A5C3A;color:#fff;font-weight:700;cursor:pointer">Cancel</button>
        <button type="button" id="checkout-confirm-btn" disabled style="flex:1;padding:10px 0;border-radius:10px;border:none;background:#2C7A1A;color:#fff;font-weight:700;cursor:not-allowed;opacity:0.5">✓ Confirm</button>
      </div>
    </div>
  </div>
  <script>
    // Self-contained checkout flow: order type (+ optional cash numpad) in one modal.
    // Exposed as window.openCheckoutModal(total, requireCash = true)
    //   -> Promise<{selectedType, cashResult} | null>
    // requireCash=false is used for customer-placed orders: they only pick
    // Dine In / Take Out here; cash is collected later by the cashier via
    // window.openPaymentModal() when the order is processed.
    window.openCheckoutModal = function(total, requireCash) {
      if (requireCash === undefined) requireCash = true;
      return new Promise((resolve) => {
        const overlay      = document.getElementById('checkout-modal-overlay');
        const totalDisplay = document.getElementById('checkout-total-display');
        const cashSection  = document.getElementById('checkout-cash-section');
        const cashDisplay  = document.getElementById('checkout-cash-display');
        const confirmBtn   = document.getElementById('checkout-confirm-btn');
        const cancelBtn    = document.getElementById('checkout-cancel-btn');
        const dineInBtn    = document.getElementById('checkout-type-dinein');
        const takeOutBtn   = document.getElementById('checkout-type-takeout');
        const clearBtn     = document.getElementById('checkout-clear-btn');
        const backspaceBtn = document.getElementById('checkout-backspace-btn');
        const digitBtns    = document.querySelectorAll('.checkout-digit-btn');

        let selectedType = null;
        let amountStr = '';

        totalDisplay.textContent = '₱' + Number(total).toLocaleString();
        cashSection.style.display = requireCash ? 'block' : 'none';
        dineInBtn.style.background = '#fff';  dineInBtn.style.color = '#2C1A0E';
        takeOutBtn.style.background = '#fff'; takeOutBtn.style.color = '#2C1A0E';

        function refresh() {
          if (!requireCash) {
            const sufficient = selectedType !== null;
            confirmBtn.disabled = !sufficient;
            confirmBtn.style.opacity = sufficient ? '1' : '0.5';
            confirmBtn.style.cursor = sufficient ? 'pointer' : 'not-allowed';
            return;
          }
          const amount = amountStr === '' ? 0 : parseInt(amountStr, 10);
          cashDisplay.textContent = '₱' + amount.toLocaleString();
          const sufficient = amountStr !== '' && amount >= total && selectedType !== null;
          confirmBtn.disabled = !sufficient;
          confirmBtn.style.opacity = sufficient ? '1' : '0.5';
          confirmBtn.style.cursor = sufficient ? 'pointer' : 'not-allowed';
        }

        function selectType(type, btn, otherBtn) {
          selectedType = type;
          btn.style.background = '#2C1A0E'; btn.style.color = '#EDE0C4';
          otherBtn.style.background = '#fff'; otherBtn.style.color = '#2C1A0E';
          refresh();
        }

        function pressDigit(d) {
          if (!requireCash) return;
          if (amountStr === '0') amountStr = '';
          if (amountStr.length >= 9) return;
          amountStr += d;
          refresh();
        }

        function cleanup() {
          overlay.style.display = 'none';
          dineInBtn.removeEventListener('click', onDineIn);
          takeOutBtn.removeEventListener('click', onTakeOut);
          clearBtn.removeEventListener('click', onClear);
          backspaceBtn.removeEventListener('click', onBackspace);
          cancelBtn.removeEventListener('click', onCancel);
          confirmBtn.removeEventListener('click', onConfirm);
          digitBtns.forEach(btn => btn.removeEventListener('click', onDigitClick));
          document.removeEventListener('keydown', onKeydown);
        }

        function onDineIn()  { selectType('Dine In', dineInBtn, takeOutBtn); }
        function onTakeOut() { selectType('Take Out', takeOutBtn, dineInBtn); }
        function onClear()   { amountStr = ''; refresh(); }
        function onBackspace() { amountStr = amountStr.slice(0, -1); refresh(); }
        function onDigitClick(e) { pressDigit(e.currentTarget.dataset.digit); }
        function onCancel()  { cleanup(); resolve(null); }
        function onConfirm() {
          const amount = requireCash ? parseInt(amountStr, 10) : null;
          const type = selectedType;
          cleanup();
          resolve({ selectedType: type, cashResult: amount });
        }
        function onKeydown(e) {
          const tag = document.activeElement && document.activeElement.tagName;
          if (tag === 'INPUT' || tag === 'TEXTAREA') return;
          if (requireCash && e.key >= '0' && e.key <= '9') { e.preventDefault(); pressDigit(e.key); return; }
          if (requireCash && (e.key === 'Backspace' || e.key === 'Delete')) { e.preventDefault(); onBackspace(); return; }
          if (requireCash && e.key === 'Escape') { e.preventDefault(); onClear(); return; }
          if (e.key === 'Enter' && !confirmBtn.disabled) { e.preventDefault(); onConfirm(); }
        }

        dineInBtn.addEventListener('click', onDineIn);
        takeOutBtn.addEventListener('click', onTakeOut);
        clearBtn.addEventListener('click', onClear);
        backspaceBtn.addEventListener('click', onBackspace);
        cancelBtn.addEventListener('click', onCancel);
        confirmBtn.addEventListener('click', onConfirm);
        digitBtns.forEach(btn => btn.addEventListener('click', onDigitClick));
        document.addEventListener('keydown', onKeydown);

        amountStr = '';
        selectedType = null;
        refresh();
        overlay.style.display = 'flex';
      });
    };
  </script>

  <!-- Payment counter for a Pending (Kiosk) order. The cashier reviews the
       order at left and enters payment at right; change updates live. -->
  <div id="payment-modal-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:10500;">
    <div class="payment-counter-modal" role="dialog" aria-modal="true" aria-labelledby="payment-title">
      <section class="payment-order-panel" aria-label="Order details">
        <h3 class="payment-panel-heading" id="payment-title">Order details</h3>
        <p class="payment-order-code" id="payment-order-code">Order</p>
        <div class="payment-order-meta">
          <span id="payment-order-type">Order type</span>
          <span id="payment-order-time">—</span>
        </div>
        <div class="payment-detail-label">Items ordered</div>
        <div class="payment-item-list" id="payment-item-list"></div>
        <div class="payment-total-row"><span>Total due</span><strong id="payment-total-display">₱0.00</strong></div>
      </section>

      <section class="payment-entry-panel" aria-label="Cash payment and change">
        <h3 class="payment-panel-heading">Collect payment</h3>
        <div class="payment-cash-label">Cash received</div>
        <div class="payment-cash-display" id="payment-cash-display">₱0</div>
        <div class="payment-change-card" aria-live="polite">
          <span class="payment-change-label">Change due</span>
          <strong class="payment-change-value" id="payment-change-display">₱0.00</strong>
        </div>
        <div class="payment-keypad" aria-label="Cash keypad">
          <button type="button" class="payment-digit-btn" data-digit="1">1</button>
          <button type="button" class="payment-digit-btn" data-digit="2">2</button>
          <button type="button" class="payment-digit-btn" data-digit="3">3</button>
          <button type="button" class="payment-digit-btn" data-digit="4">4</button>
          <button type="button" class="payment-digit-btn" data-digit="5">5</button>
          <button type="button" class="payment-digit-btn" data-digit="6">6</button>
          <button type="button" class="payment-digit-btn" data-digit="7">7</button>
          <button type="button" class="payment-digit-btn" data-digit="8">8</button>
          <button type="button" class="payment-digit-btn" data-digit="9">9</button>
          <button type="button" id="payment-clear-btn">⌫ Clear</button>
          <button type="button" class="payment-digit-btn" data-digit="0">0</button>
          <button type="button" id="payment-backspace-btn">← Back</button>
        </div>
        <div class="payment-actions">
          <button type="button" id="payment-cancel-btn">Cancel</button>
          <button type="button" id="payment-confirm-btn" disabled style="opacity:.5;cursor:not-allowed">✓ Confirm</button>
        </div>
      </section>
    </div>
  </div>
  <script>
    // Exposed as window.openPaymentModal(total, orderCode, details) -> Promise<number|null>
    // Details comes from the pending-order queue, so this counter is useful even
    // without opening a separate receipt screen.
    window.openPaymentModal = function(total, orderCode, details) {
      return new Promise((resolve) => {
        const overlay       = document.getElementById('payment-modal-overlay');
        const codeDisplay   = document.getElementById('payment-order-code');
        const typeDisplay   = document.getElementById('payment-order-type');
        const timeDisplay   = document.getElementById('payment-order-time');
        const itemList      = document.getElementById('payment-item-list');
        const totalDisplay  = document.getElementById('payment-total-display');
        const cashDisplay   = document.getElementById('payment-cash-display');
        const changeDisplay = document.getElementById('payment-change-display');
        const confirmBtn    = document.getElementById('payment-confirm-btn');
        const cancelBtn     = document.getElementById('payment-cancel-btn');
        const clearBtn      = document.getElementById('payment-clear-btn');
        const backspaceBtn  = document.getElementById('payment-backspace-btn');
        const digitBtns     = document.querySelectorAll('.payment-digit-btn');
        const money         = value => '₱' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        let amountStr = '';

        codeDisplay.textContent = orderCode ? `Order ${orderCode}` : 'Order details';
        typeDisplay.textContent = (details && details.orderType) || 'Order type unavailable';
        timeDisplay.textContent = (details && details.createdAt) || 'Time unavailable';
        totalDisplay.textContent = money(total);
        renderItems((details && details.itemsSummary) || '');

        function renderItems(summary) {
          const lines = String(summary).split(',').map(line => line.trim()).filter(Boolean);
          itemList.replaceChildren();
          if (!lines.length) {
            const empty = document.createElement('div');
            empty.className = 'payment-empty-details';
            empty.textContent = 'No item details available for this order.';
            itemList.append(empty);
            return;
          }
          lines.forEach(line => {
            const match = line.match(/^(.*)\s+x(\d+)$/i);
            const row = document.createElement('div');
            const name = document.createElement('span');
            const qty = document.createElement('strong');
            row.className = 'payment-item-row';
            name.textContent = match ? match[1] : line;
            qty.textContent = match ? `×${match[2]}` : '';
            row.append(name, qty);
            itemList.append(row);
          });
        }

        function refresh() {
          const amount = amountStr === '' ? 0 : parseInt(amountStr, 10);
          const change = Math.max(0, amount - Number(total));
          cashDisplay.textContent = '₱' + amount.toLocaleString();
          changeDisplay.textContent = money(change);
          const sufficient = amountStr !== '' && amount >= Number(total);
          confirmBtn.disabled = !sufficient;
          confirmBtn.style.opacity = sufficient ? '1' : '.5';
          confirmBtn.style.cursor = sufficient ? 'pointer' : 'not-allowed';
        }

        function pressDigit(d) {
          if (amountStr === '0') amountStr = '';
          if (amountStr.length >= 9) return;
          amountStr += d;
          refresh();
        }
        function cleanup() {
          overlay.style.display = 'none';
          clearBtn.removeEventListener('click', onClear);
          backspaceBtn.removeEventListener('click', onBackspace);
          cancelBtn.removeEventListener('click', onCancel);
          confirmBtn.removeEventListener('click', onConfirm);
          digitBtns.forEach(btn => btn.removeEventListener('click', onDigitClick));
          document.removeEventListener('keydown', onKeydown);
        }
        function onClear() { amountStr = ''; refresh(); }
        function onBackspace() { amountStr = amountStr.slice(0, -1); refresh(); }
        function onDigitClick(e) { pressDigit(e.currentTarget.dataset.digit); }
        function onCancel() { cleanup(); resolve(null); }
        function onConfirm() { const amount = parseInt(amountStr, 10); cleanup(); resolve(amount); }
        function onKeydown(e) {
          const tag = document.activeElement && document.activeElement.tagName;
          if (tag === 'INPUT' || tag === 'TEXTAREA') return;
          if (e.key >= '0' && e.key <= '9') { e.preventDefault(); pressDigit(e.key); return; }
          if (e.key === 'Backspace' || e.key === 'Delete') { e.preventDefault(); onBackspace(); return; }
          if (e.key === 'Escape') { e.preventDefault(); onCancel(); return; }
          if (e.key === 'Enter' && !confirmBtn.disabled) { e.preventDefault(); onConfirm(); }
        }
        clearBtn.addEventListener('click', onClear);
        backspaceBtn.addEventListener('click', onBackspace);
        cancelBtn.addEventListener('click', onCancel);
        confirmBtn.addEventListener('click', onConfirm);
        digitBtns.forEach(btn => btn.addEventListener('click', onDigitClick));
        document.addEventListener('keydown', onKeydown);
        amountStr = '';
        refresh();
        overlay.style.display = 'flex';
      });
    };
  </script>

  <div class="toast" id="toast"></div>

  <script src="js/swal-compat.js"></script>
  <script src="js/app.js"></script>
  <style>
    .stock-card-changed { border: 2px solid #D4BC8A !important; background: rgba(212, 188, 138, 0.08) !important; }
    .css-chart-wrap { display: flex; align-items: flex-end; gap: 8px; height: 220px; padding: 16px 0; }
    .css-bar-col { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 6px; }
    .css-bar { width: 100%; max-width: 40px; background: linear-gradient(180deg, #a9714a 0%, #8f5c39 100%); border-radius: 6px 6px 0 0; min-height: 4px; transition: height 0.3s ease; }
    .css-bar:hover { background: linear-gradient(180deg, #c0885a 0%, #a9714a 100%); }
    .css-bar-label { font-size: 0.7rem; color: #7A5C3A; text-align: center; white-space: nowrap; }
    .css-bar-value { font-size: 0.65rem; font-weight: 700; color: #2C1A0E; text-align: center; }
    .css-chart-empty { display: flex; align-items: center; justify-content: center; height: 220px; color: #7A5C3A; font-size: 0.9rem; }
  </style>

  <!-- ===== LANDING + SIDEBAR LOGIC ===== -->
  <script>
    let currentMode = null;
    let staffUser = null;

    const landingOverlay = document.getElementById('landingOverlay');
    const landingChoice = document.getElementById('landingChoice');
    const landingStaffLogin = document.getElementById('landingStaffLogin');
    const staffLoginError = document.getElementById('staffLoginError');
    const staffSigninBtn = document.getElementById('staffSigninBtn');

    // Hide all staff-only sidebar items (default: customer mode)
    function hideStaffSidebar() {
      ['side-label-staff','side-pending','side-cashier','side-label-manage','side-admin','side-sales','side-divider','side-label-account','side-account'].forEach(id => {
        document.getElementById(id).style.display = 'none';
      });
    }
    hideStaffSidebar();

    // ===== NAVIGATION =====
    async function navTo(pageId) {
      document.querySelectorAll('.pos-nav a[data-page]').forEach(a => {
        a.classList.toggle('active', a.dataset.page === pageId);
      });
      if (typeof showPage === 'function') await showPage(pageId);
      closeSidebar();
    }

    function goHome() { navTo('page-home'); }

    // ===== MOBILE SIDEBAR =====
    function toggleSidebar() {
      document.getElementById('posSidebar').classList.toggle('open');
      document.getElementById('sidebarBackdrop').classList.toggle('show');
    }
    function closeSidebar() {
      document.getElementById('posSidebar').classList.remove('open');
      document.getElementById('sidebarBackdrop').classList.remove('show');
    }

    // ===== CUSTOMER FLOW =====
    function goCustomer() {
      currentMode = 'customer';
      window.currentUser = null;
      hideStaffSidebar();
      landingOverlay.classList.add('hidden');
      document.getElementById('app').style.display = 'block';
      navTo('page-category');
      loadFeatured();
    }

    // ===== STAFF FLOW =====
    function showStaffLogin() {
      landingChoice.style.display = 'none';
      landingStaffLogin.style.display = 'block';
      staffLoginError.classList.remove('show');
      document.getElementById('staffEmail').value = '';
      document.getElementById('staffPassword').value = '';
      setTimeout(() => document.getElementById('staffEmail').focus(), 100);
    }

    function backToChoice() {
      landingStaffLogin.style.display = 'none';
      landingChoice.style.display = 'block';
      staffLoginError.classList.remove('show');
    }

    function toggleStaffPassword() {
      const pw = document.getElementById('staffPassword');
      pw.type = pw.type === 'password' ? 'text' : 'password';
    }

    async function enterStaffApp(loggedIn) {
      const role = (loggedIn.role || '').toLowerCase();

      staffUser = loggedIn;
      currentMode = 'staff';
      currentUser = loggedIn;

      // Show staff sidebar items based on role
      hideStaffSidebar();
      if (role === 'admin') {
        document.getElementById('side-label-staff').style.display = '';
        document.getElementById('side-pending').style.display = 'flex';
        document.getElementById('side-cashier').style.display = 'flex';
        document.getElementById('side-label-manage').style.display = '';
        document.getElementById('side-admin').style.display = 'flex';
        document.getElementById('side-sales').style.display = 'flex';
        document.getElementById('side-divider').style.display = '';
        document.getElementById('side-label-account').style.display = '';
        document.getElementById('side-account').style.display = 'flex';
      } else {
        document.getElementById('side-label-staff').style.display = '';
        document.getElementById('side-pending').style.display = 'flex';
        document.getElementById('side-cashier').style.display = 'flex';
      }

      landingOverlay.classList.add('hidden');
      document.getElementById('app').style.display = 'block';

      if (typeof applyRoleUI === 'function') applyRoleUI();

      const lastPage = typeof getLastPage === 'function' ? getLastPage() : null;
      const defaultPage = role === 'cashier' ? 'page-pending' : 'page-admin';
      const allowedPages = role === 'cashier'
        ? ['page-pending', 'page-cashier', 'page-account']
        : ['page-home', 'page-category', 'page-items', 'page-sales', 'page-admin', 'page-account', 'page-pending', 'page-cashier'];
      await navTo(lastPage && allowedPages.includes(lastPage) ? lastPage : defaultPage);
    }

    function showSavedPageShell() {
      const savedPage = typeof getLastPage === 'function' ? getLastPage() : null;
      const pageIds = ['page-home', 'page-category', 'page-items', 'page-sales', 'page-admin', 'page-account', 'page-cashier', 'page-pending'];
      const pageId = pageIds.includes(savedPage) ? savedPage : 'page-home';
      const page = document.getElementById(pageId);
      if (!page) return;

      document.getElementById('app').style.display = 'block';
      document.querySelectorAll('.page').forEach(item => {
        item.classList.remove('active');
        item.style.display = 'none';
      });
      page.classList.add('active');
      page.style.display = '';
    }

    // On load, check whether the session cookie still identifies a logged-in staff
    // user (PHP sessions survive a refresh even though window.currentUser does not,
    // since that's just an in-memory JS variable that resets on every page load).
    async function restoreSession() {
      showSavedPageShell();
      try {
        const r = await fetch('api/account.php?action=me', { credentials: 'same-origin' });
        if (!r.ok) {
          document.getElementById('app').style.display = 'none';
          landingChoice.style.display = 'block';
          landingStaffLogin.style.display = 'none';
          return; // 401: no active session — show the landing choice as normal
        }
        const d = await r.json();
        if (d.success && d.data) {
          const role = (d.data.role || '').toLowerCase();
          if (role === 'cashier' || role === 'admin') {
            await enterStaffApp(d.data);
          } else {
            landingChoice.style.display = 'block';
            landingStaffLogin.style.display = 'none';
          }
        } else {
          landingChoice.style.display = 'block';
          landingStaffLogin.style.display = 'none';
        }
      } catch (e) {
        // Backend unreachable — fall through to the normal landing choice.
        landingChoice.style.display = 'block';
        landingStaffLogin.style.display = 'none';
      }
    }
    restoreSession();

    async function attemptStaffLogin() {
      const email = document.getElementById('staffEmail').value.trim();
      const password = document.getElementById('staffPassword').value;
      if (!email || !password) {
        staffLoginError.textContent = 'Please enter email and password.';
        staffLoginError.classList.add('show');
        return;
      }
      staffSigninBtn.disabled = true;
      staffLoginError.classList.remove('show');

      try {
        const r = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ username: email, password })
        });
        const d = await r.json();

        if (!d.success) {
          staffLoginError.textContent = d.message || 'Invalid email or password.';
          staffLoginError.classList.add('show');
          staffSigninBtn.disabled = false;
          return;
        }

        const loggedIn = d.data;
        const role = (loggedIn.role || '').toLowerCase();
        if (role !== 'cashier' && role !== 'admin') {
          staffLoginError.textContent = 'Only Cashier and Admin accounts can sign in here.';
          staffLoginError.classList.add('show');
          staffSigninBtn.disabled = false;
          return;
        }

        await enterStaffApp(loggedIn);

      } catch (e) {
        staffLoginError.textContent = 'Cannot connect to backend.';
        staffLoginError.classList.add('show');
        staffSigninBtn.disabled = false;
      }
    }

    // ===== LOGOUT =====
    function logoutPos() {
      if (currentMode === 'customer') {
        currentMode = null;
        document.getElementById('app').style.display = 'none';
        landingOverlay.classList.remove('hidden');
        landingChoice.style.display = 'block';
        landingStaffLogin.style.display = 'none';
        hideStaffSidebar();
        navTo('page-home');
        return;
      }
      fetch('api/logout.php', { credentials: 'same-origin' }).finally(() => {
        currentMode = null;
        staffUser = null;
        currentUser = null;
        document.getElementById('app').style.display = 'none';
        landingOverlay.classList.remove('hidden');
        landingChoice.style.display = 'block';
        landingStaffLogin.style.display = 'none';
        hideStaffSidebar();
        navTo('page-home');
      });
    }
  </script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../pos-responsive.js" defer></script>
</body>
</html>