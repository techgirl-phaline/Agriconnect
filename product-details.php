
<?php
require_once "db.php";

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$product_id = (int) $_GET['id'];

$sql = "SELECT
            p.product_id,
            p.product_name,
            p.description,
            p.price,
            p.unit,
            p.quantity_available,
            p.image,
            p.location,
            p.status,
            p.approval_status,
            c.category_name,
            u.full_name
        FROM products p
        LEFT JOIN categories c
            ON p.category_id = c.category_id
        LEFT JOIN farmer_profiles fp
            ON p.farmer_id = fp.farmer_id
        LEFT JOIN users u
            ON fp.user_id = u.user_id
        WHERE p.product_id = ?
          AND p.approval_status = 'approved'
          AND p.status = 'available'
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error. Please check the query and database columns.");
}

$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    http_response_code(404);
    exit("Product not found or unavailable.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?= htmlspecialchars($product['product_name']) ?> | AgriConnect
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f5f8f2;
            font-family: Arial, sans-serif;
            color: #263528;
        }

        .details-card {
            max-width: 950px;
            margin: 45px auto;
            padding: 28px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 5px 22px rgba(0, 0, 0, .08);
        }

        .product-image {
            width: 100%;
            height: 340px;
            object-fit: cover;
            border-radius: 14px;
        }

        .price {
            color: #278044;
            font-size: 28px;
            font-weight: bold;
        }

        .detail-label {
            font-weight: bold;
        }
    </style>
</head>

<body>
<div class="container">
    <div class="details-card">

        <a href="index.php"
           class="btn btn-outline-success mb-4">
            &larr; Back to Home
        </a>

        <div class="row g-4">
            <div class="col-md-6">
                <?php if (!empty($product['image'])): ?>
                    <img
                        src="uploads/products/<?= rawurlencode(basename($product['image'])) ?>"
                        alt="<?= htmlspecialchars($product['product_name']) ?>"
                        class="product-image">
                <?php else: ?>
                    <div class="bg-light rounded p-5 text-center">
                        No product image available.
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <h2>
                    <?= htmlspecialchars($product['product_name']) ?>
                </h2>

                <p class="price">
                    KSh <?= number_format((float)$product['price'], 2) ?>
                    / <?= htmlspecialchars($product['unit']) ?>
                </p>

                <p>
                    <span class="detail-label">Category:</span>
                    <?= htmlspecialchars($product['category_name'] ?? 'Not specified') ?>
                </p>

                <p>
                    <span class="detail-label">Farmer:</span>
                    <?= htmlspecialchars($product['full_name'] ?? 'Not specified') ?>
                </p>

                <p>
                    <span class="detail-label">Available quantity:</span>
                    <?= number_format((float)$product['quantity_available'], 2) ?>
                    <?= htmlspecialchars($product['unit']) ?>
                </p>

                <p>
                    <span class="detail-label">Location:</span>
                    <?= htmlspecialchars($product['location'] ?? 'Not specified') ?>
                </p>

                <p>
                    <span class="detail-label">Description:</span><br>
                    <?= nl2br(htmlspecialchars($product['description'] ?? 'No description available.')) ?>
                </p>

                <a href="produce-catalogue.php"
                   class="btn btn-success">
                    Browse Produce Catalogue
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
