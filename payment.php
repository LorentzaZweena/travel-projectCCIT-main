<?php
session_start();
require_once 'koneksi.php';

// Helper Functions
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

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_id = (int)($_REQUEST['booking_id'] ?? 2);
$passenger_id = (int)($_REQUEST['passenger_id'] ?? 0);
$full_name = $_REQUEST['full_name'] ?? '';

/* =========================
   BOOKING
========================= */
$sql = "SELECT b.booking_id, b.trip_type, u.name AS user_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Booking tidak ditemukan.");
}

/* =========================
   TRIP PERGI
========================= */
$sql = "SELECT tp.trippergi_id, tp.travel_date, tp.departure_date,
               p1.name AS origin, p2.name AS destination
        FROM tripdetailspergi tp
        LEFT JOIN places p1 ON tp.origin_id = p1.place_id
        LEFT JOIN places p2 ON tp.destination_id = p2.place_id
        WHERE tp.booking_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$pergi = $stmt->get_result()->fetch_assoc();

/* =========================
   TRIP PULANG
========================= */
$sql = "SELECT tr.trippulang_id, tr.travel_date, tr.departure_date,
               p1.name AS origin, p2.name AS destination
        FROM tripdetailspulang tr
        LEFT JOIN places p1 ON tr.origin_id = p1.place_id
        LEFT JOIN places p2 ON tr.destination_id = p2.place_id
        WHERE tr.booking_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$pulang = $stmt->get_result()->fetch_assoc();

/* =========================
   TRANSACTION
========================= */
$sql = "SELECT transaction_id, total_amount, payment_status
        FROM transactions
        WHERE booking_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    die("Transaction tidak ditemukan.");
}

$total = (float)($transaction['total_amount'] ?? 0);
$transaction_id = (int)$transaction['transaction_id'];

$payment_result = null;

/* =========================
   PROSES PEMBAYARAN
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_type = $_POST['payment_type'] ?? 'Card';

    $res = $conn->query("SELECT COALESCE(MAX(payment_id), 0) + 1 AS new_id FROM payments");
    $new_payment_id = $res->fetch_assoc()['new_id'];

    if ($payment_type === 'Card') {
        $payment_status = 'Success';
        $trans_status   = 'Paid';
        $booking_status = 'Confirmed';

        $stmt = $conn->prepare("INSERT INTO payments (payment_id, transaction_id, payment_type, payment_date, status) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iiss", $new_payment_id, $transaction_id, $payment_type, $payment_status);
        $stmt->execute();

        $card_type = $_POST['card_type'] ?? 'MasterCard';
        $card_num  = str_replace(' ', '', $_POST['card_number'] ?? '');
        $holder    = $_POST['card_holder_name'] ?? $booking['user_name'];
        $exp       = $_POST['expired_date'] ?? '12/28';

        $res = $conn->query("SELECT COALESCE(MAX(card_id), 0) + 1 AS new_id FROM cards");
        $new_card_id = $res->fetch_assoc()['new_id'];

        $stmt = $conn->prepare("INSERT INTO cards (card_id, payment_id, card_type, card_number, card_holder_name, expired_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $new_card_id, $new_payment_id, $card_type, $card_num, $holder, $exp);
        $stmt->execute();

        $payment_result = 'success';
    } else {
        // Pembayaran Cash -> Status diset 'Pending'
        $payment_status = 'Pending';
        $trans_status   = 'Pending';
        $booking_status = 'Pending';

        $stmt = $conn->prepare("INSERT INTO payments (payment_id, transaction_id, payment_type, payment_date, status) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iiss", $new_payment_id, $transaction_id, $payment_type, $payment_status);
        $stmt->execute();

        $res = $conn->query("SELECT COALESCE(MAX(cash_id), 0) + 1 AS new_id FROM cashes");
        $new_cash_id = $res->fetch_assoc()['new_id'];

        $amount_received = 0; // Kasir yang akan mengisi ketika dibayar di counter
        $stmt = $conn->prepare("INSERT INTO cashes (cash_id, payment_id, amount_received) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $new_cash_id, $new_payment_id, $amount_received);
        $stmt->execute();

        $payment_result = 'pending';
    }

    $stmt = $conn->prepare("UPDATE transactions SET payment_status = ? WHERE transaction_id = ?");
    $stmt->bind_param("si", $trans_status, $transaction_id);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
    $stmt->bind_param("si", $booking_status, $booking_id);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #17243a;
}

.stepper {
    max-width: 900px;
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
    font-size: 13px;
    color: #8a94a6;
}

.step.active {
    color: #1688f7;
    font-weight: bold;
}

.circle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #dfe5ed;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.circle.done {
    background: #20a978;
}

.circle.active {
    background: #1688f7;
}

.line {
    width: 60px;
    height: 2px;
    background: #dfe5ed;
}

.container {
    max-width: 1100px;
    margin: auto;
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 25px;
    padding: 0 0 50px;
}

.card, .summary {
    background: white;
    border-radius: 18px;
    padding: 25px;
    box-shadow: 0 8px 30px rgba(30,60,90,.06);
}

.label {
    color: #1688f7;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 2px;
    margin-bottom: 8px;
}

h1 {
    font-size: 30px;
    margin-bottom: 8px;
}

.description {
    color: #758198;
    font-size: 13px;
    margin-bottom: 18px;
}

/* PAYMENT HISTORY BUTTON */
.payment-history-link {
    display: inline-block;
    margin-bottom: 20px;
    padding: 11px 16px;
    background: #eaf3ff;
    color: #1688f7;
    border: 1px solid #d7e8ff;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    transition: background 0.2s ease;
}

.payment-history-link:hover {
    background: #dcecff;
}

.trip-box {
    border: 1px solid #dce5ef;
    border-radius: 13px;
    padding: 18px;
    margin-bottom: 12px;
    background: #fbfdff;
}

.trip-label {
    color: #1688f7;
    font-size: 10px;
    font-weight: bold;
    margin-bottom: 8px;
}

.route {
    font-size: 16px;
    font-weight: bold;
}

.trip-info {
    color: #78859a;
    font-size: 12px;
    margin-top: 8px;
}

.section-title {
    margin: 28px 0 15px;
    font-size: 18px;
}

.payment-option {
    border: 1px solid #dce5ef;
    border-radius: 13px;
    padding: 16px;
    margin-bottom: 12px;
}

.method {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
}

.method input {
    width: 16px;
    height: 16px;
    margin-top: 3px;
    accent-color: #1688f7;
}

.method-text {
    flex: 1;
}

.method strong {
    display: block;
    font-size: 14px;
}

.method span {
    display: block;
    color: #7c8799;
    font-size: 11px;
    margin-top: 4px;
}

.form-grid {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px dashed #dce5ef;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group.full {
    grid-column: 1 / 3;
}

.form-group label {
    font-size: 11px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    width: 100%;
    height: 42px;
    border: 1px solid #d6deea;
    border-radius: 9px;
    padding: 0 12px;
    font-size: 13px;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #1688f7;
}

.cash-box {
    display: none;
    margin-top: 14px;
    padding: 15px;
    background: #f4f8fd;
    border: 1px solid #dce5ef;
    border-radius: 10px;
}

.cash-box strong {
    font-size: 13px;
}

.cash-box p {
    color: #7d8797;
    font-size: 11px;
    line-height: 17px;
    margin-top: 6px;
}

.cash-amount {
    margin-top: 12px;
    padding: 11px 13px;
    background: white;
    border: 1px solid #dce5ef;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cash-amount span {
    color: #7d8797;
    font-size: 11px;
}

.cash-amount strong {
    color: #1688f7;
    font-size: 13px;
}

.button {
    width: 100%;
    height: 46px;
    border: none;
    border-radius: 10px;
    background: #1688f7;
    color: white;
    font-weight: bold;
    font-size: 13px;
    margin-top: 18px;
    cursor: pointer;
}

.button:hover {
    background: #0f78df;
}

.summary h2 {
    font-size: 21px;
    margin-bottom: 20px;
}

.summary-route {
    border: 1px solid #dce5ef;
    border-radius: 12px;
    padding: 16px;
    display: grid;
    grid-template-columns: 1fr 25px 1fr;
    gap: 8px;
}

.summary-route small {
    color: #8b95a7;
    font-size: 9px;
}

.summary-route strong {
    display: block;
    font-size: 12px;
    margin-top: 5px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 0;
    font-size: 13px;
}

.summary-row span {
    color: #8190a5;
}

.summary-row strong {
    text-align: right;
}

hr {
    border: none;
    border-top: 1px solid #e2e7ed;
    margin: 15px 0;
}

.total {
    background: #eef6ff;
    padding: 16px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total strong {
    color: #1688f7;
    font-size: 18px;
}

@media (max-width: 900px) {
    .stepper {
        width: 95%;
    }

    .container {
        width: 95%;
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="stepper">
    <div class="step">
        <div class="circle done">✓</div>
        Passenger
    </div>

    <div class="line"></div>

    <div class="step active">
        <div class="circle active">2</div>
        Payment
    </div>

    <div class="line"></div>

    <div class="step">
        <div class="circle">3</div>
        Confirmation
    </div>
</div>

<div class="container">

<main class="card">

    <div class="label">SECURE CHECKOUT</div>
    <h1>Confirm and Pay</h1>

    <p class="description">
        Review your trip details and choose your preferred payment method.
    </p>

    <!-- PAYMENT HISTORY BUTTON -->
    <a href="paymentMenu.php" class="payment-history-link">
        View Payment History →
    </a>

    <div class="trip-box">
        <div class="trip-label">DEPARTURE</div>

        <div class="route">
            <?= htmlspecialchars($pergi['origin'] ?? '-') ?>
            →
            <?= htmlspecialchars($pergi['destination'] ?? '-') ?>
        </div>

        <div class="trip-info">
            <?= tanggal($pergi['travel_date'] ?? null) ?>
            ·
            <?= waktu($pergi['departure_date'] ?? null) ?>
        </div>
    </div>

    <?php if ($pulang): ?>
    <div class="trip-box">
        <div class="trip-label">RETURN</div>

        <div class="route">
            <?= htmlspecialchars($pulang['origin'] ?? '-') ?>
            →
            <?= htmlspecialchars($pulang['destination'] ?? '-') ?>
        </div>

        <div class="trip-info">
            <?= tanggal($pulang['travel_date'] ?? null) ?>
            ·
            <?= waktu($pulang['departure_date'] ?? null) ?>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="section-title">Payment Method</h2>

    <form action="" method="POST" id="paymentForm">
        <input type="hidden" name="process_payment" value="1">

        <input type="hidden" name="booking_id"
               id="bookingId" value="<?= $booking_id ?>">

        <input type="hidden" name="passenger_id"
               value="<?= $passenger_id ?>">

        <input type="hidden" name="transaction_id"
               id="transactionId" value="<?= $transaction_id ?>">

        <input type="hidden" name="full_name"
               value="<?= htmlspecialchars($full_name) ?>">

        <!-- CARD PAYMENT -->
        <div class="payment-option">
            <label class="method">
                <input type="radio" name="payment_type"
                       value="Card" checked>

                <div class="method-text">
                    <strong>Credit / Debit Card</strong>
                    <span>Pay securely using your card</span>
                </div>
            </label>

            <div id="cardForm" class="form-grid">

                <div class="form-group">
                    <label>Card Holder Name</label>
                    <input type="text" name="card_holder_name"
                           value="<?= htmlspecialchars($full_name ?: $booking['user_name']) ?>"
                           placeholder="Your name">
                </div>

                <div class="form-group">
                    <label>Card Type</label>
                    <select name="card_type">
                        <option value="">Select card type</option>
                        <option value="Visa">Visa</option>
                        <option value="MasterCard" selected>MasterCard</option>
                        <option value="JCB">JCB</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Card Number</label>
                    <input type="text" name="card_number"
                           placeholder="1234 5678 9012 3456"
                           maxlength="19">
                </div>

                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="text" name="expired_date"
                           placeholder="MM / YY" maxlength="7">
                </div>

                <div class="form-group">
                    <label>CVV</label>
                    <input type="password" name="cvv"
                           placeholder="***" maxlength="4">
                </div>

            </div>
        </div>

        <!-- CASH PAYMENT -->
        <div class="payment-option">
            <label class="method">
                <input type="radio" name="payment_type"
                       value="Cash">

                <div class="method-text">
                    <strong>Cash</strong>
                    <span>Pay with cash</span>
                </div>
            </label>

            <div id="cashBox" class="cash-box">
                <strong>Cash Payment</strong>

                <p>
                    Your booking will be reserved as pending.
                    Please complete the payment at the counter.
                </p>

                <div class="cash-amount">
                    <span>Amount to pay</span>
                    <strong><?= rupiah($total) ?></strong>
                </div>

                <input type="hidden" name="amount_received" value="0">
            </div>
        </div>

        <button type="submit" class="button" id="payButton">
            Pay <?= rupiah($total) ?> →
        </button>

    </form>
</main>

<!-- BOOKING SUMMARY -->
<aside class="summary">

    <h2>Booking Summary</h2>

    <div class="summary-route">
        <div>
            <small>FROM</small>
            <strong><?= htmlspecialchars($pergi['origin'] ?? '-') ?></strong>
        </div>

        <b>→</b>

        <div>
            <small>TO</small>
            <strong><?= htmlspecialchars($pergi['destination'] ?? '-') ?></strong>
        </div>
    </div>

    <div class="summary-row">
        <span>Departure</span>
        <strong>
            <?= tanggal($pergi['travel_date'] ?? null) ?>
            ·
            <?= waktu($pergi['departure_date'] ?? null) ?>
        </strong>
    </div>

    <?php if ($pulang): ?>
    <div class="summary-row">
        <span>Return</span>
        <strong>
            <?= tanggal($pulang['travel_date'] ?? null) ?>
            ·
            <?= waktu($pulang['departure_date'] ?? null) ?>
        </strong>
    </div>
    <?php endif; ?>

    <div class="summary-row">
        <span>Passenger</span>
        <strong>1 Person</strong>
    </div>

    <hr>

    <div class="summary-row">
        <span>Trip Price</span>
        <strong><?= rupiah($total) ?></strong>
    </div>

    <div class="summary-row">
        <span>Service Fee</span>
        <strong>Rp 0</strong>
    </div>

    <div class="total">
        <span>Total</span>
        <strong><?= rupiah($total) ?></strong>
    </div>

</aside>
</div>

<script>
const radios = document.querySelectorAll(
    'input[name="payment_type"]'
);

const cardForm = document.getElementById("cardForm");
const cashBox = document.getElementById("cashBox");
const payButton = document.getElementById("payButton");

const cardHolder = document.querySelector(
    'input[name="card_holder_name"]'
);
const cardType = document.querySelector(
    'select[name="card_type"]'
);
const cardNumber = document.querySelector(
    'input[name="card_number"]'
);
const expiredDate = document.querySelector(
    'input[name="expired_date"]'
);
const cvv = document.querySelector(
    'input[name="cvv"]'
);

function updatePaymentMethod() {
    const selected = document.querySelector(
        'input[name="payment_type"]:checked'
    );

    if (!selected) return;

    if (selected.value === "Card") {
        cardForm.style.display = "grid";
        cashBox.style.display = "none";

        cardHolder.required = true;
        cardType.required = true;
        cardNumber.required = true;
        expiredDate.required = true;
        cvv.required = true;

        payButton.innerHTML = "Pay <?= rupiah($total) ?> →";
    } else {
        cardForm.style.display = "none";
        cashBox.style.display = "block";

        cardHolder.required = false;
        cardType.required = false;
        cardNumber.required = false;
        expiredDate.required = false;
        cvv.required = false;

        payButton.innerHTML = "Continue →";
    }
}

radios.forEach(function(radio) {
    radio.addEventListener("change", updatePaymentMethod);
});

cardNumber.addEventListener("input", function() {
    let value = this.value.replace(/\D/g, "").substring(0, 16);
    const groups = value.match(/.{1,4}/g);
    this.value = groups ? groups.join(" ") : "";
});

expiredDate.addEventListener("input", function() {
    let value = this.value.replace(/\D/g, "").substring(0, 4);

    if (value.length >= 3) {
        value = value.substring(0, 2) + " / " + value.substring(2);
    }

    this.value = value;
});

cvv.addEventListener("input", function() {
    this.value = this.value.replace(/\D/g, "").substring(0, 4);
});

updatePaymentMethod();

<?php if ($payment_result === 'success'): ?>
Swal.fire({
    title: 'Pembayaran Berhasil!',
    text: 'Pembayaran kartu Anda telah terkonfirmasi.',
    icon: 'success',
    confirmButtonText: 'Ke Riwayat Pembayaran'
}).then(() => {
    window.location.href = 'paymentMenu.php';
});
<?php elseif ($payment_result === 'pending'): ?>
Swal.fire({
    title: 'Pemesanan Diterima!',
    text: 'Status pembayaran Tunai Anda adalah Pending. Silakan lakukan pembayaran di counter.',
    icon: 'info',
    confirmButtonText: 'Ke Riwayat Pembayaran'
}).then(() => {
    window.location.href = 'paymentMenu.php';
});
<?php endif; ?>
</script>

</body>
</html>