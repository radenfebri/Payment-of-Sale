/* =======================
   Pretty Toast & Confirm
   (Tailwind + Font Awesome optional)
   ======================= */
document.addEventListener("DOMContentLoaded", () => {
  // --- inject styles (sekali saja) ---
  if (!document.getElementById("pretty-feedback-style")) {
    const style = document.createElement("style");
    style.id = "pretty-feedback-style";
    style.textContent = `
/* Toast animations */
@keyframes toast-in-right { from {opacity:0; transform: translateX(16px) translateY(0) scale(.98);} to {opacity:1; transform: translateX(0) translateY(0) scale(1);} }
@keyframes toast-in-left  { from {opacity:0; transform: translateX(-16px) translateY(0) scale(.98);} to {opacity:1; transform: translateX(0) translateY(0) scale(1);} }
@keyframes toast-in-center{ from {opacity:0; transform: translateY(-8px) scale(.98);} to {opacity:1; transform: translateY(0) scale(1);} }
@keyframes toast-out      { to   {opacity:0; transform: translateY(-8px) scale(.98);} }

.toast-enter-right { animation: toast-in-right .25s ease-out; }
.toast-enter-left  { animation: toast-in-left  .25s ease-out; }
.toast-enter-center{ animation: toast-in-center.25s ease-out; }
.toast-exit        { animation: toast-out .2s ease-in forwards; }

/* Modal animations */
@keyframes modal-in { from {opacity:0;} to {opacity:1;} }
@keyframes dialog-in { from {opacity:0; transform: translateY(6px) scale(.98);} to {opacity:1; transform: translateY(0) scale(1);} }
@keyframes dialog-out { to {opacity:0; transform: translateY(6px) scale(.98);} }

.modal-overlay-enter { animation: modal-in .15s ease-out; }
.modal-dialog-enter  { animation: dialog-in .2s ease-out; }
.modal-dialog-exit   { animation: dialog-out .15s ease-in forwards; }
`;
    document.head.appendChild(style);
  }
});

/* =============== Helpers =============== */
const POSITIONS = {
  "top-right":   "top-5 right-5",
  "top-left":    "top-5 left-5",
  "bottom-right":"bottom-5 right-5",
  "bottom-left": "bottom-5 left-5",
  "top-center":  "top-5 left-1/2 -translate-x-1/2",
  "bottom-center":"bottom-5 left-1/2 -translate-x-1/2",
};
function getContainer(position) {
  const id = `toast-container-${position}`;
  let el = document.getElementById(id);
  if (!el) {
    el = document.createElement("div");
    el.id = id;
    el.className = `fixed ${POSITIONS[position]} z-[1100] flex flex-col gap-2 pointer-events-none`;
    document.body.appendChild(el);
  }
  return el;
}
function hasFA() {
  return !!document.querySelector('link[href*="font-awesome"],link[href*="fontawesome"],link[href*="cdnjs.cloudflare.com/ajax/libs/font-awesome"]');
}
function iconFor(type) {
  const fa = hasFA();
  const map = {
    success: fa ? '<i class="fa-solid fa-circle-check"></i>' : '✅',
    error:   fa ? '<i class="fa-solid fa-circle-xmark"></i>' : '❌',
    info:    fa ? '<i class="fa-solid fa-circle-info"></i>'  : 'ℹ️',
    warning: fa ? '<i class="fa-solid fa-triangle-exclamation"></i>' : '⚠️',
    question:fa ? '<i class="fa-solid fa-circle-question"></i>' : '❓',
  };
  return map[type] || map.info;
}
function colorSet(type) {
  // warna bg/border/text utk toast & modal
  switch (type) {
    case "success": return {bg:"bg-emerald-50 dark:bg-emerald-900/40", ring:"ring-emerald-200 dark:ring-emerald-700/60", text:"text-emerald-900 dark:text-emerald-50", bar:"bg-emerald-500"};
    case "error":   return {bg:"bg-rose-50 dark:bg-rose-900/40",       ring:"ring-rose-200 dark:ring-rose-700/60",       text:"text-rose-900 dark:text-rose-50",       bar:"bg-rose-500"};
    case "warning": return {bg:"bg-amber-50 dark:bg-amber-900/40",     ring:"ring-amber-200 dark:ring-amber-700/60",     text:"text-amber-900 dark:text-amber-50",     bar:"bg-amber-500"};
    case "question":
    case "info":
    default:        return {bg:"bg-sky-50 dark:bg-sky-900/40",         ring:"ring-sky-200 dark:ring-sky-700/60",         text:"text-sky-900 dark:text-sky-50",         bar:"bg-sky-500"};
  }
}

/* =============== Toast =============== */
/**
 * showToast(message, typeOrOpts='success', duration=3000)
 * typeOrOpts bisa: 'success'|'error'|'info'|'warning' atau {type,duration,position,title,closable}
 */
window.showToast = function(message, typeOrOpts = "success", legacyDuration) {
  const defaults = { type:"success", duration:3000, position:"top-right", title:null, closable:true, max:6 };
  let opts;
  if (typeof typeOrOpts === "string") {
    opts = {...defaults, type:typeOrOpts, duration: legacyDuration ?? defaults.duration};
  } else {
    opts = {...defaults, ...(typeOrOpts || {})};
  }
  if (!POSITIONS[opts.position]) opts.position = "top-right";

  const container = getContainer(opts.position);
  const {bg, ring, text, bar} = colorSet(opts.type);

  // limit jumlah toast (buang paling awal)
  while (container.children.length >= opts.max) {
    container.firstElementChild?.remove();
  }

  const wrap = document.createElement("div");
  wrap.setAttribute("role","status");
  wrap.setAttribute("aria-live","polite");
  wrap.className = `pointer-events-auto ${bg} ${text} ring-1 ${ring} shadow-lg rounded-xl px-4 py-3 min-w-[260px] max-w-[360px] backdrop-blur-sm
                    flex items-start gap-3`;
  // animasi masuk sesuai posisi
  const enterClass = opts.position.includes("left") ? "toast-enter-left" :
                     opts.position.includes("center") ? "toast-enter-center" : "toast-enter-right";
  wrap.classList.add(enterClass);

  // icon
  const icon = document.createElement("div");
  icon.className = "mt-0.5 text-lg shrink-0";
  icon.innerHTML = iconFor(opts.type);

  // text area
  const body = document.createElement("div");
  body.className = "flex-1";
  const title = opts.title ? `<div class="font-semibold leading-none mb-0.5">${opts.title}</div>` : "";
  body.innerHTML = `${title}<div class="text-sm leading-snug">${message}</div>`;

  // close
  const closeBtn = document.createElement("button");
  closeBtn.type = "button";
  closeBtn.className = "ms-1 shrink-0 rounded-md px-2 py-1 hover:bg-black/5 dark:hover:bg-white/10 transition";
  closeBtn.innerHTML = hasFA() ? '<i class="fa-solid fa-xmark"></i>' : '✖';
  closeBtn.onclick = () => dismiss(true);

  // progress
  const progress = document.createElement("div");
  progress.className = `h-1 w-full rounded-b-xl overflow-hidden absolute left-0 right-0 bottom-0`;
  const barEl = document.createElement("div");
  barEl.className = `${bar} h-full w-full origin-left`;
  barEl.style.transform = "scaleX(1)";
  barEl.style.transition = `transform ${opts.duration}ms linear`;
  progress.appendChild(barEl);

  // composition
  const inner = document.createElement("div");
  inner.className = "relative flex items-start gap-3";
  inner.appendChild(icon);
  inner.appendChild(body);
  if (opts.closable) inner.appendChild(closeBtn);
  wrap.appendChild(inner);
  wrap.appendChild(progress);

  // mount
  container.appendChild(wrap);

  // timer + pause on hover
  let remaining = opts.duration;
  let start = Date.now();
  let ended = false;
  const startProgress = () => {
    start = Date.now();
    requestAnimationFrame(() => { barEl.style.transform = "scaleX(0)"; });
  };
  const pause = () => {
    const elapsed = Date.now() - start;
    remaining = Math.max(0, remaining - elapsed);
    barEl.style.transition = "none";
    const doneRatio = (opts.duration - remaining) / opts.duration;
    barEl.style.transform = `scaleX(${Math.max(0, 1 - doneRatio)})`;
    clearTimeout(timer);
  };
  const resume = () => {
    barEl.style.transition = `transform ${remaining}ms linear`;
    requestAnimationFrame(() => { barEl.style.transform = "scaleX(0)"; });
    timer = setTimeout(dismiss, remaining);
    start = Date.now();
  };
  wrap.addEventListener("mouseenter", pause);
  wrap.addEventListener("mouseleave", () => { if (!ended) resume(); });

  function dismiss(immediate = false) {
    if (ended) return;
    ended = true;
    clearTimeout(timer);
    wrap.classList.remove(enterClass);
    if (immediate) {
      wrap.remove();
      return;
    }
    wrap.classList.add("toast-exit");
    wrap.addEventListener("animationend", () => wrap.remove());
  }

  // kick
  startProgress();
  let timer = setTimeout(dismiss, remaining);
};

/* =============== Confirm Modal =============== */
/**
 * showConfirm(messageOrOpts, onConfirm?, onCancel?)
 * - Backward compatible: showConfirm("Yakin?", ok, cancel)
 * - Object form:
 *   showConfirm({
 *     title: "Hapus Barang?",
 *     message: "Aksi ini tidak bisa dibatalkan.",
 *     type: "warning"|"error"|"info"|"success"|"question",
 *     confirmText: "Hapus",
 *     cancelText: "Batal",
 *     onConfirm(){}, onCancel(){}
 *   })
 */
window.showConfirm = function(messageOrOpts, onConfirm, onCancel) {
  const isObj = typeof messageOrOpts === "object" && messageOrOpts !== null;
  const opts = isObj ? {
    title: messageOrOpts.title ?? "Konfirmasi",
    message: messageOrOpts.message ?? "",
    type: messageOrOpts.type ?? "question",
    confirmText: messageOrOpts.confirmText ?? "OK",
    cancelText: messageOrOpts.cancelText ?? "Batal",
    onConfirm: messageOrOpts.onConfirm,
    onCancel: messageOrOpts.onCancel,
  } : {
    title: "Konfirmasi",
    message: String(messageOrOpts ?? ""),
    type: "question",
    confirmText: "OK",
    cancelText: "Batal",
    onConfirm, onCancel
  };

  const {bg, ring, text} = colorSet(opts.type);
  const overlay = document.createElement("div");
  overlay.className = "fixed inset-0 z-[1200] bg-black/50 backdrop-blur-sm modal-overlay-enter";

  const dialog = document.createElement("div");
  dialog.className = `mx-auto modal-dialog-enter
    absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2
    max-w-sm w-[90%] rounded-2xl ${bg} ${text} ring-1 ${ring} shadow-2xl p-5`;

  const iconWrap = document.createElement("div");
  iconWrap.className = "text-2xl mb-2";
  iconWrap.innerHTML = iconFor(opts.type);

  const titleEl = document.createElement("h3");
  titleEl.className = "text-lg font-semibold";
  titleEl.textContent = opts.title;

  const msgEl = document.createElement("p");
  msgEl.className = "mt-1 text-sm opacity-90";
  msgEl.textContent = opts.message;

  const btns = document.createElement("div");
  btns.className = "mt-4 flex justify-end gap-2";
  const cancelBtn = document.createElement("button");
  cancelBtn.className = "px-4 py-2 rounded-md bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/20 transition";
  cancelBtn.textContent = opts.cancelText;

  const confirmBtn = document.createElement("button");
  const confirmColor = (opts.type === "error" || opts.type === "warning") ? "bg-rose-600 hover:bg-rose-500 text-white"
                     : (opts.type === "success") ? "bg-emerald-600 hover:bg-emerald-500 text-white"
                     : "bg-sky-600 hover:bg-sky-500 text-white";
  confirmBtn.className = `px-4 py-2 rounded-md ${confirmColor} transition`;
  confirmBtn.textContent = opts.confirmText;

  btns.appendChild(cancelBtn);
  btns.appendChild(confirmBtn);

  dialog.appendChild(iconWrap);
  dialog.appendChild(titleEl);
  dialog.appendChild(msgEl);
  dialog.appendChild(btns);
  overlay.appendChild(dialog);
  document.body.appendChild(overlay);

  // focus trap + keyboard
  const prevFocus = document.activeElement;
  confirmBtn.focus();

  function cleanup() {
    dialog.classList.add("modal-dialog-exit");
    dialog.addEventListener("animationend", () => overlay.remove());
    prevFocus && prevFocus.focus?.();
  }
  function doCancel() {
    try { opts.onCancel && opts.onCancel(); } finally { cleanup(); }
  }
  function doConfirm() {
    try { opts.onConfirm && opts.onConfirm(); } finally { cleanup(); }
  }

  cancelBtn.addEventListener("click", doCancel);
  confirmBtn.addEventListener("click", doConfirm);
  overlay.addEventListener("click", (e) => { if (e.target === overlay) doCancel(); });
  window.addEventListener("keydown", function onKey(e) {
    if (!document.body.contains(overlay)) return window.removeEventListener("keydown", onKey);
    if (e.key === "Escape") doCancel();
    if (e.key === "Enter")  doConfirm();
  });
};
