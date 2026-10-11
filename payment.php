<?php

require_once "koneksi.php";

/* =========================
   DATA BOOKING
========================= */

$booking_id = (int)($_POST['booking_id'] ?? 2);
$passenger_id = (int)($_POST['passenger_id'] ?? 0);
$full_name = $_POST['full_name'] ?? '';
$id_number = $_POST['id_number'] ?? '';
$birth_date = $_POST['birth_date'] ?? '';
$seat_number = $_POST['seat_number'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';


/* =========================
   BOOKING
========================= */

$sql = "
    SELECT
        b.booking_id,
        b.trip_type,
        u.name AS user_name
    FROM bookings b
    LEFT JOIN users u
        ON b.user_id = u.user_id
    WHERE b.booking_id = ?
    LIMIT 1
";

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

$sql = "
    SELECT
        tp.trippergi_id,
        tp.travel_date,
        tp.departure_date,
        p1.name AS origin,
        p2.name AS destination
    FROM tripdetailspergi tp
    LEFT JOIN places p1
        ON tp.origin_id = p1.place_id
    LEFT JOIN places p2
        ON tp.destination_id = p2.place_id
    WHERE tp.booking_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();

$pergi = $stmt->get_result()->fetch_assoc();


/* =========================
   TRIP PULANG
========================= */

$sql = "
    SELECT
        tr.trippulang_id,
        tr.travel_date,
        tr.departure_date,
        p1.name AS origin,
        p2.name AS destination
    FROM tripdetailspulang tr
    LEFT JOIN places p1
        ON tr.origin_id = p1.place_id
    LEFT JOIN places p2
        ON tr.destination_id = p2.place_id
    WHERE tr.booking_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();

$pulang = $stmt->get_result()->fetch_assoc();


/* =========================
   TRANSACTION
========================= */

$sql = "
    SELECT
        transaction_id,
        total_amount,
        payment_status
    FROM transactions
    WHERE booking_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();

$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    die("Transaction tidak ditemukan.");
}

$total = (float)$transaction['total_amount'];
$transaction_id = (int)$transaction['transaction_id'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Payment</title>


<style>

/* =========================
   RESET
========================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* =========================
   BODY
========================= */

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #17243a;
}


/* =========================
   STEPPER
========================= */

.stepper {
    width: 900px;
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


/* =========================
   MAIN
========================= */

.container {
    width: 1100px;
    margin: auto;

    display: grid;
    grid-template-columns: 1fr 340px;

    gap: 25px;

    padding-bottom: 50px;
}

.card,
.summary {
    background: white;
    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 8px 30px rgba(30,60,90,.06);
}


/* =========================
   TITLE
========================= */

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

    margin-bottom: 25px;
}


/* =========================
   TRIP
========================= */

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


/* =========================
   PAYMENT TITLE
========================= */

.section-title {
    margin-top: 28px;
    margin-bottom: 15px;

    font-size: 18px;
}


/* =========================
   PAYMENT OPTION
========================= */

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

    flex-shrink: 0;

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


/* =========================
   CARD FORM
========================= */

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


/* =========================
   CASH
========================= */

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


/* =========================
   BUTTON
========================= */

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


/* =========================
   SUMMARY
========================= */

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

    padding: 11px 0;

    font-size: 13px;
}

.summary-row span {
    color: #8190a5;
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


/* =========================
   RESPONSIVE
========================= */

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


<!-- =========================
     STEPPER
========================= -->

<div class="stepper">

    <div class="step">

        <div class="circle done">
            ✓
        </div>

        Passenger

    </div>


    <div class="line"></div>


    <div class="step active">

        <div class="circle active">
            2
        </div>

        Payment

    </div>


    <div class="line"></div>


    <div class="step">

        <div class="circle">
            3
        </div>

        Confirmation

    </div>

</div>



<!-- =========================
     MAIN CONTAINER
========================= -->

<div class="container">


<!-- =========================
     LEFT
========================= -->

<main class="card">


    <div class="label">
        SECURE CHECKOUT
    </div>


    <h1>
        Confirm and Pay
    </h1>


    <p class="description">
        Review your trip details and choose your preferred payment method.
    </p>


    <!-- DEPARTURE -->

    <div class="trip-box">

        <div class="trip-label">
            DEPARTURE
        </div>

        <div class="route">

            <?= htmlspecialchars(
                $pergi['origin'] ?? '-'
            ) ?>

            →

            <?= htmlspecialchars(
                $pergi['destination'] ?? '-'
            ) ?>

        </div>

        <div class="trip-info">

            <?= tanggal(
                $pergi['travel_date'] ?? null
            ) ?>

            ·

            <?= waktu(
                $pergi['departure_date'] ?? null
            ) ?>

        </div>

    </div>


    <!-- RETURN -->

    <?php if ($pulang): ?>

    <div class="trip-box">

        <div class="trip-label">
            RETURN
        </div>

        <div class="route">

            <?= htmlspecialchars(
                $pulang['origin'] ?? '-'
            ) ?>

            →

            <?= htmlspecialchars(
                $pulang['destination'] ?? '-'
            ) ?>

        </div>

        <div class="trip-info">

            <?= tanggal(
                $pulang['travel_date'] ?? null
            ) ?>

            ·

            <?= waktu(
                $pulang['departure_date'] ?? null
            ) ?>

        </div>

    </div>

    <?php endif; ?>


    <h2 class="section-title">
        Payment Method
    </h2>


    <form
        action="process_payment.php"
        method="POST"
        id="paymentForm"
    >


        <input
            type="hidden"
            name="booking_id"
            value="<?= $booking_id ?>"
        >


        <input
            type="hidden"
            name="passenger_id"
            value="<?= $passenger_id ?>"
        >


        <input
            type="hidden"
            name="transaction_id"
            value="<?= $transaction_id ?>"
        >


        <input
            type="hidden"
            name="full_name"
            value="<?= htmlspecialchars($full_name) ?>"
        >


        <!-- =====================
             CARD
        ====================== -->

        <div class="payment-option">


            <label class="method">

                <input
                    type="radio"
                    name="payment_type"
                    value="Card"
                    checked
                >

                <div class="method-text">

                    <strong>
                        Credit / Debit Card
                    </strong>

                    <span>
                        Pay securely using your card
                    </span>

                </div>

            </label>


            <div
                id="cardForm"
                class="form-grid"
            >


                <div class="form-group">

                    <label>
                        Card Holder Name
                    </label>

                    <input
                        type="text"
                        name="card_holder_name"
                        value="<?= htmlspecialchars($full_name) ?>"
                        placeholder="Your name"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Card Type
                    </label>

                    <select name="card_type">

                        <option value="">
                            Select card type
                        </option>

                        <option value="Visa">
                            Visa
                        </option>

                        <option value="MasterCard">
                            MasterCard
                        </option>

                        <option value="JCB">
                            JCB
                        </option>

                    </select>

                </div>


                <div class="form-group full">

                    <label>
                        Card Number
                    </label>

                    <input
                        type="text"
                        name="card_number"
                        placeholder="1234 5678 9012 3456"
                        maxlength="19"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Expiry Date
                    </label>

                    <input
                        type="text"
                        name="expired_date"
                        placeholder="MM / YY"
                        maxlength="7"
                    >

                </div>


                <div class="form-group">

                    <label>
                        CVV
                    </label>

                    <input
                        type="password"
                        name="cvv"
                        placeholder="***"
                        maxlength="4"
                    >

                </div>


            </div>

        </div>


        <!-- =====================
             CASH
        ====================== -->

        <div class="payment-option">


            <label class="method">

                <input
                    type="radio"
                    name="payment_type"
                    value="Cash"
                >

                <div class="method-text">

                    <strong>
                        Cash
                    </strong>

                    <span>
                        Pay with cash
                    </span>

                </div>

            </label>


            <div
                id="cashBox"
                class="cash-box"
            >

                <strong>
                    Cash Payment
                </strong>


                <p>
                    Your booking will be reserved as pending.
                    Please complete the payment at the counter.
                </p>


                <div class="cash-amount">

                    <span>
                        Amount to pay
                    </span>

                    <strong>
                        <?= rupiah($total) ?>
                    </strong>

                </div>


                <input
                    type="hidden"
                    name="amount_received"
                    value="0"
                >

            </div>


        </div>


        <!-- BUTTON -->

        <button
            type="submit"
            class="button"
            id="payButton"
        >
            Pay <?= rupiah($total) ?> →
        </button>


    </form>


</main>



<!-- =========================
     RIGHT SUMMARY
========================= -->

<aside class="summary">


    <h2>
        Booking Summary
    </h2>


    <div class="summary-route">


        <div>

            <small>
                FROM
            </small>

            <strong>

                <?= htmlspecialchars(
                    $pergi['origin'] ?? '-'
                ) ?>

            </strong>

        </div>


        <b>
            →
        </b>


        <div>

            <small>
                TO
            </small>

            <strong>

                <?= htmlspecialchars(
                    $pergi['destination'] ?? '-'
                ) ?>

            </strong>

        </div>


    </div>


    <div class="summary-row">

        <span>
            Departure
        </span>

        <strong>

            <?= tanggal(
                $pergi['travel_date'] ?? null
            ) ?>

            ·

            <?= waktu(
                $pergi['departure_date'] ?? null
            ) ?>

        </strong>

    </div>


    <?php if ($pulang): ?>

    <div class="summary-row">

        <span>
            Return
        </span>

        <strong>

            <?= tanggal(
                $pulang['travel_date'] ?? null
            ) ?>

            ·

            <?= waktu(
                $pulang['departure_date'] ?? null
            ) ?>

        </strong>

    </div>

    <?php endif; ?>


    <div class="summary-row">

        <span>
            Passenger
        </span>

        <strong>
            1 Person
        </strong>

    </div>


    <hr>


    <div class="summary-row">

        <span>
            Trip Price
        </span>

        <strong>
            <?= rupiah($total) ?>
        </strong>

    </div>


    <div class="summary-row">

        <span>
            Service Fee
        </span>

        <strong>
            Rp 0
        </strong>

    </div>


    <div class="total">

        <span>
            Total
        </span>

        <strong>
            <?= rupiah($total) ?>
        </strong>

    </div>


</aside>


</div>



<script>

/* =========================
   ELEMENTS
========================= */

const radios =
    document.querySelectorAll(
        'input[name="payment_type"]'
    );

const cardForm =
    document.getElementById("cardForm");

const cashBox =
    document.getElementById("cashBox");

const payButton =
    document.getElementById("payButton");

const cardHolder =
    document.querySelector(
        'input[name="card_holder_name"]'
    );

const cardType =
    document.querySelector(
        'select[name="card_type"]'
    );

const cardNumber =
    document.querySelector(
        'input[name="card_number"]'
    );

const expiredDate =
    document.querySelector(
        'input[name="expired_date"]'
    );

const cvv =
    document.querySelector(
        'input[name="cvv"]'
    );


/* =========================
   PAYMENT METHOD
========================= */

function updatePaymentMethod() {

    const selected =
        document.querySelector(
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

        payButton.innerHTML =
            "Pay <?= rupiah($total) ?> →";

    } else {

        cardForm.style.display = "none";

        cashBox.style.display = "block";

        cardHolder.required = false;
        cardType.required = false;
        cardNumber.required = false;
        expiredDate.required = false;
        cvv.required = false;

        payButton.innerHTML =
            "Continue →";
    }

}


/* =========================
   RADIO
========================= */

radios.forEach(function(radio) {

    radio.addEventListener(
        "change",
        updatePaymentMethod
    );

});


/* =========================
   CARD NUMBER
========================= */

cardNumber.addEventListener(
    "input",
    function() {

        let value =
            this.value
                .replace(/\D/g, "")
                .substring(0, 16);

        const groups =
            value.match(/.{1,4}/g);

        this.value =
            groups
                ? groups.join(" ")
                : "";

    }
);


/* =========================
   EXPIRY DATE
========================= */

expiredDate.addEventListener(
    "input",
    function() {

        let value =
            this.value
                .replace(/\D/g, "")
                .substring(0, 4);

        if (value.length >= 3) {

            value =
                value.substring(0, 2)
                + " / "
                + value.substring(2);

        }

        this.value = value;

    }
);


/* =========================
   CVV
========================= */

cvv.addEventListener(
    "input",
    function() {

        this.value =
            this.value
                .replace(/\D/g, "")
                .substring(0, 4);

    }
);


/* =========================
   START
========================= */

updatePaymentMethod();

</script>


</body>

</html>