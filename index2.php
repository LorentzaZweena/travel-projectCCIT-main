<?php
session_start();
require_once 'koneksi.php';

$user_id = $_SESSION['user_id'] ?? 1;
$query_user = "SELECT * FROM users WHERE user_id = $user_id LIMIT 1";
$res_user = $conn->query($query_user);
$user = $res_user ? $res_user->fetch_assoc() : ['name' => 'Budi Santoso', 'email' => 'budi@example.com'];

$query_places = "SELECT * FROM places ORDER BY place_id ASC LIMIT 3";
$res_places = $conn->query($query_places);

$query_bookings = "SELECT b.booking_id, b.booking_date, b.trip_type, b.status, 
                          p.name AS place_name, p.location 
                   FROM bookings b
                   LEFT JOIN tripdetailspergi t ON b.booking_id = t.booking_id
                   LEFT JOIN places p ON t.destination_id = p.place_id
                   WHERE b.user_id = $user_id OR 1=1
                   ORDER BY b.booking_id DESC LIMIT 4";
$res_bookings = $conn->query($query_bookings);

$query_reviews = "SELECT r.*, u.name AS user_name, p.name AS place_name, p.location, p.price, p.image_url 
                  FROM reviews r 
                  JOIN users u ON r.user_id = u.user_id 
                  JOIN places p ON r.place_id = p.place_id 
                  ORDER BY r.review_date DESC LIMIT 3";
$res_reviews = $conn->query($query_reviews);

$query_card = "SELECT * FROM cards ORDER BY card_id ASC LIMIT 1";
$res_card = $conn->query($query_card);
$user_card = $res_card ? $res_card->fetch_assoc() : null;

$raw_card_number = $user_card['card_number'] ?? '4532750012340001';
$formatted_card_number = implode(' ', str_split($raw_card_number, 4));

$fallback_images = [
    'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=600&q=80',
    'https://images.unsplash.com/photo-1564507592333-c60657eea523?auto=format&fit=crop&w=600&q=80',
    'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=600&q=80'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Travel</title>

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
            <a href="dashboard.php" class="nav-link-custom active">
                <i class="bi bi-grid-fill"></i> <span>Dashboard</span>
            </a>
            <a href="paymentMenu.php" class="nav-link-custom">
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
                <h1 class="welcome-title">Hello, <?= htmlspecialchars(explode(' ', $user['name'])[0]); ?></h1>
                <p class="welcome-sub mb-0">Welcome back and Explore the world</p>
            </div>
        </header>

        <section class="mb-4">
            <h5 class="fw-bold mb-3">Places</h5>

            <div class="row g-3">
                <?php 
                $idx = 0;
                if ($res_places && $res_places->num_rows > 0): 
                    while ($place = $res_places->fetch_assoc()): 
                        $img = (!empty($place['image_url']) && strpos($place['image_url'], 'example.com') === false) 
                               ? $place['image_url'] 
                               : $fallback_images[$idx % 3];
                        $idx++;
                ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="place-card">
                            <img src="<?= htmlspecialchars($img); ?>" alt="<?= htmlspecialchars($place['name']); ?>">
                            <div class="place-card-overlay">
                                <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($place['name']); ?></h6>
                                <div class="small opacity-75 mb-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($place['location']); ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="badge bg-light text-dark fw-bold">Rp <?= number_format((float)$place['price'], 0, ',', '.'); ?></span>
                                    <span class="small text-warning"><i class="bi bi-star-fill"></i> 4.8</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile; 
                endif; 
                ?>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">Bookings</h6>
                    <!-- <span class="badge bg-light text-secondary">Database Connected</span> -->
                </div>

                <div class="table-responsive rounded-3 border-0">
                    <table class="table table-hover table-custom align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Destinasi</th>
                                <th>Tipe Trip</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_bookings && $res_bookings->num_rows > 0): ?>
                                <?php while ($b = $res_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark">
                                            <div class="text-truncate" style="max-width: 140px;"><?= htmlspecialchars($b['place_name'] ?? 'Wisata Indonesia'); ?></div>
                                            <small class="text-muted fw-normal"><?= htmlspecialchars($b['location'] ?? 'Indonesia'); ?></small>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($b['trip_type']); ?></span></td>
                                        <td class="text-muted"><?= htmlspecialchars($b['booking_date']); ?></td>
                                        <td>
                                            <?php 
                                            $status = $b['status'];
                                            $badge_class = 'bg-warning text-dark';
                                            if ($status == 'Completed' || $status == 'Confirmed') $badge_class = 'bg-success text-white';
                                            if ($status == 'Cancelled') $badge_class = 'bg-danger text-white';
                                            ?>
                                            <span class="badge rounded-pill <?= $badge_class; ?>"><?= htmlspecialchars($status); ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted">Belum ada data booking.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <h6 class="fw-bold mb-2">Reviews</h6>
                <div class="d-flex flex-column gap-2">
                    <?php if ($res_reviews && $res_reviews->num_rows > 0): ?>
                        <?php while ($r = $res_reviews->fetch_assoc()): ?>
                            <div class="p-3 rounded-4 bg-light d-flex align-items-center justify-content-between gap-2 shadow-sm">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 38px; height: 38px; font-size: 0.85rem;">
                                        <?= strtoupper(substr($r['user_name'], 0, 1)); ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold small text-dark text-truncate"><?= htmlspecialchars($r['place_name']); ?></div>
                                        <div class="text-muted extra-small text-truncate" style="font-size:0.72rem;">by <?= htmlspecialchars($r['user_name']); ?></div>
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="text-warning extra-small" style="font-size:0.75rem;"><i class="bi bi-star-fill"></i> <?= htmlspecialchars($r['rating']); ?>.0</div>
                                    <div class="fw-bold extra-small text-primary" style="font-size:0.75rem;">Rp <?= number_format((float)$r['price'], 0, ',', '.'); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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