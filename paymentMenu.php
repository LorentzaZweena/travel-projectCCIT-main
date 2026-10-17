<?php
session_start();
require_once 'koneksi.php';

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)($angka ?? 0), 0, ',', '.');
}

function tanggal($date) {
    if (!$date) return '-';
    return date('d M Y, H:i', strtotime($date));
}

function paymentDisplayStatus($paymentStatus, $transactionStatus) {
    $p = strtolower(trim((string)$paymentStatus));
    $t = strtolower(trim((string)$transactionStatus));

    if (
        in_array($t, ["paid", "success", "completed"], true) ||
        in_array($p, ["success", "paid", "completed"], true)
    ) {
        return "Success";
    }

    if (
        in_array($t, ["cancelled", "canceled"], true) ||
        in_array($p, ["cancelled", "canceled"], true)
    ) {
        return "Cancelled";
    }

    if ($p === "failed" || $t === "failed") {
        return "Failed";
    }

    return "Pending";
}

$user_id = (int)($_SESSION['user_id'] ?? 1);

$query_user = "SELECT * FROM users WHERE user_id = $user_id LIMIT 1";
$res_user = $conn->query($query_user);
$user = $res_user ? $res_user->fetch_assoc() : ['name' => 'Budi Santoso', 'email' => 'budi@example.com'];

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

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) AS total 
              FROM payments p 
              JOIN transactions t ON p.transaction_id = t.transaction_id 
              JOIN bookings b ON t.booking_id = b.booking_id 
              WHERE b.user_id = ?";
$stmt_count = $conn->prepare($count_sql);
$stmt_count->bind_param("i", $user_id);
$stmt_count->execute();
$count_res = $stmt_count->get_result();
$total_rows = $count_res ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

$sql = "
    SELECT
        p.payment_id,
        p.transaction_id,
        p.payment_type,
        p.payment_date,
        p.status AS payment_status,
        t.booking_id,
        t.total_amount,
        t.payment_status AS transaction_status
    FROM payments p
    JOIN transactions t ON p.transaction_id = t.transaction_id
    JOIN bookings b ON t.booking_id = b.booking_id
    WHERE b.user_id = ?
    ORDER BY p.payment_id DESC
    LIMIT ? OFFSET ?
";

$stmt_data = $conn->prepare($sql);
$stmt_data->bind_param("iii", $user_id, $limit, $offset);
$stmt_data->execute();
$result = $stmt_data->get_result();

if (!$result) {
    die("Unable to load payments. Please check your database columns.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Travel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index2-style.css">

    <style>
        .eyebrow {
            color: #1685f8;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .payment-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(30,65,100,.04);
        }

        .count {
            background: #edf5ff;
            color: #1685f8;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-success { color: #119c70; background: #e5f8ef; }
        .status-pending { color: #b77912; background: #fff4d8; }
        .status-failed { color: #c62828; background: #ffebeb; }
        .status-cancelled { color: #c62828; background: #ffebeb; }

        .btn-edit {
            background: #eaf3ff;
            color: #1685f8;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
        }
        .btn-edit:hover { background: #d0e4ff; color: #1066c6; }

        .btn-cancel {
            background: #fff0f0;
            color: #d14343;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
        }
        .btn-cancel:hover { background: #ffdada; color: #a82e2e; }

        .btn-disabled {
            background: #f0f2f5;
            color: #8994a3;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-block;
        }
    </style>
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
            <a href="index.php" class="nav-link-custom">
                <i class="bi bi-arrow-left"></i> <span>Back to home</span>  
            </a>
        </nav>

        <a href="logout.php" class="btn-logout mt-0 mt-lg-auto">
            <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-wrapper">
        <header class="top-header mb-4">
            <div>
                <div class="eyebrow mb-1">YOUR ACCOUNT</div>
                <h1 class="welcome-title fs-2 fw-bold mb-1">Payment History</h1>
                <p class="welcome-sub text-muted mb-0">View and manage your travel booking payments.</p>
            </div>
        </header>

        <div class="payment-card border mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 class="fs-5 fw-bold mb-0">Payment Transactions</h2>
                <div class="count">
                    <?= $total_rows ?> Payments
                </div>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="min-width: 850px;">
                        <thead class="table-light">
                            <tr>
                                <th>Payment ID</th>
                                <th>Booking</th>
                                <th>Transaction</th>
                                <th>Payment Method</th>
                                <th>Total Price</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $paymentStatus = strtolower(trim((string)($row["payment_status"] ?? "")));
                                $transactionStatus = strtolower(trim((string)($row["transaction_status"] ?? "")));

                                $status = paymentDisplayStatus($paymentStatus, $transactionStatus);

                                $canManage = in_array($paymentStatus, ["pending", "failed"], true) 
                                             && $transactionStatus === "pending";

                                $statusClass = strtolower($status);
                                ?>

                                <tr>
                                    <td><strong>#<?= h($row["payment_id"]) ?></strong></td>
                                    <td>#<?= h($row["booking_id"] ?? "-") ?></td>
                                    <td>#<?= h($row["transaction_id"]) ?></td>
                                    <td><?= h($row["payment_type"]) ?></td>
                                    <td><strong><?= rupiah($row["total_amount"] ?? 0) ?></strong></td>
                                    <td>
                                        <span class="status status-<?= h($statusClass) ?>">
                                            <?= h($status) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?= !empty($row["payment_date"]) ? h(tanggal($row["payment_date"])) : "-" ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <?php if ($canManage): ?>
                                                <a class="btn-edit" href="edit_payment.php?payment_id=<?= (int)$row["payment_id"] ?>">Edit</a>
                                                <a class="btn-cancel" href="cancel_payment.php?payment_id=<?= (int)$row["payment_id"] ?>" onclick="return confirm('Are you sure you want to cancel this payment?');">Cancel</a>
                                            <?php elseif ($status === "Success"): ?>
                                                <span class="btn-disabled">Completed</span>
                                            <?php else: ?>
                                                <span class="btn-disabled">Unavailable</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
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

            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <h3 class="fs-5 fw-bold mb-2">No Payments Yet</h3>
                    <p class="mb-0">Your payment transactions will appear here after checkout.</p>
                </div>
            <?php endif; ?>
        </div>

        <a class="text-decoration-none fw-bold text-primary" href="booking.php">
            ← Back to Booking
        </a>
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