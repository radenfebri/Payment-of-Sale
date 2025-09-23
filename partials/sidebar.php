<aside class="w-64 bg-gray-900 text-white flex flex-col">
    <div class="p-4 text-center border-b border-gray-700">
        <h1 class="text-2xl font-bold">POS System</h1>
        <p class="text-sm text-gray-400">Dashboard</p>
    </div>
    <nav class="flex-1 p-4 space-y-2">
        <a href="index.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gray-700' : '' ?>">🏠 Dashboard</a>
        <a href="penjualan.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'penjualan.php' ? 'bg-gray-700' : '' ?>">🛒 Penjualan</a>
        <a href="barang.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'barang.php' ? 'bg-gray-700' : '' ?>">📦 Barang</a>
        <a href="stok.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'stok.php' ? 'bg-gray-700' : '' ?>">📊 Stok Barang</a>
        <a href="satuan.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'satuan.php' ? 'bg-gray-700' : '' ?>">📏 Satuan</a>
        <a href="history.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'bg-gray-700' : '' ?>">📋 History Transaksi</a>
        <a href="keuangan.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'keuangan.php' ? 'bg-gray-700' : '' ?>">💰 Keuangan</a>
        <a href="piutang.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'piutang.php' ? 'bg-gray-700' : '' ?>">📑 Piutang</a>
        <a href="setting.php" class="block px-3 py-2 rounded hover:bg-gray-700 <?= basename($_SERVER['PHP_SELF']) == 'setting.php' ? 'bg-gray-700' : '' ?>">🛠️ Setting</a>
    </nav>
    <div class="p-4 border-t border-gray-700 text-sm text-gray-400">
        © 2025 POS App
    </div>
</aside>