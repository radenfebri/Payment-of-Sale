(function () {
  if (window.__basicAlertLoaded) return;
  window.__basicAlertLoaded = true;

  // --- CSS (warna lebih vivid)
  const css = `
#toast-stack{
  position:fixed; right:16px; top:16px;
  display:flex; flex-direction:column; gap:8px; z-index:99999; pointer-events:none;
}
.toast{
  pointer-events:auto;
  min-width:240px; max-width:380px;
  padding:10px 32px 10px 14px; border-radius:10px;
  color:#0b1220; background:#f8fafc; border:1px solid #d7dde7;
  box-shadow:0 6px 24px rgba(0,0,0,.10);
  font:14px/1.35 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  opacity:0; transform:translateY(-8px); transition:opacity .18s ease, transform .18s ease;
  position:relative; display:flex; align-items:flex-start; gap:8px;
}
.toast.show{opacity:1; transform:translateY(0)}
.toast .leftbar{position:absolute; left:-1px; top:-1px; bottom:-1px; width:4px; border-radius:10px 0 0 10px;}
.toast .icon{font-size:16px; line-height:1; margin-top:1px}
.toast .msg{flex:1}
.toast .close{
  position:absolute; right:6px; top:4px; border:0; background:transparent; cursor:pointer;
  font-size:16px; color:#334155; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:6px;
}
.toast .close:hover{background:rgba(0,0,0,.06)}

.toast.success{background:#d1fae5; border-color:#34d399}   /* hijau lebih terang */
.toast.success .leftbar{background:#059669}
.toast.error  {background:#fee2e2; border-color:#f87171}   /* merah lebih terang */
.toast.error  .leftbar{background:#dc2626}
.toast.info   {background:#dbeafe; border-color:#60a5fa}   /* biru lebih terang */
.toast.info   .leftbar{background:#2563eb}
.toast.warning{background:#fef3c7; border-color:#fbbf24}   /* kuning lebih terang */
.toast.warning .leftbar{background:#d97706}

/* Confirm Modal (center) */
.confirm-mask{
  position:fixed; inset:0; background:rgba(0,0,0,.45);
  display:flex; align-items:center; justify-content:center; z-index:99998;
}
.confirm-box{
  width:92%; max-width:380px; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px;
  padding:16px; box-shadow:0 18px 48px rgba(0,0,0,.18);
  font:14px/1.45 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  transform:translateY(-6px); opacity:0; transition:opacity .18s ease, transform .18s ease;
}
.confirm-box.show{transform:translateY(0); opacity:1}
.confirm-box h3{margin:0 0 6px 0; font-size:16px; font-weight:600; color:#0f172a}
.confirm-box p{margin:0 0 14px 0; color:#374151}
.confirm-actions{display:flex; gap:8px; justify-content:flex-end}
.btn{border:1px solid #e5e7eb; border-radius:8px; padding:8px 12px; background:#f9fafb; cursor:pointer; font-weight:500}
.btn.primary{background:#2563eb; color:#fff; border-color:#2563eb}
.btn:hover{filter:brightness(1.03)}
`;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  // --- toast stack
  function stack() {
    let el = document.getElementById('toast-stack');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast-stack';
      document.body.appendChild(el);
    }
    return el;
  }

  // Font Awesome optional
  function hasFA(){
    return !!document.querySelector('link[href*="font-awesome"],link[href*="fontawesome"],link[href*="cdnjs.cloudflare.com/ajax/libs/font-awesome"]');
  }
  function iconFor(type){
    const fa = hasFA();
    const map = {
      success: fa ? '<i class="fa-solid fa-circle-check"></i>' : '✅',
      error:   fa ? '<i class="fa-solid fa-circle-xmark"></i>' : '❌',
      info:    fa ? '<i class="fa-solid fa-circle-info"></i>'  : 'ℹ️',
      warning: fa ? '<i class="fa-solid fa-triangle-exclamation"></i>' : '⚠️',
    };
    return map[type] || map.info;
  }

  // --- API: showToast (auto-close default 3000ms)
  window.showToast = function (msg, type = 'info', duration = 3000) {
    try {
      const s = stack();
      while (s.children.length >= 6) s.firstElementChild?.remove();

      const t = document.createElement('div');
      t.className = `toast ${type}`;
      t.setAttribute('role','status');
      t.setAttribute('aria-live','polite');
      t.innerHTML = `
        <div class="leftbar"></div>
        <div class="icon">${iconFor(type)}</div>
        <div class="msg">${msg}</div>
      `;
      s.appendChild(t);
      requestAnimationFrame(() => t.classList.add('show'));

      // close button (safe-guard)
      const closeBtn = t.querySelector('.close');
      if (closeBtn) {
        closeBtn.onclick = () => {
          t.classList.remove('show');
          setTimeout(() => t.remove(), 160);
        };
      }

      // auto hide + pause on hover
      let rem = Math.max(800, duration|0);
      let start = Date.now();
      let timer = setTimeout(hide, rem);

      function hide(){
        t.classList.remove('show');
        setTimeout(() => t.remove(), 160);
      }
      t.addEventListener('mouseenter', () => {
        clearTimeout(timer);
        rem -= (Date.now() - start);
      });
      t.addEventListener('mouseleave', () => {
        start = Date.now();
        timer = setTimeout(hide, Math.max(300, rem));
      });
    } catch(e) {
      console.log('[TOAST]', type, msg);
    }
  };

  // --- API: showConfirm (center)
  window.showConfirm = function (message, onYes, onNo) {
    try {
      const mask = document.createElement('div');
      mask.className = 'confirm-mask';
      const box = document.createElement('div');
      box.className = 'confirm-box';
      box.innerHTML = `
        <h3>Konfirmasi</h3>
        <p>${String(message ?? '')}</p>
        <div class="confirm-actions">
          <button class="btn" data-act="no">Batal</button>
          <button class="btn primary" data-act="yes">OK</button>
        </div>
      `;
      mask.appendChild(box);
      document.body.appendChild(mask);
      requestAnimationFrame(() => box.classList.add('show'));

      function done(yes){
        try { box.classList.remove('show'); } catch(e){}
        setTimeout(() => { try { document.body.removeChild(mask); } catch(e){} }, 160);
        try { yes ? onYes && onYes() : onNo && onNo(); } catch(e){}
      }
      box.querySelector('[data-act="no"]').onclick  = () => done(false);
      box.querySelector('[data-act="yes"]').onclick = () => done(true);
      mask.addEventListener('click', (e) => { if (e.target === mask) done(false); });

      window.addEventListener('keydown', function esc(e){
        if (!document.body.contains(mask)) return window.removeEventListener('keydown', esc);
        if (e.key === 'Escape') done(false);
        if (e.key === 'Enter')  done(true);
      });
    } catch(e) {
      if (confirm(String(message ?? 'Yakin?'))) { try { onYes && onYes(); } catch(e){} }
      else { try { onNo && onNo(); } catch(e){} }
    }
  };
})();