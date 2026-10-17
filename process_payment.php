<?php
require_once "koneksi.php";

$booking_id = (int)($_POST['booking_id'] ?? 0);
$transaction_id = (int)($_POST['transaction_id'] ?? 0);
$payment_type = trim($_POST['payment_type'] ?? '');

if ($booking_id <= 0 || $transaction_id <= 0) {
    die("Booking atau transaction tidak valid.");
}

if ($payment_type !== 'Card' && $payment_type !== 'Cash') {
    die("Payment method tidak valid.");
}

/* Pastikan transaction sesuai dengan booking */
$stmt = $conn->prepare("
    SELECT transaction_id
    FROM transactions
    WHERE transaction_id = ? AND booking_id = ?
");

$stmt->bind_param("ii", $transaction_id, $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("Transaction tidak ditemukan untuk booking ini.");
}
$stmt->close();

/* Validasi data jika memilih Card */
if ($payment_type === 'Card') {
    $card_holder_name = trim($_POST['card_holder_name'] ?? '');
    $card_type = trim($_POST['card_type'] ?? '');
    $card_number = trim($_POST['card_number'] ?? '');
    $expired_date = trim($_POST['expired_date'] ?? '');

    if (
        $card_holder_name === '' ||
        $card_type === '' ||
        $card_number === '' ||
        $expired_date === ''
    ) {
        die("Please complete all card fields.");
    }

    // Data kartu hanya untuk validasi, tidak disimpan.
}

/* Tentukan status berdasarkan metode pembayaran */
if ($payment_type === 'Card') {
    $transaction_status = 'Paid';
    $booking_status = 'Approved';
    $payment_record_status = 'completed';
    $payment_date_sql = 'NOW()';
} else {
    $transaction_status = 'Pending';
    $booking_status = 'Pending';
    $payment_record_status = 'Pending';
    $payment_date_sql = 'NULL';
}

/* Update status transaksi */
$stmt = $conn->prepare("
    UPDATE transactions
    SET payment_status = ?
    WHERE transaction_id = ? AND booking_id = ?
");

$stmt->bind_param(
    "sii",
    $transaction_status,
    $transaction_id,
    $booking_id
);

if (!$stmt->execute()) {
    die("Gagal memperbarui transaction: " . $stmt->error);
}
$stmt->close();

/* Update status booking */
$stmt = $conn->prepare("
    UPDATE bookings
    SET status = ?
    WHERE booking_id = ?
");

$stmt->bind_param("si", $booking_status, $booking_id);

if (!$stmt->execute()) {
    die("Gagal memperbarui booking: " . $stmt->error);
}
$stmt->close();

/* Cari data pembayaran yang sudah ada */
$stmt = $conn->prepare("
    SELECT payment_id
    FROM payments
    WHERE transaction_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if ($payment) {

    /* UPDATE metode pembayaran yang sudah ada */
    if ($payment_type === 'Card') {
        $stmt = $conn->prepare("
            UPDATE payments
            SET payment_type = 'Card',
                status = 'completed',
                payment_date = NOW()
            WHERE transaction_id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE payments
            SET payment_type = 'Cash',
                status = 'Pending',
                payment_date = NULL
            WHERE transaction_id = ?
        ");
    }

    $stmt->bind_param("i", $transaction_id);

    if (!$stmt->execute()) {
        die("Gagal memperbarui payment: " . $stmt->error);
    }
    $stmt->close();

} else {

    /* INSERT jika data pembayaran belum ada */
    $stmt = $conn->prepare("
        INSERT INTO payments
            (transaction_id, payment_type, status, payment_date)
        VALUES (?, ?, ?, $payment_date_sql)
    ");

    $stmt->bind_param(
        "iss",
        $transaction_id,
        $payment_type,
        $payment_record_status
    );

    if (!$stmt->execute()) {
        die("Gagal menyimpan payment: " . $stmt->error);
    }
    $stmt->close();
}

/* Arahkan sesuai metode pembayaran */
if ($payment_type === 'Card') {
    header(
        "Location: processing_payment.php"
        . "?booking_id=" . $booking_id
        . "&transaction_id=" . $transaction_id
    );
    exit;
}

header(
    "Location: confirmation_payment.php"
    . "?booking_id=" . $booking_id
    . "&transaction_id=" . $transaction_id
);
exit;
?>