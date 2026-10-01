/* ===== Inventory registered-signature pad (profile pages) ===== */
let invSigStrokes = 0, invSigDrawing = false;
function invSigInit(){
  const pad = document.getElementById('invSigPad');
  if(!pad) return;
  const ctx = pad.getContext('2d');
  ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#22303f';
  function pos(e){ const r = pad.getBoundingClientRect(); return { x:(e.clientX-r.left)*(pad.width/r.width), y:(e.clientY-r.top)*(pad.height/r.height) }; }
  const gate = document.getElementById('invSigGate');
  if (gate) { document.getElementById('invSigGateBtn').onclick = async () => { if (!await swalAsk('Confirm e-sign: you are about to enter your official inventory e-signature. It will be required when signing purchase requests. Continue?')) return; gate.remove(); pad.style.pointerEvents = 'auto'; }; }
  pad.addEventListener('pointerdown', e => { invSigDrawing = true; invSigStrokes++; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); pad.setPointerCapture(e.pointerId); e.preventDefault(); });
  pad.addEventListener('pointermove', e => { if(!invSigDrawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); });
  ['pointerup','pointercancel','pointerleave'].forEach(ev => pad.addEventListener(ev, () => { invSigDrawing = false; }));
  window.invSigCtx = ctx; window.invSigPadEl = pad;
}
function invSigClear(){
  if(window.invSigCtx && window.invSigPadEl){ window.invSigCtx.clearRect(0,0,window.invSigPadEl.width,window.invSigPadEl.height); }
  invSigStrokes = 0;
}
function invSigSubmit(e){
  if(invSigStrokes === 0){
    e.preventDefault();
    if(window.toast) toast('Please draw your signature first.', true); else alert('Please draw your signature first.');
    return false;
  }
  document.getElementById('invSigData').value = window.invSigPadEl.toDataURL('image/png');
  return sweetConfirmSubmit(e, 'Save this as your official inventory e-signature? It can only be updated once a month.');
}
document.addEventListener('DOMContentLoaded', invSigInit);
