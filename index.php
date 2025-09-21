<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .chart-container {
      position: relative;
      height: 250px;
      width: 100%;
    }

    .card-value {
      font-size: 1.5rem;
      font-weight: bold;
    }

    @media (min-width: 768px) {
      .card-value {
        font-size: 1.875rem;
      }

      .chart-container {
        height: 280px;
      }
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
  <!-- Sidebar -->
  <?php include "partials/sidebar.php"; ?>

  <!-- Main -->
  <main class="flex-1 p-4 md:p-6">
    <h2 class="text-2xl md:text-3xl font-bold mb-6 flex items-center gap-2">
      <i data-lucide="bar-chart-3" class="w-6 h-6 md:w-7 md:h-7 text-blue-600"></i>
      Ringkasan
    </h2>

    <!-- Alert -->
    <div id="alertLowStock" class="mb-6 hidden">
      <div class="flex items-center gap-3 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg shadow">
        <i data-lucide="alert-triangle" class="text-yellow-600 w-6 h-6"></i>
        <p id="alertLowStockText" class="text-sm text-yellow-800 font-medium"></p>
        <button id="closeAlert" class="ml-auto text-yellow-600 hover:text-yellow-800">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
    </div>

    <!-- Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5 mb-6">
      <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 md:p-5 rounded-xl shadow">
        <div class="flex justify-between items-center">
          <h3 class="text-sm md:text-base font-medium">Total Barang</h3>
          <i data-lucide="package" class="w-5 h-5 md:w-6 md:h-6 opacity-80"></i>
        </div>
        <p id="totalBarang" class="card-value mt-2">0</p>
        <p class="text-blue-100 text-xs mt-1">Stok minimum: <span id="lowStockCount">0</span> barang</p>
      </div>

      <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4 md:p-5 rounded-xl shadow">
        <div class="flex justify-between items-center">
          <h3 class="text-sm md:text-base font-medium">Penjualan Hari Ini</h3>
          <i data-lucide="shopping-cart" class="w-5 h-5 md:w-6 md:h-6 opacity-80"></i>
        </div>
        <p id="todaySales" class="card-value mt-2">Rp 0</p>
        <p class="text-green-100 text-xs mt-1"><span id="todayTransactions">0</span> transaksi</p>
      </div>

      <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-4 md:p-5 rounded-xl shadow">
        <div class="flex justify-between items-center">
          <h3 class="text-sm md:text-base font-medium">Total Piutang</h3>
          <i data-lucide="file-text" class="w-5 h-5 md:w-6 md:h-6 opacity-80"></i>
        </div>
        <p id="totalPiutang" class="card-value mt-2">Rp 0</p>
        <p class="text-orange-100 text-xs mt-1"><span id="debtorsCount">0</span> pelanggan</p>
      </div>

      <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4 md:p-5 rounded-xl shadow">
        <div class="flex justify-between items-center">
          <h3 class="text-sm md:text-base font-medium">Saldo</h3>
          <i data-lucide="wallet" class="w-5 h-5 md:w-6 md:h-6 opacity-80"></i>
        </div>
        <p id="balance" class="card-value mt-2">Rp 0</p>
        <p class="text-purple-100 text-xs mt-1">Update: <span id="lastUpdate">-</span></p>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
      <div class="bg-white p-5 rounded-xl shadow">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
          <i data-lucide="trending-up" class="w-5 h-5 text-blue-600"></i>
          Penjualan 7 Hari Terakhir
        </h3>
        <div class="chart-container">
          <canvas id="salesChart"></canvas>
        </div>
      </div>

      <div class="bg-white p-5 rounded-xl shadow">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
          <i data-lucide="package" class="w-5 h-5 text-blue-600"></i>
          Stok Barang
        </h3>
        <div class="chart-container">
          <canvas id="stockChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white p-5 rounded-xl shadow">
      <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
        <i data-lucide="history" class="w-5 h-5 text-blue-600"></i>
        Transaksi Terbaru
      </h3>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left border-b text-gray-600 text-sm">
              <th class="pb-3">Waktu</th>
              <th class="pb-3">Pelanggan</th>
              <th class="pb-3 text-right">Total</th>
              <th class="pb-3 text-right">Bayar</th>
              <th class="pb-3 text-right">Status</th>
            </tr>
          </thead>
          <tbody id="recentTransactions">
            <tr>
              <td colspan="5" class="py-6 text-center text-gray-500">
                Memuat data transaksi...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script>
    lucide.createIcons();

    // Format currency function
    function formatCurrency(amount) {
      if (isNaN(amount)) amount = 0;
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount);
    }

    // Format date function - FIXED VERSION
    function formatDate(dateInput) {
      if (!dateInput) return '-';

      try {
        let date;

        // If it's already a Date object
        if (dateInput instanceof Date) {
          date = dateInput;
        }
        // If it's a string
        else if (typeof dateInput === 'string') {
          if (dateInput.includes('T')) {
            date = new Date(dateInput);
          } else if (dateInput.includes(' ')) {
            // Handle format like "2025-09-20 16:52:08"
            const [datePart, timePart] = dateInput.split(' ');
            const [year, month, day] = datePart.split('-');
            const [hour, minute, second] = timePart ? timePart.split(':') : [0, 0, 0];
            date = new Date(year, month - 1, day, hour, minute, second);
          } else {
            // Handle date-only format
            const [year, month, day] = dateInput.split('-');
            date = new Date(year, month - 1, day);
          }
        } else {
          return '-';
        }

        if (isNaN(date.getTime())) return '-';

        const options = {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        };
        return date.toLocaleDateString('id-ID', options);
      } catch (e) {
        console.error('Error formatting date:', dateInput, e);
        return '-';
      }
    }

    // Load data from JSON files - FIXED untuk piutang (hutang.json)
    async function loadData(file) {
      try {
        // Handle khusus untuk file keuangan (menggunakan keuangan.json)
        const actualFile = file === 'keuangan.json' ? 'keungan.json' : file;
        const response = await fetch('data/' + actualFile);

        if (!response.ok) {
          // Return empty array instead of throwing error for missing files
          console.warn(`File ${actualFile} tidak ditemukan atau error`);
          return [];
        }

        const text = await response.text();

        // Handle empty files
        if (!text.trim()) {
          return [];
        }

        return JSON.parse(text);
      } catch (error) {
        console.error("Error loading data from", file + ":", error);
        return [];
      }
    }

    // Calculate saldo from keuangan data and penjualan
    function calculateSaldo(keuanganData, penjualanData) {
      let saldo = 0;

      // Add from keuangan (pemasukan)
      keuanganData.forEach(transaksi => {
        const jumlah = parseFloat(transaksi.jumlah) || 0;
        if (transaksi.jenis === 'pemasukan') {
          saldo += jumlah;
        } else if (transaksi.jenis === 'pengeluaran') {
          saldo -= jumlah;
        }
      });

      // Add from penjualan (hanya yang sudah dibayar, bukan hutang)
      penjualanData.forEach(transaksi => {
        const bayar = parseFloat(transaksi.bayar) || 0;
        saldo += bayar;
      });

      return saldo;
    }

    // Get latest transaction date for saldo update - FIXED VERSION
    function getLatestTransactionDate(keuanganData, penjualanData) {
      const allDates = [];

      // Get dates from keuangan
      keuanganData.forEach(t => {
        if (t.tanggal) {
          try {
            const date = new Date(t.tanggal);
            if (!isNaN(date.getTime())) {
              allDates.push(date);
            }
          } catch (e) {
            console.error('Error parsing keuangan date:', t.tanggal);
          }
        }
      });

      // Get dates from penjualan
      penjualanData.forEach(t => {
        if (t.waktu) {
          try {
            const date = new Date(t.waktu);
            if (!isNaN(date.getTime())) {
              allDates.push(date);
            }
          } catch (e) {
            console.error('Error parsing penjualan date:', t.waktu);
          }
        }
      });

      if (allDates.length === 0) return null;

      // Get the latest date
      const latestDate = new Date(Math.max(...allDates));
      return latestDate;
    }

    // Close alert functionality
    document.getElementById('closeAlert')?.addEventListener('click', function() {
      document.getElementById('alertLowStock').classList.add('hidden');
    });

    async function renderSummary() {
      try {
        console.log('Starting to render summary...');

        // Load data from JSON files - piutang akan di-load dari hutang.json
        const [barang, piutang, penjualan, keuangan] = await Promise.all([
          loadData('barang.json'),
          loadData('hutang.json'),
          loadData('penjualan.json'),
          loadData('keuangan.json')
        ]);

        console.log('Data loaded successfully:', {
          barang: barang.length,
          piutang: piutang.length,
          penjualan: penjualan.length,
          keuangan: keuangan.length
        });

        // Update total barang
        document.getElementById("totalBarang").innerText = barang.length;

        // Update piutang - jumlah semua piutang yang status belum lunas
        const unpaidPiutang = piutang.filter(p => {
          const status = (p.status || '').toLowerCase();
          return status.includes('belum') || status === 'hutang' || (status !== 'lunas' && status !== 'paid');
        });

        const totalPiutang = unpaidPiutang.reduce((total, item) => {
          return total + (parseFloat(item.jumlah) || 0);
        }, 0);

        console.log('Piutang data:', {
          totalPiutang: totalPiutang,
          unpaidCount: unpaidPiutang.length,
          allRecords: piutang
        });

        document.getElementById("totalPiutang").innerText = formatCurrency(totalPiutang);

        // Count debtors (pelanggan dengan piutang belum lunas)
        const debtorsCount = new Set(unpaidPiutang.map(p => p.nama || p.id_pelanggan || 'Unknown')).size;
        document.getElementById("debtorsCount").innerText = debtorsCount;

        // Check for low stock
        const lowStockItems = barang.filter(b => {
          const stok = parseInt(b.stok) || 0;
          const stokMin = parseInt(b.stokMin) || 5;
          return stok <= stokMin;
        });

        document.getElementById("lowStockCount").innerText = lowStockItems.length;

        if (lowStockItems.length > 0) {
          document.getElementById("alertLowStock").classList.remove("hidden");
          document.getElementById("alertLowStockText").innerText =
            `Ada ${lowStockItems.length} barang dengan stok minim!`;
        }

        // Calculate today's sales - FIXED DATE COMPARISON
        const today = new Date();
        const todayFormatted = today.toLocaleDateString('id-ID');

        const todaySales = penjualan
          .filter(sale => {
            try {
              if (!sale.waktu) return false;
              const saleDate = new Date(sale.waktu);
              if (isNaN(saleDate.getTime())) return false;

              const saleDateFormatted = saleDate.toLocaleDateString('id-ID');
              return saleDateFormatted === todayFormatted;
            } catch (e) {
              console.error('Error parsing sale date:', sale.waktu);
              return false;
            }
          })
          .reduce((total, sale) => total + (parseFloat(sale.grandTotal) || 0), 0);

        document.getElementById("todaySales").innerText = formatCurrency(todaySales);

        // Count today's transactions
        const todayTransactions = penjualan.filter(sale => {
          try {
            if (!sale.waktu) return false;
            const saleDate = new Date(sale.waktu);
            if (isNaN(saleDate.getTime())) return false;

            const saleDateFormatted = saleDate.toLocaleDateString('id-ID');
            return saleDateFormatted === todayFormatted;
          } catch (e) {
            return false;
          }
        }).length;

        document.getElementById("todayTransactions").innerText = todayTransactions;

        // Calculate saldo from keuangan data and penjualan
        const saldo = calculateSaldo(keuangan, penjualan);
        document.getElementById("balance").innerText = formatCurrency(saldo);

        // Update last balance update time - FIXED
        const lastUpdate = getLatestTransactionDate(keuangan, penjualan);
        document.getElementById("lastUpdate").innerText = lastUpdate ? formatDate(lastUpdate) : '-';

        // Render recent transactions
        renderRecentTransactions(penjualan);

        // Render charts - FIXED DATA FORMAT
        renderCharts(penjualan, barang);

        console.log('Dashboard rendered successfully');

      } catch (error) {
        console.error("Error rendering summary:", error);
      }
    }

    function renderRecentTransactions(transactions) {
      const container = document.getElementById('recentTransactions');
      if (!container) return;

      // Sort transactions by date (newest first) and take top 5
      const recentTransactions = transactions
        .filter(t => t.waktu) // Only transactions with waktu
        .sort((a, b) => {
          try {
            return new Date(b.waktu) - new Date(a.waktu);
          } catch (e) {
            return 0;
          }
        })
        .slice(0, 5);

      if (recentTransactions.length === 0) {
        container.innerHTML = `
            <tr>
              <td colspan="5" class="py-6 text-center text-gray-500">
                Tidak ada transaksi terbaru
              </td>
            </tr>
          `;
        return;
      }

      container.innerHTML = recentTransactions.map(transaction => {
        const hutang = parseFloat(transaction.hutang) || 0;
        const bayar = parseFloat(transaction.bayar) || 0;
        const grandTotal = parseFloat(transaction.grandTotal) || 0;
        const status = hutang > 0 ? 'Hutang' : 'Lunas';

        return `
            <tr class="border-b hover:bg-gray-50">
              <td class="py-3 text-sm">${formatDate(transaction.waktu)}</td>
              <td class="py-3 text-sm">${transaction.nama_pembeli || 'Pelanggan Umum'}</td>
              <td class="py-3 text-sm text-right font-medium">${formatCurrency(grandTotal)}</td>
              <td class="py-3 text-sm text-right">${formatCurrency(bayar)}</td>
              <td class="py-3 text-sm text-right">
                <span class="px-2 py-1 rounded-full text-xs font-medium 
                  ${status === 'Lunas' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                  ${status}
                </span>
              </td>
            </tr>
          `;
      }).join('');
    }

    function renderCharts(transactions, products) {
      // Sales chart for last 7 days - FIXED DATA COLLECTION
      const last7Days = [...Array(7)].map((_, i) => {
        const d = new Date();
        d.setDate(d.getDate() - i);
        return d;
      }).reverse();

      const salesData = last7Days.map(day => {
        const dayFormatted = day.toLocaleDateString('id-ID');

        return transactions
          .filter(t => {
            try {
              if (!t.waktu) return false;
              const saleDate = new Date(t.waktu);
              if (isNaN(saleDate.getTime())) return false;

              const saleDateFormatted = saleDate.toLocaleDateString('id-ID');
              return saleDateFormatted === dayFormatted;
            } catch (e) {
              return false;
            }
          })
          .reduce((sum, t) => sum + (parseFloat(t.grandTotal) || 0), 0);
      });

      // Format labels for display
      const dayLabels = last7Days.map(day =>
        day.toLocaleDateString('id-ID', {
          day: '2-digit',
          month: 'short'
        })
      );

      console.log('Sales data for chart:', salesData);
      console.log('Day labels:', dayLabels);

      // Create sales chart
      const salesCtx = document.getElementById('salesChart');
      if (salesCtx) {
        try {
          // Destroy previous chart if exists
          if (window.salesChartInstance) {
            window.salesChartInstance.destroy();
          }

          // Only create chart if we have data
          if (salesData.some(value => value > 0)) {
            window.salesChartInstance = new Chart(salesCtx, {
              type: 'line',
              data: {
                labels: dayLabels,
                datasets: [{
                  label: 'Penjualan',
                  data: salesData,
                  borderColor: '#3B82F6',
                  backgroundColor: 'rgba(59, 130, 246, 0.1)',
                  tension: 0.3,
                  fill: true,
                  borderWidth: 2
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: {
                    display: false
                  },
                  tooltip: {
                    callbacks: {
                      label: function(context) {
                        return formatCurrency(context.raw);
                      }
                    }
                  }
                },
                scales: {
                  y: {
                    beginAtZero: true,
                    ticks: {
                      callback: function(value) {
                        if (value >= 1000000) {
                          return 'Rp' + (value / 1000000).toFixed(1) + 'Jt';
                        } else if (value >= 1000) {
                          return 'Rp' + (value / 1000).toFixed(0) + 'Rb';
                        }
                        return 'Rp' + value;
                      },
                      font: {
                        size: 11
                      }
                    }
                  },
                  x: {
                    ticks: {
                      font: {
                        size: 11
                      }
                    }
                  }
                }
              }
            });
          } else {
            // Show message if no sales data
            const ctx = salesCtx.getContext('2d');
            ctx.clearRect(0, 0, salesCtx.width, salesCtx.height);
            ctx.font = '14px Arial';
            ctx.fillStyle = '#999';
            ctx.textAlign = 'center';
            ctx.fillText('Tidak ada data penjualan 7 hari terakhir', salesCtx.width / 2, salesCtx.height / 2);
          }
        } catch (e) {
          console.error('Error creating sales chart:', e);
        }
      }

      // Stock chart (top 10 products by stock)
      const validProducts = products.filter(p => parseInt(p.stok) >= 0);
      const sortedProducts = [...validProducts]
        .sort((a, b) => (parseInt(b.stok) || 0) - (parseInt(a.stok) || 0))
        .slice(0, 10);

      console.log('Stock data for chart:', sortedProducts.map(p => ({
        nama: p.nama,
        stok: p.stok
      })));

      const stockCtx = document.getElementById('stockChart');
      if (stockCtx) {
        try {
          // Destroy previous chart if exists
          if (window.stockChartInstance) {
            window.stockChartInstance.destroy();
          }

          if (sortedProducts.length > 0) {
            window.stockChartInstance = new Chart(stockCtx, {
              type: 'bar',
              data: {
                labels: sortedProducts.map(p => {
                  const nama = p.nama || p.nama_barang || 'Unknown';
                  return nama.substring(0, 12) + (nama.length > 12 ? '...' : '');
                }),
                datasets: [{
                  label: 'Stok',
                  data: sortedProducts.map(p => parseInt(p.stok) || 0),
                  backgroundColor: '#10B981',
                  borderRadius: 4
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: {
                    display: false
                  }
                },
                scales: {
                  y: {
                    beginAtZero: true,
                    ticks: {
                      font: {
                        size: 11
                      }
                    }
                  },
                  x: {
                    ticks: {
                      font: {
                        size: 11
                      }
                    }
                  }
                }
              }
            });
          } else {
            const ctx = stockCtx.getContext('2d');
            ctx.clearRect(0, 0, stockCtx.width, stockCtx.height);
            ctx.font = '14px Arial';
            ctx.fillStyle = '#999';
            ctx.textAlign = 'center';
            ctx.fillText('Tidak ada data stok', stockCtx.width / 2, stockCtx.height / 2);
          }
        } catch (e) {
          console.error('Error creating stock chart:', e);
        }
      }
    }

    // Initialize the dashboard
    document.addEventListener('DOMContentLoaded', function() {
      console.log('Dashboard initializing...');
      renderSummary();
    });
  </script>
</body>

</html>