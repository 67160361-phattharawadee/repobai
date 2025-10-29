<?php
require __DIR__ . '/config_mysqli.php';
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) return [];
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  $res->free();
  return $rows;
}

$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");

$kpiRes = fetch_all($mysqli, "
  SELECT
    (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT SUM(quantity) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpiRes ? $kpiRes[0] : ['sales_30d'=>0, 'qty_30d'=>0, 'buyers_30d'=>0];
function nf($n) { return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard | Retail DW</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  body { background: #fefefe; color: #333; font-family: "Prompt", sans-serif; }
  .navbar { background: linear-gradient(90deg, #60a5fa, #a78bfa); }
  .navbar .navbar-brand { color: #fff !important; }
  .navbar .btn { border-color: #fff; color: #fff; }
  .card { 
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  }
  .card h5 { color: #334155; }
  .kpi { font-size: 1.4rem; font-weight: 700; color: #2563eb; }
  .sub { color: #64748b; font-size: .9rem; }
  .grid { display: grid; gap: 1rem; grid-template-columns: repeat(12, 1fr); }
  .col-12 { grid-column: span 12; }
  .col-6 { grid-column: span 6; }
  .col-4 { grid-column: span 4; }
  .col-8 { grid-column: span 8; }
  @media (max-width: 991px) { .col-6, .col-4, .col-8 { grid-column: span 12; } }
  canvas { max-height: 360px; }
</style>
</head>
<body class="p-3 p-md-4">

<nav class="navbar navbar-dark mb-4">
  <div class="container-fluid">
    <span class="navbar-brand">🌈 Retail Dashboard</span>
    <div>
      <span class="text-light small me-3">Hi, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
      <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">ภาพรวมยอดขาย</h2>
    <span class="sub">ข้อมูลจากฐานข้อมูล MySQL (s67160361)</span>
  </div>

  <!-- KPI -->
  <div class="grid mb-3">
    <div class="card p-3 col-4">
      <h5>ยอดขาย 30 วันล่าสุด</h5>
      <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
    </div>
    <div class="card p-3 col-4">
      <h5>จำนวนสินค้าที่ขาย</h5>
      <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div>
    </div>
    <div class="card p-3 col-4">
      <h5>จำนวนผู้ซื้อทั้งหมด</h5>
      <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="grid">
    <div class="card p-3 col-8"><h5>ยอดขายรายเดือน</h5><canvas id="chartMonthly"></canvas></div>
    <div class="card p-3 col-4"><h5>สัดส่วนยอดขายตามหมวดสินค้า</h5><canvas id="chartCategory"></canvas></div>
    <div class="card p-3 col-6"><h5>Top 10 สินค้าขายดี</h5><canvas id="chartTopProducts"></canvas></div>
    <div class="card p-3 col-6"><h5>ยอดขายตามภูมิภาค</h5><canvas id="chartRegion"></canvas></div>
    <div class="card p-3 col-6"><h5>วิธีการชำระเงิน</h5><canvas id="chartPayment"></canvas></div>
    <div class="card p-3 col-6"><h5>ยอดขายรายชั่วโมง</h5><canvas id="chartHourly"></canvas></div>
    <div class="card p-3 col-12"><h5>ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5><canvas id="chartNewReturning"></canvas></div>
  </div>
</div>

<script>
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;
const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });

// ===== สีชุดสดใส =====
const colors = ['#60a5fa','#f472b6','#facc15','#34d399','#a78bfa','#fb7185','#38bdf8','#fbbf24'];

(() => {
  const {labels, values} = toXY(monthly, 'ym', 'net_sales');
  new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: { labels, datasets: [{ label: 'ยอดขาย (บาท)', data: values, tension: .3, fill: true, borderColor: '#60a5fa', backgroundColor: 'rgba(96,165,250,0.3)' }] },
    options: { plugins: { legend: { labels: { color: '#111' } } } }
  });
})();
(() => {
  const {labels, values} = toXY(category, 'category', 'net_sales');
  new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values, backgroundColor: colors }] },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#111' } } } }
  });
})();
(() => {
  const labels = topProducts.map(o => o.product_name);
  const qty = topProducts.map(o => parseInt(o.qty_sold));
  new Chart(document.getElementById('chartTopProducts'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'จำนวนชิ้น', data: qty, backgroundColor: '#f472b6' }] }
  });
})();
(() => {
  const {labels, values} = toXY(region, 'region', 'net_sales');
  new Chart(document.getElementById('chartRegion'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ยอดขาย (บาท)', data: values, backgroundColor: '#a78bfa' }] }
  });
})();
(() => {
  const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
  new Chart(document.getElementById('chartPayment'), {
    type: 'pie',
    data: { labels, datasets: [{ data: values, backgroundColor: colors.slice(0,4) }] }
  });
})();
(() => {
  const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
  new Chart(document.getElementById('chartHourly'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ยอดขาย (บาท)', data: values, backgroundColor: '#34d399' }] }
  });
})();
(() => {
  const labels = newReturning.map(o => o.date_key);
  const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
  const retC = newReturning.map(o => parseFloat(o.returning_sales));
  new Chart(document.getElementById('chartNewReturning'), {
    type: 'line',
    data: { labels, datasets: [
      { label: 'ลูกค้าใหม่ (บาท)', data: newC, borderColor: '#60a5fa', fill: false },
      { label: 'ลูกค้าเดิม (บาท)', data: retC, borderColor: '#f472b6', fill: false }
    ] }
  });
})();
</script>

</body>
</html>
