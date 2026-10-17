
<?php
session_start();
require_once 'koneksi.php';

function rupiah($angka) {
    return 'Rp ' . number_format((float)($angka ?? 0), 0, ',', '.');
}

function tanggal($date) {
    return $date ? date('d M Y', strtotime($date)) : '-';
}

function waktu($datetime) {
    return $datetime ? date('H:i', strtotime($datetime)) : '-';
}

// Menghitung jarak berdasarkan koordinat latitude dan longitude
function hitungJarak($lat1, $lon1, $lat2, $lon2) {
    $radiusBumi = 6371;

    $lat1 = deg2rad((float)$lat1);
    $lon1 = deg2rad((float)$lon1);
    $lat2 = deg2rad((float)$lat2);
    $lon2 = deg2rad((float)$lon2);

    $selisihLat = $lat2 - $lat1;
    $selisihLon = $lon2 - $lon1;

    $a = sin($selisihLat / 2) ** 2
       + cos($lat1) * cos($lat2)
       * sin($selisihLon / 2) ** 2;

    $a = min(1, max(0, $a));

    return 2 * $radiusBumi * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );
}

// Menghitung harga berdasarkan transportasi dan jarak
function hitungHarga($jarak, $transport) {
    $tarif = [
        'Bus' => [
            'per_km' => 1000,
            'minimum' => 50000
        ],
        'Kereta Api' => [
            'per_km' => 2000,
            'minimum' => 100000
        ],
        'Pesawat' => [
            'per_km' => 5000,
            'minimum' => 250000
        ]
    ];

    if (!isset($tarif[$transport])) {
        return 0;
    }

    return (int) ceil(max(
        $tarif[$transport]['minimum'],
        $jarak * $tarif[$transport]['per_km']
    ));
}

if (!isset($_SESSION['user_id'])) {
    $redirectPlace = (int)($_GET['place_id'] ?? 1);
    header(
        'Location: login.php?redirect=' .
        urlencode('booking.php?place_id=' . $redirectPlace)
    );
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$place_id = (int)($_GET['place_id'] ?? 1);
$origin_id = 1;

$transportOptions = ['Bus', 'Kereta Api', 'Pesawat'];
$transport = 'Bus';
$error = '';

$stmt = $conn->prepare(
    "SELECT * FROM users WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT * FROM places WHERE place_id = ?"
);
$stmt->bind_param("i", $place_id);
$stmt->execute();
$dest_place = $stmt->get_result()->fetch_assoc();

if (!$dest_place) {
    die("Destinasi wisata tidak ditemukan.");
}

// Asal perjalanan tetap place_id = 1, yaitu Monas Jakarta
$stmt = $conn->prepare(
    "SELECT * FROM places WHERE place_id = ?"
);
$stmt->bind_param("i", $origin_id);
$stmt->execute();
$origin_place = $stmt->get_result()->fetch_assoc();

if (!$origin_place) {
    die("Data tempat asal tidak ditemukan.");
}

$jarak = hitungJarak(
    $origin_place['latitude'],
    $origin_place['longitude'],
    $dest_place['latitude'],
    $dest_place['longitude']
);

$ticket_price = hitungHarga($jarak, $transport);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $seat_num = trim($_POST['seat_number'] ?? '');
    $travel_date = $_POST['travel_date'] ?? '';
    $trip_type = $_POST['trip_type'] ?? 'OneWay';
    $transport = $_POST['transport_type'] ?? '';
    $travel_class = $_POST['travel_class'] ?? 'Economy';
    $classMultiplier = [
    'Economy' => 1,
    'Business' => 1.5,
    'First' => 2
];

if (!isset($classMultiplier[$travel_class])) {
    $error = "Pilihan kelas tidak valid.";
} else {
    $basePrice = hitungHarga($jarak, $transport);

    $ticket_price = (int) ceil(
        $basePrice * $classMultiplier[$travel_class]
    );
}

    if (!in_array($transport, $transportOptions, true)) {
        $error = "Silakan pilih transportasi yang tersedia.";
    } elseif (
        $full_name === '' ||
        $id_number === '' ||
        $birth_date === '' ||
        $seat_num === '' ||
        $travel_date === ''
    ) {
        $error = "Semua data penumpang wajib diisi.";
    } elseif (!in_array($trip_type, ['OneWay', 'RoundTrip'], true)) {
        $error = "Jenis perjalanan tidak valid.";
    } elseif (
        !DateTime::createFromFormat('!Y-m-d', $birth_date) ||
        !DateTime::createFromFormat('!Y-m-d', $travel_date)
    ) {
        $error = "Tanggal yang dimasukkan tidak valid.";
    } elseif ($travel_date < date('Y-m-d')) {
        $error = "Tanggal perjalanan tidak boleh di masa lalu.";
    } else {
        // Hitung ulang harga sesuai transportasi dan kelas.
        $basePrice = hitungHarga($jarak, $transport);

        $classMultiplier = [
         'Economy' => 1,
        'Business' => 1.5,
        'First' => 2
        ];

$ticket_price = (int) ceil(
    $basePrice * $classMultiplier[$travel_class]
);
        $conn->begin_transaction();

        try {
            // Buat ID booking sesuai pola kode project yang sekarang.
            $res = $conn->query(
                "SELECT COALESCE(MAX(booking_id), 0) + 1 AS new_id
                 FROM bookings"
            );
            $new_booking_id = (int)$res->fetch_assoc()['new_id'];

            $today = date('Y-m-d');

            
            $stmt = $conn->prepare(
            "INSERT INTO bookings
            (booking_id, user_id, booking_date, trip_type,
            status, transport_type, travel_class)
            VALUES (?, ?, ?, ?, 'Pending', ?, ?)"
            );

            $stmt->bind_param(
            "iissss",
            $new_booking_id,
            $user_id,
            $today,
            $trip_type,
            $transport,
            $travel_class
            );

            $stmt->execute();



            // Data perjalanan pergi
            $res = $conn->query(
                "SELECT COALESCE(MAX(trippergi_id), 0) + 1 AS new_id
                 FROM tripdetailspergi"
            );
            $new_trip_id = (int)$res->fetch_assoc()['new_id'];

            $dep_datetime = $travel_date . ' 08:00:00';

            $stmt = $conn->prepare(
                "INSERT INTO tripdetailspergi
                (trippergi_id, booking_id, origin_id,
                 destination_id, travel_date, departure_date)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iiiiss",
                $new_trip_id,
                $new_booking_id,
                $origin_id,
                $place_id,
                $travel_date,
                $dep_datetime
            );
            $stmt->execute();

            // Data penumpang
            $res = $conn->query(
                "SELECT COALESCE(MAX(passenger_id), 0) + 1 AS new_id
                 FROM passengers"
            );
            $new_passenger_id = (int)$res->fetch_assoc()['new_id'];

            $category = 'Adult';

            $stmt = $conn->prepare(
                "INSERT INTO passengers
                (passenger_id, user_id, name, birth_date, ID, category)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iissss",
                $new_passenger_id,
                $user_id,
                $full_name,
                $birth_date,
                $id_number,
                $category
            );
            $stmt->execute();

            // Hubungkan penumpang dengan perjalanan
            $res = $conn->query(
                "SELECT COALESCE(MAX(passpergi_id), 0) + 1 AS new_id
                 FROM passengerpergi"
            );
            $new_passpergi_id = (int)$res->fetch_assoc()['new_id'];

            $stmt = $conn->prepare(
                "INSERT INTO passengerpergi
                (passpergi_id, trippergi_id, passenger_id, seat_number)
                VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iiis",
                $new_passpergi_id,
                $new_trip_id,
                $new_passenger_id,
                $seat_num
            );
            $stmt->execute();

            // Simpan transaksi menggunakan harga hasil perhitungan jarak
            $res = $conn->query(
                "SELECT COALESCE(MAX(transaction_id), 0) + 1 AS new_id
                 FROM transactions"
            );
            $new_trans_id = (int)$res->fetch_assoc()['new_id'];

            $payment_status = 'Unpaid';

            $stmt = $conn->prepare(
                "INSERT INTO transactions
                (transaction_id, booking_id, total_amount,
                 transaction_date, payment_status)
                VALUES (?, ?, ?, NOW(), ?)"
            );
            $stmt->bind_param(
                "iids",
                $new_trans_id,
                $new_booking_id,
                $ticket_price,
                $payment_status
            );
            $stmt->execute();

            // Simpan rincian harga tiket
            $res = $conn->query(
                "SELECT COALESCE(MAX(transaction_detail_id), 0) + 1 AS new_id
                 FROM transactiondetails"
            );
            $new_tdetail_id = (int)$res->fetch_assoc()['new_id'];

            $qty = 1;
            $subtotal = $ticket_price;

            $stmt = $conn->prepare(
                "INSERT INTO transactiondetails
                (transaction_detail_id, transaction_id, passenger_id,
                 trippergi_id, ticket_price, quantity, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iiiidid",
                $new_tdetail_id,
                $new_trans_id,
                $new_passenger_id,
                $new_trip_id,
                $ticket_price,
                $qty,
                $subtotal
            );
            $stmt->execute();

            $conn->commit();

            header(
                "Location: payment.php?booking_id=" . $new_booking_id
            );
            exit();

        
            } catch (Throwable $e) {
            $conn->rollback();

            error_log($e->getMessage());

            $error = "Detail error: " . $e->getMessage();
        }

    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Details - Travel</title>

   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

    <style>
        body {
            background: #f4f7fb;
            color: #17243a;
            font-family: sans-serif;
        }

        .stepper {
            width: 100%;
            max-width: 700px;
            margin: 30px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #8a94a6;
        }

        .step.active {
            color: #1688f7;
            font-weight: bold;
        }

        .circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1688f7;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .line {
            width: 60px;
            height: 2px;
            background: #dfe5ed;
        }

        .card-custom {
            background: white;
            border-radius: 18px;
            padding: 25px;
            border: 0;
            box-shadow: 0 8px 30px rgba(30, 60, 90, 0.06);
        }

        .trip-box {
            border: 1px solid #dce5ef;
            border-radius: 13px;
            padding: 18px;
            background: #fbfdff;
        }

        .transport-option {
            display: block;
            height: 100%;
            border: 1px solid #dce5ef;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: .2s;
        }

        .transport-option:has(input:checked) {
            border: 2px solid #1688f7;
            background: #eef6ff;
        }

        .transport-option input {
            accent-color: #1688f7;
        }

        .transport-option .transport-icon {
            font-size: 25px;
        }

        .price-box {
            background: #eef6ff;
            border-radius: 12px;
            padding: 16px;
        }

        .price-value {
            color: #1688f7;
            font-size: 22px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="stepper">
    <div class="step active">
        <div class="circle">1</div> Passenger
    </div>
    <div class="line"></div>
    <div class="step">
        <div class="circle" style="background:#ccc;">2</div> Payment
    </div>
    <div class="line"></div>
    <div class="step">
        <div class="circle" style="background:#ccc;">3</div> Confirmation
    </div>
</div>

<div class="container my-4" style="max-width:1100px;">
    <div class="row g-4">

        <main class="col-lg-8">
            <div class="card-custom">
                <div class="text-primary fw-bold small mb-1"
                     style="letter-spacing:1px;">
                    PASSENGER INFORMATION
                </div>

                <h2 class="fw-bold mb-3">Passenger &amp; Trip Details</h2>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="trip-box mb-4">
                    <div class="text-primary fw-bold small mb-1">
                        DESTINATION
                    </div>

                    <div class="fs-5 fw-bold">
                        <?= htmlspecialchars($dest_place['name']) ?>
                        (<?= htmlspecialchars($dest_place['location']) ?>)
                    </div>

                    <div class="text-muted small mt-1">
                        <?= htmlspecialchars($dest_place['description'] ?? '') ?>
                    </div>

                    <hr>

                    <div class="text-primary fw-bold small mb-1">
                        DEPARTURE
                    </div>
                    <strong>
                        <?= htmlspecialchars($origin_place['name']) ?>
                        → <?= htmlspecialchars($dest_place['name']) ?>
                    </strong>
                    <div class="small text-muted mt-1">
                        Estimated distance:
                        <?= number_format($jarak, 1, ',', '.') ?> km
                    </div>
                </div>

                <form method="POST">
                    <h5 class="fw-bold mb-3">Choose Transportation</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="transport-option">
                                <div class="transport-icon"> <i class="bi bi-airplane-fill"></i>
                                </div>
                                <input type="radio" name="transport_type"
                                       value="Pesawat"
                                       <?= $transport === 'Pesawat' ? 'checked' : '' ?>
                                       required>
                                <strong> Pesawat</strong>
                                <small class="d-block text-muted">
                                    Rp5.000/km
                                </small>
                            </label>
                        </div>

                        <div class="col-md-4">
                            <label class="transport-option">
                                <div class="transport-icon"><i class="bi bi-train-front-fill"></i>
                                </div>
                                <input type="radio" name="transport_type"
                                       value="Kereta Api"
                                       <?= $transport === 'Kereta Api' ? 'checked' : '' ?>>
                                <strong> Kereta Api</strong>
                                <small class="d-block text-muted">
                                    Rp2.000/km
                                </small>
                            </label>
                        </div>

                        <div class="col-md-4">
                            <label class="transport-option">
                                <div class="transport-icon"><i class="bi bi-bus-front-fill"></i>
                                </div>
                                <input type="radio" name="transport_type"
                                       value="Bus"
                                       <?= $transport === 'Bus' ? 'checked' : '' ?>>
                                <strong> Bus</strong>
                                <small class="d-block text-muted">
                                    Rp1.000/km
                                </small>
                            </label>
                        </div>
                    </div>

                    
<!-- Choose Class -->
<h5 class="fw-bold mb-3 mt-4">Choose Class</h5>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <label class="transport-option w-100">
            <input type="radio" name="travel_class"
            value="Economy" checked>
            <i class="bi bi-ticket-perforated-fill"></i>
            Economy Class
            <div class="small text-muted mt-1">
                Harga dasar
            </div>
        </label>
    </div>

    <div class="col-md-4">
        <label class="transport-option w-100">
            <input type="radio" name="travel_class"
            value="Business">
            <i class="bi bi-person-workspace"></i>
            Business Class
            <div class="small text-muted mt-1">
                Harga dasar × 1,5
            </div>
        </label>
    </div>

    <div class="col-md-4">
        <label class="transport-option w-100">
            <input type="radio" name="travel_class"
            value="First">
            <i class="bi bi-award-fill"></i>
            First Class
            <div class="small text-muted mt-1">
                Harga dasar × 2
            </div>
        </label>
    </div>
</div>


                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                Full Name
                            </label>
                            <input type="text" name="full_name"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? $user_info['name'] ?? '') ?>"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                ID / NIK Number
                            </label>
                            <input type="text" name="id_number"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                                   placeholder="Enter ID number"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                Birth Date
                            </label>
                            <input type="date" name="birth_date"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                Seat Number
                            </label>
                            <input type="text" name="seat_number"
                                   class="form-control"
                                   value="<?= htmlspecialchars($_POST['seat_number'] ?? 'A1') ?>"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                Travel Date
                            </label>
                            <input type="date" name="travel_date"
                                   class="form-control"
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= htmlspecialchars($_POST['travel_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                Trip Type
                            </label>
                            <select name="trip_type" class="form-select">
                                <option value="OneWay"
                                    <?= ($_POST['trip_type'] ?? 'OneWay') === 'OneWay' ? 'selected' : '' ?>>
                                    OneWay
                                </option>
                                <option value="RoundTrip"
                                    <?= ($_POST['trip_type'] ?? '') === 'RoundTrip' ? 'selected' : '' ?>>
                                    RoundTrip
                                </option>
                            </select>
                        </div>
                    </div>

                    <button type="submit"
                            class="btn btn-primary w-100 py-3 fw-bold mt-4 rounded-3">
                        Continue to Payment →
                    </button>
                </form>
            </div>
        </main>

        <aside class="col-lg-4">
            <div class="card-custom">
                <h4 class="fw-bold mb-3">Booking Summary</h4>

                <div class="d-flex justify-content-between gap-3 mb-3">
                    <span class="text-muted small">Destination</span>
                    <strong class="small text-end">
                        <?= htmlspecialchars($dest_place['name']) ?>
                    </strong>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted small">Transport</span>
                    <strong class="small" id="summaryTransport">
                        <?= htmlspecialchars($transport) ?>
                    </strong>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted small">Distance</span>
                    <strong class="small">
                        <?= number_format($jarak, 1, ',', '.') ?> km
                    </strong>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted small">Ticket Price</span>
                    <strong class="small" id="summaryPrice">
                        <?= rupiah($ticket_price) ?>
                    </strong>
                </div>

                <hr>

                <div class="price-box d-flex justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Total Price</span>
                    <strong class="price-value" id="summaryTotal">
                        <?= rupiah($ticket_price) ?>
                    </strong>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    Harga adalah simulasi berdasarkan perkiraan jarak
                    dan jenis transportasi.
                </p>
            </div>
        </aside>

    </div>
</div>

<script>
const distanceKm = <?= json_encode($jarak) ?>;

const rates = {
    'Bus': {
        perKm: 1000,
        minimum: 50000
    },
    'Kereta Api': {
        perKm: 2000,
        minimum: 100000
    },
    'Pesawat': {
        perKm: 5000,
        minimum: 250000
    }
};

function formatRupiah(value) {
    return 'Rp ' + Math.round(value).toLocaleString('id-ID');
}

function updatePrice() {
    const selected = document.querySelector(
        'input[name="transport_type"]:checked'
    );

    if (!selected) return;

    const transport = selected.value;
const config = rates[transport];

const basePrice = Math.ceil(
    Math.max(config.minimum, distanceKm * config.perKm)
);

const selectedClass = document.querySelector(
    'input[name="travel_class"]:checked'
)?.value || "Economy";

const classMultiplier = {
    Economy: 1,
    Business: 1.5,
    First: 2
};

const price = Math.ceil(
    basePrice * classMultiplier[selectedClass]
);

    document.getElementById('summaryTransport').textContent = transport;
    document.getElementById('summaryPrice').textContent = formatRupiah(price);
    document.getElementById('summaryTotal').textContent = formatRupiah(price);
}

document.querySelectorAll('input[name="transport_type"]')
    .forEach(function(input) {
        input.addEventListener('change', updatePrice);
    });
document.querySelectorAll('input[name="travel_class"]')
    .forEach(function(input) {
        input.addEventListener('change', updatePrice);
    });

updatePrice();
</script>

</body>
</html>
