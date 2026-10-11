<?php

require_once "koneksi.php";


/*
|--------------------------------------------------------------------------
| BOOKING ID
|--------------------------------------------------------------------------
| Untuk demo kita menggunakan Booking #2.
| Booking #2 di database adalah RoundTrip.
*/

$booking_id = 2;


/*
|--------------------------------------------------------------------------
| BOOKING + USER
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        b.booking_id,
        b.user_id,
        b.booking_date,
        b.trip_type,
        b.status,

        u.name AS user_name,
        u.email,
        u.phone

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


/*
|--------------------------------------------------------------------------
| TRIP PERGI
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| TRIP PULANG
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| PASSENGER
|--------------------------------------------------------------------------
*/

$passenger = null;

if ($pergi) {

    $sql = "
        SELECT
            pp.passpergi_id,
            pp.seat_number,

            p.passenger_id,
            p.name,
            p.birth_date,
            p.ID,
            p.category

        FROM passengerpergi pp

        LEFT JOIN passengers p
            ON pp.passenger_id = p.passenger_id

        WHERE pp.trippergi_id = ?

        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "i",
        $pergi['trippergi_id']
    );

    $stmt->execute();

    $passenger =
        $stmt->get_result()->fetch_assoc();
}


/*
|--------------------------------------------------------------------------
| TRANSACTION
|--------------------------------------------------------------------------
*/

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

$transaction =
    $stmt->get_result()->fetch_assoc();


$total =
    (float)($transaction['total_amount'] ?? 0);

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Passenger Details
    </title>


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
            background: #1688f7;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }


        .circle.done {
            background: #20a978;
        }


        .line {
            width: 60px;
            height: 2px;
            background: #dfe5ed;
        }


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
            box-shadow: 0 8px 30px rgba(30, 60, 90, 0.06);
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
            margin-bottom: 25px;
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
            font-size: 17px;
            font-weight: bold;
        }


        .trip-info {
            color: #78859a;
            font-size: 12px;
            margin-top: 8px;
        }


        .passenger-title {
            margin-top: 28px;
            margin-bottom: 15px;
            font-size: 20px;
        }


        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 17px;
        }


        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }


        .form-group label {
            font-size: 13px;
            font-weight: bold;
        }


        input,
        select {
            height: 45px;
            border: 1px solid #d6deea;
            border-radius: 9px;
            padding: 0 13px;
            font-size: 14px;
            outline: none;
        }


        input:focus,
        select:focus {
            border-color: #1688f7;
        }


        .button {
            margin-top: 25px;
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 10px;
            background: #1688f7;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
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
            margin-bottom: 20px;
        }


        .summary-route small {
            color: #8b95a7;
            font-size: 9px;
        }


        .summary-route strong {
            display: block;
            margin-top: 6px;
            font-size: 12px;
        }


        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
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
            border-radius: 12px;
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }


        .total strong {
            color: #1688f7;
            font-size: 21px;
        }


        .database-note {
            margin-top: 18px;
            padding: 12px;
            background: #f7f9fc;
            border-radius: 9px;
            font-size: 11px;
            color: #7d8797;
        }

    </style>

</head>


<body>


<!-- STEPPER -->

<div class="stepper">

    <div class="step active">

        <div class="circle">
            1
        </div>

        Passenger

    </div>


    <div class="line"></div>


    <div class="step">

        <div class="circle">
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


<div class="container">


    <!-- LEFT -->

    <main class="card">

        <div class="label">
            PASSENGER INFORMATION
        </div>


        <h1>
            Passenger Details
        </h1>


        <p class="description">
            Review your passenger information before continuing to payment.
        </p>


        <!-- DEPARTURE -->

        <div class="trip-box">

            <div class="trip-label">
                DEPARTURE
            </div>

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


        <!-- RETURN -->

        <?php if ($pulang): ?>

        <div class="trip-box">

            <div class="trip-label">
                RETURN
            </div>

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


        <h2 class="passenger-title">
            Passenger 1
        </h2>


        <form
            action="payment.php"
            method="POST"
        >

            <input
                type="hidden"
                name="booking_id"
                value="<?= $booking_id ?>"
            >


            <input
                type="hidden"
                name="passenger_id"
                value="<?= $passenger['passenger_id'] ?? '' ?>"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="full_name"
                        value="<?= htmlspecialchars($passenger['name'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        ID Number
                    </label>

                    <input
                        type="text"
                        name="id_number"
                        value="<?= htmlspecialchars($passenger['ID'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Birth Date
                    </label>

                    <input
                        type="date"
                        name="birth_date"
                        value="<?= htmlspecialchars($passenger['birth_date'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Seat Number
                    </label>

                    <input
                        type="text"
                        name="seat_number"
                        value="<?= htmlspecialchars($passenger['seat_number'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($booking['email'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Phone Number
                    </label>

                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars($booking['phone'] ?? '') ?>"
                        required
                    >

                </div>


            </div>


            <button
                type="submit"
                class="button"
            >
                Continue to Payment →
            </button>

        </form>


    </main>


    <!-- RIGHT -->

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
                    <?= htmlspecialchars($pergi['origin'] ?? '-') ?>
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
                    <?= htmlspecialchars($pergi['destination'] ?? '-') ?>
                </strong>

            </div>

        </div>


        <div class="summary-row">

            <span>
                Departure
            </span>

            <strong>

                <?= tanggal($pergi['travel_date'] ?? null) ?>

                ·

                <?= waktu($pergi['departure_date'] ?? null) ?>

            </strong>

        </div>


        <?php if ($pulang): ?>

        <div class="summary-row">

            <span>
                Return
            </span>

            <strong>

                <?= tanggal($pulang['travel_date'] ?? null) ?>

                ·

                <?= waktu($pulang['departure_date'] ?? null) ?>

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


        <div class="total">

            <span>
                Total Price
            </span>

            <strong>
                <?= rupiah($total) ?>
            </strong>

        </div>

    </aside>

</div>


</body>

</html>