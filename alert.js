(function () {
  if (window.__basicAlertLoaded) return;
  window.__basicAlertLoaded = true;

  // --- CSS (warna lebih vivid)
  const css = `
.confirm-mask{
  position:fixed; inset:0; z-index:99998;
  display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.40);
  backdrop-filter:saturate(140%) blur(4px);
}
.confirm-box{
  width:min(420px, 92vw);
  background:#ffffff;
  border:1px solid #e5e7eb; border-radius:12px;
  padding:16px;
  box-shadow:0 24px 64px rgba(2,6,23,.18);
  font:14px/1.45 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  transform:translateY(8px) scale(.98); opacity:0;
  transition:opacity .18s ease, transform .18s cubic-bezier(.2,.8,.2,1);
}
.confirm-box.show{transform:translateY(0) scale(1); opacity:1}
.confirm-head{display:flex; align-items:center; gap:10px; margin:2px 2px 8px}
.confirm-icon{
  width:28px; height:28px; border-radius:9px; display:grid; place-items:center; font-size:16px;
  background:#dbeafe; color:#1d4ed8;
}
.confirm-icon.danger{background:#fee2e2; color:#b91c1c}
.confirm-title{margin:0; font-size:15px; font-weight:600; color:#0f172a}
.confirm-body{margin:6px 2px 14px; color:#374151}
.confirm-actions{display:flex; gap:8px; justify-content:flex-end}
.btn{border:1px solid #e5e7eb; border-radius:8px; padding:8px 12px; background:#f9fafb; cursor:pointer; font-weight:600}
.btn:hover{filter:brightness(1.03)}
.btn.primary{background:#2563eb; color:#fff; border-color:#2563eb}
.btn.danger{background:#dc2626; color:#fff; border-color:#dc2626}

/* optional: kunci scroll body saat modal tampil */
.body-no-scroll{overflow:hidden;}


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
  const style = document.createElement("style");
  style.textContent = css;
  document.head.appendChild(style);

  // --- toast stack
  function stack() {
    let el = document.getElementById("toast-stack");
    if (!el) {
      el = document.createElement("div");
      el.id = "toast-stack";
      document.body.appendChild(el);
    }
    return el;
  }

  // Font Awesome optional
  function hasFA() {
    return !!document.querySelector(
      'link[href*="font-awesome"],link[href*="fontawesome"],link[href*="cdnjs.cloudflare.com/ajax/libs/font-awesome"]'
    );
  }
  function iconFor(type) {
    const fa = hasFA();
    const map = {
      success: fa ? '<i class="fa-solid fa-circle-check"></i>' : "✅",
      error: fa ? '<i class="fa-solid fa-circle-xmark"></i>' : "❌",
      info: fa ? '<i class="fa-solid fa-circle-info"></i>' : "ℹ️",
      warning: fa ? '<i class="fa-solid fa-triangle-exclamation"></i>' : "⚠️",
    };
    return map[type] || map.info;
  }

  // --- API: showToast (auto-close default 3000ms)
  window.showToast = function (msg, type = "info", duration = 3000) {
    try {
      const s = stack();
      while (s.children.length >= 6) s.firstElementChild?.remove();

      const t = document.createElement("div");
      t.className = `toast ${type}`;
      t.setAttribute("role", "status");
      t.setAttribute("aria-live", "polite");
      t.innerHTML = `
        <div class="leftbar"></div>
        <div class="icon">${iconFor(type)}</div>
        <div class="msg">${msg}</div>
      `;
      s.appendChild(t);
      requestAnimationFrame(() => t.classList.add("show"));

      // close button (safe-guard)
      const closeBtn = t.querySelector(".close");
      if (closeBtn) {
        closeBtn.onclick = () => {
          t.classList.remove("show");
          setTimeout(() => t.remove(), 160);
        };
      }

      // auto hide + pause on hover
      let rem = Math.max(800, duration | 0);
      let start = Date.now();
      let timer = setTimeout(hide, rem);

      function hide() {
        t.classList.remove("show");
        setTimeout(() => t.remove(), 160);
      }
      t.addEventListener("mouseenter", () => {
        clearTimeout(timer);
        rem -= Date.now() - start;
      });
      t.addEventListener("mouseleave", () => {
        start = Date.now();
        timer = setTimeout(hide, Math.max(300, rem));
      });
    } catch (e) {
      console.log("[TOAST]", type, msg);
    }
  };

  // --- API: showConfirm (center)
  window.showConfirm = function (message, onYes, onNo) {
    try {
      // backward compatible: string | options object
      const isObj = typeof message === "object" && message !== null;
      const opts = isObj ? message : { message };
      const esc = (v) =>
        String(v ?? "").replace(
          /[&<>"']/g,
          (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[m])
        );
      const title = opts.title ?? "Konfirmasi";
      const msg = esc(opts.message ?? "");
      const okText = opts.okText ?? "OK";
      const cancelText = opts.cancelText ?? "Batal";
      const danger = !!opts.danger;

      const mask = document.createElement("div");
      mask.className = "confirm-mask";
      const box = document.createElement("div");
      box.className = "confirm-box";
      box.setAttribute("role", "dialog");
      box.setAttribute("aria-modal", "true");
      box.setAttribute("aria-labelledby", "c-title");
      box.setAttribute("aria-describedby", "c-body");

      box.innerHTML = `
  <div class="confirm-head">
    <div class="confirm-icon ${danger ? "danger" : ""}" aria-hidden="true">
      <i class="fa-solid ${
        danger ? "fa-triangle-exclamation" : "fa-circle-info"
      }"></i>
    </div>
    <h3 id="c-title" class="confirm-title">${esc(title)}</h3>
  </div>
  <div id="c-body" class="confirm-body">${msg}</div>
  <div class="confirm-actions">
    <button class="btn" data-act="no">${esc(cancelText)}</button>
    <button class="btn ${danger ? "danger" : "primary"}" data-act="yes">${esc(
        okText
      )}</button>
  </div>
`;

      mask.appendChild(box);
      document.body.appendChild(mask);
      // kunci scroll
      document.body.classList.add("body-no-scroll");

      // animate in
      requestAnimationFrame(() => box.classList.add("show"));

      const btnNo = box.querySelector('[data-act="no"]');
      const btnYes = box.querySelector('[data-act="yes"]');

      // focus management + tab trap
      const focusables = box.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      (btnYes || first)?.focus();

      function cleanup() {
        try {
          box.classList.remove("show");
        } catch (e) {}
        setTimeout(() => {
          try {
            document.body.removeChild(mask);
          } catch (e) {}
          document.body.classList.remove("body-no-scroll");
          document.removeEventListener("keydown", onKey);
        }, 160);
      }
      function finish(yes) {
        cleanup();
        try {
          if (yes) {
            if (typeof opts.onYes === "function") opts.onYes();
            else if (typeof onYes === "function") onYes();
          } else {
            if (typeof opts.onNo === "function") opts.onNo();
            else if (typeof onNo === "function") onNo();
          }
        } catch (e) {}
      }
      function onKey(e) {
        if (e.key === "Escape") {
          e.preventDefault();
          finish(false);
        }
        if (e.key === "Enter") {
          e.preventDefault();
          finish(true);
        }
        if (e.key === "Tab" && focusables.length) {
          // trap
          if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
          } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        }
      }
      document.addEventListener("keydown", onKey);
      mask.addEventListener("click", (e) => {
        if (e.target === mask) finish(false);
      });
      btnNo.addEventListener("click", () => finish(false));
      btnYes.addEventListener("click", () => finish(true));
    } catch (e) {
      // fallback native confirm
      if (
        confirm(
          String(isObj ? message?.message ?? "Yakin?" : message ?? "Yakin?")
        )
      ) {
        try {
          typeof message === "object" && message?.onYes
            ? message.onYes()
            : onYes && onYes();
        } catch (_) {}
      } else {
        try {
          typeof message === "object" && message?.onNo
            ? message.onNo()
            : onNo && onNo();
        } catch (_) {}
      }
    }
  };
})();
