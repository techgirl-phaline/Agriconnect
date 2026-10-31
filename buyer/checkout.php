<?php
session_start();

require_once "../db.php";
require_once "../payment-config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Check buyer login. */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "buyer") {
    http_response_code(403);
    exit("Access denied. Please log in using a buyer account.");
}

$buyer_id = (int) $_SESSION["user_id"];
$errors = [];

$full_name = "";
$phone = "";
$delivery_location = "";
$notes = "";
$fulfillment_method = "delivery";
$pickup_point_id = 0;
$selected_point = null;

/* CSRF protection. */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

/* Ensure the cart contains products. */
if (empty($_SESSION["cart"]) || !is_array($_SESSION["cart"])) {
    header("Location: cart.php");
    exit;
}

/* Load and verify buyer details. */
$stmt = $conn->prepare(
    "SELECT full_name, phone, account_status
     FROM users
     WHERE user_id = ? AND role = 'buyer'
     LIMIT 1"
);
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$buyer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$buyer || $buyer["account_status"] !== "approved") {
    http_response_code(403);
    exit("Your buyer account must be approved before placing an order.");
}

$full_name = $buyer["full_name"] ?? "";
$phone = $buyer["phone"] ?? "";

/* Load active pickup offices. */
$pickup_points = [];

$stmt = $conn->prepare(
    "SELECT pickup_point_id, point_name, address, phone, description
     FROM pickup_points
     WHERE status = 'active'
     ORDER BY point_name ASC"
);
$stmt->execute();
$pickup_points = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Load products in the cart. */
$cart_items = [];
$total_amount = 0;

try {
    foreach ($_SESSION["cart"] as $id => $qty) {
        $product_id = filter_var($id, FILTER_VALIDATE_INT);
        $quantity = filter_var($qty, FILTER_VALIDATE_INT);

        if (!$product_id || !$quantity || $quantity < 1) {
            throw new Exception(
                "Your cart contains an invalid item. Please review your cart."
            );
        }

        $stmt = $conn->prepare(
            "SELECT product_id, product_name, price, unit,
                    quantity_available, image
             FROM products
             WHERE product_id = ?
               AND status = 'available'
               AND approval_status = 'approved'
             LIMIT 1"
        );
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception(
                "A product in your cart is no longer available. Please return to your cart."
            );
        }

        if ($quantity > (float) $product["quantity_available"]) {
            throw new Exception(
                $product["product_name"] . " has only " .
                $product["quantity_available"] . " " .
                $product["unit"] . " available. Please update your cart."
            );
        }

        $product["cart_quantity"] = $quantity;
        $product["subtotal"] = round(
            (float) $product["price"] * $quantity,
            2
        );

        $total_amount += $product["subtotal"];
        $cart_items[] = $product;
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$total_amount = round($total_amount, 2);

/* Process checkout. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($errors)) {

    $posted_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($posted_token) ||
        !hash_equals($_SESSION["csrf_token"], $posted_token)
    ) {
        $errors[] = "Your session token is invalid. Please refresh the page and try again.";
    }

    $posted_name = trim($_POST["full_name"] ?? "");
    $posted_phone = trim($_POST["phone"] ?? "");
    $delivery_location = trim($_POST["delivery_location"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    $fulfillment_method = $_POST["fulfillment_method"] ?? "delivery";

    $pickup_point_id = filter_var(
        $_POST["pickup_point_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if ($pickup_point_id === false || $pickup_point_id === null) {
        $pickup_point_id = 0;
    }

    /* Validate buyer details. */
    if ($posted_name === "" || strlen($posted_name) > 100) {
        $errors[] = "Please enter your full name (maximum 100 characters).";
    }

    if (!preg_match('/^[0-9+\s()-]{9,16}$/', $posted_phone)) {
        $errors[] = "Please enter a valid phone number.";
    }

    /* Validate delivery or pickup. */
    if (!in_array($fulfillment_method, ["delivery", "pickup"], true)) {
        $errors[] = "Please select a valid fulfilment method.";
    }

    if ($fulfillment_method === "delivery") {

        $pickup_point_id = 0;

        if ($delivery_location === "") {
            $errors[] = "Please enter your delivery location.";
        }

        if (strlen($delivery_location) > 255) {
            $errors[] = "Delivery location must not exceed 255 characters.";
        }

    } elseif ($fulfillment_method === "pickup") {

        if ($pickup_point_id < 1) {
            $errors[] = "Please select a pickup booking office.";
        } else {
            $stmt = $conn->prepare(
                "SELECT pickup_point_id, point_name, address, phone, description
                 FROM pickup_points
                 WHERE pickup_point_id = ?
                   AND status = 'active'
                 LIMIT 1"
            );

            $stmt->bind_param("i", $pickup_point_id);
            $stmt->execute();
            $selected_point = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$selected_point) {
                $errors[] = "The selected pickup office is unavailable. Please choose another.";
            } else {
                $delivery_location = $selected_point["address"];
            }
        }
    }

    if (strlen($notes) > 1000) {
        $errors[] = "Additional notes must not exceed 1000 characters.";
    }

    /* Place order if validation passes. */
    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $locked_items = [];
            $locked_total = 0;

            /* Recheck stock before creating the order. */
            foreach ($_SESSION["cart"] as $id => $qty) {
                $product_id = filter_var($id, FILTER_VALIDATE_INT);
                $quantity = filter_var($qty, FILTER_VALIDATE_INT);

                if (!$product_id || !$quantity || $quantity < 1) {
                    throw new Exception("Your cart contains an invalid item.");
                }

                $stmt = $conn->prepare(
                    "SELECT product_id, product_name, price, unit,
                            quantity_available
                     FROM products
                     WHERE product_id = ?
                       AND status = 'available'
                       AND approval_status = 'approved'
                     FOR UPDATE"
                );

                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$product) {
                    throw new Exception(
                        "A product is no longer available. Please review your cart."
                    );
                }

                if ($quantity > (float) $product["quantity_available"]) {
                    throw new Exception(
                        $product["product_name"] .
                        " no longer has enough stock. Please update your cart."
                    );
                }

                $price = (float) $product["price"];
                $subtotal = round($price * $quantity, 2);
                $locked_total += $subtotal;

                $locked_items[] = [
                    "product_id" => (int) $product["product_id"],
                    "quantity" => $quantity,
                    "price" => $price,
                    "subtotal" => $subtotal
                ];
            }

            $locked_total = round($locked_total, 2);

            if (empty($locked_items) || $locked_total <= 0) {
                throw new Exception("Your cart is empty or its total is invalid.");
            }

            /* Set order and payment details. */
            $status = "placed";
            $payment_method = "mpesa_manual";
            $payment_status = "unpaid";

            /*
             * Store the selected pickup office ID for pickup orders.
             * Delivery orders use NULL for pickup_point_id.
             */
            $selected_pickup_id = null;

            if ($fulfillment_method === "pickup") {
                $selected_pickup_id = (int) $pickup_point_id;
            }

            /* Insert the order for BOTH delivery and pickup. */
            $stmt = $conn->prepare(
                "INSERT INTO orders
                    (
                        buyer_id,
                        order_date,
                        total_amount,
                        delivery_location,
                        status,
                        fulfillment_method,
                        pickup_point_id,
                        payment_method,
                        payment_status
                    )
                 VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "idsssiss",
                $buyer_id,
                $locked_total,
                $delivery_location,
                $status,
                $fulfillment_method,
                $selected_pickup_id,
                $payment_method,
                $payment_status
            );

            $stmt->execute();
            $order_id = (int) $conn->insert_id;
            $stmt->close();

            /* Save order items and reduce stock. */
            $item_stmt = $conn->prepare(
                "INSERT INTO order_items
                    (order_id, product_id, quantity, unit_price, subtotal)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stock_stmt = $conn->prepare(
                "UPDATE products
                 SET quantity_available = quantity_available - ?,
                     updated_at = NOW()
                 WHERE product_id = ?
                   AND quantity_available >= ?
                   AND status = 'available'
                   AND approval_status = 'approved'"
            );

            foreach ($locked_items as $item) {
                $product_id = $item["product_id"];
                $quantity = $item["quantity"];
                $price = $item["price"];
                $subtotal = $item["subtotal"];

                $item_stmt->bind_param(
                    "iiidd",
                    $order_id,
                    $product_id,
                    $quantity,
                    $price,
                    $subtotal
                );
                $item_stmt->execute();

                $stock_stmt->bind_param(
                    "iii",
                    $quantity,
                    $product_id,
                    $quantity
                );
                $stock_stmt->execute();

                if ($stock_stmt->affected_rows !== 1) {
                    throw new Exception(
                        "Stock changed while placing your order. Please try again."
                    );
                }
            }

            $item_stmt->close();
            $stock_stmt->close();

            /* Create admin notification for the new order. */
$notification_type = "order";
$title = "New Order Received";

$message = "A buyer has placed Order #" . $order_id .
           " worth KSh " . number_format($locked_total, 2) . ".";

$action_url = "orders.php";

$notification_stmt = $conn->prepare(
    "INSERT IGNORE INTO admin_notifications
        (notification_type, reference_id, title, message, action_url)
     VALUES (?, ?, ?, ?, ?)"
);

$notification_stmt->bind_param(
    "sisss",
    $notification_type,
    $order_id,
    $title,
    $message,
    $action_url
);

$notification_stmt->execute();
$notification_stmt->close();

/* Save all database changes. */
$conn->commit();

            $_SESSION["cart"] = [];
            $_SESSION["last_order_id"] = $order_id;

            header("Location: order-success.php");
            exit;

        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $rollback_error) {
                // Preserve the original error.
            }

            error_log("AgriConnect checkout error: " . $e->getMessage());

            $errors[] = "We could not place your order. Please check your cart and try again.";
        }
    }

    $full_name = $posted_name;
    $phone = $posted_phone;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Checkout | AgriConnect</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f7faf4;
            color: #25372d;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #075b3d;
        }

        .brand {
            color: white;
            font-size: 1.4rem;
            font-weight: 800;
            text-decoration: none;
        }

        .brand span {
            color: #f5cf32;
        }

        .checkout-card {
            background: white;
            border: 1px solid #e2e9e1;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(7, 91, 61, .06);
        }

        .section-title {
            color: #075b3d;
            font-weight: 800;
        }

        .form-control,
        .form-select {
            padding: 11px;
            border-radius: 8px;
        }

        .btn-green {
            background: #13804f;
            color: white;
            border: 0;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
        }

        .btn-green:hover {
            background: #06452f;
            color: white;
        }

        .total {
            color: #075b3d;
            font-weight: 800;
            font-size: 1.2rem;
        }

        .payment-note {
            background: #fff9df;
            border: 1px solid #f1df91;
            border-radius: 10px;
            padding: 14px;
        }

        .pickup-details {
            background: #f0f8f2;
            border: 1px solid #d5e9d9;
            border-radius: 8px;
            padding: 12px;
        }
    </style>
</head>

<body>

<nav class="navbar py-3">
    <div class="container">
        <a class="brand" href="../index.php">
            Agri<span>Connect</span>
        </a>

        <a class="text-white text-decoration-none" href="cart.php">
            Back to Cart
        </a>
    </div>
</nav>

<main class="container py-5">

    <h1 class="section-title mb-2">Checkout</h1>

    <p class="text-secondary mb-4">
        Choose delivery or collect your produce from an AgriConnect booking office.
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="checkout.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>"
        >

        <div class="row g-4">

            <div class="col-lg-7">

                <section class="checkout-card">

                    <h2 class="section-title h4 mb-4">
                        Buyer Information
                    </h2>

                    <div class="mb-3">
                        <label for="full_name" class="form-label">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-control"
                            maxlength="100"
                            value="<?= htmlspecialchars($full_name, ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label">
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            class="form-control"
                            maxlength="16"
                            value="<?= htmlspecialchars($phone, ENT_QUOTES, "UTF-8") ?>"
                            placeholder="e.g. 0712345678"
                            required
                        >
                    </div>

                    <hr>

                    <h2 class="section-title h4 mb-3">
                        How Would You Like to Receive Your Order?
                    </h2>

                    <div class="mb-3">
                        <label for="fulfillment_method" class="form-label">
                            Fulfilment Method
                        </label>

                        <select
                            id="fulfillment_method"
                            name="fulfillment_method"
                            class="form-select"
                            required
                        >
                            <option
                                value="delivery"
                                <?= $fulfillment_method === "delivery" ? "selected" : "" ?>
                            >
                                Delivery to My Location
                            </option>

                            <option
                                value="pickup"
                                <?= $fulfillment_method === "pickup" ? "selected" : "" ?>
                            >
                                Pickup at a Booking Office
                            </option>
                        </select>
                    </div>

                    <div class="mb-3" id="delivery_section">

                        <label for="delivery_location" class="form-label">
                            Delivery Location
                        </label>

                        <textarea
                            id="delivery_location"
                            name="delivery_location"
                            class="form-control"
                            rows="3"
                            maxlength="255"
                            placeholder="Enter county, town, estate, and directions"
                        ><?= htmlspecialchars($delivery_location, ENT_QUOTES, "UTF-8") ?></textarea>

                    </div>

                    <div class="mb-3" id="pickup_section" hidden>

                        <label for="pickup_point_id" class="form-label">
                            Select Booking Office
                        </label>

                        <select
                            id="pickup_point_id"
                            name="pickup_point_id"
                            class="form-select"
                        >
                            <option value="">Choose a booking office</option>

                            <?php foreach ($pickup_points as $point): ?>
                                <option
                                    value="<?= (int) $point["pickup_point_id"] ?>"
                                    <?= (int) $pickup_point_id === (int) $point["pickup_point_id"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($point["point_name"], ENT_QUOTES, "UTF-8") ?>
                                    — <?= htmlspecialchars($point["address"], ENT_QUOTES, "UTF-8") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if (empty($pickup_points)): ?>
                            <small class="text-danger">
                                No active booking offices are available. Please contact the administrator.
                            </small>
                        <?php else: ?>
                            <small class="text-secondary">
                                Select the office where you want to collect your produce.
                            </small>
                        <?php endif; ?>

                        <div id="pickup_details" class="pickup-details mt-3" hidden>
                            <strong id="pickup_name"></strong>
                            <div id="pickup_address" class="small mt-1"></div>
                            <div id="pickup_phone" class="small mt-1"></div>
                            <div id="pickup_description" class="small mt-1"></div>
                        </div>

                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">
                            Additional Notes (Optional)
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            class="form-control"
                            rows="2"
                            maxlength="1000"
                            placeholder="Any special instructions"
                        ><?= htmlspecialchars($notes, ENT_QUOTES, "UTF-8") ?></textarea>

                        <small class="text-secondary">
                            Additional notes are not yet stored in the database.
                        </small>
                    </div>

                    <div class="payment-note mb-4">
                        <strong>Payment Method: Manual M-Pesa</strong>

                        <p class="mb-0 mt-1 small">
                            Your order will initially be unpaid. Follow the
                            payment instructions provided by AgriConnect.
                            Payment must be independently verified before
                            your order is marked as paid.
                        </p>
                    </div>

                    <button type="submit" class="btn-green w-100">
                        Place Order — KSh <?= number_format($total_amount, 2) ?>
                    </button>

                </section>

            </div>

            <div class="col-lg-5">

                <section class="checkout-card">

                    <h2 class="section-title h4 mb-4">
                        Order Summary
                    </h2>

                    <?php foreach ($cart_items as $item): ?>

                        <div class="d-flex justify-content-between gap-3 mb-3">

                            <div>
                                <strong>
                                    <?= htmlspecialchars($item["product_name"], ENT_QUOTES, "UTF-8") ?>
                                </strong>

                                <div class="text-secondary small">
                                    <?= (int) $item["cart_quantity"] ?>

                                    <?= htmlspecialchars($item["unit"], ENT_QUOTES, "UTF-8") ?>

                                    × KSh <?= number_format((float) $item["price"], 2) ?>
                                </div>
                            </div>

                            <strong>
                                KSh <?= number_format($item["subtotal"], 2) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                    <hr>

                    <div class="d-flex justify-content-between total">
                        <span>Total</span>
                        <span>KSh <?= number_format($total_amount, 2) ?></span>
                    </div>

                    <p class="small text-secondary mt-3 mb-0">
                        Placing an order does not confirm payment.
                        An administrator must verify the M-Pesa transaction
                        before the order can be fulfilled.
                    </p>

                </section>

            </div>

        </div>

    </form>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const methodSelect = document.getElementById("fulfillment_method");
    const deliverySection = document.getElementById("delivery_section");
    const pickupSection = document.getElementById("pickup_section");

    const deliveryInput = document.getElementById("delivery_location");
    const pickupSelect = document.getElementById("pickup_point_id");

    const pickupDetails = document.getElementById("pickup_details");
    const pickupName = document.getElementById("pickup_name");
    const pickupAddress = document.getElementById("pickup_address");
    const pickupPhone = document.getElementById("pickup_phone");
    const pickupDescription = document.getElementById("pickup_description");

    const pickupPoints = <?= json_encode(
        $pickup_points,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    function updateFulfillmentMethod() {

        const isPickup = methodSelect.value === "pickup";

        deliverySection.hidden = isPickup;
        pickupSection.hidden = !isPickup;

        deliveryInput.required = !isPickup;
        deliveryInput.disabled = isPickup;

        pickupSelect.required = isPickup;
        pickupSelect.disabled = !isPickup;

        if (isPickup) {
            deliveryInput.value = "";
        }

        updatePickupDetails();
    }

    function updatePickupDetails() {

        const selectedId = pickupSelect.value;

        const point = pickupPoints.find(function (item) {
            return String(item.pickup_point_id) === String(selectedId);
        });

        if (!point || methodSelect.value !== "pickup") {
            pickupDetails.hidden = true;
            return;
        }

        pickupName.textContent = point.point_name || "";
        pickupAddress.textContent = "Address: " + (point.address || "");
        pickupPhone.textContent = point.phone
            ? "Phone: " + point.phone
            : "Phone: Contact the administrator for details.";

        pickupDescription.textContent = point.description || "";

        pickupDetails.hidden = false;
    }

    methodSelect.addEventListener("change", updateFulfillmentMethod);
    pickupSelect.addEventListener("change", updatePickupDetails);

    updateFulfillmentMethod();

});
</script>

</body>
</html>