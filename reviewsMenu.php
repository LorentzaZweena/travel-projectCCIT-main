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

$query_total = "SELECT COUNT(*) AS total FROM reviews WHERE user_id = $user_id";
$res_total = $conn->query($query_total);
$total_rows = $res_total ? (int)$res_total->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

$query_reviews = "
    SELECT 
        r.review_id, r.rating, r.comment, r.review_date,
        p.place_id, p.name AS place_name, p.location, p.category, p.description AS place_desc, p.price, p.image_url
    FROM reviews r
    JOIN places p ON r.place_id = p.place_id
    WHERE r.user_id = $user_id
    ORDER BY r.review_id DESC
    LIMIT $limit OFFSET $offset
";
$res_reviews = $conn->query($query_reviews);

$query_stats = "SELECT 
                    COUNT(*) AS total_reviews,
                    AVG(CAST(rating AS DECIMAL(3,1))) AS avg_rating,
                    COUNT(CASE WHEN rating = '5' THEN 1 END) AS total_five_star,
                    COUNT(CASE WHEN rating <= '3' THEN 1 END) AS total_low_star
                FROM reviews 
                WHERE user_id = $user_id";
$res_stats = $conn->query($query_stats);
$stats = $res_stats ? $res_stats->fetch_assoc() : [];

$avg_rating = $stats['avg_rating'] ? number_format((float)$stats['avg_rating'], 1) : '0.0';

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
    <title>My Reviews - Travel</title>

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
            <a href="bookingMenu.php" class="nav-link-custom">
                <i class="bi bi-journal-bookmark-fill"></i> <span>Booking</span>
            </a>
            <a href="reviewsMenu.php" class="nav-link-custom active">
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
                <h1 class="welcome-title">My Reviews</h1>
                <p class="welcome-sub mb-0">Kelola ulasan dan pengalaman wisata yang telah Anda bagikan</p>
            </div>
        </header>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Total ulasan</div>
                    <div class="fw-bold fs-5 text-primary"><?= $stats['total_reviews'] ?? 0; ?> Ulasan</div>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Rata rata rating</div>
                    <div class="fw-bold fs-5 text-warning"><i class="bi bi-star-fill me-1"></i><?= $avg_rating; ?> / 5.0</div>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Bintang 5</div>
                    <div class="fw-bold fs-5 text-success"><?= $stats['total_five_star'] ?? 0; ?> Ulasan</div>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <div class="p-3 bg-light rounded-4 shadow-sm border-0">
                    <div class="text-muted extra-small fw-semibold mb-1">Rating < 3</div>
                    <div class="fw-bold fs-5 text-secondary"><?= $stats['total_low_star'] ?? 0; ?> Ulasan</div>
                </div>
            </div>
        </div>

        <section>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Daftar Ulasan Saya</h6>
                <span class="badge bg-primary-subtle text-primary fw-semibold"><?= $total_rows; ?> Total Ulasan</span>
            </div>

            <div class="table-responsive rounded-4 shadow-sm bg-white border">
                <table class="table table-hover table-custom align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID Review</th>
                            <th>Destinasi Wisata</th>
                            <th>Kategori</th>
                            <th>Rating</th>
                            <th>Komentar</th>
                            <th>Tanggal Ulasan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_reviews && $res_reviews->num_rows > 0): ?>
                            <?php while ($r = $res_reviews->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-secondary">#REV-<?= str_pad($r['review_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td class="fw-semibold text-dark">
                                        <div class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars($r['place_name']); ?></div>
                                        <small class="text-muted fw-normal"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($r['location']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <?= htmlspecialchars($r['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-warning fw-bold small">
                                            <?php 
                                            $rating_num = (int)$r['rating'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating_num) {
                                                    echo '<i class="bi bi-star-fill"></i>';
                                                } else {
                                                    echo '<i class="bi bi-star text-muted opacity-25"></i>';
                                                }
                                            }
                                            ?>
                                            <span class="text-dark ms-1">(<?= $rating_num; ?>)</span>
                                        </div>
                                    </td>
                                    <td class="text-muted" style="max-width: 220px;">
                                        <div class="text-truncate" title="<?= htmlspecialchars($r['comment']); ?>">
                                            "<?= htmlspecialchars($r['comment']); ?>"
                                        </div>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= date('d M Y, H:i', strtotime($r['review_date'] ?? 'now')); ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $r['review_id']; ?>">
                                            <i class="bi bi-eye me-1"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat ulasan untuk akun Anda.</td>
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

<!-- MODAL DETAIL ULASAN -->
<?php 
if ($res_reviews && $res_reviews->num_rows > 0):
    $res_reviews->data_seek(0);
    while ($r = $res_reviews->fetch_assoc()):
?>
<div class="modal fade" id="reviewModal<?= $r['review_id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom p-3">
                <h6 class="modal-title fw-bold text-dark mb-0">Detail Ulasan Destinasi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge bg-primary-subtle text-primary mb-1"><?= htmlspecialchars($r['category']); ?></span>
                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($r['place_name']); ?></h5>
                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($r['location']); ?></small>
                </div>

                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="extra-small text-muted">RATING ANDA:</span>
                        <div class="text-warning fw-bold">
                            <?php 
                            $rating_num = (int)$r['rating'];
                            for ($i = 1; $i <= 5; $i++) {
                                echo ($i <= $rating_num) ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star text-muted opacity-25"></i>';
                            }
                            ?>
                            <span class="text-dark ms-1">(<?= $rating_num; ?> / 5)</span>
                        </div>
                    </div>
                    <p class="mb-0 text-dark small fst-italic">"<?= htmlspecialchars($r['comment']); ?>"</p>
                </div>

                <div class="small text-muted mb-2">
                    <strong>Deskripsi Tempat:</strong> <?= htmlspecialchars($r['place_desc'] ?? 'Tidak ada deskripsi.'); ?>
                </div>

                <div class="extra-small text-muted">
                    <i class="bi bi-calendar-event me-1"></i> Dibuat pada: <?= date('d F Y, H:i', strtotime($r['review_date'] ?? 'now')); ?>
                </div>
            </div>
            <div class="modal-footer border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
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