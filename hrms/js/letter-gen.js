/* =====================================================================
   letter-gen.js — auto-generated formal request letter.
   Builds the letter text from the request form answers and renders a
   real one-page PDF (Helvetica text + the drawn signature as a JPEG
   placed above the requester's printed name). No external libraries.

   API:
     LetterGen.lines(data)           -> array of letter body lines
     LetterGen.text(data)            -> plain text incl. signature slot
     LetterGen.sigJpegFromCanvas(c)  -> {bytes:Uint8Array, w, h}
     LetterGen.buildPdf(data, sig)   -> Blob (application/pdf)

   data = {
     dateLabel, addressee, name, roleLabel, subject, bodyParas: [..]
   }
   ===================================================================== */
(function () {
  function wrap(text, max) {
    const words = String(text).split(/\s+/).filter(Boolean);
    const out = [];
    let cur = '';
    words.forEach(w => {
      if ((cur + ' ' + w).trim().length > max) { if (cur) out.push(cur); cur = w; }
      else cur = (cur + ' ' + w).trim();
    });
    if (cur) out.push(cur);
    return out.length ? out : [''];
  }

  function lines(data) {
    const L = [];
    L.push(data.dateLabel || '');
    L.push('');
    L.push('To: ' + (data.addressee || 'The Management'));
    L.push('Brew & Co. Coffee Shop');
    L.push('Philippines');
    L.push('');
    L.push('Dear Sir/Madam:');
    L.push('');
    L.push('Subject: ' + (data.subject || 'Request'));
    L.push('');
    (data.bodyParas || []).forEach(p => {
      wrap(p, 88).forEach(w => L.push(w));
      L.push('');
    });
    L.push('I am hoping for your kind consideration and favorable action on this request.');
    L.push('');
    return L;
  }

  function text(data) {
    const L = lines(data);
    L.push('Respectfully yours,');
    L.push('');
    L.push('[ Your signature will be placed here, above your name, when you sign. ]');
    L.push('');
    L.push(String(data.name || '').toUpperCase());
    L.push(data.roleLabel || '');
    return L.join('\n');
  }

  function sigJpegFromCanvas(canvas) {
    const w = 600;
    const h = Math.max(60, Math.round(600 * (canvas.height || 300) / (canvas.width || 1000)));
    const t = document.createElement('canvas');
    t.width = w; t.height = h;
    const cx = t.getContext('2d');
    cx.fillStyle = '#ffffff';
    cx.fillRect(0, 0, w, h);
    cx.drawImage(canvas, 0, 0, w, h);
    const url = t.toDataURL('image/jpeg', 0.92);
    const bin = atob(url.split(',')[1]);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return { bytes: bytes, w: w, h: h };
  }

  function pdfEsc(s) {
    return String(s).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
  }

  function buildPdf(data, sig) {
    const enc = new TextEncoder();
    const ops = [];
    let y = 752;
    const lead = 16;
    function put(txt, font, size) {
      if (String(txt).trim() === '') { y -= 6; return; }
      ops.push('BT /' + font + ' ' + size + ' Tf 1 0 0 1 72 ' + y.toFixed(1) + ' Tm (' + pdfEsc(txt) + ') Tj ET');
      y -= lead;
    }
    lines(data).forEach(t => put(t, 'F1', 11));
    y -= 4;
    put('Respectfully yours,', 'F1', 11);
    if (sig) {
      const sigW = 170;
      const sigH = Math.round(sigW * sig.h / sig.w);
      y -= 6;
      ops.push('q ' + sigW + ' 0 0 ' + sigH + ' 72 ' + (y - sigH).toFixed(1) + ' cm /Im0 Do Q');
      y -= sigH + 6;
    } else {
      y -= 40;
    }
    // signature line above the printed name
    ops.push('0.8 w 72 ' + (y + 12).toFixed(1) + ' m 262 ' + (y + 12).toFixed(1) + ' l S');
    put(String(data.name || '').toUpperCase(), 'F2', 11);
    put(data.roleLabel || '', 'F1', 10);

    const contentBytes = enc.encode(ops.join('\n'));
    const hasImg = !!sig;
    const pageDict = '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</Font<</F1 4 0 R/F2 5 0 R>>'
      + (hasImg ? '/XObject<</Im0 6 0 R>>' : '') + '>>/Contents 7 0 R>>';

    const parts = [];
    let off = 0;
    const offs = [];
    function w(s) { const b = enc.encode(s); parts.push(b); off += b.length; }
    function wb(b) { parts.push(b); off += b.length; }
    function obj(n, dict, streamBytes) {
      offs[n] = off;
      w(n + ' 0 obj\n' + dict + '\n');
      if (streamBytes) { w('stream\n'); wb(streamBytes); w('\nendstream\n'); }
      w('endobj\n');
    }
    w('%PDF-1.4\n');
    obj(1, '<</Type/Catalog/Pages 2 0 R>>');
    obj(2, '<</Type/Pages/Kids[3 0 R]/Count 1>>');
    obj(3, pageDict);
    obj(4, '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>');
    obj(5, '<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>>');
    if (hasImg) obj(6, '<</Type/XObject/Subtype/Image/Width ' + sig.w + ' /Height ' + sig.h + ' /ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/DCTDecode/Length ' + sig.bytes.length + '>>', sig.bytes);
    obj(7, '<</Length ' + contentBytes.length + '>>', contentBytes);
    const xrefStart = off;
    const count = hasImg ? 8 : 7;
    w('xref\n0 ' + count + '\n0000000000 65535 f \n');
    for (let n = 1; n < count; n++) w(String(offs[n]).padStart(10, '0') + ' 00000 n \n');
    w('trailer\n<</Size ' + count + '/Root 1 0 R>>\nstartxref\n' + xrefStart + '\n%%EOF');
    return new Blob(parts, { type: 'application/pdf' });
  }

  /* ================= DOCX (Word document) output ================= */
  var CRC_TABLE = (function () {
    var t = new Uint32Array(256);
    for (var n = 0; n < 256; n++) {
      var c = n;
      for (var k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
      t[n] = c >>> 0;
    }
    return t;
  })();
  function crc32(bytes) {
    var c = 0xFFFFFFFF;
    for (var i = 0; i < bytes.length; i++) c = CRC_TABLE[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function zipStore(files) {
    const enc = new TextEncoder();
    const chunks = [], central = [];
    let offset = 0;
    const now = new Date();
    const dosTime = ((now.getHours() << 11) | (now.getMinutes() << 5) | (now.getSeconds() >> 1)) & 0xFFFF;
    const dosDate = (((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate()) & 0xFFFF;
    files.forEach(f => {
      const nameB = enc.encode(f.name);
      const crc = crc32(f.bytes);
      const lh = new DataView(new ArrayBuffer(30));
      lh.setUint32(0, 0x04034b50, true); lh.setUint16(4, 20, true); lh.setUint16(6, 0, true); lh.setUint16(8, 0, true);
      lh.setUint16(10, dosTime, true); lh.setUint16(12, dosDate, true); lh.setUint32(14, crc, true);
      lh.setUint32(18, f.bytes.length, true); lh.setUint32(22, f.bytes.length, true);
      lh.setUint16(26, nameB.length, true); lh.setUint16(28, 0, true);
      chunks.push(new Uint8Array(lh.buffer), nameB, f.bytes);
      central.push({ nameB, crc, size: f.bytes.length, offset });
      offset += 30 + nameB.length + f.bytes.length;
    });
    const cdStart = offset;
    let cdSize = 0;
    central.forEach(c => {
      const ch = new DataView(new ArrayBuffer(46));
      ch.setUint32(0, 0x02014b50, true); ch.setUint16(4, 20, true); ch.setUint16(6, 20, true);
      ch.setUint16(8, 0, true); ch.setUint16(10, 0, true);
      ch.setUint16(12, dosTime, true); ch.setUint16(14, dosDate, true); ch.setUint32(16, c.crc, true);
      ch.setUint32(20, c.size, true); ch.setUint32(24, c.size, true);
      ch.setUint16(28, c.nameB.length, true); ch.setUint16(30, 0, true); ch.setUint16(32, 0, true);
      ch.setUint16(34, 0, true); ch.setUint16(36, 0, true); ch.setUint32(38, 0, true);
      ch.setUint32(42, c.offset, true);
      chunks.push(new Uint8Array(ch.buffer), c.nameB);
      cdSize += 46 + c.nameB.length;
    });
    const eo = new DataView(new ArrayBuffer(22));
    eo.setUint32(0, 0x06054b50, true); eo.setUint16(4, 0, true); eo.setUint16(6, 0, true);
    eo.setUint16(8, central.length, true); eo.setUint16(10, central.length, true);
    eo.setUint32(12, cdSize, true); eo.setUint32(16, cdStart, true); eo.setUint16(20, 0, true);
    chunks.push(new Uint8Array(eo.buffer));
    return new Blob(chunks, { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
  }
  function xmlEsc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function para(text, opts) {
    opts = opts || {};
    if (String(text).trim() === '' && !opts.border) return '<w:p/>';
    const rPr = '<w:rPr><w:rFonts w:ascii="Georgia" w:hAnsi="Georgia"/><w:sz w:val="' + (opts.size || 22) + '"/>' + (opts.bold ? '<w:b/>' : '') + '</w:rPr>';
    const pBdr = opts.border ? '<w:pBdr><w:top w:val="single" w:sz="6" w:space="2" w:color="000000"/></w:pBdr>' : '';
    const pPr = (pBdr || opts.align) ? '<w:pPr>' + pBdr + (opts.align ? '<w:jc w:val="' + opts.align + '"/>' : '') + '</w:pPr>' : '';
    return '<w:p>' + pPr + '<w:r>' + rPr + '<w:t xml:space="preserve">' + xmlEsc(text) + '</w:t></w:r></w:p>';
  }
  function sigDrawing(sig) {
    const inchesW = 2.4;
    const cx = Math.round(inchesW * 914400);
    const cy = Math.round(cx * sig.h / sig.w);
    return '<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
      + '<wp:extent cx="' + cx + '" cy="' + cy + '"/><wp:docPr id="1" name="Signature"/>'
      + '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
      + '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="1" name="signature.jpg"/><pic:cNvPicPr/></pic:nvPicPr>'
      + '<pic:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
      + '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' + cx + '" cy="' + cy + '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
      + '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
  }
  function buildDocx(data, sig) {
    const enc = new TextEncoder();
    const body = [];
    lines(data).forEach(t => body.push(para(t)));
    body.push(para('Respectfully yours,'));
    body.push('<w:p/>');
    if (sig) body.push(sigDrawing(sig));
    body.push(para(String(data.name || '').toUpperCase(), { bold: true, border: true }));
    body.push(para(data.roleLabel || ''));
    const documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      + '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
      + 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
      + 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
      + 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
      + 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
      + '<w:body>' + body.join('')
      + '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
      + '</w:body></w:document>';
    const contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      + '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      + '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      + '<Default Extension="xml" ContentType="application/xml"/>'
      + '<Default Extension="jpeg" ContentType="image/jpeg"/>'
      + '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
      + '</Types>';
    const rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      + '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
      + '</Relationships>';
    const docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      + '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.jpeg"/>'
      + '</Relationships>';
    const files = [
      { name: '[Content_Types].xml', bytes: enc.encode(contentTypes) },
      { name: '_rels/.rels', bytes: enc.encode(rootRels) },
      { name: 'word/document.xml', bytes: enc.encode(documentXml) }
    ];
    if (sig) {
      files.push({ name: 'word/_rels/document.xml.rels', bytes: enc.encode(docRels) });
      files.push({ name: 'word/media/image1.jpeg', bytes: sig.bytes });
    }
    return zipStore(files);
  }


  /* ================= PAYSLIP DOCX (Word document, payslip only) ================= */
  function psCell(text, bold, width) {
    return '<w:tc><w:tcPr><w:tcW w:w="' + width + '" w:type="dxa"/>'
      + (bold ? '<w:shd w:val="clear" w:color="auto" w:fill="F3EAD9"/>' : '')
      + '</w:tcPr>' + para(text, { bold: bold, size: 20 }) + '</w:tc>';
  }
  function psRow(label, amount, bold) {
    return '<w:tr>' + psCell(label, bold, 6240) + psCell(amount, bold, 3120) + '</w:tr>';
  }
  function buildPayslipDocx(info, rows) {
    info = info || {}; rows = rows || [];
    const enc = new TextEncoder();
    const body = [];
    body.push(para(info.company || 'Brew & Co.', { bold: true, size: 32, align: 'center' }));
    body.push(para('PAYSLIP', { bold: true, size: 26, align: 'center' }));
    body.push(para(info.period ? 'Pay period: ' + info.period : '', { size: 20, align: 'center' }));
    body.push(para(''));
    body.push(para('Employee: ' + (info.name || ''), { bold: true }));
    body.push(para('Position: ' + (info.role || '')));
    body.push(para('Email: ' + (info.email || '')));
    if (info.released) body.push(para('Released: ' + info.released));
    body.push(para(''));
    const trs = [psRow('Description', 'Amount (PHP)', true)];
    rows.forEach(r => trs.push(psRow(r.label, String(r.amount), !!r.net)));
    body.push('<w:tbl><w:tblPr><w:tblW w:w="9360" w:type="dxa"/><w:tblBorders>'
      + '<w:top w:val="single" w:sz="4" w:space="0" w:color="8A7A6A"/><w:left w:val="single" w:sz="4" w:space="0" w:color="8A7A6A"/>'
      + '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="8A7A6A"/><w:right w:val="single" w:sz="4" w:space="0" w:color="8A7A6A"/>'
      + '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="8A7A6A"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="8A7A6A"/>'
      + '</w:tblBorders></w:tblPr>' + trs.join('') + '</w:tbl>');
    body.push(para(''));
    body.push(para('System-generated by Brew & Co. HRMS - valid without a handwritten signature.', { size: 18 }));
    const documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      + '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
      + '<w:body>' + body.join('')
      + '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
      + '</w:body></w:document>';
    const contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      + '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      + '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      + '<Default Extension="xml" ContentType="application/xml"/>'
      + '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
      + '</Types>';
    const rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      + '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
      + '</Relationships>';
    return zipStore([
      { name: '[Content_Types].xml', bytes: enc.encode(contentTypes) },
      { name: '_rels/.rels', bytes: enc.encode(rootRels) },
      { name: 'word/document.xml', bytes: enc.encode(documentXml) }
    ]);
  }

  window.LetterGen = {
    lines: lines,
    text: text,
    sigJpegFromCanvas: sigJpegFromCanvas,
    buildPdf: buildPdf,
    buildDocx: buildDocx,
    buildPayslipDocx: buildPayslipDocx
  };
})();
