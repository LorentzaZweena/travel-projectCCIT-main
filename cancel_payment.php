
<?php
require_once "koneksi.php";

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function canCancel($payment) {
    $p = strtolower((string)($payment["status"] ?? ""));
    $t = strtolower((string)($payment["transaction_status"] ?? ""));

    return in_array($p, ["pending", "failed"]) && $t === "pending";
}

if (!isset($_GET["payment_id"]) ||
    !ctype_digit($_GET["payment_id"])) {
    header("Location: paymentMenu.php");
    exit;
}

$id = (int)$_GET["payment_id"];
$error = "";

$stmt = $conn->prepare("
    SELECT p.payment_id, p.payment_type, p.status,
           t.booking_id,
           t.payment_status AS transaction_status
    FROM payments p
    JOIN transactions t ON p.transaction_id = t.transaction_id
    WHERE p.payment_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment || !canCancel($payment)) {
    die("This payment cannot be cancelled. <a href='paymentMenu.php'>Back</a>");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn->begin_transaction();

    try {
        // Lock and check the payment again before updating it.
        $stmt = $conn->prepare("
            SELECT p.status, t.payment_status AS transaction_status,
                   t.booking_id
            FROM payments p
            JOIN transactions t ON p.transaction_id = t.transaction_id
            WHERE p.payment_id = ?
            FOR UPDATE
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$current || !canCancel($current)) {
            throw new Exception("This payment can no longer be cancelled.");
        }

        $stmt = $conn->prepare("
            UPDATE payments
            SET status = 'Cancelled'
            WHERE payment_id = ?
              AND LOWER(status) IN ('pending', 'failed')
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new Exception("Payment could not be cancelled.");
        }
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE transactions
            SET payment_status = 'Cancelled'
            WHERE booking_id = ?
              AND LOWER(payment_status) = 'pending'
        ");
        $bookingId = (int)$current["booking_id"];
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();

        // Cancel the booking only if it is still pending.
        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = 'Cancelled'
            WHERE booking_id = ?
              AND LOWER(status) = 'pending'
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        header("Location: paymentMenu.php");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cancel Payment</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0; padding: 30px 18px;
    font-family: Arial, sans-serif;
    background: #f3f7fc; color: #102b50;
}
.card {
    max-width: 620px; margin: 30px auto; padding: 34px;
    background: white; border-radius: 22px;
    box-shadow: 0 12px 40px rgba(30,65,100,.06);
}
.eyebrow { color: #1685f8; font-size: 13px; font-weight: bold; letter-spacing: 3px; }
h1 { margin: 14px 0; }
p { color: #7b8da7; line-height: 1.7; }
button, .back {
    display: inline-block; margin-top: 22px; padding: 14px 20px;
    border: 0; border-radius: 10px; font-weight: bold;
    text-decoration: none; cursor: pointer; font-size: 15px;
}
button { color: white; background: #d14343; }
.back { color: #1685f8; background: #eaf3ff; }
.error { color: #c62828; }
</style>
</head>
<body>
<div class="card">
    <div class="eyebrow">PAYMENT MANAGEMENT</div>
    <h1>Cancel Payment</h1>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <p>
        Are you sure you want to cancel payment
        <strong>#<?= h($id) ?></strong>
        using <strong><?= h($payment["payment_type"]) ?></strong>?
    </p>

    <p>
        The payment record will remain in your history with Cancelled status.
    </p>

    <form method="POST"
          onsubmit="return confirm('Are you sure you want to cancel this payment?');">
        <button type="submit">Yes, Cancel Payment</button>
        <a class="back" href="paymentMenu.php">Go Back</a>
    </form>
</div>
</body>
</html>