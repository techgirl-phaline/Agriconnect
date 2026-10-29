
<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only GET requests are allowed."
    ]);
    exit;
}

$sql = "SELECT
            product_id,
            product_name,
            description,
            price,
            unit,
            quantity_available,
            location,
            image
        FROM products
        WHERE status = 'available'
          AND approval_status = 'approved'
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve products."
    ]);
    exit;
}

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        "product_id" => (int) $row["product_id"],
        "product_name" => $row["product_name"],
        "description" => $row["description"],
        "price" => (float) $row["price"],
        "unit" => $row["unit"],
        "quantity_available" => (float) $row["quantity_available"],
        "location" => $row["location"],
        "image" => $row["image"]
    ];
}

echo json_encode([
    "success" => true,
    "count" => count($products),
    "products" => $products
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
