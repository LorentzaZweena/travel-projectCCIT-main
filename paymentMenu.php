<?php
session_start();
require_once 'koneksi.php';

$user_id = $_SESSION['user_id'] ?? 1;
$query_user = "SELECT * FROM users WHERE user_id = $user_id LIMIT 1";
$res_user = $conn->query($query_user);
$user = $res_user ? $res_user->fetch_assoc() : ['name' => 'Budi Santoso', 'email' => 'budi@example.com'];

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$query_total = "SELECT COUNT(*) AS total FROM payments p JOIN transactions t ON p.transaction_id = t.transaction_id JOIN bookings b ON t.booking_id = b.booking_id WHERE b.user_id = $user_id";
$res_total = $conn->query($query_total);
$total_rows = $res_total ? (int)$res_total->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

$query_payments = "SELECT p.payment_id, p.payment_type, p.payment_date, p.status AS payment_status,
                          t.transaction_id, t.total_amount,
                          b.booking_id, b.trip_type,
                          pl.name AS place_name, pl.location
                   FROM payments p
                   JOIN transactions t ON p.transaction_id = t.transaction_id
                   JOIN bookings b ON t.booking_id = b.booking_id
                   LEFT JOIN tripdetailspergi tpergi ON b.booking_id = tpergi.booking_id
                   LEFT JOIN places pl ON tpergi.destination_id = pl.place_id
                   WHERE b.user_id = $user_id
                   ORDER BY p.payment_id DESC
                   LIMIT $limit OFFSET $offset";
$res_payments = $conn->query($query_payments);

$query_stats = "SELECT 
                    SUM(CASE WHEN p.status = 'Success' THEN CAST(t.total_amount AS UNSIGNED) ELSE 0 END) AS total_spent,
                    COUNT(CASE WHEN p.status = 'Success' THEN 1 END) AS total_success,
                    COUNT(CASE WHEN p.status = 'Pending' THEN 1 END) AS total_pending
                FROM payments p
                JOIN transactions t ON p.transaction_id = t.transaction_id
                JOIN bookings b ON t.booking_id = b.booking_id
                WHERE b.user_id = $user_id";
$res_stats = $conn->query($query_stats);
$stats = $res_stats ? $res_stats->fetch_assoc() : [];

$total_spent = (float)($stats['total_spent'] ?? 0);
$total_success = (int)($stats['total_success'] ?? 0);
$total_pending = (int)($stats['total_pending'] ?? 0);

$query_card = "SELECT c.* FROM cards c 
               JOIN payments pm ON c.payment_id = pm.payment_id
               JOIN transactions t ON pm.transaction_id = t.transaction_id
               JOIN bookings b ON t.booking_id = b.booking_id
               WHERE b.user_id = $user_id 
               ORDER BY c.card_id ASC LIMIT 1";
$res_card = $conn->query($query_card);
$user_card = $res_card ? $res_card->fetch_assoc() : null;

$raw_card_number = $user_card['card_number'] ?? '4532750012340001';
$formatted_card_number = implode(' ', str_split($raw_card_number, 4));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Travel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index2-style.css">
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar-wrapper">
        <div class="brand-logo mb-0 mb-lg-4">
            <div class="brand-icon-box"><i class="bi bi-compass-fill"></i></div>
            <span class="fw-bold">Travel</span>
        </div>

        <nav class="nav-menu">
            <a href="index2.php" class="nav-link-custom">
                <i class="bi bi-grid-fill"></i> <span>Dashboard</span>
            </a>
            <a href="paymentMenu.php" class="nav-link-custom active">
                <i class="bi bi-credit-card-fill"></i> <span>Payment</span>
            </a>
            <a href="bookingMenu.php" class="nav-link-custom">
                <i class="bi bi-journal-bookmark-fill"></i> <span>Booking</span>
            </a>
            <a href="reviewsMenu.php" class="nav-link-custom">
                <i class="bi bi-star-fill"></i> <span>Reviews</span>
            </a>
        </nav>

        <a href="logout.php" class="btn-logout mt-0 mt-lg-auto">
            <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-wrapper">
        <header class="top-header">
            <div>
                <h1 class="welcome-title">Payment history</h1>
                <p class="welcome-sub mb-0">Kelola dan pantau seluruh transaksi pembayaran wisata Anda</p>
            </div>
        </header>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-4">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Total pengeluaran</div>
                    <div class="fw-bold fs-5 text-primary">Rp <?= number_format($total_spent, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Transaksi berhasil</div>
                    <div class="fw-bold fs-5 text-success"><?= $total_success; ?> Transaksi</div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Pending</div>
                    <div class="fw-bold fs-5 text-warning"><?= $total_pending; ?> Transaksi</div>
                </div>
            </div>
        </div>

        <section>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Daftar Transaksi Saya</h6>
                <span class="badge bg-primary-subtle text-primary fw-semibold"><?= $total_rows; ?> Total Data</span>
            </div>

            <div class="table-responsive rounded-4 shadow-sm bg-white border">
                <table class="table table-hover table-custom align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID Pay</th>
                            <th>Destinasi</th>
                            <th>Metode Bayar</th>
                            <th>Tanggal Pembayaran</th>
                            <th>Total Tagihan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_payments && $res_payments->num_rows > 0): ?>
                            <?php while ($pay = $res_payments->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-secondary">#PAY-<?= str_pad($pay['payment_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td class="fw-semibold text-dark">
                                        <div class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars($pay['place_name'] ?? 'Wisata Indonesia'); ?></div>
                                        <small class="text-muted fw-normal"><?= htmlspecialchars($pay['location'] ?? 'Indonesia'); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($pay['payment_type'] === 'Card'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                <i class="bi bi-credit-card-2-front me-1"></i> Card
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                <i class="bi bi-cash-stack me-1"></i> Cash
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">
                                        <?= date('d M Y, H:i', strtotime($pay['payment_date'] ?? 'now')); ?>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        Rp <?= number_format((float)($pay['total_amount'] ?? 0), 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $pay['payment_status'];
                                        $badge_class = 'bg-warning text-dark';
                                        if ($status === 'Success') $badge_class = 'bg-success text-white';
                                        if ($status === 'Failed') $badge_class = 'bg-danger text-white';
                                        ?>
                                        <span class="badge rounded-pill <?= $badge_class; ?>"><?= htmlspecialchars($status); ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat pembayaran untuk akun Anda.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div class="small text-muted">
                        Menampilkan <?= $offset + 1; ?>–<?= min($offset + $limit, $total_rows); ?> dari <?= $total_rows; ?> data
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?= $page - 1; ?>"><i class="bi bi-chevron-left"></i></a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?= $page + 1; ?>"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <aside class="right-panel">
        <div class="d-flex align-items-center gap-3 mb-4">
            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80" class="rounded-circle" width="44" height="44" style="object-fit:cover;" alt="Profile">
            <div>
                <div class="fw-bold text-dark small"><?= htmlspecialchars($user['name']); ?></div>
                <div class="text-muted extra-small" style="font-size:0.72rem;">Indonesia</div>
            </div>
        </div>

        <h6 class="fw-bold mb-3 small">Transportation</h6>
        <div class="transport-grid">
            <div class="transport-box" title="Pesawat">
                <i class="bi bi-airplane-fill"></i>
                <span>Flight</span>
            </div>
            <div class="transport-box" title="Kereta Api">
                <i class="bi bi-train-front-fill"></i>
                <span>Train</span>
            </div>
            <div class="transport-box" title="Kapal Laut">
                <i class="bi bi-tsunami"></i>
                <span>Ship</span>
            </div>
            <div class="transport-box" title="Bus">
                <i class="bi bi-bus-front-fill"></i>
                <span>Bus</span>
            </div>
            <div class="transport-box" title="Bayi / Infant">
                <i class="bi bi-person-arms-up"></i>
                <span>Infant</span>
            </div>
            <div class="transport-box" title="Mobil">
                <i class="bi bi-car-front-fill"></i>
                <span>Taxi</span>
            </div>
        </div>

        <h6 class="fw-bold mb-3 small">My Card</h6>
        <div class="credit-card-widget">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <i class="bi bi-sim fs-4"></i>
                <span class="fw-bold extra-small"><?= htmlspecialchars($user_card['card_type'] ?? 'MasterCard'); ?></span>
            </div>
            
            <div class="card-number-text">
                <?= htmlspecialchars($formatted_card_number); ?>
            </div>

            <div class="d-flex justify-content-between align-items-end">
                <div>
                    <div class="extra-small opacity-75" style="font-size:0.6rem;">CARD HOLDER</div>
                    <div class="fw-semibold extra-small text-truncate" style="max-width: 110px; font-size:0.72rem;"><?= htmlspecialchars($user_card['card_holder_name'] ?? $user['name']); ?></div>
                </div>
                <div>
                    <div class="extra-small opacity-75" style="font-size:0.6rem;">EXPIRES</div>
                    <div class="fw-semibold extra-small" style="font-size:0.72rem;"><?= htmlspecialchars($user_card['expired_date'] ?? '12/29'); ?></div>
                </div>
            </div>
        </div>
    </aside>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>