
<?php
require_once "koneksi.php";

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function canEdit($payment) {
    $p = strtolower((string)($payment["status"] ?? ""));
    $t = strtolower((string)($payment["transaction_status"] ?? ""));

    return in_array($p, ["pending", "failed"]) && $t === "pending";
}

if (!isset($_GET["payment_id"]) ||
    !ctype_digit($_GET["payment_id"])) {
    header("Location: my_payments.php");
    exit;
}

$id = (int)$_GET["payment_id"];
$error = "";

$stmt = $conn->prepare("
    SELECT p.payment_id, p.payment_type, p.status,
           t.payment_status AS transaction_status
    FROM payments p
    JOIN transactions t ON p.transaction_id = t.transaction_id
    WHERE p.payment_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment || !canEdit($payment)) {
    die("This payment cannot be edited. <a href='my_payments.php'>Back</a>");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $method = $_POST["payment_type"] ?? "";

    if (!in_array($method, ["Card", "Cash"], true)) {
        $error = "Please choose a valid payment method.";
    } else {
        $stmt = $conn->prepare("
            UPDATE payments p
            JOIN transactions t ON p.transaction_id = t.transaction_id
            SET p.payment_type = ?, p.status = 'Pending'
            WHERE p.payment_id = ?
              AND LOWER(p.status) IN ('pending', 'failed')
              AND LOWER(t.payment_status) = 'pending'
        ");
        $stmt->bind_param("si", $method, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: paymentMenu.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Payment</title>
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
p { color: #7b8da7; line-height: 1.6; }
label { display: block; margin: 24px 0 10px; font-weight: bold; }
select {
    width: 100%; padding: 14px; font-size: 15px;
    border: 1px solid #d5e1f1; border-radius: 10px; background: white;
}
button, .back {
    display: inline-block; margin-top: 22px; padding: 14px 20px;
    border: 0; border-radius: 10px; font-weight: bold;
    text-decoration: none; cursor: pointer; font-size: 15px;
}
button { color: white; background: #1685f8; }
.back { color: #1685f8; background: #eaf3ff; }
.error { color: #c62828; }
</style>
</head>
<body>
<div class="card">
    <div class="eyebrow">PAYMENT MANAGEMENT</div>
    <h1>Edit Payment</h1>
    <p>Payment ID: <strong>#<?= h($id) ?></strong></p>
    <p>Change the payment method for this pending transaction.</p>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="payment_type">Payment Method</label>
        <select name="payment_type" id="payment_type" required>
            <option value="Card"
                <?= $payment["payment_type"] === "Card" ? "selected" : "" ?>>
                Credit / Debit Card
            </option>
            <option value="Cash"
                <?= $payment["payment_type"] === "Cash" ? "selected" : "" ?>>
                Cash
            </option>
        </select>

        <button type="submit">Save Changes</button>
        <a class="back" href="paymentMenu.php">Back</a>
    </form>
</div>
</body>
</html>