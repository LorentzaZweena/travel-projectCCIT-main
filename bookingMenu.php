<?php
session_start();
require_once 'koneksi.php';

function rupiah($angka) {
    return 'Rp ' . number_format((float)($angka ?? 0), 0, ',', '.');
}

function tanggal($date) {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function waktu($datetime) {
    if (!$datetime) return '-';
    return date('H:i', strtotime($datetime));
}

$user_id = (int)($_SESSION['user_id'] ?? 1);

$query_user = "SELECT * FROM users WHERE user_id = $user_id LIMIT 1";
$res_user = $conn->query($query_user);
$user = $res_user ? $res_user->fetch_assoc() : ['name' => 'Budi Santoso', 'email' => 'budi@example.com'];

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$query_total = "SELECT COUNT(*) AS total FROM bookings WHERE user_id = $user_id";
$res_total = $conn->query($query_total);
$total_rows = $res_total ? (int)$res_total->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

$query_bookings = "
    SELECT 
        b.booking_id, b.booking_date, b.trip_type,
        b.transport_type,
        b.travel_class,
        b.status AS booking_status,
        u.name AS booker_name, u.email AS booker_email, u.phone AS booker_phone, u.address AS booker_address,
        tp.trippergi_id, tp.travel_date AS pergi_travel_date, tp.departure_date AS pergi_dep_date,
        p_orig.name AS pergi_origin_name, p_orig.location AS pergi_origin_loc,
        p_dest.place_id AS dest_place_id, p_dest.name AS pergi_dest_name, p_dest.location AS pergi_dest_loc, p_dest.category AS dest_category, p_dest.description AS dest_desc, p_dest.price AS dest_price,
        tpu.trippulang_id, tpu.travel_date AS pulang_travel_date, tpu.departure_date AS pulang_dep_date, pu_orig.name AS pulang_origin_name, pu_dest.name AS pulang_dest_name,
        t.transaction_id, t.total_amount, t.payment_status,
        pm.payment_id, pm.payment_type, pm.payment_date, pm.status AS pay_status,
        c.card_type, c.card_number, c.card_holder_name, c.expired_date, cs.amount_received
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    LEFT JOIN tripdetailspergi tp ON b.booking_id = tp.booking_id
    LEFT JOIN places p_orig ON tp.origin_id = p_orig.place_id
    LEFT JOIN places p_dest ON tp.destination_id = p_dest.place_id
    LEFT JOIN tripdetailspulang tpu ON b.booking_id = tpu.booking_id
    LEFT JOIN places pu_orig ON tpu.origin_id = pu_orig.place_id
    LEFT JOIN places pu_dest ON tpu.destination_id = pu_dest.place_id
    LEFT JOIN transactions t ON b.booking_id = t.booking_id
    LEFT JOIN payments pm ON t.transaction_id = pm.transaction_id
    LEFT JOIN cards c ON pm.payment_id = c.payment_id
    LEFT JOIN cashes cs ON pm.payment_id = cs.payment_id
    WHERE b.user_id = $user_id
    ORDER BY b.booking_id DESC
    LIMIT $limit OFFSET $offset
";
$res_bookings = $conn->query($query_bookings);

$query_stats = "SELECT 
                    COUNT(*) AS total_booking,
                    COUNT(CASE WHEN status = 'Confirmed' THEN 1 END) AS total_confirmed,
                    COUNT(CASE WHEN status = 'Completed' THEN 1 END) AS total_completed,
                    COUNT(CASE WHEN status = 'Pending' THEN 1 END) AS total_pending
                FROM bookings WHERE user_id = $user_id";
$res_stats = $conn->query($query_stats);
$stats = $res_stats ? $res_stats->fetch_assoc() : [];

$query_card = "SELECT c.* FROM cards c JOIN payments pm ON c.payment_id = pm.payment_id JOIN transactions t ON pm.transaction_id = t.transaction_id JOIN bookings b ON t.booking_id = b.booking_id WHERE b.user_id = $user_id ORDER BY c.card_id ASC LIMIT 1";
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
    <title>Booking - Travel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index2-style.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        .ticket-modal-body {
            background: #f4f7fb;
            color: #17243a;
            padding: 20px;
        }

        .modal-stepper {
            max-width: 600px;
            margin: 10px auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .modal-step {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #20a978;
            font-size: 12px;
            font-weight: bold;
        }

        .modal-circle {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #20a978;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .modal-line {
            width: 45px;
            height: 2px;
            background: #20a978;
        }

        .modal-container-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            align-items: start;
        }

        .modal-card, .modal-summary {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(30, 60, 90, .05);
        }

        /* Success & Pending Banners */
        .modal-success {
            background: #e8f8f0;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .modal-success-icon {
            width: 36px;
            height: 36px;
            background: #20a978;
            border-radius: 50%;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .modal-success h2 { font-size: 15px; margin: 0; }
        .modal-success p { font-size: 11px; color: #65758b; margin: 2px 0 0; }

        .modal-pending {
            background: #fff6df;
            border: 1px solid #f4d58a;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .modal-pending-icon {
            width: 36px;
            height: 36px;
            background: #f59e0b;
            border-radius: 50%;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .modal-pending h2 { font-size: 15px; color: #9a6500; margin: 0; }
        .modal-pending p { font-size: 11px; color: #8a6a2a; margin: 2px 0 0; }

        .modal-label {
            color: #1688f7;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1.5px;
            margin-bottom: 5px;
        }

        .modal-details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e7ed;
        }

        .modal-detail span { display: block; color: #8994a7; font-size: 10px; margin-bottom: 4px; }
        .modal-detail strong { font-size: 12px; }
        .modal-paid { color: #20a978; }
        .modal-pending-status { color: #f59e0b; font-weight: 700; }

        /* Transaction Table */
        .modal-transaction {
            margin-top: 20px;
            border: 1px solid #dce5ef;
            border-radius: 12px;
            overflow: hidden;
        }

        .modal-transaction-title {
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-transaction-title h2 { font-size: 15px; margin: 0; }

        .modal-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
        }

        .modal-badge-paid { background: #e5f8ef; color: #20a978; }
        .modal-badge-pending { background: #fff1cf; color: #d88900; }

        .modal-table {
            display: grid;
            grid-template-columns: 1.1fr 1.4fr .6fr 1fr .4fr 1fr;
            padding: 10px 12px;
            font-size: 11px;
            gap: 6px;
        }

        .modal-table.header { background: #f5f8fc; color: #738096; font-weight: bold; }
        .modal-table.total { display: flex; justify-content: flex-end; gap: 20px; border-top: 1px solid #e2e7ed; padding: 12px; }

        /* Travel Ticket */
        .modal-ticket {
            margin-top: 20px;
            background: #f4f9ff;
            border: 1px solid #d8e9fa;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .modal-ticket h2 { margin-bottom: 12px; font-size: 16px; }
        .modal-ticket-info { display: flex; gap: 20px; flex-wrap: wrap; }
        .modal-ticket-info span { display: block; color: #8290a3; font-size: 9px; margin-bottom: 3px; }
        .modal-ticket-info strong { font-size: 11px; }

       
.modal-qr-wrapper {
    width: 110px;
    height: 110px;
    background: white;
    border: 1px solid #d6deea;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    border-radius: 8px;
    flex-shrink: 0;
}


        .modal-download {
            width: 100%;
            margin-top: 15px;
            height: 42px;
            border: none;
            border-radius: 8px;
            background: #1688f7;
            color: white;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
        }

        .modal-download:hover { background: #0876df; }

        /* Next Steps & Pending */
        .modal-next-steps {
            margin-top: 15px;
            background: #fff6df;
            border-radius: 10px;
            padding: 12px 15px;
        }

        .modal-next-steps-title { font-weight: bold; color: #9a6500; margin-bottom: 6px; font-size: 12px; }
        .modal-next-steps ol { padding-left: 18px; color: #7f682f; font-size: 11px; margin: 0; line-height: 1.6; }

        .modal-ticket-pending {
            margin-top: 15px;
            background: #f3f6fa;
            border-radius: 10px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
            color: #718096;
        }

        /* Summary */
        .modal-summary h2 { font-size: 16px; margin-bottom: 15px; }
        .modal-route {
            border: 1px solid #dce5ef;
            padding: 12px;
            border-radius: 10px;
            display: grid;
            grid-template-columns: 1fr 20px 1fr;
            text-align: center;
        }

        .modal-route small { color: #8994a7; font-size: 8px; }
        .modal-route strong { display: block; font-size: 10px; margin-top: 3px; }

        .modal-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 11px;
        }

        .modal-summary-row span { color: #8490a3; }

        .modal-total-price {
            background: #eef9f4;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-total-price strong { color: #20a978; font-size: 16px; }

        @media (max-width: 900px) {
            .modal-container-grid { grid-template-columns: 1fr; }
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
            <a href="paymentMenu.php" class="nav-link-custom">
                <i class="bi bi-credit-card-fill"></i> <span>Payment</span>
            </a>
            <a href="bookingMenu.php" class="nav-link-custom active">
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
        <header class="top-header">
            <div>
                <h1 class="welcome-title">My Bookings</h1>
                <p class="welcome-sub mb-0">Daftar pemesanan tiket perjalanan dan tiket wisata Anda</p>
            </div>
        </header>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Total booking</div>
                    <div class="fw-bold fs-5 text-primary"><?= $stats['total_booking'] ?? 0; ?> Data</div>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Confirmed</div>
                    <div class="fw-bold fs-5 text-success"><?= $stats['total_confirmed'] ?? 0; ?> Booking</div>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Completed</div>
                    <div class="fw-bold fs-5 text-info"><?= $stats['total_completed'] ?? 0; ?> Booking</div>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Pending</div>
                    <div class="fw-bold fs-5 text-warning"><?= $stats['total_pending'] ?? 0; ?> Booking</div>
                </div>
            </div>
        </div>

        <section>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Daftar Tiket & Perjalanan Saya</h6>
                <span class="badge bg-primary-subtle text-primary fw-semibold"><?= $total_rows; ?> Total Booking</span>
            </div>

            <div class="table-responsive rounded-4 shadow-sm bg-white border">
                <table class="table table-hover table-custom align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID Book</th>
                            <th>Rute / Destinasi</th>
                            <th>Tipe Trip</th>
                            <th>Tgl Perjalanan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_bookings && $res_bookings->num_rows > 0): ?>
                            <?php while ($b = $res_bookings->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-secondary">#BK-<?= str_pad($b['booking_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td class="fw-semibold text-dark">
                                        <div class="text-truncate" style="max-width: 200px;"><?= htmlspecialchars($b['pergi_origin_name'] ?? 'Origin'); ?> &rarr; <?= htmlspecialchars($b['pergi_dest_name'] ?? 'Destination'); ?></div>
                                        <small class="text-muted fw-normal"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($b['pergi_dest_loc'] ?? 'Indonesia'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <?= htmlspecialchars($b['trip_type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?= htmlspecialchars($b['pergi_travel_date'] ?? $b['booking_date']); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $b['booking_status'];
                                        $badge_class = 'bg-warning text-dark';
                                        if ($status === 'Completed' || $status === 'Confirmed') $badge_class = 'bg-success text-white';
                                        if ($status === 'Cancelled') $badge_class = 'bg-danger text-white';
                                        ?>
                                        <span class="badge rounded-pill <?= $badge_class; ?>"><?= htmlspecialchars($status); ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#ticketModal<?= $b['booking_id']; ?>">
                                            <i class="bi bi-ticket-perforated me-1"></i> View Ticket
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat booking untuk akun Anda.</td>
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
            <div class="transport-box" title="Pesawat"><i class="bi bi-airplane-fill"></i><span>Flight</span></div>
            <div class="transport-box" title="Kereta Api"><i class="bi bi-train-front-fill"></i><span>Train</span></div>
            <div class="transport-box" title="Bus"><i class="bi bi-bus-front-fill"></i><span>Bus</span></div>
        </div>

        <h6 class="fw-bold mb-3 small">My Card</h6>
        <div class="credit-card-widget">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <i class="bi bi-sim fs-4"></i>
                <span class="fw-bold extra-small"><?= htmlspecialchars($user_card['card_type'] ?? 'MasterCard'); ?></span>
            </div>
            
            <div class="card-number-text"><?= htmlspecialchars($formatted_card_number); ?></div>

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

<?php 
if ($res_bookings && $res_bookings->num_rows > 0):
    $res_bookings->data_seek(0);
    while ($b = $res_bookings->fetch_assoc()):
        $bk_id = $b['booking_id'];
        $trippergi_id = $b['trippergi_id'] ?? 0;
        $trippulang_id = $b['trippulang_id'] ?? 0;
        $trans_id = (int)($b['transaction_id'] ?? 0);

// Transportasi sesuai pilihan saat booking
$transportType = $b['transport_type'] ?? 'Bus';

$transportConfig = [
    'Bus' => [
        'icon' => 'bi-bus-front-fill',
        'title' => 'BUS EXPRESS TICKET',
        'operator' => 'PO Travel Express',
        'download' => 'Download Bus Ticket'
    ],
    'Kereta Api' => [
        'icon' => 'bi-train-front-fill',
        'title' => 'TRAIN TICKET',
        'operator' => 'Railway Service',
        'download' => 'Download Train Ticket'
    ],
    'Pesawat' => [
        'icon' => 'bi-airplane-fill',
        'title' => 'FLIGHT TICKET',
        'operator' => 'Airline Service',
        'download' => 'Download Flight Ticket'
    ]
];

$transport = $transportConfig[$transportType]
    ?? $transportConfig['Bus'];

$trippergi_id = $b['trippergi_id'] ?? 0;
$trippulang_id = $b['trippulang_id'] ?? 0;
$trans_id = (int)($b['transaction_id'] ?? 0);

        $q_pass_pergi = "
            SELECT pg.passenger_id, pg.name, pg.category, pg.ID, pg.birth_date, pp.seat_number
            FROM passengerpergi pp
            JOIN passengers pg ON pp.passenger_id = pg.passenger_id
            WHERE pp.trippergi_id = $trippergi_id
            LIMIT 1
        ";
        $res_pass_pergi = $conn->query($q_pass_pergi);
        $passenger = $res_pass_pergi ? $res_pass_pergi->fetch_assoc() : null;

        $payment_status = $b['payment_status'] ?? 'Pending';
        $is_paid = in_array(strtolower(trim($payment_status)), ['paid', 'success', 'completed'], true);

        $payment_type = $b['payment_type'] ?? '';
        $display_payment_type = ($payment_type === 'Card') 
            ? 'Credit / Debit Card' 
            : (($payment_type === 'Cash') ? 'Cash' : 'Unknown');

        $total_amount = (float)($b['total_amount'] ?? $b['dest_price'] ?? 0);

        $qr_data = [
            "type" => "TRAVEL TICKET",
            "booking_id" => $bk_id,
            "transaction_id" => "TRX-" . str_pad($trans_id, 5, "0", STR_PAD_LEFT),
            "passenger" => $passenger['name'] ?? "-",
            "route" => ($b['pergi_origin_name'] ?? "-") . " -> " . ($b['pergi_dest_name'] ?? "-"),
            "departure" => tanggal($b['pergi_travel_date'] ?? null),
            "time" => waktu($b['pergi_dep_date'] ?? null),
            "seat" => $passenger['seat_number'] ?? "-",
            "status" => $payment_status
        ];
?>

<div class="modal fade" id="ticketModal<?= $bk_id; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            
            <div class="modal-header border-0 bg-white pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body ticket-modal-body">
                <div class="modal-stepper">
                    <div class="modal-step">
                        <div class="modal-circle">✓</div> Passenger
                    </div>
                    <div class="modal-line"></div>
                    <div class="modal-step">
                        <div class="modal-circle">✓</div> Payment
                    </div>
                    <div class="modal-line"></div>
                    <div class="modal-step">
                        <div class="modal-circle">✓</div> Confirmation
                    </div>
                </div>

                <div class="modal-container-grid">
                    <main class="modal-card">
                        <?php if ($is_paid): ?>
                            <div class="modal-success">
                                <div class="modal-success-icon">✓</div>
                                <div>
                                    <h2>Your booking is confirmed!</h2>
                                    <p>Your payment has been successfully processed.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="modal-pending">
                                <div class="modal-pending-icon">!</div>
                                <div>
                                    <h2>Payment Pending</h2>
                                    <p>Your booking has been reserved, but the payment has not been completed yet.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="modal-label">BOOKING INFORMATION</div>
                        <h1 class="fs-3 fw-bold mb-3">Booking Details</h1>

                        <div class="modal-details-grid">
                            <div class="modal-detail">
                                <span>Passenger</span>
                                <strong><?= htmlspecialchars($passenger['name'] ?? '-') ?></strong>
                            </div>
                            <div class="modal-detail">
                                <span>Seat Number</span>
                                <strong><?= htmlspecialchars($passenger['seat_number'] ?? '-') ?></strong>
                            </div>
                            <div class="modal-detail">
                                <span>Booking Number</span>
                                <strong>#<?= $bk_id ?></strong>
                            </div>
                            <div class="modal-detail">
                                <span>Transaction ID</span>
                                <strong>TRX-<?= str_pad($trans_id, 5, "0", STR_PAD_LEFT) ?></strong>
                            </div>
                            <div class="modal-detail">
                                <span>Payment Method</span>
                                <strong><?= htmlspecialchars($display_payment_type) ?></strong>
                            </div>
                            <div class="modal-detail">
                                <span>Payment Status</span>
                                <strong class="<?= $is_paid ? 'modal-paid' : 'modal-pending-status' ?>">
                                    <?= htmlspecialchars($payment_status) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="modal-transaction">
                            <div class="modal-transaction-title">
                                <div>
                                    <div class="modal-label">TRANSACTION</div>
                                    <h2>Transaction Details</h2>
                                </div>
                                <span class="modal-badge <?= $is_paid ? 'modal-badge-paid' : 'modal-badge-pending' ?>">
                                    <?= $is_paid ? 'Paid' : 'Pending' ?>
                                </span>
                            </div>

                            <div class="modal-table header">
                                <span>Passenger</span>
                                <span>Route</span>
                                <span>Seat</span>
                                <span>Price</span>
                                <span>Qty</span>
                                <span>Subtotal</span>
                            </div>

                            <div class="modal-table">
                                <span><?= htmlspecialchars($passenger['name'] ?? '-') ?></span>
                                <span>
                                    <?= htmlspecialchars($b['pergi_origin_name'] ?? '-') ?> → <?= htmlspecialchars($b['pergi_dest_name'] ?? '-') ?>
                                </span>
                                <span><?= htmlspecialchars($passenger['seat_number'] ?? '-') ?></span>
                                <span><?= rupiah($total_amount) ?></span>
                                <span>1</span>
                                <span><?= rupiah($total_amount) ?></span>
                            </div>

                            <div class="modal-table total">
                                <span>Total</span>
                                <strong><?= rupiah($total_amount) ?></strong>
                            </div>
                        </div>

                        <?php if ($is_paid): ?>
                            <div class="modal-ticket" id="travelTicket_<?= $bk_id; ?>">
                                <div class="w-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="modal-label text-primary fw-bold" style="font-size: 11px;">
                                          <i class="bi <?= htmlspecialchars($transport['icon']) ?> me-1 fs-6"></i>
                                            <?= htmlspecialchars($transport['title']) ?>

                                        </div>
                                        <span class="badge bg-primary text-white px-2 py-1" style="font-size: 10px; border-radius: 6px;">
                                        <?= htmlspecialchars([
                                        'Economy' => 'Economy Class',
                                        'Business' => 'Business Class',
                                        'First' => 'First Class'
                                        ][$b['travel_class'] ?? 'Economy'] ?? 'Economy Class') ?>
                                        </span>
                                    </div>

                                    <h2 class="fs-5 fw-bold mb-3">
                                        <?= htmlspecialchars($b['pergi_origin_name'] ?? '-') ?> &rarr; <?= htmlspecialchars($b['pergi_dest_name'] ?? '-') ?>
                                    </h2>

                                    <div class="modal-ticket-info">
                                        <div>
                                            <span>Passenger</span>
                                            <strong><?= htmlspecialchars($passenger['name'] ?? '-') ?></strong>
                                        </div>
                                        <div>
                                            <span>Operator / Armada</span>
                                            <strong><?= htmlspecialchars($transport['operator']) ?></strong>
                                        </div>
                                        <div>
                                            <span>Departure Date</span>
                                            <strong><?= tanggal($b['pergi_travel_date'] ?? null) ?></strong>
                                        </div>
                                        <div>
                                            <span>Time</span>
                                            <strong><?= waktu($b['pergi_dep_date'] ?? null) ?> WIB</strong>
                                        </div>
                                        <div>
                                            <span>Seat No.</span>
                                            <strong class="text-primary fs-6"><?= htmlspecialchars($passenger['seat_number'] ?? '-') ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-qr-wrapper ms-3">
                                    <div id="qrcode_<?= $bk_id; ?>"></div>
                                </div>
                            </div>

                            <button class="modal-download" type="button" onclick="downloadTicket_<?= $bk_id; ?>()">
                               <i class="bi bi-download me-1"></i>
                               <?= htmlspecialchars($transport['download']) ?>

                            </button>
                        <?php else: ?>
                            <div class="modal-next-steps">
                                <div class="modal-next-steps-title">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Next Steps for <?= htmlspecialchars($transportType) ?> Ticket
                                </div>
                                <ol>
                                    <li>Please pay the exact amount at the <?= htmlspecialchars($transportType) ?> counter / agent.</li>
                                    <li>Show your booking number: #<?= $bk_id ?>.</li>
                                    <li>After payment, your <?= htmlspecialchars($transportType) ?> ticket will be issued automatically.</li>
                                </ol>
                            </div>

                            <div class="modal-ticket-pending">
                                <i class="bi <?= htmlspecialchars($transport['icon']) ?> fs-3 text-warning"></i>
                                <div>
                                    <strong class="d-block small"><?= htmlspecialchars($transportType) ?> ticket will be available</strong>
                                    <span class="extra-small text-muted">after counter payment is completed.</span>
                                </div>
                            </div>
                        <?php endif; ?>

                    </main>

                    <aside class="modal-summary">
                        <h2>Booking Summary</h2>

                        <div class="modal-route">
                            <div>
                                <small>FROM</small>
                                <strong><?= htmlspecialchars($b['pergi_origin_name'] ?? '-') ?></strong>
                            </div>
                            <b>→</b>
                            <div>
                                <small>TO</small>
                                <strong><?= htmlspecialchars($b['pergi_dest_name'] ?? '-') ?></strong>
                            </div>
                        </div>

                        <div class="modal-summary-row mt-3">
                            <span>Departure</span>
                            <strong>
                                <?= tanggal($b['pergi_travel_date'] ?? null) ?> · <?= waktu($b['pergi_dep_date'] ?? null) ?>
                            </strong>
                        </div>

                        <?php if (!empty($b['pulang_origin_name'])): ?>
                            <div class="modal-summary-row">
                                <span>Return</span>
                                <strong>
                                    <?= tanggal($b['pulang_travel_date'] ?? null) ?> · <?= waktu($b['pulang_dep_date'] ?? null) ?>
                                </strong>
                            </div>
                        <?php endif; ?>

                        <div class="modal-summary-row">
                            <span>Passenger</span>
                            <strong>1 Person</strong>
                        </div>

                        <div class="modal-summary-row">
                            <span>Payment</span>
                            <strong><?= htmlspecialchars($display_payment_type) ?></strong>
                        </div>

                        <hr class="my-2">

                        <div class="modal-summary-row">
                            <span>Trip Price</span>
                            <strong><?= rupiah($total_amount) ?></strong>
                        </div>

                        <div class="modal-summary-row">
                            <span>Service Fee</span>
                            <strong>Rp 0</strong>
                        </div>

                        <div class="modal-total-price mt-2">
                            <span>Total Price</span>
                            <strong><?= rupiah($total_amount) ?></strong>
                        </div>
                    </aside>

                </div>

            </div>

        </div>
    </div>
</div>

<script>
<?php if ($is_paid): ?>
document.addEventListener("DOMContentLoaded", function() {
    const qrElement = document.getElementById("qrcode_<?= $bk_id; ?>");
    if (qrElement) {
        new QRCode(qrElement, {
            text: "TRAVEL-BOOKING-<?= $bk_id; ?>",
            width: 90,
            height: 90,
            colorDark: "#17243a",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    }
});

function downloadTicket_<?= $bk_id; ?>() {
    const ticket = document.getElementById("travelTicket_<?= $bk_id; ?>");
    if (!ticket) {
        alert("Ticket tidak ditemukan.");
        return;
    }

    html2canvas(ticket, {
        scale: 3,
        backgroundColor: "#ffffff",
        useCORS: true
    }).then(function(canvas) {
        const link = document.createElement("a");
        link.download = "Travel_Ticket_Booking_<?= $bk_id; ?>.png";
        link.href = canvas.toDataURL("image/png");
        link.click();
    }).catch(function(error) {
        console.error(error);
        alert("Ticket gagal didownload.");
    });
}
<?php endif; ?>
</script>

<?php 
    endwhile; 
endif; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>