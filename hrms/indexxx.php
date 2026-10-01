<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HRMS Staff Dashboard</title>
<style>
:root{--espresso:#3b2313;--cream:#faf6f0;--cream-soft:#f5ede3;--tan:#e8ddd0;--gold:#a9714a;--gold-dark:#8f5c39;--ink:#3b2313;--ink-soft:#7a6055;--line:#e8ddd0;--bg:#faf6f0;--sidebar-w:250px;--s:#fff;--g:#4f7a4a;--gb:#e2ecdf;--r:#a8492f;--rb:#f6e3dc;--y:#fff8ea}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);font-family:Georgia,"Iowan Old Style",serif;color:var(--ink);display:flex;min-height:100vh}

.sidebar{width:var(--sidebar-w);background:var(--cream);color:var(--ink);display:flex;flex-direction:column;padding:22px 16px;position:fixed;top:0;left:0;height:100vh;z-index:60;overflow-y:auto;overflow-x:hidden;border-right:1px solid var(--line);scrollbar-width:none}
.sidebar:hover{scrollbar-width:thin;scrollbar-color:rgba(139,111,79,.25) transparent}
.sidebar::-webkit-scrollbar{width:5px}
.sidebar::-webkit-scrollbar-thumb{background:transparent;border-radius:3px;transition:background .2s}
.sidebar:hover::-webkit-scrollbar-thumb{background:rgba(139,111,79,.25)}

.sidebar-brand{display:flex;align-items:center;gap:10px;padding:6px 10px 20px}
.sidebar-logo{width:36px;height:36px;border-radius:50%;background:var(--gold);display:flex;align-items:center;justify-content:center}
.sidebar-logo-dot{width:12px;height:12px;border-radius:50%;background:#fff}
.sidebar-title{font-size:16px;font-weight:900;letter-spacing:.01em;color:var(--ink)}

.sidebar-user{display:flex;align-items:center;gap:10px;padding:12px 10px;margin-bottom:4px;border-bottom:1px solid var(--line)}
.sidebar-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0}
.sidebar-user-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-user-role{font-size:11px;color:var(--ink-soft)}

.sidebar-section{font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--ink-soft);opacity:.6;padding:14px 10px 6px}
.sidebar-divider{height:1px;background:var(--line);margin:6px 10px}

.sidebar-nav{flex:1;min-height:0;overflow-y:auto}
.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14px;margin-bottom:2px;cursor:pointer;transition:all .15s;text-decoration:none}
.sidebar-nav a:hover{background:rgba(169,113,74,.08);color:var(--gold)}
.sidebar-nav a.active{background:var(--gold);color:#fff;font-weight:700}
.sidebar-nav a .nav-icon{width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}

.sidebar-bottom{padding-top:10px;border-top:1px solid var(--line);margin-top:6px}
.sidebar-bottom a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14px;cursor:pointer;text-decoration:none;transition:all .15s}
.sidebar-bottom a:hover{background:rgba(169,113,74,.08);color:var(--gold)}

.sidebar-toggle{display:none;position:fixed;top:12px;left:12px;z-index:1001;width:44px;height:44px;border-radius:10px;background:var(--bg);color:var(--gold);border:1.5px solid var(--line);cursor:pointer;font-size:20px;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(59,35,19,.08)}
.sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:55}
.sidebar-backdrop.show{display:block}

.main{margin-left:var(--sidebar-w);flex:1;padding:24px;min-height:100vh}
.main h1{font-size:24px;margin-bottom:4px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:20px}
.card{background:var(--s);border:1px solid var(--line);border-radius:14px;padding:18px;min-width:0}
.full{grid-column:1/-1}
.row{display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding:9px 0;gap:12px}
.row label{font-weight:900;color:var(--ink-soft)}
button{border:0;background:var(--gold);color:#fff;border-radius:8px;padding:10px 14px;font:inherit;font-weight:900;cursor:pointer}
button:disabled{opacity:.6;cursor:not-allowed}
input,select,textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--cream);font:inherit;margin:5px 0 10px}
video,canvas{width:100%;max-width:360px;border-radius:12px;border:1px solid var(--line);background:#000}
.msg{font-weight:900}
.ok{color:var(--g)}
.err{color:var(--r)}
.badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:900}
.hist-section{background:#3b2313;color:#faf6f0;font-weight:900;font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;padding:8px 12px;margin:14px 0 0;border-radius:8px 8px 0 0}
.Pending{background:var(--y);color:var(--gold)}
.Approved{background:var(--gb);color:var(--g)}
.Rejected{background:var(--rb);color:var(--r)}
.Cancelled{background:#eee;color:#777}
.sched-grid{display:grid;grid-template-columns:repeat(7,1fr);border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-top:12px}
.day{border-right:1px solid var(--line);min-height:95px;background:#fbf6ec}
.day:last-child{border-right:0}
.day h3{margin:0;padding:10px;background:var(--espresso);color:var(--cream);font-size:13px;text-align:center}
.day .body{padding:10px;text-align:center}
.work{font-weight:900;color:var(--g)}
.off{color:var(--ink-soft)}
.p-row{display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding:10px}
.net{background:var(--gb);color:var(--g);font-weight:900}
.att-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
.att-card{border:1px solid var(--line);border-radius:12px;padding:12px;background:#fbf6ec}
.att-card h3{margin:0 0 8px;font-size:15px}
.att-photo{width:100%;max-width:230px;height:150px;object-fit:cover;border-radius:10px;border:1px solid var(--line);background:#eee;display:block}
.att-total{margin-top:12px;background:var(--gb);color:var(--g);border:1px solid #b9d4b4;border-radius:10px;padding:10px;font-weight:900}
.no-photo{color:var(--ink-soft);font-size:13px}
.att-time{font-weight:900;margin:8px 0 0}

.view{display:none}
.view.active{display:block}

@media(max-width:850px){
  .grid{grid-template-columns:1fr}
  .full{grid-column:auto}
  .sched-grid{grid-template-columns:1fr}
  .day{border-right:0;border-bottom:1px solid var(--line)}
}
@media(max-width:820px){
  .sidebar{transform:translateX(-100%);transition:transform .3s;box-shadow:4px 0 30px rgba(59,35,19,.1)}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .sidebar-toggle{display:flex}
}
/* ===== Formal letter uploader + signature pad ===== */
.dropzone{border:2px dashed var(--line);border-radius:12px;background:var(--cream,#faf6f0);padding:16px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;margin:10px 0}
.dropzone:hover,.dropzone.drag{border-color:var(--accent,#a9714a);background:#f4ece1}
.dropzone .dz-icon{font-size:24px;margin-bottom:4px}
.dropzone .dz-main{font-size:13px;font-weight:700}
.dropzone .dz-hint{font-size:12px;color:var(--ink-soft,#7a6055);margin-top:2px}
.file-chip{display:flex;align-items:center;gap:8px;margin:8px 0;background:#fff;border:1px solid var(--line);border-radius:10px;padding:8px 12px;font-size:13px}
.file-chip .fname{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:700}
.file-chip .fsize{color:var(--ink-soft,#7a6055);font-size:12px}
.file-chip button{border:none;background:#fce4dc;color:#a8492f;border-radius:8px;padding:4px 10px;font-size:12px;font-weight:700;cursor:pointer}
.req-label{display:block;font-weight:700;margin:10px 0 6px;font-size:13px}
.letter-paper{background:#fffdf8;border:1px solid #e8ddd0;border-radius:10px;padding:20px 24px;font-family:Georgia,serif;font-size:13px;line-height:1.7;color:#22303f;white-space:pre-wrap;max-height:240px;overflow:auto}
.letter-paper.full{max-height:65vh}
.sig-overlay{position:fixed;inset:0;background:rgba(59,35,19,.45);display:flex;align-items:center;justify-content:center;z-index:999;padding:20px}
.sig-box{background:#fff;border-radius:16px;padding:24px;max-width:560px;width:100%;box-shadow:0 12px 40px rgba(59,35,19,.25)}
.sig-box h3{font-size:18px;font-weight:800;margin-bottom:4px;color:var(--accent,#a9714a)}
.sig-box .sig-sub{color:var(--ink-soft,#7a6055);font-size:13px;margin-bottom:12px}
.sig-as{font-size:13px;margin-bottom:8px}
.sig-as b{color:var(--accent,#a9714a)}
#sigPad{width:100%;height:180px;border:1.5px solid var(--line);border-radius:12px;background:#fff;cursor:crosshair;touch-action:none;display:block}
.sig-hint{font-size:12px;color:var(--ink-soft,#7a6055);margin-top:6px}
.sig-actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
.doc-link{display:inline-block;padding:2px 8px;border-radius:8px;font-size:12px;font-weight:700;background:#eee6da;color:#8a6f4f;text-decoration:none;margin-left:6px}
.doc-link.sig{background:#e2ecdf;color:#4f7a4a}
.sig-grid{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
.sig-grid .sig-left{flex:1;min-width:240px}
.cam-box{width:170px;flex:none;text-align:center}
.cam-box video{width:170px;height:128px;object-fit:cover;border-radius:12px;border:1.5px solid var(--line);background:#000;display:block}
.cam-box .cam-label{font-size:11px;color:var(--ink-soft,#7a6055);margin-top:5px;font-weight:700}
.cam-err{color:#a8492f;font-size:12px;margin-top:6px;display:none}
.sig-name-input{width:100%;padding:9px 12px;border:1.5px solid var(--line);border-radius:10px;font:inherit;margin:4px 0 10px;background:#faf6f0}
.sig-name-input.bad{border-color:#a8492f}
.reg-sig-box{border:1.5px dashed var(--line);border-radius:10px;padding:8px;background:#fff;min-height:70px;display:flex;align-items:center;justify-content:center;font-size:12.5px;color:var(--ink-soft,#7a6055);text-align:center;margin-bottom:10px}
.reg-sig-box img{max-height:80px;max-width:100%;object-fit:contain}
.photo-thumb{width:34px;height:34px;object-fit:cover;border-radius:50%;border:2px solid var(--line);vertical-align:middle;cursor:pointer;background:#fff;margin-left:6px}
</style>
<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
<link rel="stylesheet" href="../final-operations.css">
</head>
<body>

<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
  <svg viewBox="0 0 24 24" style="width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="../brewco-logo.svg" alt="Brew &amp; Co." style="width:36px;height:36px;border-radius:50%;background:var(--cream);padding:4px">
    <div class="sidebar-title">Brew &amp; Co.</div>
  </div>

  <div class="sidebar-user">
    <div class="sidebar-avatar" id="sidebarAvatar">S</div>
    <div>
      <div class="sidebar-user-name" id="sidebarName">Staff</div>
      <div class="sidebar-user-role" id="sidebarRole">Employee</div>
    </div>
  </div>

  <nav class="sidebar-nav" id="sidebarNav">
    <div class="sidebar-section">Main</div>
    <a href="#" data-view="dashboard" onclick="showView('dashboard');return false;">
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a href="#" data-view="schedule" onclick="showView('schedule');return false;">
      <span class="nav-icon">📅</span> My Schedule
    </a>
    <a href="#" data-view="attendance" onclick="showView('attendance');return false;">
      <span class="nav-icon">🕐</span> Attendance
    </a>

    <div class="sidebar-divider"></div>
    <div class="sidebar-section">Requests</div>
    <a href="#" data-view="requests" onclick="showView('requests');return false;">
      <span class="nav-icon"></span> My Requests
    </a>
    <a href="#" data-view="payslip" onclick="showView('payslip');return false;">
      <span class="nav-icon">💰</span> Payslip
    </a>

    <div class="sidebar-divider"></div>
    <a href="#" data-view="notifications" onclick="showView('notifications');return false;">
      <span class="nav-icon">🔔</span> Notifications
    </a>
    <a href="#" data-view="profile" onclick="showView('profile');return false;">
      <span class="nav-icon">✍️</span> Profile & Signature
    </a>
  </nav>

  <div class="sidebar-bottom">
    <a href="#" onclick="doLogout();return false;">
      <span class="nav-icon"></span> Logout
    </a>
  </div>
</aside>

<div class="main">

  <!-- DASHBOARD VIEW -->
  <div id="view-dashboard" class="view active">
    <h1 id="title">Staff Dashboard</h1>
    <p style="color:var(--ink-soft);font-size:14px">Welcome back — here's your overview.</p>
    <div class="grid">
      <section class="card full">
        <h2 style="font-size:17px;margin-bottom:12px">My Profile</h2>
        <div id="profile">Loading...</div>
      </section>
      <section class="card full">
        <h2 style="font-size:17px;margin-bottom:12px">My Weekly Schedule</h2>
        <div id="weeklySchedule">Loading schedule...</div>
      </section>
      <section class="card">
        <h2 style="font-size:17px;margin-bottom:12px">Notifications</h2>
        <div id="notes">Loading...</div>
      </section>
      <section class="card">
        <h2 style="font-size:17px;margin-bottom:12px">My Latest Payslip</h2>
        <div id="payroll">No payslip yet.</div>
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
          <button onclick="downloadPayslipImage()">⬇ Download Payslip</button>
          <button onclick="downloadPayslipDocx()">📄 Word (.docx)</button>
          <button onclick="printPayslip()">🖨 Print / Save as PDF (payslip only)</button>
        </div>
      </section>
    </div>
  </div>

  <!-- SCHEDULE VIEW -->
  <div id="view-schedule" class="view">
    <h1>My Schedule</h1>
    <p style="color:var(--ink-soft);font-size:14px">Your weekly work schedule.</p>
    <div class="grid">
      <section class="card full">
        <h2 style="font-size:17px;margin-bottom:12px">Weekly Schedule</h2>
        <div id="weeklySchedule2">Loading...</div>
      </section>
    </div>
  </div>

  <!-- ATTENDANCE VIEW -->
  <div id="view-attendance" class="view">
    <h1>Attendance</h1>
    <p style="color:var(--ink-soft);font-size:14px">Time in and time out with camera.</p>
    <div class="grid">
      <section class="card">
        <h2 style="font-size:17px;margin-bottom:12px">Time In / Time Out</h2>
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display:none"></canvas>
        <p style="margin-top:12px"><button id="timeInBtn" onclick="att('time_in')">Time In</button> <button id="timeOutBtn" onclick="att('time_out')">Time Out</button></p>
        <p class="msg" id="attMsg"></p>
        <div id="todayAttendance"></div>
      </section>
    </div>
  </div>

  <!-- REQUESTS VIEW -->
  <div id="view-requests" class="view">
    <h1>My Requests</h1>
    <p style="color:var(--ink-soft);font-size:14px">Submit and view your requests.</p>
    <div class="grid">
      <section class="card">
        <h2 style="font-size:17px;margin-bottom:12px">Submit Request</h2>
        <label style="display:block;font-weight:700;margin-bottom:6px;font-size:13px">Request type</label>
        <select id="requestType" onchange="toggleRequestForm()">
          <option value="General">General Request (goes to Superadmin)</option>
          <option value="Leave">Leave Request (goes to Admin/HR)</option>
        </select>
        <div id="generalFields"><textarea id="generalDetail" placeholder="Write your general request..." style="min-height:80px"></textarea></div>
        <div id="leaveFields" style="display:none">
          <select id="leaveType"><option>Paid</option><option>Unpaid</option></select>
          <input id="start" type="date" placeholder="Start date">
          <input id="end" type="date" placeholder="End date">
          <textarea id="reason" placeholder="Reason for leave" style="min-height:60px"></textarea>
        </div>
        <label class="req-label">Formal Letter (auto-generated) <span style="color:#a8492f">*</span></label>
        <div class="letter-paper" id="letterPreview"></div>
        <div style="margin:8px 0 2px">
          <button type="button" class="btn-secondary" id="letterViewBtn">👁 View full letter</button>
        </div>
        <p style="color:#7a6055;font-size:12px;margin:6px 0 12px">The letter writes itself from your answers above and is saved as a Word document (.docx). When you sign, your signature is placed on the letter above your full name.</p>
        <button onclick="sendRequest()">Submit Request → Sign</button>
        <p class="msg" id="requestMsg"></p>
      </section>
      <section class="card full">
        <h2 style="font-size:17px;margin-bottom:12px">Request History</h2>
        <div id="myRequests">Loading...</div>
      </section>
    </div>
  </div>

  <!-- PAYSLIP VIEW -->
  <div id="view-payslip" class="view">
    <h1>Payslip</h1>
    <p style="color:var(--ink-soft);font-size:14px">Your latest payslip.</p>
    <div class="grid">
      <section class="card full">
        <h2 style="font-size:17px;margin-bottom:12px">Latest Payslip</h2>
        <div id="payroll2">No payslip yet.</div>
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
          <button onclick="downloadPayslipImage()">⬇ Download Payslip</button>
          <button onclick="downloadPayslipDocx()">📄 Word (.docx)</button>
          <button onclick="printPayslip()">🖨 Print / Save as PDF (payslip only)</button>
        </div>
      </section>
    </div>
  </div>

  <!-- NOTIFICATIONS VIEW -->
  <div id="view-notifications" class="view">
    <h1>Notifications</h1>
    <p style="color:var(--ink-soft);font-size:14px">Recent notifications.</p>
    <div class="grid">
      <section class="card full">
        <div id="notes2">Loading...</div>
      </section>
    </div>
  </div>

  <!-- PROFILE VIEW -->
  <div id="view-profile" class="view">
    <h1>My Profile</h1>
    <p style="color:var(--ink-soft);font-size:14px">Your account and your registered signature.</p>
    <div class="grid">
      <section class="card full">
        <h2 style="font-size:17px;margin-bottom:12px">Registered Signature</h2>
        <p style="color:var(--ink-soft);font-size:13px;margin-bottom:12px">This signature verifies every request you sign. It can be updated <b>once a month</b>.</p>
        <p class="msg" id="regSigAlert"></p>
        <div class="reg-sig-box" id="regSigPreview">Loading…</div>
        <p style="color:var(--ink-soft);font-size:12px;margin:8px 0" id="regSigLock"></p>
        <div style="position:relative;margin:0"><canvas id="regSigPad" width="1000" height="300" style="pointer-events:none;width:100%;height:150px;background:#fff;border:1.5px solid var(--line);border-radius:12px;cursor:crosshair;touch-action:none;display:block"></canvas><div id="regSigGate" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.65);border-radius:10px"><button type="button" class="logout" style="background:var(--accent);border-color:var(--accent);color:#fff" id="regSigGateBtn">✍️ Confirm &amp; Begin E-Sign</button></div></div>
        <div style="margin-top:12px;display:flex;gap:10px">
          <button id="regSigUpdate">Update Signature</button>
          <button type="button" id="regSigClear">Clear</button>
        </div>
      </section>
    </div>
  </div>

</div>

<!-- Signature modal: staff must sign before the request is sent -->
<div class="sig-overlay" id="letterModal" style="display:none">
  <div class="sig-box" style="max-width:720px">
    <h3>Formal Letter Preview</h3>
    <div class="sig-sub">This is the letter that will be saved with your request, signature included once you sign.</div>
    <div class="letter-paper full" id="letterPreviewFull"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn-secondary" onclick="document.getElementById('letterModal').style.display='none'">Close</button>
    </div>
  </div>
</div>
<div class="sig-overlay" id="sigModal" style="display:none">
  <div class="sig-box">
    <h3>Sign Your Request</h3>
    <p class="sig-sub">Review your attached letter, then draw your signature below. The request is only sent after you sign.</p>
    <p class="sig-as">Signing as: <b id="sigAs">—</b></p>
    <div class="sig-grid">
      <div class="sig-left">
        <label class="req-label" style="margin:0 0 4px">Your registered signature — draw it the same way</label>
        <div class="reg-sig-box" id="regSigBox">Loading…</div>
        <canvas id="sigPad" width="1000" height="360"></canvas>
        <div class="sig-hint">Draw your signature with your mouse, finger, or stylus.</div>
      </div>
      <div class="cam-box">
        <video id="sigCam" autoplay playsinline muted></video>
        <div class="cam-label">📸 Security photo (auto-captured when you sign)</div>
        <div class="cam-err" id="camErr">Camera access is required for signed requests.</div>
      </div>
    </div>
    <p class="msg" id="sigMsg"></p>
    <div class="sig-actions">
      <button id="sigSubmit" disabled>✍️ Sign &amp; Send Request</button>
      <button type="button" id="sigClear">Clear Signature</button>
      <button type="button" id="sigCancel">Cancel</button>
    </div>
  </div>
</div>

<script src="../alerts.js"></script>
<script src="js/letter-gen.js"></script>
<script>
function peso(n){return '₱'+Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2})}
function fmt(t){if(!t)return'--:--';const[h,m]=t.split(':').map(Number),d=new Date();d.setHours(h||0,m||0,0,0);return d.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})}
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
function fmtDateTime(dt){return dt?new Date(dt).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'}):'—'}
function minutesBetween(a,b){if(!a||!b)return 0;return Math.max(0,Math.round((new Date(b)-new Date(a))/60000))}
function hm(min){min=Number(min||0);return `${Math.floor(min/60)}h ${min%60}m`}
function photoTag(path){return path?`<a href="${path}" target="_blank"><img class="att-photo" src="${path}" alt="attendance photo" onerror="this.closest('a').outerHTML='<div class=&quot;no-photo&quot;>Photo missing</div>'"></a>`:'<div class="no-photo">No photo yet</div>'}
function prow(l,v,c=''){return `<div class="p-row ${c}"><label>${l}</label><b>${peso(v)}</b></div>`}
function parseDays(days){if(!days)return[];return days.split(',').map(x=>x.trim()).filter(Boolean)}

let STAFF=null, SCHEDULE=null;

async function load(){
  let r = await fetch('api/staff.php',{credentials:'same-origin'}), d = await r.json();
  if (!d.success) { location='login.php'; return; }
  let e = d.employee, p = d.payroll||{}, s = d.schedule;
  STAFF = e; SCHEDULE = s;

  // Update sidebar
  const initials = (e.name||'S').split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
  document.getElementById('sidebarAvatar').textContent = initials;
  document.getElementById('sidebarName').textContent = e.name || 'Staff';
  if (window.LetterGen) refreshLetterPreview();
  document.getElementById('sidebarRole').textContent = e.role || 'Employee';

  title.textContent = 'Welcome, ' + e.name;
  profile.innerHTML = `<div class="row"><label>Name</label><b>${esc(e.name)}</b></div><div class="row"><label>Email</label><b>${esc(e.email)}</b></div><div class="row"><label>Role</label><b>${esc(e.role)}</b></div><div class="row"><label>Work hours</label><b>8 hours/day</b></div>`;

  renderScheduleEl('weeklySchedule', s);
  renderScheduleEl('weeklySchedule2', s);

  const gross = Number(p.gross||0);
  window.PAYSLIP = {
    meta: { company: 'Brew & Co. Coffee Shop', name: e.name, role: e.role, email: e.email },
    period: p.payroll_period || p.period || '',
    released: p.released_at || '',
    rows: gross > 0 ? [
      { label: 'Gross Pay', amount: p.gross },
      { label: 'Less: SSS', amount: p.sss },
      { label: 'Less: PhilHealth', amount: p.philhealth },
      { label: 'Less: Pag-IBIG', amount: p.pagibig },
      { label: 'Less: Other deductions', amount: p.other_deductions },
      { label: 'Total deductions', amount: p.deductions },
      { label: 'NET PAY', amount: (p.net_pay || p.salary), net: true }
    ] : null
  };
  const payslipHtml = gross > 0
    ? `${prow('Gross Pay',p.gross)}${prow('SSS',p.sss)}${prow('PhilHealth',p.philhealth)}${prow('Pag-IBIG',p.pagibig)}${prow('Other deductions',p.other_deductions)}${prow('Total deductions',p.deductions)}${prow('Net Pay',p.net_pay||p.salary,'net')}`
    : 'No released payslip yet.';
  payroll.innerHTML = payslipHtml;
  payroll2.innerHTML = payslipHtml;

  loadNotes();
  loadNotes2();
  loadRequests();
  loadTodayAttendance();
  startCamera();
}

function payslipMoney(v){ return '₱' + Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }
function downloadPayslipImage(){
  const ps = window.PAYSLIP;
  if (!ps || !ps.rows) { swalAlert('No released payslip yet.', 'info'); return; }
  const W = 1240, H = 1754, M = 90;                 // A4-ish page @ ~150dpi
  const c = document.createElement('canvas'); c.width = W; c.height = H;
  const x = c.getContext('2d');
  const ink = '#33261d', line = '#9a8a7a', soft = '#d8cbb8';
  x.fillStyle = '#ffffff'; x.fillRect(0, 0, W, H);
  x.textBaseline = 'alphabetic'; x.textAlign = 'center';
  x.fillStyle = '#2C1A0E'; x.font = 'bold 34px Georgia, serif';
  x.fillText('BREW & CO. COFFEE SHOP', W / 2, 150);
  x.fillStyle = '#7a6055'; x.font = 'bold 24px Georgia, serif';
  x.fillText('P A Y S L I P', W / 2, 192);
  x.font = '19px Georgia, serif';
  x.fillText('Pay period: ' + (ps.period || '—'), W / 2, 224);
  x.strokeStyle = line; x.lineWidth = 2;
  x.beginPath(); x.moveTo(M, 252); x.lineTo(W - M, 252); x.stroke();
  x.textAlign = 'left'; x.font = '20px Georgia, serif'; x.fillStyle = ink;
  x.fillText('Employee: ' + (ps.meta.name || ''), M, 296);
  x.fillText('Position: ' + (ps.meta.role || ''), M + 560, 296);
  x.fillText('Email: ' + (ps.meta.email || ''), M, 328);
  x.fillText('Released: ' + (ps.released || '—'), M + 560, 328);
  // ---- table (Layout A: bordered, shaded header + shaded NET row) ----
  const tw = W - M * 2, tx = M; let ty = 366;
  const hh = 50;
  x.fillStyle = '#efe3d3'; x.fillRect(tx, ty, tw, hh);
  x.strokeStyle = line; x.lineWidth = 1.5; x.strokeRect(tx, ty, tw, hh);
  x.fillStyle = ink; x.font = 'bold 20px Georgia, serif';
  x.fillText('Description', tx + 18, ty + 32);
  x.textAlign = 'right'; x.fillText('Amount (PHP)', tx + tw - 18, ty + 32);
  ty += hh;
  ps.rows.forEach(r => {
    const rh = r.net ? 60 : 46;
    if (r.net) { x.fillStyle = '#f3ead9'; x.fillRect(tx, ty, tw, rh); }
    x.strokeStyle = soft; x.lineWidth = 1;
    x.beginPath(); x.moveTo(tx, ty + rh); x.lineTo(tx + tw, ty + rh); x.stroke();
    x.fillStyle = ink;
    x.textAlign = 'left';  x.font = (r.net ? 'bold 23px' : '20px') + ' Georgia, serif';
    x.fillText(r.label, tx + 18, ty + (r.net ? 38 : 30));
    x.textAlign = 'right';
    x.fillText(r.net ? payslipMoney(r.amount) : Number(r.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), tx + tw - 18, ty + (r.net ? 38 : 30));
    ty += rh;
  });
  x.strokeStyle = line; x.lineWidth = 2; x.strokeRect(tx, 366, tw, ty - 366);
  x.textAlign = 'center'; x.fillStyle = '#8a7a6a'; x.font = '16px Georgia, serif';
  x.fillText('System-generated by Brew & Co. HRMS — valid without a handwritten signature.', W / 2, ty + 56);
  const a = document.createElement('a');
  a.href = c.toDataURL('image/png');
  a.download = 'Payslip_' + String(ps.meta.name || 'staff').replace(/[^A-Za-z0-9_-]+/g, '_') + (ps.period ? '_' + String(ps.period).replace(/[^A-Za-z0-9_-]+/g, '-') : '') + '.png';
  document.body.appendChild(a); a.click(); a.remove();
}
function downloadPayslipDocx(){
  const ps = window.PAYSLIP;
  if (!ps || !ps.rows) { swalAlert('No released payslip yet.', 'info'); return; }
  const blob = LetterGen.buildPayslipDocx(ps.meta, ps.rows.map(r => ({ label: r.label, amount: payslipMoney(r.amount), net: r.net })));
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'Payslip_' + String(ps.meta.name||'staff').replace(/[^A-Za-z0-9_-]+/g,'_') + (ps.period ? '_' + String(ps.period).replace(/[^A-Za-z0-9_-]+/g,'-') : '') + '.docx';
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}
function printPayslip(){
  const ps = window.PAYSLIP;
  if (!ps || !ps.rows) { swalAlert('No released payslip yet.', 'info'); return; }
  const w = window.open('', '_blank');
  if (!w) { swalAlert('Please allow pop-ups to print your payslip.', 'error'); return; }
  const rowsHtml = ps.rows.map(r => '<tr' + (r.net ? ' class="net"' : '') + '><td>' + esc(r.label) + '</td><td style="text-align:right">' + payslipMoney(r.amount) + '</td></tr>').join('');
  w.document.write('<!DOCTYPE html><html><head><title>Payslip - ' + esc(ps.meta.name) + '</title><style>'
    + 'body{font-family:Georgia,serif;padding:48px;color:#222}'
    + 'h1{margin:0;font-size:24px;text-align:center}h2{margin:6px 0 18px;font-size:15px;font-weight:600;color:#7a6055;text-align:center}'
    + '.meta{font-size:13px;margin:3px 0}.meta b{display:inline-block;min-width:90px}'
    + 'table{width:100%;border-collapse:collapse;margin-top:18px}td{border:1px solid #9a8a7a;padding:8px 10px;font-size:14px}'
    + 'tr.net td{font-weight:800;background:#f3ead9}thead td{font-weight:800;background:#efe3d3}'
    + '.foot{margin-top:22px;font-size:11px;color:#777}'
    + '</style></head><body>'
    + '<h1>' + esc(ps.meta.company) + '</h1><h2>PAYSLIP' + (ps.period ? ' - ' + esc(ps.period) : '') + '</h2>'
    + '<p class="meta"><b>Employee:</b> ' + esc(ps.meta.name) + '</p>'
    + '<p class="meta"><b>Position:</b> ' + esc(ps.meta.role) + '</p>'
    + '<p class="meta"><b>Email:</b> ' + esc(ps.meta.email) + '</p>'
    + (ps.released ? '<p class="meta"><b>Released:</b> ' + esc(ps.released) + '</p>' : '')
    + '<table><thead><tr><td>Description</td><td style="text-align:right">Amount</td></tr></thead><tbody>' + rowsHtml + '</tbody></table>'
    + '<p class="foot">System-generated by Brew & Co. HRMS - valid without a handwritten signature.</p>'
    + '</body></html>');
  w.document.close(); w.focus();
  setTimeout(() => w.print(), 300);
}
function renderScheduleEl(id, s){
  const el = document.getElementById(id);
  if (!el) return;
  const days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  const active=parseDays(s?.days||'');
  el.innerHTML = `<div class="sched-grid">${days.map(d=>`<div class="day"><h3>${d}</h3><div class="body">${active.includes(d)?`<div class="work">${fmt(s.start)} - ${fmt(s.end)}</div><small>8 hours</small>`:'<div class="off">OFF</div>'}</div></div>`).join('')}</div>`;
}

async function loadNotes(){
  let r=await fetch('api/notifications.php',{credentials:'same-origin'}),d=await r.json();
  const el = document.getElementById('notes');
  if (el) el.innerHTML=(d.data||[]).slice(0,5).map(n=>`<p style="margin-bottom:10px"><b>${esc(n.title)}</b><br><span style="font-size:13px;color:var(--ink-soft)">${esc(n.message)}</span></p>`).join('')||'No notifications.';
}

async function loadNotes2(){
  let r=await fetch('api/notifications.php',{credentials:'same-origin'}),d=await r.json();
  const el = document.getElementById('notes2');
  if (el) el.innerHTML=(d.data||[]).map(n=>`<div style="padding:12px 0;border-bottom:1px solid var(--line)"><b>${esc(n.title)}</b><br><span style="font-size:13px;color:var(--ink-soft)">${esc(n.message)}</span><br><small style="color:var(--ink-soft)">${esc(n.created_at||'')}</small></div>`).join('')||'<div style="color:var(--ink-soft);padding:20px">No notifications.</div>';
}

async function loadRequests(){
  let r=await fetch('api/staff_requests.php',{credentials:'same-origin'}),d=await r.json();
  let all=d.data||[];
  const el = document.getElementById('myRequests');
if (el) el.innerHTML=all.length ? (function(){ const item=x=>`<div style="padding:12px 0;border-bottom:1px solid var(--line)"><span class="badge ${x.status}">${x.status}</span> <b>${esc(x.kind)}</b>: ${esc(x.detail)}${x.letter_path?`<a class="doc-link" href="${esc(x.letter_path)}" target="_blank" rel="noopener">📄 Letter</a>`:''}${x.signature_path?`<a class="doc-link sig" href="${esc(x.signature_path)}" target="_blank" rel="noopener">✍️ Signed</a>`:''}${x.signer_photo_path?`<img class="photo-thumb" src="${esc(x.signer_photo_path)}" title="Security photo of ${esc(x.signer_name||'')} at signing" onclick="window.open('${esc(x.signer_photo_path)}','_blank')">`:''}<br><small style="color:var(--ink-soft)">${esc(x.date||'')}${x.signed_by?` · signed by ${esc(x.signed_by)}`:''}</small></div>`; const pend=all.filter(x=>x.status==='Pending'); const done=all.filter(x=>x.status!=='Pending'); const sec=t=>`<div class="hist-section">${t}</div>`; return (pend.length?sec('⏳ Pending — waiting for action')+pend.map(item).join(''):'') + (done.length?sec('✅ Approved / Rejected — completed')+done.map(item).join(''):''); })() : '<div style="color:var(--ink-soft);padding:20px">No requests yet.</div>';
}

async function loadTodayAttendance(){
  let r=await fetch('api/attendance.php',{credentials:'same-origin'}),d=await r.json();
  let today=new Date().toISOString().slice(0,10);
  let rec=(d.data||[]).filter(x=>String(x.created_at).slice(0,10)===today);
  let ins=rec.filter(x=>x.type==='time_in').sort((a,b)=>new Date(a.created_at)-new Date(b.created_at));
  let outs=rec.filter(x=>x.type==='time_out').sort((a,b)=>new Date(a.created_at)-new Date(b.created_at));
  let inRec=ins[0]||null, outRec=outs[outs.length-1]||null;
  let hasIn=!!inRec, hasOut=!!outRec;
  let mins=minutesBetween(inRec?.created_at,outRec?.created_at);
  timeInBtn.disabled=hasIn;
  timeOutBtn.disabled=!hasIn||hasOut;
  todayAttendance.innerHTML=`
    <div class="att-grid">
      <div class="att-card"><h3>Time In</h3>${photoTag(inRec?.photo_path)}<div class="att-time">${fmtDateTime(inRec?.created_at)}</div></div>
      <div class="att-card"><h3>Time Out</h3>${photoTag(outRec?.photo_path)}<div class="att-time">${fmtDateTime(outRec?.created_at)}</div></div>
    </div>
    <div class="att-total">Today's total hours: ${hm(mins)}</div>`;
}

async function startCamera(){
  try{video.srcObject=await navigator.mediaDevices.getUserMedia({video:true,audio:false})}
  catch(e){attMsg.textContent='Camera permission is required.';attMsg.className='msg err'}
}

function snap(){canvas.width=video.videoWidth||360;canvas.height=video.videoHeight||260;canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);return canvas.toDataURL('image/jpeg',.85)}

async function att(type){
  let photo=snap();
  let r=await fetch('api/attendance.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({type,photo})});
  let d=await r.json();
  attMsg.textContent=d.success?`${type.replace('_',' ')} recorded.`:(d.message||'Failed');
  attMsg.className='msg '+(d.success?'ok':'err');
  loadTodayAttendance();
}

function toggleRequestForm(){
  leaveFields.style.display=requestType.value==='Leave'?'block':'none';
  generalFields.style.display=requestType.value==='General'?'block':'none';
}

/* ===== Formal letter uploader ===== */
let pendingReqBody=null, sigStrokes=0;
function fmtLongDate(d){return d.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'})}
function gatherLetterData(){
  const name=(document.getElementById('sidebarName').textContent||'').trim();
  const role=(document.getElementById('sidebarRole').textContent||'Staff').trim();
  const isLeave=requestType.value==='Leave';
  const paras=[];
  if(isLeave){
    const sd=start.value?new Date(start.value+'T00:00:00'):null;
    const ed=end.value?new Date(end.value+'T00:00:00'):null;
    const days=(sd&&ed)?Math.round((ed-sd)/86400000)+1:0;
    paras.push('I, '+name+' ('+role+'), respectfully request a '+leaveType.value+' leave from '+(sd?fmtLongDate(sd):'[start date]')+' to '+(ed?fmtLongDate(ed):'[end date]')+', covering '+days+' day(s), for the following reason: '+(reason.value.trim()||'[reason]')+'.');
    paras.push('During my absence, I will ensure that my responsibilities are properly turned over so that operations continue smoothly.');
  }else{
    paras.push('I, '+name+' ('+role+'), respectfully submit the following request for your review and approval:');
    paras.push(generalDetail.value.trim()||'[request details]');
  }
  return {
    dateLabel: fmtLongDate(new Date()),
    addressee: isLeave ? 'The HR Manager' : 'The Superadmin',
    name: name,
    roleLabel: role,
    subject: isLeave ? ('Leave Request ('+leaveType.value+')') : 'General Request',
    bodyParas: paras
  };
}
function refreshLetterPreview(){
  const t=LetterGen.text(gatherLetterData());
  document.getElementById('letterPreview').textContent=t;
  const f=document.getElementById('letterPreviewFull'); if(f) f.textContent=t;
}
['requestType','leaveType','start','end','reason','generalDetail'].forEach(id=>{
  const el=document.getElementById(id);
  if(el){el.addEventListener('input',refreshLetterPreview);el.addEventListener('change',refreshLetterPreview);}
});
document.getElementById('letterViewBtn').addEventListener('click',()=>{refreshLetterPreview();document.getElementById('letterModal').style.display='flex';});
refreshLetterPreview();

/* ===== Signature pad ===== */
const sigCanvas=document.getElementById('sigPad');
const sigCtx=sigCanvas.getContext('2d');
sigCtx.lineWidth=3;sigCtx.lineCap='round';sigCtx.lineJoin='round';sigCtx.strokeStyle='#22303f';
let sigDrawing=false;
function sigPos(e){const r=sigCanvas.getBoundingClientRect();return{x:(e.clientX-r.left)*(sigCanvas.width/r.width),y:(e.clientY-r.top)*(sigCanvas.height/r.height)}}
sigCanvas.addEventListener('pointerdown',e=>{sigDrawing=true;sigStrokes++;document.getElementById('sigSubmit').disabled=false;const p=sigPos(e);sigCtx.beginPath();sigCtx.moveTo(p.x,p.y);sigCanvas.setPointerCapture(e.pointerId);e.preventDefault()});
sigCanvas.addEventListener('pointermove',e=>{if(!sigDrawing)return;const p=sigPos(e);sigCtx.lineTo(p.x,p.y);sigCtx.stroke();e.preventDefault()});
['pointerup','pointercancel','pointerleave'].forEach(ev=>sigCanvas.addEventListener(ev,()=>{sigDrawing=false}));
function sigClear(){sigCtx.clearRect(0,0,sigCanvas.width,sigCanvas.height);sigStrokes=0;document.getElementById('sigSubmit').disabled=true;document.getElementById('sigMsg').textContent=''}
document.getElementById('sigClear').addEventListener('click',sigClear);
document.getElementById('sigCancel').addEventListener('click',()=>{document.getElementById('sigModal').style.display='none';pendingReqBody=null;sigClear();stopSigCam()});

/* ===== Signature security: typed name + camera selfie ===== */
function nameNorm(s){return (s||'').toLowerCase().replace(/\s+/g,' ').trim()}
let sigStream=null,camOk=false;
async function startSigCam(){
  camOk=false;document.getElementById('camErr').style.display='none';
  const vid=document.getElementById('sigCam');
  try{sigStream=await navigator.mediaDevices.getUserMedia({video:{width:480,height:360},audio:false});vid.srcObject=sigStream;camOk=true}
  catch(e){document.getElementById('camErr').style.display='block'}
}
function stopSigCam(){if(sigStream){sigStream.getTracks().forEach(t=>t.stop());sigStream=null}const vid=document.getElementById('sigCam');if(vid)vid.srcObject=null;camOk=false}
function captureSignerPhoto(){
  const vid=document.getElementById('sigCam');
  if(!camOk||!vid.videoWidth)return '';
  const c=document.createElement('canvas');c.width=vid.videoWidth;c.height=vid.videoHeight;
  c.getContext('2d').drawImage(vid,0,0,c.width,c.height);
  return c.toDataURL('image/jpeg',0.85);
}
/* ===== Signature matching vs registered signature ===== */
let regSigGrid=null;
function inkGridFromCanvas(srcCanvas){
  const w=srcCanvas.width,h=srcCanvas.height;
  const d=srcCanvas.getContext('2d').getImageData(0,0,w,h).data;
  let minx=w,miny=h,maxx=-1,maxy=-1;
  const mask=new Uint8Array(w*h);
  for(let y=0;y<h;y++)for(let x=0;x<w;x++){
    const i=(y*w+x)*4,a=d[i+3],lum=(d[i]+d[i+1]+d[i+2])/3;
    const ink=a>40&&lum<160;
    mask[y*w+x]=ink?1:0;
    if(ink){if(x<minx)minx=x;if(x>maxx)maxx=x;if(y<miny)miny=y;if(y>maxy)maxy=y}
  }
  if(maxx<0)return null;
  const S=48,grid=new Uint8Array(S*S),gw=maxx-minx+1,gh=maxy-miny+1;
  for(let gy=0;gy<S;gy++)for(let gx=0;gx<S;gx++){
    const sx=Math.min(maxx,Math.max(minx,minx+Math.floor((gx+0.5)*gw/S)));
    const sy=Math.min(maxy,Math.max(miny,miny+Math.floor((gy+0.5)*gh/S)));
    grid[gy*S+gx]=mask[sy*w+sx];
  }
  return grid;
}
function inkGridFromImage(img){
  const c=document.createElement('canvas');
  c.width=img.naturalWidth||img.width;c.height=img.naturalHeight||img.height;
  const cx=c.getContext('2d');cx.fillStyle='#fff';cx.fillRect(0,0,c.width,c.height);cx.drawImage(img,0,0);
  return inkGridFromCanvas(c);
}
function dilateGrid(g,S){
  const out=new Uint8Array(g);
  for(let y=0;y<S;y++)for(let x=0;x<S;x++)if(g[y*S+x]){
    for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++){
      const ny=y+dy,nx=x+dx;
      if(ny>=0&&ny<S&&nx>=0&&nx<S)out[ny*S+nx]=1;
    }
  }
  return out;
}
function sigSimilarity(gridA,gridB){
  const S=48;
  if(!gridA||!gridB)return 0;
  const da=dilateGrid(gridA,S),db=dilateGrid(gridB,S);
  let ca=0,cb=0,ha=0,hb=0;
  for(let i=0;i<S*S;i++){if(gridA[i]){ca++;if(db[i])ha++}if(gridB[i]){cb++;if(da[i])hb++}}
  if(!ca||!cb)return 0;
  const p=ha/ca,r=hb/cb;
  return (p+r)>0?2*p*r/(p+r):0;
}
async function loadRegSig(){
  const box=document.getElementById('regSigBox');
  regSigGrid=null;
  box.textContent='Loading…';
  try{
    const r=await fetch('api/my_signature.php',{credentials:'same-origin'});
    const d=await r.json();
    if(d.success&&d.has&&d.path){
      box.innerHTML='';
      const img=new Image();
      img.onload=()=>{regSigGrid=inkGridFromImage(img)};
      img.onerror=()=>{regSigGrid=null;box.textContent='Your registered signature file is missing on the server — this signing will re-record it as your official signature.'};
      img.src=d.path;
      box.appendChild(img);
    }else{
      box.textContent='First-time signing: this signature will be recorded as your official registered signature.';
    }
  }catch(e){box.textContent='Could not load your registered signature.'}
}

/* ===== Profile: registered signature update (once a month) ===== */
let regPadStrokes=0;
async function initProfileSig(){
  const box=document.getElementById('regSigPreview');
  const lock=document.getElementById('regSigLock');
  const btn=document.getElementById('regSigUpdate');
  if(!box)return;
  try{
    const r=await fetch('api/my_signature.php',{credentials:'same-origin'});
    const d=await r.json();
    if(d.has&&d.path){box.innerHTML='';const img=new Image();img.onerror=()=>{box.textContent='Registered signature file missing on server — update it below or sign a request to re-record it.'};img.src=d.path;box.appendChild(img);}
    else box.textContent='No registered signature yet — your first signed request records it, or draw one below.';
    if(d.can_update===false){btn.disabled=true;lock.textContent='🔒 Locked — you can update again in '+d.days_left+' day(s) (last update '+String(d.updated_at).split(' ')[0]+').';}
    else if(d.updated_at){lock.textContent='Last updated '+String(d.updated_at).split(' ')[0]+'.';}
    else lock.textContent='';
  }catch(e){box.textContent='Could not load your registered signature.'}
}
(function initRegPad(){
  const pad=document.getElementById('regSigPad'); if(!pad)return;
  const gate=document.getElementById('regSigGate');
  if(gate){document.getElementById('regSigGateBtn').onclick=async()=>{if(!await swalAsk('Confirm e-sign: you are about to enter your new registered signature. It will verify your future request signatures. Continue?'))return;gate.remove();pad.style.pointerEvents='auto';};}

  const ctx=pad.getContext('2d');ctx.lineWidth=3;ctx.lineCap='round';ctx.lineJoin='round';ctx.strokeStyle='#22303f';
  let drawing=false;
  const pos=e=>{const r=pad.getBoundingClientRect();return{x:(e.clientX-r.left)*(pad.width/r.width),y:(e.clientY-r.top)*(pad.height/r.height)}};
  pad.addEventListener('pointerdown',e=>{drawing=true;regPadStrokes++;const p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);pad.setPointerCapture(e.pointerId);e.preventDefault()});
  pad.addEventListener('pointermove',e=>{if(!drawing)return;const p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();e.preventDefault()});
  ['pointerup','pointercancel','pointerleave'].forEach(ev=>pad.addEventListener(ev,()=>{drawing=false}));
  document.getElementById('regSigClear').onclick=()=>{ctx.clearRect(0,0,pad.width,pad.height);regPadStrokes=0};
  document.getElementById('regSigUpdate').onclick=async function(){
    const al=document.getElementById('regSigAlert');
    if(regPadStrokes===0){al.textContent='Please draw your new signature first.';al.className='msg err';return}
    this.disabled=true;
    try{
      const r=await fetch('api/my_signature.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'update',signature:pad.toDataURL('image/png')})});
      const d=await r.json();
      if(d.success){
        al.textContent=d.message;al.className='msg ok';
        setTimeout(()=>{regPadStrokes=0;ctx.clearRect(0,0,pad.width,pad.height);initProfileSig();},1200);
      }else{al.textContent=d.message||'Failed to update.';al.className='msg err';this.disabled=false;}
    }catch(e){al.textContent='Network error.';al.className='msg err';this.disabled=false;}
  };
})();
initProfileSig();

/* ===== Submit flow: validate -> sign -> send ===== */
async function sendRequest(){
  let body={kind:requestType.value};
  if(requestType.value==='General'){body.detail=generalDetail.value.trim()}
  else{body.leave_type=leaveType.value;body.start_date=start.value;body.end_date=end.value;body.reason=reason.value.trim()}

  // validation
  if(requestType.value==='General'&&body.detail.length<3){requestMsg.textContent='Please describe your request (at least 3 characters).';requestMsg.className='msg err';return}
  if(requestType.value==='Leave'&&(!body.start_date||!body.end_date||body.start_date>body.end_date||body.reason==='')){requestMsg.textContent='Leave needs valid start/end dates and a reason.';requestMsg.className='msg err';return}

  // confirm the e-sign intent BEFORE the pad is shown
  if(!await swalAsk('Confirm e-sign: you are about to enter your official e-signature for this request. It will be placed on the formal letter and used for signature security. Continue?')) return;
  // open signature modal — nothing is sent until the staff signs
  pendingReqBody=body;
  document.getElementById('sigAs').textContent=document.getElementById('sidebarName').textContent||'me';
  sigClear();
  loadRegSig();
  document.getElementById('sigModal').style.display='flex';
  startSigCam();
}

document.getElementById('sigSubmit').addEventListener('click',async function(){
  if(!pendingReqBody)return;
  if(sigStrokes===0){document.getElementById('sigMsg').textContent='Please draw your signature first.';document.getElementById('sigMsg').className='msg err';return}
  // Security pre-check: drawn signature must resemble the registered one.
  if(regSigGrid){
    const score=sigSimilarity(regSigGrid,inkGridFromCanvas(sigCanvas));
    if(score<0.45){
      document.getElementById('sigMsg').textContent='Security check: your signature matches only '+Math.round(score*100)+'% of your registered signature. Sign again the same way.';
      document.getElementById('sigMsg').className='msg err';
      return;
    }
  }
  // Security: capture the selfie from the camera.
  const signerPhoto=captureSignerPhoto();
  if(!signerPhoto){document.getElementById('camErr').style.display='block';document.getElementById('sigMsg').textContent='Camera photo is required. Allow camera access and try again.';document.getElementById('sigMsg').className='msg err';return}
  this.disabled=true;
  const fd=new FormData();
  for(const[k,v]of Object.entries(pendingReqBody))fd.append(k,v);
  const sigJpeg=LetterGen.sigJpegFromCanvas(sigCanvas);
  const pdfBlob=LetterGen.buildDocx(gatherLetterData(),sigJpeg);
  const lname=(document.getElementById('sidebarName').textContent||'staff').trim().replace(/[^A-Za-z0-9]+/g,'_');
  fd.append('letter',pdfBlob,'Formal_Letter_'+lname+'.docx');
  fd.append('signature',sigCanvas.toDataURL('image/png'));
  fd.append('signer_photo',signerPhoto);
  try{
    let r=await fetch('api/request_submit.php',{method:'POST',credentials:'same-origin',body:fd});
    const raw=await r.text();
    let d=null; try{d=JSON.parse(raw)}catch(_e){d=null}
    if(!d){requestMsg.textContent='Server error (HTTP '+r.status+'): '+raw.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim().slice(0,200);requestMsg.className='msg err';return}
    requestMsg.textContent=d.success?(d.message||'Request submitted with your signed letter.'):(d.message||'Failed');
    requestMsg.className='msg '+(d.success?'ok':'err');
    if(d.success){
      document.getElementById('sigModal').style.display='none';
      stopSigCam();
      generalDetail.value='';reason.value='';refreshLetterPreview();sigClear();pendingReqBody=null;loadRequests();
    }
  }catch(e){requestMsg.textContent='Network error. Please try again.';requestMsg.className='msg err'}
  finally{this.disabled=false}
});

function showView(name){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  const el = document.getElementById('view-'+name);
  if (el) el.classList.add('active');
  document.querySelectorAll('.sidebar-nav a[data-view]').forEach(a=>a.classList.toggle('active', a.dataset.view===name));
  closeSidebar();
  window.scrollTo({top:0,behavior:'smooth'});
}

function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarBackdrop').classList.toggle('show');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarBackdrop').classList.remove('show');
}

async function doLogout(){
  await fetch('api/logout.php',{credentials:'same-origin'});
  location='login.php';
}

load();
</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
<script src="../final-operations.js" defer></script>
</body>
</html>
