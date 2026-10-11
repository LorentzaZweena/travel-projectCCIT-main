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

$query_total = "SELECT COUNT(*) AS total FROM bookings WHERE user_id = $user_id";
$res_total = $conn->query($query_total);
$total_rows = $res_total ? (int)$res_total->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

$query_bookings = "
    SELECT 
        b.booking_id, b.booking_date, b.trip_type, b.status AS booking_status, u.name AS booker_name, u.email AS booker_email, u.phone AS booker_phone, u.address AS booker_address,
        tp.trippergi_id, tp.travel_date AS pergi_travel_date, tp.departure_date AS pergi_dep_date,
        p_orig.name AS pergi_origin_name, p_orig.location AS pergi_origin_loc,
        p_dest.place_id AS dest_place_id, p_dest.name AS pergi_dest_name, p_dest.location AS pergi_dest_loc, p_dest.category AS dest_category, p_dest.description AS dest_desc, p_dest.price AS dest_price, tpu.trippulang_id, tpu.travel_date AS pulang_travel_date, tpu.departure_date AS pulang_dep_date, pu_orig.name AS pulang_origin_name, pu_dest.name AS pulang_dest_name, t.transaction_id, t.total_amount, t.payment_status, pm.payment_id, pm.payment_type, pm.payment_date, pm.status AS pay_status, c.card_type, c.card_number, c.card_holder_name, c.expired_date, cs.amount_received FROM bookings b
    
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

            <!-- Paginasi -->
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

    <!-- PANEL KANAN -->
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
            <div class="transport-box" title="Kapal Laut"><i class="bi bi-tsunami"></i><span>Ship</span></div>
            <div class="transport-box" title="Bus"><i class="bi bi-bus-front-fill"></i><span>Bus</span></div>
            <div class="transport-box" title="Bayi / Infant"><i class="bi bi-person-arms-up"></i><span>Infant</span></div>
            <div class="transport-box" title="Mobil"><i class="bi bi-car-front-fill"></i><span>Taxi</span></div>
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
        $trans_id = $b['transaction_id'] ?? 0;
        $dest_place_id = $b['dest_place_id'] ?? 0;

        $q_pass_pergi = "
            SELECT pg.passenger_id, pg.name, pg.category, pg.ID, pg.birth_date, pp.seat_number,
                   inf.name AS infant_name
            FROM passengerpergi pp
            JOIN passengers pg ON pp.passenger_id = pg.passenger_id
            LEFT JOIN companion comp ON pg.passenger_id = comp.parent_id
            LEFT JOIN passengers inf ON comp.infant_id = inf.passenger_id
            WHERE pp.trippergi_id = $trippergi_id
        ";
        $res_pass_pergi = $conn->query($q_pass_pergi);

        $q_pass_pulang = "
            SELECT pg.passenger_id, pg.name, pp.seat_number
            FROM passengerpulang pp
            JOIN passengers pg ON pp.passenger_id = pg.passenger_id
            WHERE pp.trippulang_id = $trippulang_id
        ";
        $res_pass_pulang = $conn->query($q_pass_pulang);
        $seats_pulang = [];
        if ($res_pass_pulang) {
            while ($sp = $res_pass_pulang->fetch_assoc()) {
                $seats_pulang[$sp['passenger_id']] = $sp['seat_number'];
            }
        }

        $q_trans_det = "
            SELECT td.*, pg.name AS pass_name
            FROM transactiondetails td
            LEFT JOIN passengers pg ON td.passenger_id = pg.passenger_id
            WHERE td.transaction_id = $trans_id
        ";
        $res_trans_det = $conn->query($q_trans_det);

        $q_review = "SELECT * FROM reviews WHERE place_id = $dest_place_id ORDER BY review_id DESC LIMIT 1";
        $res_review = $conn->query($q_review);
        $review_data = $res_review ? $res_review->fetch_assoc() : null;
?>
<div class="modal fade" id="ticketModal<?= $bk_id; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            
            <div class="modal-header text-white p-4" style="background: linear-gradient(135deg, #5d5fef 0%, #3b82f6 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-white text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-compass-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold lh-1">E-TICKET / BOARDING PASS</h5>
                        <small class="opacity-75">Booking ID: #BK-<?= str_pad($bk_id, 4, '0', STR_PAD_LEFT); ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded-4 mb-4 border">
                    <div class="row align-items-center text-center text-md-start g-3">
                        <div class="col-md-5">
                            <span class="badge bg-primary mb-1">PEMBERANGKATAN</span>
                            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($b['pergi_origin_name'] ?? 'Origin'); ?></h5>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= htmlspecialchars($b['pergi_dep_date'] ?? $b['pergi_travel_date']); ?></small>
                        </div>
                        <div class="col-md-2 text-center my-2 my-md-0">
                            <i class="bi bi-airplane-fill fs-3 text-primary d-none d-md-inline-block"></i>
                            <div class="extra-small text-primary fw-bold"><?= htmlspecialchars($b['trip_type']); ?></div>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <span class="badge bg-success mb-1">DESTINASI</span>
                            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($b['pergi_dest_name'] ?? 'Destination'); ?></h5>
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($b['pergi_dest_loc'] ?? 'Indonesia'); ?></small>
                        </div>
                    </div>

                    <?php if ($b['trip_type'] === 'RoundTrip' && !empty($b['pulang_origin_name'])): ?>
                        <hr class="my-3">
                        <div class="row align-items-center text-center text-md-start g-3">
                            <div class="col-md-5">
                                <span class="badge bg-secondary mb-1">PULANG</span>
                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($b['pulang_origin_name']); ?></h6>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= htmlspecialchars($b['pulang_dep_date'] ?? $b['pulang_travel_date']); ?></small>
                            </div>
                            <div class="col-md-2 text-center">
                                <i class="bi bi-arrow-left-right text-muted"></i>
                            </div>
                            <div class="col-md-5 text-md-end">
                                <span class="badge bg-secondary mb-1">KEMBALI KE</span>
                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($b['pulang_dest_name']); ?></h6>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 h-100">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-person-circle me-1 text-primary"></i> Data Pemesan</h6>
                            <div class="small mb-1"><strong>Nama:</strong> <?= htmlspecialchars($b['booker_name']); ?></div>
                            <div class="small mb-1"><strong>Email:</strong> <?= htmlspecialchars($b['booker_email']); ?></div>
                            <div class="small mb-1"><strong>Telepon:</strong> <?= htmlspecialchars($b['booker_phone']); ?></div>
                            <div class="small"><strong>Alamat:</strong> <?= htmlspecialchars($b['booker_address']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 h-100">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-credit-card-2-back me-1 text-primary"></i> Pembayaran</h6>
                            <div class="small mb-1"><strong>Status Bayar:</strong> <span class="badge bg-success"><?= htmlspecialchars($b['payment_status'] ?? 'Paid'); ?></span></div>
                            <div class="small mb-1"><strong>Metode:</strong> <?= htmlspecialchars($b['payment_type'] ?? 'Card'); ?></div>
                            
                            <?php if ($b['payment_type'] === 'Card'): ?>
                                <div class="small mb-1"><strong>Kartu:</strong> <?= htmlspecialchars($b['card_type'] ?? 'MasterCard'); ?> (<?= htmlspecialchars($b['card_number'] ?? '****'); ?>)</div>
                                <div class="small"><strong>Atas Nama:</strong> <?= htmlspecialchars($b['card_holder_name'] ?? $b['booker_name']); ?></div>
                            <?php else: ?>
                                <div class="small"><strong>Jumlah Diterima:</strong> Rp <?= number_format((float)($b['amount_received'] ?? $b['total_amount']), 0, ',', '.'); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-people me-1 text-primary"></i> Daftar Penumpang & Kursi</h6>
                <div class="table-responsive rounded-3 border mb-4">
                    <table class="table table-sm table-striped align-middle mb-0 extra-small" style="font-size:0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Penumpang</th>
                                <th>Kategori</th>
                                <th>NIK / ID</th>
                                <th>Kursi Pergi</th>
                                <?php if ($b['trip_type'] === 'RoundTrip'): ?><th>Kursi Pulang</th><?php endif; ?>
                                <th>Pendamping (Infant)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_pass_pergi && $res_pass_pergi->num_rows > 0): ?>
                                <?php while ($p = $res_pass_pergi->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($p['name']); ?></td>
                                        <td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($p['category']); ?></span></td>
                                        <td><?= htmlspecialchars($p['ID']); ?></td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($p['seat_number'] ?? '-'); ?></td>
                                        <?php if ($b['trip_type'] === 'RoundTrip'): ?>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($seats_pulang[$p['passenger_id']] ?? '-'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?= !empty($p['infant_name']) ? '<span class="badge bg-warning text-dark"><i class="bi bi-person-arms-up me-1"></i>' . htmlspecialchars($p['infant_name']) . '</span>' : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Data penumpang tidak ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($res_trans_det && $res_trans_det->num_rows > 0): ?>
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-receipt me-1 text-primary"></i> Rincian Biaya</h6>
                    <div class="table-responsive rounded-3 border mb-3">
                        <table class="table table-sm align-middle mb-0 extra-small" style="font-size:0.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Item / Penumpang</th>
                                    <th>Harga Tiket</th>
                                    <th>Qty</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($td = $res_trans_det->fetch_assoc()): ?>
                                    <tr>
                                        <td>Tiket Wisata (<?= htmlspecialchars($td['pass_name'] ?? 'Penumpang'); ?>)</td>
                                        <td>Rp <?= number_format((float)$td['ticket_price'], 0, ',', '.'); ?></td>
                                        <td><?= htmlspecialchars($td['quantity']); ?></td>
                                        <td class="text-end fw-bold">Rp <?= number_format((float)$td['subtotal'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($review_data): ?>
                    <div class="p-3 bg-warning-subtle rounded-3 border border-warning-subtle d-flex align-items-center justify-content-between">
                        <div>
                            <small class="fw-bold text-dark"><i class="bi bi-star-fill text-warning me-1"></i> Ulasan Destinasi <?= htmlspecialchars($b['pergi_dest_name']); ?></small>
                            <div class="extra-small text-secondary">"<?= htmlspecialchars($review_data['comment']); ?>"</div>
                        </div>
                        <span class="badge bg-warning text-dark fw-bold fs-6"><?= htmlspecialchars($review_data['rating']); ?>.0</span>
                    </div>
                <?php endif; ?>

            </div>

            <div class="modal-footer bg-light border-0 d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted extra-small">TOTAL PEMBAYARAN:</span>
                    <h5 class="fw-bold text-primary mb-0">Rp <?= number_format((float)($b['total_amount'] ?? $b['dest_price']), 0, ',', '.'); ?></h5>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" onclick="window.print();">
                        <i class="bi bi-printer me-1"></i> Cetak Tiket
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
<?php 
    endwhile; 
endif; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>