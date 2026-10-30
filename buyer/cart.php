
<?php
session_start();
require_once "../db.php";

// Initialise cart.
if (!isset($_SESSION["cart"]) || !is_array($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$message = "";
$message_type = "success";

// Handle cart actions.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $product_id = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);

    if (!$product_id || $product_id < 1) {
        $message = "Invalid product selected.";
        $message_type = "danger";

    } elseif ($action === "remove") {
        unset($_SESSION["cart"][$product_id]);
        $message = "Product removed from your cart.";

    } elseif ($action === "add" || $action === "update") {
        $quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

        if (!$quantity || $quantity < 1) {
            $message = "Please enter a valid whole-number quantity.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare(
                "SELECT quantity_available
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

            if (!$product || (float)$product["quantity_available"] <= 0) {
                unset($_SESSION["cart"][$product_id]);
                $message = "This product is unavailable or not approved.";
                $message_type = "danger";
            } else {
                $stock = (int)$product["quantity_available"];
                $current_quantity = (int)($_SESSION["cart"][$product_id] ?? 0);

                $new_quantity = ($action === "add")
                    ? $current_quantity + $quantity
                    : $quantity;

                if ($new_quantity > $stock) {
                    $message = "Requested quantity exceeds available stock.";
                    $message_type = "danger";
                } else {
                    $_SESSION["cart"][$product_id] = $new_quantity;

                    $message = ($action === "add")
                        ? "Product added to your cart!"
                        : "Cart quantity updated successfully.";
                }
            }
        }

    } else {
        $message = "Invalid cart action.";
        $message_type = "danger";
    }

    header(
        "Location: cart.php?message=" . urlencode($message)
        . "&type=" . urlencode($message_type)
    );
    exit;
}

// Display messages after redirects.
if (isset($_GET["message"])) {
    $message = (string)$_GET["message"];
    $message_type = (($_GET["type"] ?? "") === "danger")
        ? "danger"
        : "success";
}

// Remove invalid cart entries.
foreach ($_SESSION["cart"] as $id => $quantity) {
    if ((int)$id < 1 || (int)$quantity < 1) {
        unset($_SESSION["cart"][$id]);
    }
}

// Load approved, available cart products.
$cart_products = [];
$total = 0;

if (!empty($_SESSION["cart"])) {
    $cart_items = [];

    foreach ($_SESSION["cart"] as $id => $quantity) {
        $cart_items[(int)$id] = (int)$quantity;
    }

    $ids = array_keys($cart_items);
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $types = str_repeat("i", count($ids));

    $sql = "SELECT product_id, product_name, price, unit,
                   quantity_available, image, status, approval_status
            FROM products
            WHERE product_id IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($product = $result->fetch_assoc()) {
        $id = (int)$product["product_id"];

        if (
            $product["status"] !== "available" ||
            $product["approval_status"] !== "approved" ||
            (float)$product["quantity_available"] <= 0
        ) {
            unset($_SESSION["cart"][$id]);
            continue;
        }

        $stock = (int)$product["quantity_available"];
        $quantity = (int)($_SESSION["cart"][$id] ?? 0);

        if ($quantity < 1) {
            unset($_SESSION["cart"][$id]);
            continue;
        }

        if ($quantity > $stock) {
            $quantity = $stock;
            $_SESSION["cart"][$id] = $quantity;
        }

        $product["cart_quantity"] = $quantity;
        $product["subtotal"] = (float)$product["price"] * $quantity;

        $total += $product["subtotal"];
        $cart_products[] = $product;
    }

    $stmt->close();
}

// Count distinct products for the navbar badge.
$cart_item_count = count($cart_products);

function cartEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        :root {
            --dark-green: #075B3D;
            --main-green: #13804F;
            --deep-green: #06452F;
            --yellow: #F5CF32;
            --background: #F7FAF4;
            --border: #E2E9E1;
        }
        .marquee-bar {
    background: #FFF9C4;
    color: #06452F;
    overflow: hidden;
    white-space: nowrap;
    padding: 10px 0;
    font-size: 14px;
    font-weight: 700;
}

.marquee-content {
    display: flex;
    width: max-content;
    animation: marqueeScroll 25s linear infinite;
}

.marquee-content span {
    padding-right: 60px;
}

@keyframes marqueeScroll {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}
        body {
            background: var(--background);
            color: #25372D;
            font-family: Arial, Helvetica, sans-serif;
        }

        .navbar {
            background: var(--dark-green);
            padding: 15px 0;
        }

        .navbar-brand {
            color: white !important;
            font-size: 23px;
            font-weight: 800;
        }

        .navbar-brand span {
            color: var(--yellow);
        }

        .nav-link {
            color: white !important;
            font-weight: 600;
        }

        .nav-link:hover {
            color: var(--yellow) !important;
        }
        .cart-nav-link {
    display: inline-flex !important;
    align-items: center;
    gap: 8px;
    background-color: #F5CF32;
    color: #06452F !important;
    padding: 8px 14px !important;
    border-radius: 8px;
    font-weight: 800;
    transition: background-color 0.3s ease;
}

.cart-nav-link:hover {
    background-color: #E8BD20;
    color: #06452F !important;
}

.cart-nav-link .cart-count-badge {
    background-color: #FFFFFF;
    color: #06452F;
}

        .cart-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 23px;
            height: 23px;
            padding: 0 6px;
            border-radius: 50px;
            background-color: var(--yellow);
            color: var(--deep-green);
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
        }

        .page-heading {
            color: var(--deep-green);
            font-weight: 800;
        }

        .cart-panel,
        .summary-panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 6px 20px rgba(7, 91, 61, .05);
        }

        .product-name {
            color: var(--deep-green);
            font-weight: 800;
        }

        .product-price {
            color: var(--main-green);
            font-weight: 800;
        }

        .quantity-input {
            max-width: 90px;
        }

        .btn-green {
            background: var(--dark-green);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 700;
        }

        .btn-green:hover {
            background: var(--main-green);
            color: white;
        }

        .btn-yellow {
            display: block;
            text-align: center;
            background: var(--yellow);
            color: var(--deep-green);
            border-radius: 8px;
            padding: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .btn-yellow:hover {
            background: #E8BD20;
            color: var(--deep-green);
        }

        .empty-cart {
            text-align: center;
            padding: 55px 20px;
        }

        .empty-cart i {
            font-size: 55px;
            color: var(--main-green);
        }

        .table th {
            color: var(--deep-green);
            white-space: nowrap;
        }

        footer {
            background: var(--deep-green);
            color: white;
            padding: 22px 0;
            margin-top: 60px;
        }

        @media (max-width: 767px) {
            .cart-panel,
            .summary-panel {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- WELCOME MARQUEE -->
<div class="marquee-bar">
    <div class="marquee-content">
        <span>🌾 Welcome to AgriConnect — Connecting Farmers to Markets!</span>
        <span>🌱 Support local farmers and enjoy fresh produce!</span>
        <span>🚜 Growing Together, Connecting Markets!</span>
    </div>
</div>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="bi bi-leaf-fill me-2"></i>Agri<span>Connect</span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navigation"
                aria-controls="navigation"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navigation">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="../produce-catalogue.php">
                        Continue Shopping
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active cart-nav-link"
                       aria-current="page"
                       href="cart.php">
                        <span><i class="bi bi-cart3 me-1"></i>My Cart</span>

                        <?php if ($cart_item_count > 0): ?>
                            <span class="cart-count-badge">
                                <?php echo $cart_item_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="mb-4">
        <h1 class="page-heading">
            <i class="bi bi-cart3 me-2"></i>My Shopping Cart
        </h1>
        <p class="text-secondary">
            Review your selected produce before checkout.
        </p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?php echo cartEscape($message_type); ?>"
             role="alert">
            <?php echo cartEscape($message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_products)): ?>
        <div class="cart-panel empty-cart">
            <i class="bi bi-basket"></i>
            <h3 class="page-heading mt-3">Your Cart Is Empty</h3>
            <p class="text-secondary">
                Explore the catalogue and add produce you would like to buy.
            </p>
            <a href="../produce-catalogue.php" class="btn btn-green mt-2">
                <i class="bi bi-search me-1"></i>Browse Produce
            </a>
        </div>

    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="cart-panel">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Produce</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cart_products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-name">
                                            <?php echo cartEscape($product["product_name"]); ?>
                                        </div>
                                        <small class="text-secondary">
                                            Per <?php echo cartEscape($product["unit"]); ?>
                                        </small>
                                    </td>

                                    <td>
                                        KSh <?php echo number_format((float)$product["price"], 2); ?>
                                    </td>

                                    <td>
                                        <form method="POST" action="cart.php"
                                              class="d-flex gap-2 align-items-center">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id"
                                                   value="<?php echo (int)$product["product_id"]; ?>">

                                            <input type="number" name="quantity"
                                                   class="form-control quantity-input"
                                                   min="1"
                                                   max="<?php echo (int)$product["quantity_available"]; ?>"
                                                   value="<?php echo (int)$product["cart_quantity"]; ?>"
                                                   required>

                                            <button type="submit"
                                                    class="btn btn-outline-success btn-sm"
                                                    title="Update quantity"
                                                    aria-label="Update quantity">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <small class="text-secondary">
                                            Stock: <?php echo (int)$product["quantity_available"]; ?>
                                        </small>
                                    </td>

                                    <td class="product-price">
                                        KSh <?php echo number_format($product["subtotal"], 2); ?>
                                    </td>

                                    <td>
                                        <form method="POST" action="cart.php"
                                              onsubmit="return confirm('Remove this product from your cart?');">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id"
                                                   value="<?php echo (int)$product["product_id"]; ?>">

                                            <button type="submit"
                                                    class="btn btn-outline-danger btn-sm"
                                                    aria-label="Remove product">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <a href="../produce-catalogue.php"
                       class="btn btn-outline-success mt-2">
                        <i class="bi bi-arrow-left me-1"></i>Continue Shopping
                    </a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="summary-panel">
                    <h4 class="page-heading mb-4">Order Summary</h4>

                    <div class="d-flex justify-content-between mb-3">
                        <span>Items</span>
                        <strong><?php echo count($cart_products); ?></strong>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span>Subtotal</span>
                        <strong>KSh <?php echo number_format($total, 2); ?></strong>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-4">
                        <strong>Total</strong>
                        <strong class="product-price">
                            KSh <?php echo number_format($total, 2); ?>
                        </strong>
                    </div>

                    <p class="small text-secondary">
                        Delivery charges, if applicable, will be confirmed
                        during checkout.
                    </p>

                    <a href="checkout.php" class="btn-yellow w-100">
                        Proceed to Checkout
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<footer>
    <div class="container text-center">
        <p class="mb-0">
            &copy; <?php echo date("Y"); ?> AgriConnect.
            Connecting farmers and buyers.
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
