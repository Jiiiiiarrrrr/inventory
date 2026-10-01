<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Careers — Brew &amp; Co.</title>
<style>
  :root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--danger:#a8492f;--danger-bg:#fde8e8;--ok:#4f7a4a;--ok-bg:#e2ecdf;--shadow:0 18px 50px rgba(59,35,19,.12)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Georgia,"Iowan Old Style","Segoe UI",serif;background:var(--cream);color:var(--ink);min-height:100vh}
  :focus-visible{outline:3px solid rgba(169,113,74,.35);outline-offset:2px;border-radius:6px}

  .top{background:linear-gradient(160deg, rgba(58,36,27,.75) 0%, rgba(92,58,41,.6) 45%, rgba(169,113,74,.55) 100%),
                url('../coffee-bg.jpg') center/cover no-repeat;color:#fff;padding:44px 24px 40px;text-align:center}
  .top img{width:64px;height:64px;filter:drop-shadow(0 4px 12px rgba(0,0,0,.3));margin-bottom:10px}
  .top h1{font-size:32px;font-weight:800;margin-bottom:6px;text-shadow:0 2px 8px rgba(0,0,0,.2)}
  .top p{font-size:14px;color:rgba(255,255,255,.85);max-width:480px;margin:0 auto}
  .top-links{margin-top:18px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
  .top-links a{color:#fff;text-decoration:none;font-size:13px;font-weight:700;border:1.5px solid rgba(255,255,255,.5);border-radius:999px;padding:8px 16px;transition:all .2s}
  .top-links a:hover{background:rgba(255,255,255,.15)}

  .wrap{max-width:880px;margin:0 auto;padding:36px 24px 60px}

  /* Step indicator */
  .steps{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:30px;flex-wrap:wrap}
  .step{display:flex;align-items:center;gap:9px;color:var(--muted);font-size:13px;font-weight:700}
  .step .dot{width:26px;height:26px;border-radius:50%;background:var(--card);border:2px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:var(--muted);flex-shrink:0;transition:all .2s}
  .step.active .dot{border-color:var(--accent);background:var(--accent);color:#fff}
  .step.done .dot{border-color:var(--ok);background:var(--ok);color:#fff}
  .step.active,.step.done{color:var(--ink)}
  .step-line{width:44px;height:2px;background:var(--line);margin:0 10px}
  .step-line.done{background:var(--ok)}
  @media(max-width:560px){.step span.step-label{display:none}.step-line{width:24px;margin:0 6px}}

  .section-label{font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:14px}

  .job-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:8px}
  .job-card{position:relative;background:var(--card);border:1.5px solid var(--line);border-radius:14px;padding:18px;cursor:pointer;transition:all .15s;text-align:left;font:inherit;color:inherit;box-shadow:var(--shadow)}
  .job-card:hover{border-color:var(--accent);transform:translateY(-1px)}
  .job-card.selected{border-color:var(--accent);background:rgba(169,113,74,.06);box-shadow:0 18px 50px rgba(169,113,74,.18)}
  .job-card.full{opacity:.6;cursor:not-allowed}
  .job-card.full:hover{border-color:var(--line);transform:none}
  .job-card .check{position:absolute;top:14px;right:14px;width:22px;height:22px;border-radius:50%;background:var(--accent);color:#fff;display:none;align-items:center;justify-content:center;font-size:13px;font-weight:900}
  .job-card.selected .check{display:flex}
  .job-card h3{font-size:17px;margin-bottom:8px;padding-right:26px}
  .job-card .desc{font-size:12.5px;color:var(--muted);margin-bottom:12px;line-height:1.4;min-height:17px}
  .slot-row{display:flex;align-items:center;justify-content:space-between;gap:8px}
  .slot-bar{flex:1;height:6px;border-radius:999px;background:var(--line);overflow:hidden}
  .slot-bar-fill{height:100%;background:var(--accent);border-radius:999px;transition:width .3s}
  .slot-text{font-size:11.5px;font-weight:800;color:var(--muted);white-space:nowrap}
  .badge-full{display:inline-block;margin-top:10px;font-size:11px;font-weight:800;color:var(--danger);background:#fbe4dd;padding:3px 10px;border-radius:999px}
  .badge-open{display:inline-block;margin-top:10px;font-size:11px;font-weight:800;color:var(--ok);background:var(--ok-bg);padding:3px 10px;border-radius:999px}
  .loading,.empty{color:var(--muted);text-align:center;padding:30px;font-size:14px}
  .loading .spinner{display:inline-block;width:18px;height:18px;border:2.5px solid var(--line);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin-right:8px;vertical-align:middle}
  @keyframes spin{to{transform:rotate(360deg)}}

  .form-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px;margin-top:26px;box-shadow:var(--shadow);display:none}
  .form-card.show{display:block}
  .form-card h2{font-size:20px;margin-bottom:4px}
  .form-card .sub{font-size:13px;color:var(--muted);margin-bottom:22px}
  .alert{background:var(--danger-bg);color:var(--danger);border-radius:10px;padding:11px 14px;font-size:13px;font-weight:700;margin-bottom:16px;display:none}
  .alert.show{display:block}
  .success{background:var(--ok-bg);color:var(--ok);border-radius:10px;padding:16px 18px;font-size:13.5px;font-weight:600;margin-bottom:16px;display:none;line-height:1.55}
  .success.show{display:block}
  .success .success-title{font-size:16px;font-weight:800;margin-bottom:6px;display:flex;align-items:center;gap:8px}
  .success .success-title .tick{width:24px;height:24px;border-radius:50%;background:var(--ok);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
  .success .summary-list{margin-top:10px;padding-top:10px;border-top:1px solid rgba(79,122,74,.25);font-size:12.5px}
  .success .summary-list div{display:flex;justify-content:space-between;gap:10px;padding:3px 0}
  .success .summary-list b{font-weight:800}

  .form-group{margin-bottom:18px}
  .form-group label{display:block;font-size:13px;font-weight:700;color:var(--ink);margin-bottom:6px}
  .form-group label .req{color:var(--danger);margin-left:2px}
  .form-group label .opt{color:var(--muted);font-weight:400;font-size:11.5px;margin-left:4px}
  .form-group input[type=text],.form-group input[type=email],.form-group select,.form-group textarea{width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:15px;background:var(--card);color:var(--ink);outline:none;font-family:inherit;transition:border-color .15s}
  .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
  .form-group.has-error input,.form-group.has-error select{border-color:var(--danger)}
  .form-group.valid-ok input{border-color:var(--ok)}
  .field-error{color:var(--danger);font-size:12px;margin-top:5px;display:none;font-weight:600}
  .field-error.show,.form-group.has-error .field-error{display:block}
  .field-hint{font-size:11.5px;color:var(--muted);margin-top:5px}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

  .input-icon-wrap{position:relative}
  .input-icon-wrap input{padding-right:38px !important}
  .input-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:15px;pointer-events:none}
  .input-icon.ok{color:var(--ok)}
  .input-icon.err{color:var(--danger)}

  .choice-row{display:flex;gap:10px;margin-top:6px}
  .choice-option{flex:1;position:relative}
  .choice-option input[type=radio]{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer}
  .choice-option .choice-card{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px 10px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;font-weight:700;color:var(--ink);background:var(--card);transition:all .15s;text-align:center;position:relative}
  .choice-option input[type=radio]:checked + .choice-card{border-color:var(--accent);background:rgba(169,113,74,.08);color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
  .choice-option input[type=radio]:focus-visible + .choice-card{outline:3px solid rgba(169,113,74,.35);outline-offset:2px}
  .choice-option .choice-card::after{content:'';width:16px;height:16px;border-radius:50%;border:1.5px solid var(--line);flex-shrink:0;order:-1}
  .choice-option input[type=radio]:checked + .choice-card::after{content:'✓';border-color:var(--accent);background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px}

  /* Drag-and-drop CV uploader */
  .dropzone{border:2px dashed var(--line);border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:all .15s;background:var(--cream)}
  .dropzone:hover,.dropzone.dragover{border-color:var(--accent);background:rgba(169,113,74,.06)}
  .dropzone .dz-icon{font-size:26px;margin-bottom:6px}
  .dropzone .dz-text{font-size:13.5px;font-weight:700;color:var(--ink)}
  .dropzone .dz-sub{font-size:12px;color:var(--muted);margin-top:3px}
  .dropzone input[type=file]{display:none}
  .file-picked{display:none;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--ok);border-radius:10px;padding:10px 12px;margin-top:10px}
  .file-picked.show{display:flex}
  .file-picked .f-icon{font-size:20px;flex-shrink:0}
  .file-picked .f-info{flex:1;min-width:0}
  .file-picked .f-name{font-size:13px;font-weight:700;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .file-picked .f-size{font-size:11.5px;color:var(--muted)}
  .file-picked .f-remove{background:none;border:none;color:var(--danger);font-weight:800;cursor:pointer;font-size:16px;padding:2px 6px;flex-shrink:0;font-family:inherit}

  .char-count{font-size:11px;color:var(--muted);text-align:right;margin-top:4px}
  .char-count.near-limit{color:var(--accent);font-weight:700}
  .char-count.at-limit{color:var(--danger);font-weight:700}

  .btn-submit{width:100%;padding:14px;background:var(--accent);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer;transition:background .2s;margin-top:6px;display:flex;align-items:center;justify-content:center;gap:9px}
  .btn-submit:hover{background:var(--accent-dark)}
  .btn-submit:disabled{opacity:.7;cursor:not-allowed}
  .btn-submit .btn-spinner{width:16px;height:16px;border:2.5px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
  .btn-submit.loading .btn-spinner{display:inline-block}
  .btn-submit.loading .btn-label{opacity:.85}
  .btn-cancel{width:100%;padding:11px;background:transparent;border:1.5px solid var(--line);border-radius:10px;color:var(--muted);font:inherit;font-size:13px;font-weight:700;cursor:pointer;margin-top:10px}
  .btn-cancel:hover{border-color:var(--muted)}

  @media(max-width:600px){.form-row{grid-template-columns:1fr}.top h1{font-size:26px}}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
</head>
<body>

<div class="top">
  <img src="../brewco-logo.svg" alt="Brew &amp; Co.">
  <h1>Careers at Brew &amp; Co.</h1>
  <p>Coffee, done right — and we're hiring. Pick an open role below to apply.</p>
  <div class="top-links">
    <a href="login.php">Back to login</a>
    <a href="applicant-status.php">Check application status</a>
  </div>
</div>

<div class="wrap">

  <div class="steps" id="stepsBar">
    <div class="step active" id="stepPick"><span class="dot">1</span><span class="step-label">Choose role</span></div>
    <div class="step-line" id="stepLine"></div>
    <div class="step" id="stepFill"><span class="dot">2</span><span class="step-label">Your details</span></div>
    <div class="step-line" id="stepLine2"></div>
    <div class="step" id="stepDone"><span class="dot">3</span><span class="step-label">Submitted</span></div>
  </div>

  <div class="section-label">Open positions</div>
  <div class="job-grid" id="jobGrid"><div class="loading"><span class="spinner"></span>Loading open positions...</div></div>

  <div class="form-card" id="formCard">
    <h2 id="formTitle">Apply</h2>
    <p class="sub" id="formSub">Submit your information and CV for HR review.</p>

    <div class="alert" id="applyError"></div>
    <div class="success" id="applySuccess"></div>

    <div id="formFields">
      <div class="form-row">
        <div class="form-group" id="field-firstName">
          <label for="firstName">First name<span class="req">*</span></label>
          <input id="firstName" type="text" placeholder="First name" autocomplete="given-name">
          <div class="field-error">Please enter your first name.</div>
        </div>
        <div class="form-group" id="field-lastName">
          <label for="lastName">Last name<span class="req">*</span></label>
          <input id="lastName" type="text" placeholder="Last name" autocomplete="family-name">
          <div class="field-error">Please enter your last name.</div>
        </div>
      </div>

      <div class="form-group" id="field-applyEmail">
        <label for="applyEmail">Email<span class="req">*</span></label>
        <div class="input-icon-wrap">
          <input id="applyEmail" type="email" placeholder="Enter your email" autocomplete="email">
          <span class="input-icon" id="emailIcon"></span>
        </div>
        <div class="field-error" id="emailError">Please enter a valid email address.</div>
        <div class="field-hint">We'll send interview updates here — double check it's correct.</div>
      </div>

      <div class="form-group" id="field-contactNumber">
        <label for="contactNumber">Contact number<span class="req">*</span></label>
        <input id="contactNumber" type="text" inputmode="tel" placeholder="09XX XXX XXXX" autocomplete="tel">
        <div class="field-error" id="contactNumberError">Please enter a valid PH mobile number, e.g. 09171234567.</div>
      </div>

      <div class="form-row">
        <div class="form-group" id="field-birthdate">
          <label for="birthdate">Birthdate<span class="req">*</span></label>
          <input id="birthdate" type="date" autocomplete="bday">
          <div class="field-error" id="birthdateError">Please enter your birthdate. You must be 18 or older to apply.</div>
        </div>
        <div class="form-group" id="field-gender">
          <label for="gender">Gender<span class="opt">optional</span></label>
          <select id="gender">
            <option value="">Prefer not to say</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Prefer not to say">Prefer not to say</option>
          </select>
        </div>
      </div>

      <div class="form-group" id="field-civilStatus">
        <label for="civilStatus">Civil status<span class="opt">optional</span></label>
        <select id="civilStatus">
          <option value="">Select...</option>
          <option value="Single">Single</option>
          <option value="Married">Married</option>
          <option value="Widowed">Widowed</option>
          <option value="Separated">Separated</option>
        </select>
      </div>

      <div class="form-group" id="field-referralSource">
        <label for="referralSource">How did you hear about us?<span class="opt">optional</span></label>
        <select id="referralSource">
          <option value="">Select...</option>
          <option value="Referral">Referral from someone</option>
          <option value="Walk-in poster">Walk-in / Poster</option>
          <option value="Facebook">Facebook</option>
          <option value="Job site">Job site (JobStreet, Indeed, etc.)</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-group" id="field-interviewMode">
        <label>Interview preference<span class="req">*</span></label>
        <div class="choice-row">
          <label class="choice-option">
            <input type="radio" name="interviewMode" value="Online">
            <span class="choice-card">💻 Online</span>
          </label>
          <label class="choice-option">
            <input type="radio" name="interviewMode" value="Walk-in">
            <span class="choice-card">🚶 Walk-in</span>
          </label>
        </div>
        <div class="field-error">Please choose Online or Walk-in.</div>
      </div>

      <div class="form-group" id="field-cvFile">
        <label for="cvFile">Upload CV / Resume<span class="req">*</span></label>
        <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Upload CV or resume">
          <div class="dz-icon">📄</div>
          <div class="dz-text">Click to browse or drag your file here</div>
          <div class="dz-sub">PDF, DOC, DOCX, JPG, or PNG — up to 10 MB</div>
          <input id="cvFile" type="file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
        </div>
        <div class="file-picked" id="filePicked">
          <span class="f-icon">✅</span>
          <div class="f-info">
            <div class="f-name" id="fileName"></div>
            <div class="f-size" id="fileSize"></div>
          </div>
          <button type="button" class="f-remove" id="fileRemoveBtn" aria-label="Remove file">✕</button>
        </div>
        <div class="field-error">Please attach your CV or resume.</div>
      </div>

      <div class="form-group">
        <label for="applyNotes">Message to HR<span class="opt">optional</span></label>
        <textarea id="applyNotes" placeholder="Short note for HR..." style="min-height:70px;resize:vertical" maxlength="500"></textarea>
        <div class="char-count" id="notesCount">0 / 500</div>
      </div>

      <button class="btn-submit" id="submitApplyBtn" onclick="submitApplication()">
        <span class="btn-spinner"></span>
        <span class="btn-label">Submit application</span>
      </button>
      <button class="btn-cancel" onclick="deselectJob()">Choose a different role</button>
    </div>
  </div>
</div>

<script>
const API_BASE='api';
const MAX_FILE_MB = 10;
let positions = [];
let selectedTitle = null;

function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}

function setStep(n){
  const steps = [document.getElementById('stepPick'), document.getElementById('stepFill'), document.getElementById('stepDone')];
  const lines = [document.getElementById('stepLine'), document.getElementById('stepLine2')];
  steps.forEach((el, i) => {
    el.classList.remove('active', 'done');
    if (i < n - 1) el.classList.add('done');
    else if (i === n - 1) el.classList.add('active');
  });
  lines.forEach((el, i) => el.classList.toggle('done', i < n - 1));
}

async function loadPositions(){
  try{
    const r = await fetch(`${API_BASE}/positions.php`, { credentials: 'same-origin' });
    const d = await r.json();
    if (!d.success) throw new Error(d.message || 'Failed to load positions');
    positions = d.data || [];
    renderPositions();
  }catch(e){
    document.getElementById('jobGrid').innerHTML = '<div class="empty">Could not load open positions. Please refresh the page.</div>';
  }
}

function renderPositions(){
  const grid = document.getElementById('jobGrid');
  const open = positions.filter(p => p.is_open);
  if (!open.length){ grid.innerHTML = '<div class="empty">No open positions right now — please check back soon.</div>'; return; }

  grid.innerHTML = open.map(p => {
    const full = p.slots_remaining <= 0;
    const pct = p.slots_total > 0 ? Math.min(100, Math.round((p.slots_filled / p.slots_total) * 100)) : 0;
    const sel = selectedTitle === p.title ? 'selected' : '';
    return `
      <button type="button" class="job-card ${full ? 'full' : ''} ${sel}" ${full ? 'disabled' : `onclick="selectJob('${p.title.replace(/'/g, "\\'")}')"`}>
        <span class="check">✓</span>
        <h3>${esc(p.title)}</h3>
        <div class="desc">${esc(p.description || '')}</div>
        <div class="slot-row">
          <div class="slot-bar"><div class="slot-bar-fill" style="width:${pct}%"></div></div>
          <div class="slot-text">${p.slots_filled}/${p.slots_total} filled</div>
        </div>
        ${full ? '<span class="badge-full">Slots full</span>' : `<span class="badge-open">${esc(p.slots_remaining)} slot${p.slots_remaining === 1 ? '' : 's'} open</span>`}
      </button>`;
  }).join('');
}

function selectJob(title){
  selectedTitle = title;
  renderPositions();
  const card = document.getElementById('formCard');
  document.getElementById('formTitle').textContent = `Apply — ${title}`;
  document.getElementById('formSub').textContent = 'Submit your information and CV for HR review.';
  card.classList.add('show');
  setStep(2);
  card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  document.getElementById('firstName').focus();
}

function deselectJob(){
  selectedTitle = null;
  renderPositions();
  document.getElementById('formCard').classList.remove('show');
  setStep(1);
}

function showError(el,msg){el.textContent=msg;el.classList.add('show')}
function clearMsg(){document.getElementById('applyError').classList.remove('show');document.getElementById('applySuccess').classList.remove('show')}
function clearFieldErrors(){document.querySelectorAll('#formFields .form-group').forEach(f=>f.classList.remove('has-error'))}

function scrollToFirstError(){
  const first = document.querySelector('#formFields .form-group.has-error');
  if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function formatBytes(bytes){
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/(1024*1024)).toFixed(2) + ' MB';
}

// ---- Live contact number validation ----
const contactInput = document.getElementById('contactNumber');
contactInput.addEventListener('input', () => {
  const group = document.getElementById('field-contactNumber');
  const digitsOnly = contactInput.value.replace(/[\s\-()]/g, '');
  if (!digitsOnly) { group.classList.remove('has-error'); return; }
  const ok = /^(\+63|0)9\d{9}$/.test(digitsOnly);
  group.classList.toggle('has-error', !ok);
});

// ---- Birthdate: live 18+ check ----
const birthdateInput = document.getElementById('birthdate');
function calcAge(dateStr){
  const b = new Date(dateStr + 'T00:00:00');
  const today = new Date();
  let age = today.getFullYear() - b.getFullYear();
  const m = today.getMonth() - b.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < b.getDate())) age--;
  return age;
}
birthdateInput.addEventListener('change', () => {
  const group = document.getElementById('field-birthdate');
  const v = birthdateInput.value;
  if (!v) { group.classList.remove('has-error'); return; }
  const bDate = new Date(v + 'T00:00:00');
  const isFuture = bDate > new Date();
  const under18 = calcAge(v) < 18;
  group.classList.toggle('has-error', isFuture || under18);
  document.getElementById('birthdateError').textContent = isFuture
    ? 'Birthdate cannot be in the future.'
    : 'Please enter your birthdate. You must be 18 or older to apply.';
});

// ---- Live email validation ----
const emailInput = document.getElementById('applyEmail');
const emailIcon = document.getElementById('emailIcon');
emailInput.addEventListener('input', () => {
  const v = emailInput.value.trim();
  const group = document.getElementById('field-applyEmail');
  if (!v) { emailIcon.textContent = ''; group.classList.remove('has-error','valid-ok'); return; }
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  if (ok) {
    emailIcon.textContent = '✓'; emailIcon.className = 'input-icon ok';
    group.classList.remove('has-error'); group.classList.add('valid-ok');
  } else {
    emailIcon.textContent = '!'; emailIcon.className = 'input-icon err';
    group.classList.remove('valid-ok');
  }
});

// ---- Character counter for HR message ----
const notesInput = document.getElementById('applyNotes');
const notesCount = document.getElementById('notesCount');
notesInput.addEventListener('input', () => {
  const len = notesInput.value.length;
  notesCount.textContent = `${len} / 500`;
  notesCount.classList.toggle('near-limit', len >= 400 && len < 500);
  notesCount.classList.toggle('at-limit', len >= 500);
});

// ---- Drag-and-drop CV upload ----
const dropzone = document.getElementById('dropzone');
const cvFileInput = document.getElementById('cvFile');
const filePicked = document.getElementById('filePicked');
const fileNameEl = document.getElementById('fileName');
const fileSizeEl = document.getElementById('fileSize');
const ALLOWED_EXT = ['pdf','doc','docx','png','jpg','jpeg'];

function handleFileSelection(file){
  const errEl = document.querySelector('#field-cvFile .field-error');
  const group = document.getElementById('field-cvFile');
  if (!file) return;

  const ext = file.name.split('.').pop().toLowerCase();
  if (!ALLOWED_EXT.includes(ext)) {
    errEl.textContent = 'That file type isn\'t supported. Please use PDF, DOC, DOCX, JPG, or PNG.';
    group.classList.add('has-error');
    cvFileInput.value = '';
    filePicked.classList.remove('show');
    dropzone.style.display = '';
    return;
  }
  if (file.size > MAX_FILE_MB * 1024 * 1024) {
    errEl.textContent = `That file is too large (${formatBytes(file.size)}). Max size is ${MAX_FILE_MB} MB.`;
    group.classList.add('has-error');
    cvFileInput.value = '';
    filePicked.classList.remove('show');
    dropzone.style.display = '';
    return;
  }

  group.classList.remove('has-error');
  fileNameEl.textContent = file.name;
  fileSizeEl.textContent = formatBytes(file.size);
  filePicked.classList.add('show');
  dropzone.style.display = 'none';
}

dropzone.addEventListener('click', () => cvFileInput.click());
dropzone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cvFileInput.click(); } });
cvFileInput.addEventListener('change', () => handleFileSelection(cvFileInput.files[0]));

['dragenter','dragover'].forEach(evt => dropzone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); dropzone.classList.add('dragover'); }));
['dragleave','drop'].forEach(evt => dropzone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('dragover'); }));
dropzone.addEventListener('drop', (e) => {
  const file = e.dataTransfer.files[0];
  if (file) {
    // Assign the dropped file to the real input so FormData picks it up on submit.
    const dt = new DataTransfer();
    dt.items.add(file);
    cvFileInput.files = dt.files;
    handleFileSelection(file);
  }
});

document.getElementById('fileRemoveBtn').addEventListener('click', () => {
  cvFileInput.value = '';
  filePicked.classList.remove('show');
  dropzone.style.display = '';
});

async function submitApplication(){
  clearMsg();
  clearFieldErrors();

  if (!selectedTitle) { showError(document.getElementById('applyError'), 'Please choose a position above first.'); return; }

  const firstName=document.getElementById('firstName').value.trim();
  const lastName=document.getElementById('lastName').value.trim();
  const email=document.getElementById('applyEmail').value.trim();
  const contactNumber=document.getElementById('contactNumber').value.trim();
  const birthdate=document.getElementById('birthdate').value;
  const gender=document.getElementById('gender').value;
  const civilStatus=document.getElementById('civilStatus').value;
  const referralSource=document.getElementById('referralSource').value;
  const file=cvFileInput.files[0];
  const modeInput=document.querySelector('input[name="interviewMode"]:checked');
  const interviewMode=modeInput?modeInput.value:'';
  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  const contactDigitsOnly = contactNumber.replace(/[\s\-()]/g, '');
  const contactOk = /^(\+63|0)9\d{9}$/.test(contactDigitsOnly);
  const birthdateOk = !!birthdate && !(new Date(birthdate + 'T00:00:00') > new Date()) && calcAge(birthdate) >= 18;

  let valid = true;
  if (!firstName) { document.getElementById('field-firstName').classList.add('has-error'); valid = false; }
  if (!lastName) { document.getElementById('field-lastName').classList.add('has-error'); valid = false; }
  if (!emailOk) { document.getElementById('field-applyEmail').classList.add('has-error'); valid = false; }
  if (!contactOk) { document.getElementById('field-contactNumber').classList.add('has-error'); valid = false; }
  if (!birthdateOk) { document.getElementById('field-birthdate').classList.add('has-error'); valid = false; }
  if (!interviewMode) { document.getElementById('field-interviewMode').classList.add('has-error'); valid = false; }
  if (!file) { document.getElementById('field-cvFile').classList.add('has-error'); valid = false; }
  if (!valid) {
    showError(document.getElementById('applyError'),'Please complete all required fields below.');
    scrollToFirstError();
    return;
  }

  const fd=new FormData();
  fd.append('first_name',firstName);
  fd.append('last_name',lastName);
  fd.append('email',email);
  fd.append('contact_number',contactDigitsOnly);
  fd.append('birthdate',birthdate);
  fd.append('gender',gender);
  fd.append('civil_status',civilStatus);
  fd.append('referral_source',referralSource);
  fd.append('position',selectedTitle);
  fd.append('interview_mode',interviewMode);
  fd.append('notes',document.getElementById('applyNotes').value.trim());
  fd.append('cv',file);

  const btn = document.getElementById('submitApplyBtn');
  btn.disabled=true;
  btn.classList.add('loading');
  try{
    const r=await fetch(`${API_BASE}/applicants_upload.php`,{method:'POST',body:fd});
    const d=await r.json();
    if(!d.success){
      showError(document.getElementById('applyError'),d.message||'Application failed. Please try again.');
      return;
    }
    const s=document.getElementById('applySuccess');
    s.innerHTML = `
      <div class="success-title"><span class="tick">✓</span>Application submitted!</div>
      HR will review your CV and reach out to schedule your <b>${esc(interviewMode)}</b> interview.
      <div class="summary-list">
        <div><span>Position</span><b>${esc(selectedTitle)}</b></div>
        <div><span>Name</span><b>${esc(firstName)} ${esc(lastName)}</b></div>
        <div><span>Email</span><b>${esc(email)}</b></div>
        <div><span>CV attached</span><b>${esc(file.name)}</b></div>
      </div>
      <div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(79,122,74,.25)">
        Log in to the <a href="login.php" style="color:var(--ok);font-weight:800">applicant portal</a> with your email and the password <code style="background:rgba(0,0,0,.06);padding:2px 6px;border-radius:5px">applicant123</code> to check your status anytime.
      </div>`;
    s.classList.add('show');
    document.getElementById('formFields').style.display = 'none';
    setStep(3);
    s.scrollIntoView({ behavior: 'smooth', block: 'start' });
    loadPositions();
  }catch(e){
    showError(document.getElementById('applyError'),'Could not reach the server. Please check your connection and try again.');
  }
  finally{
    btn.disabled=false;
    btn.classList.remove('loading');
  }
}

loadPositions();
</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
</body>
</html>