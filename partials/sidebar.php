<!-- SIDEBAR FIXED -->
<aside class="fixed left-0 top-0 h-screen w-64 bg-gray-900 text-white flex flex-col z-40">
    <div class="p-4 text-center border-b border-gray-700">
        <h1 class="text-2xl font-bold">POS System</h1>
        <p class="text-sm text-gray-400">Dashboard</p>
    </div>

    <!-- penting: biar area menu bisa scroll sendiri -->
    <nav class="flex-1 min-h-0 p-4 space-y-2 overflow-y-auto">
        <a href="index.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
        </a>

        <a href="penjualan.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'penjualan.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-cart-shopping"></i><span>Penjualan</span>
        </a>

        <a href="barang.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'barang.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-box"></i><span>Barang</span>
        </a>

        <a href="stok.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'stok.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-boxes-stacked"></i><span>Stok Barang</span>
        </a>

        <a href="satuan.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'satuan.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-ruler"></i><span>Satuan</span>
        </a>

        <a href="history.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-clipboard-list"></i><span>History Transaksi</span>
        </a>

        <a href="keuangan.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'keuangan.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-sack-dollar"></i><span>Keuangan</span>
        </a>

        <a href="piutang.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'piutang.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i><span>Piutang</span>
        </a>

        <a href="setting.php"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'setting.php' ? 'bg-gray-700' : '' ?>">
            <i class="fa-solid fa-gear"></i><span>Setting</span>
        </a>
    </nav>

    <div class="p-4 border-t border-gray-700 text-sm text-gray-400 text-center">
        &copy; <span id="year"></span> POS App
    </div>
    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
</aside>