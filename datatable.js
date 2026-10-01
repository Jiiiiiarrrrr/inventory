/* =====================================================================
   datatable.js — lightweight DataTables-style enhancer (no CDN).
   Add class="dt" to any <table> to get: search, sort, pagination,
   row count, and per-page selector.
   ===================================================================== */
(function () {
  function enhance(table) {
    if (table.dataset.dt === '1') return;
    table.dataset.dt = '1';
    var perDefault = parseInt(table.getAttribute('data-per') || '10', 10);
    var sortCol = table.getAttribute('data-sort');
    var headers = table.querySelectorAll('thead th');
    var sortIndex = sortCol !== null ? parseInt(sortCol, 10) : -1;
    var sortAsc = true;
    var tbody = table.querySelector('tbody') || table;
    function getRows() {
      return Array.prototype.slice.call(tbody.querySelectorAll('tr'))
        .filter(function (r) { return r.querySelector('td'); });
    }
    var search = ''; var page = 0; var per = perDefault; var currentSort = sortIndex;
    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;margin-bottom:12px;';
    var searchBox = document.createElement('input');
    searchBox.type = 'text'; searchBox.placeholder = 'Search...';
    searchBox.style.cssText = 'flex:1;min-width:160px;border:1px solid #eaded0;border-radius:10px;padding:9px 12px;font-size:14px;color:#33261d;outline:none;background:#f6ecdd;';
    var right = document.createElement('div'); right.style.cssText = 'display:flex;gap:10px;align-items:center;';
    var perLabel = document.createElement('span'); perLabel.style.cssText = 'font-size:13px;color:#6f6055;'; perLabel.textContent = 'Show';
    var perSel = document.createElement('select');
    perSel.style.cssText = 'border:1px solid #eaded0;border-radius:8px;padding:6px 8px;font-size:13px;background:#fff;color:#33261d;';
    [5,10,25,50,100].forEach(function(n){ var o=document.createElement('option'); o.value=n; o.textContent=n; if(n===perDefault)o.selected=true; perSel.appendChild(o); });
    right.appendChild(perLabel); right.appendChild(perSel);
    wrap.appendChild(searchBox); wrap.appendChild(right);
    var info = document.createElement('div'); info.style.cssText = 'font-size:13px;color:#6f6055;margin-bottom:10px;';
    var pager = document.createElement('div'); pager.style.cssText = 'display:flex;gap:8px;align-items:center;margin-top:12px;flex-wrap:wrap;';
    var parent = table.parentNode;
    parent.insertBefore(wrap, table); parent.insertBefore(info, table);
    parent.insertBefore(table, table); parent.insertBefore(pager, table.nextSibling);
    function cellText(td){ var t = td.textContent||''; return t.replace(/₱|,|%/g,'').trim(); }
    function compare(a,b){
      if(currentSort<0)return 0;
      var av=cellText(a.children[currentSort]), bv=cellText(b.children[currentSort]);
      var an=parseFloat(av), bn=parseFloat(bv);
      if(!isNaN(an)&&!isNaN(bn)&&av!==''&&bv!=='') return sortAsc?an-bn:bn-an;
      var res=av.localeCompare(bv,undefined,{numeric:true}); return sortAsc?res:-res;
    }
    function matches(row){ if(!search)return true; return(row.textContent||'').toLowerCase().indexOf(search)!==-1; }
    function render(){
      var all=getRows(), filtered=all.filter(matches);
      if(currentSort>=0) filtered.sort(compare);
      var pages=Math.max(1,Math.ceil(filtered.length/per));
      if(page>=pages) page=pages-1;
      var start=page*per, visible=filtered.slice(start,start+per);
      all.forEach(function(r){r.style.display='none';}); visible.forEach(function(r){r.style.display='';});
      var total=all.length, shown=filtered.length;
      info.textContent='Showing '+(shown?(start+1)+'–'+Math.min(start+per,shown)+' of '+shown+' entries':'0 entries')+(search?' (filtered from '+total+' total)':' (of '+total+' total)');
      pager.innerHTML='';
      var prev=document.createElement('button'); prev.textContent='← Prev'; prev.style.cssText='border:1px solid #eaded0;background:#fff;border-radius:8px;padding:6px 12px;font-size:13px;font-weight:700;cursor:pointer;color:#33261d;'; prev.disabled=page<=0; prev.onclick=function(){page--;render();}; pager.appendChild(prev);
      var mid=document.createElement('span'); mid.textContent='Page '+(page+1)+' / '+pages; mid.style.cssText='padding:6px 12px;border:1px solid #eaded0;border-radius:8px;font-size:13px;font-weight:700;background:#fff;color:#33261d;'; pager.appendChild(mid);
      var next=document.createElement('button'); next.textContent='Next →'; next.style.cssText='border:1px solid #eaded0;background:#fff;border-radius:8px;padding:6px 12px;font-size:13px;font-weight:700;cursor:pointer;color:#33261d;'; next.disabled=page>=pages-1; next.onclick=function(){page++;render();}; pager.appendChild(next);
    }
    searchBox.addEventListener('input',function(){search=this.value.toLowerCase();page=0;render();});
    perSel.addEventListener('change',function(){per=parseInt(this.value,10);page=0;render();});
    Array.prototype.forEach.call(headers,function(th,i){
      if(i>=(tbody.querySelector('tr')?tbody.querySelector('tr').children.length:0))return;
      th.style.cursor='pointer'; th.style.userSelect='none';
      th.addEventListener('click',function(){ if(currentSort===i)sortAsc=!sortAsc; else{currentSort=i;sortAsc=true;} render(); });
    });
    render();
  }
  function init(){
    var tables=document.querySelectorAll('table.dt');
    Array.prototype.forEach.call(tables,enhance);
    if(window.MutationObserver){
      var mo=new MutationObserver(function(){ document.querySelectorAll('table.dt').forEach(enhance); });
      mo.observe(document.body,{childList:true,subtree:true});
    }
  }
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);} else{init();}
})();