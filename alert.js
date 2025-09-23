document.addEventListener("DOMContentLoaded", function () {
  // Buat container toast sekali
  if (!document.getElementById("toast-container")) {
    const container = document.createElement("div");
    container.id = "toast-container";
    container.className =
      "fixed top-5 right-5 flex flex-col gap-2 z-[1100] pointer-events-none";
    document.body.appendChild(container);
  }

  // Fungsi global untuk menampilkan toast
  window.showToast = function (message, type = "success", duration = 3000) {
    const container = document.getElementById("toast-container");
    if (!container) return;

    const colors = {
      success: "bg-green-500",
      error: "bg-red-500",
      info: "bg-blue-500",
      warning: "bg-yellow-500 text-black",
    };
    const colorClass = colors[type] || colors.info;

    const toast = document.createElement("div");
    toast.className = `px-4 py-2 rounded shadow-md text-white font-medium ${colorClass} animate-slide-in pointer-events-auto`;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add("animate-slide-out");
      toast.addEventListener("animationend", () => toast.remove());
    }, duration);
  };

  // Tambahkan style animasi jika belum ada
  if (!document.getElementById("toast-style")) {
    const style = document.createElement("style");
    style.id = "toast-style";
    style.innerHTML = `
      @keyframes slide-in {0% {opacity:0; transform: translateX(100%);} 100% {opacity:1; transform: translateX(0);}}
      @keyframes slide-out {0% {opacity:1; transform: translateX(0);} 100% {opacity:0; transform: translateX(100%);}}
      .animate-slide-in { animation: slide-in 0.3s ease-out; }
      .animate-slide-out { animation: slide-out 0.3s ease-in; }
    `;
    document.head.appendChild(style);
  }
});

window.showConfirm = function (message, onConfirm, onCancel) {
  const overlay = document.createElement("div");
  overlay.className =
    "fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[1200]";

  const box = document.createElement("div");
  box.className =
    "bg-white rounded shadow-md p-6 w-80 max-w-full text-center flex flex-col gap-4";

  const msg = document.createElement("p");
  msg.textContent = message;

  const btnContainer = document.createElement("div");
  btnContainer.className = "flex justify-center gap-4";

  const okBtn = document.createElement("button");
  okBtn.textContent = "OK";
  okBtn.className =
    "px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600";
  okBtn.onclick = () => {
    onConfirm && onConfirm();
    overlay.remove();
  };

  const cancelBtn = document.createElement("button");
  cancelBtn.textContent = "Cancel";
  cancelBtn.className =
    "px-4 py-2 bg-gray-300 text-black rounded hover:bg-gray-400";
  cancelBtn.onclick = () => {
    onCancel && onCancel();
    overlay.remove();
  };

  btnContainer.appendChild(okBtn);
  btnContainer.appendChild(cancelBtn);
  box.appendChild(msg);
  box.appendChild(btnContainer);
  overlay.appendChild(box);
  document.body.appendChild(overlay);
};
