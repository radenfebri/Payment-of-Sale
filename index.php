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
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
      <h2 class="text-2xl md:text-3xl font-bold flex items-center gap-2">
        <i data-lucide="bar-chart-3" class="w-6 h-6 md:w-7 md:h-7 text-blue-600"></i>
        Ringkasan
      </h2>

      <!-- Date Filter -->
      <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-2">
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium">Dari:</label>
          <input type="date" id="startDate" class="border rounded-md p-2 text-sm">
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium">Sampai:</label>
          <input type="date" id="endDate" class="border rounded-md p-2 text-sm">
        </div>
        <button id="applyFilter" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
          Terapkan
        </button>
        <button id="resetFilter" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 text-sm">
          Reset
        </button>
      </div>
    </div>

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
          <h3 class="text-sm md:text-base font-medium">Penjualan</h3>
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
        <p class="text-purple-100 text-xs mt-1">Periode: <span id="dateRange">Hari Ini</span></p>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
      <div class="bg-white p-5 rounded-xl shadow">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
          <i data-lucide="trending-up" class="w-5 h-5 text-blue-600"></i>
          <span id="salesChartTitle">Penjualan 7 Hari Terakhir</span>
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

    // Global variables
    let allPenjualanData = [];
    let allKeuanganData = [];
    let allBarangData = [];
    let allPiutangData = [];
    let dateFilter = {
      startDate: null,
      endDate: null
    };

    // Format currency function
    function formatCurrency(amount) {
      if (isNaN(amount)) amount = 0;
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount);
    }

    // Format date function
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

    // Load data from JSON files
    async function loadData(file) {
      try {
        const response = await fetch('data/' + file);

        if (!response.ok) {
          console.warn(`File ${file} tidak ditemukan atau error`);
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

    // Calculate saldo from keuangan data
    function calculateSaldo(keuanganData) {
      let saldo = 0;

      // Add from keuangan
      keuanganData.forEach(transaksi => {
        const jumlah = parseFloat(transaksi.jumlah) || 0;
        if (transaksi.jenis === 'pemasukan') {
          saldo += jumlah;
        } else if (transaksi.jenis === 'pengeluaran') {
          saldo -= jumlah;
        }
      });

      return saldo;
    }

    // Filter data by date range
    function filterDataByDate(data, dateField) {
      if (!dateFilter.startDate && !dateFilter.endDate) return data;

      return data.filter(item => {
        const itemDate = new Date(item[dateField]);
        if (isNaN(itemDate.getTime())) return false;

        if (dateFilter.startDate && itemDate < dateFilter.startDate) return false;
        if (dateFilter.endDate && itemDate > dateFilter.endDate) return false;

        return true;
      });
    }


    // Get date range text for display
    function getDateRangeText() {
      if (!dateFilter.startDate && !dateFilter.endDate) {
        return "Hari Ini";
      }

      const options = {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      };

      if (dateFilter.startDate && dateFilter.endDate) {
        const start = dateFilter.startDate.toLocaleDateString('id-ID', options);
        const end = dateFilter.endDate.toLocaleDateString('id-ID', options);
        return `${start} - ${end}`;
      } else if (dateFilter.startDate) {
        return `Sejak ${dateFilter.startDate.toLocaleDateString('id-ID', options)}`;
      } else {
        return `Sampai ${dateFilter.endDate.toLocaleDateString('id-ID', options)}`;
      }
    }

    // Close alert functionality
    document.getElementById('closeAlert')?.addEventListener('click', function() {
      document.getElementById('alertLowStock').classList.add('hidden');
    });

    // Apply date filter
    document.getElementById('applyFilter')?.addEventListener('click', function() {
      const startDateVal = document.getElementById('startDate').value;
      const endDateVal = document.getElementById('endDate').value;

      dateFilter.startDate = startDateVal ? new Date(startDateVal) : null;
      dateFilter.endDate = endDateVal ? new Date(endDateVal) : null;

      // Adjust end date to include the entire day
      if (dateFilter.endDate) {
        dateFilter.endDate.setHours(23, 59, 59, 999);
      }

      renderFilteredData();
    });

    // Reset date filter
    document.getElementById('resetFilter')?.addEventListener('click', function() {
      document.getElementById('startDate').value = '';
      document.getElementById('endDate').value = '';
      dateFilter.startDate = null;
      dateFilter.endDate = null;
      renderFilteredData();
    });

    // Set default date values (today)
    function setDefaultDates() {
      const today = new Date();
      const todayFormatted = today.toISOString().split('T')[0];
      document.getElementById('startDate').value = todayFormatted;
      document.getElementById('endDate').value = todayFormatted;
    }

    async function loadAllData() {
      try {
        console.log('Loading all data...');

        // Load data from JSON files
        [allBarangData, allPiutangData, allPenjualanData, allKeuanganData] = await Promise.all([
          loadData('barang.json'),
          loadData('hutang.json'),
          loadData('penjualan.json'),
          loadData('keuangan.json')
        ]);

        console.log('All data loaded successfully');
        setDefaultDates();
        renderFilteredData();
      } catch (error) {
        console.error("Error loading all data:", error);
      }
    }

    function renderFilteredData() {
      // Filter data based on date range
      const filteredPenjualan = filterDataByDate(allPenjualanData, 'waktu');
      const filteredKeuangan = filterDataByDate(allKeuanganData, 'tanggal');
      const saldo = calculateSaldo(filteredKeuangan);

      // Update UI with filtered data
      updateSummary(allBarangData, allPiutangData, filteredPenjualan, filteredKeuangan);
      renderRecentTransactions(filteredPenjualan);
      renderCharts(filteredPenjualan, allBarangData);

      // Update date range text
      document.getElementById('dateRange').innerText = getDateRangeText();
    }

    function updateSummary(barang, piutang, penjualan, keuangan) {
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

      // Calculate sales for the filtered period
      const totalSales = penjualan.reduce((total, sale) => total + (parseFloat(sale.grandTotal) || 0), 0);
      document.getElementById("todaySales").innerText = formatCurrency(totalSales);
      document.getElementById("todayTransactions").innerText = penjualan.length;

      // Calculate saldo from keuangan data
      const saldo = calculateSaldo(keuangan);
      document.getElementById("balance").innerText = formatCurrency(saldo);
    }

    function renderRecentTransactions(transactions) {
      const container = document.getElementById('recentTransactions');
      if (!container) return;

      // Sort transactions by date (newest first) and take top 5
      const recentTransactions = transactions
        .filter(t => t.waktu)
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
                Tidak ada transaksi
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
      // Determine date range for sales chart
      let dateRange = [];
      let chartTitle = "Penjualan";

      if (dateFilter.startDate && dateFilter.endDate) {
        // Custom date range
        const start = new Date(dateFilter.startDate);
        const end = new Date(dateFilter.endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays <= 31) {
          // Show daily data for up to 31 days
          for (let i = 0; i <= diffDays; i++) {
            const date = new Date(start);
            date.setDate(start.getDate() + i);
            dateRange.push(new Date(date));
          }
          chartTitle = `Penjualan ${getDateRangeText()}`;
        } else {
          // Show monthly data for longer ranges
          const startMonth = start.getMonth();
          const startYear = start.getFullYear();
          const endMonth = end.getMonth();
          const endYear = end.getFullYear();

          let currentYear = startYear;
          let currentMonth = startMonth;

          while (currentYear < endYear || (currentYear === endYear && currentMonth <= endMonth)) {
            dateRange.push(new Date(currentYear, currentMonth, 1));
            currentMonth++;
            if (currentMonth > 11) {
              currentMonth = 0;
              currentYear++;
            }
          }
          chartTitle = `Penjualan Bulanan ${getDateRangeText()}`;
        }
      } else {
        // Default: last 7 days
        dateRange = [...Array(7)].map((_, i) => {
          const d = new Date();
          d.setDate(d.getDate() - i);
          return d;
        }).reverse();
        chartTitle = "Penjualan 7 Hari Terakhir";
      }

      // Update chart title
      document.getElementById("salesChartTitle").innerText = chartTitle;

      // Prepare sales data
      const salesData = dateRange.map(date => {
        if (dateRange.length > 31) {
          // Monthly aggregation
          const month = date.getMonth();
          const year = date.getFullYear();

          return transactions
            .filter(t => {
              try {
                if (!t.waktu) return false;
                const saleDate = new Date(t.waktu);
                return saleDate.getMonth() === month && saleDate.getFullYear() === year;
              } catch (e) {
                return false;
              }
            })
            .reduce((sum, t) => sum + (parseFloat(t.grandTotal) || 0), 0);
        } else {
          // Daily aggregation
          const dateFormatted = date.toLocaleDateString('id-ID');

          return transactions
            .filter(t => {
              try {
                if (!t.waktu) return false;
                const saleDate = new Date(t.waktu);
                const saleDateFormatted = saleDate.toLocaleDateString('id-ID');
                return saleDateFormatted === dateFormatted;
              } catch (e) {
                return false;
              }
            })
            .reduce((sum, t) => sum + (parseFloat(t.grandTotal) || 0), 0);
        }
      });

      // Format labels for display
      const labels = dateRange.map(date => {
        if (dateRange.length > 31) {
          return date.toLocaleDateString('id-ID', {
            month: 'short',
            year: 'numeric'
          });
        } else {
          return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short'
          });
        }
      });

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
              type: dateRange.length > 31 ? 'bar' : 'line',
              data: {
                labels: labels,
                datasets: [{
                  label: 'Penjualan',
                  data: salesData,
                  borderColor: '#3B82F6',
                  backgroundColor: dateRange.length > 31 ? '#3B82F6' : 'rgba(59, 130, 246, 0.1)',
                  tension: 0.3,
                  fill: dateRange.length > 31 ? true : true,
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
            ctx.fillText('Tidak ada data penjualan', salesCtx.width / 2, salesCtx.height / 2);
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
      loadAllData();
    });
  </script>
</body>

</html>