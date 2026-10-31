
<?php
session_start();

require_once __DIR__ . '/../db.php';

// Protect the page: farmers only
if (
    !isset($_SESSION['farmer_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'farmer'
) {
    header("Location: login.php");
    exit;
}

// Get the logged-in user's ID
$user_id = (int) ($_SESSION['user_id'] ?? 0);

$farmer_name = $_SESSION['farmer_name'] ?? '';

// Check that the user is logged in
if ($user_id <= 0) {
    header("Location: login.php");
    exit;
}

// Get the correct farmer ID from the farmer profile
$stmt = $conn->prepare(
    "SELECT farmer_id, farm_name
     FROM farmer_profiles
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$farmer_profile = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$farmer_profile) {
    die("Farmer profile not found. Please contact the administrator.");
}

$farmer_id = (int) $farmer_profile['farmer_id'];

if (empty($farmer_name)) {
    $farmer_name = $farmer_profile['farm_name'];
}
$message = '';
$message_type = 'success';

// Create a CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// Image upload directory
$upload_dir = __DIR__ . '/../uploads/products/';

if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        $message = 'Unable to create the image upload folder.';
        $message_type = 'danger';
    }
}

// Escape output
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Delete a product image only if its path is inside our upload folder
function deleteProductImage($image) {
    $base = realpath(__DIR__ . '/../uploads/products/');
    if (!$base || !$image) {
        return;
    }

    $filename = basename($image);
    $path = realpath($base . DIRECTORY_SEPARATOR . $filename);

    if (
        $path &&
        strpos($path, $base . DIRECTORY_SEPARATOR) === 0 &&
        is_file($path)
    ) {
        unlink($path);
    }
}

// Handle add, edit and delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $message = 'Your session token is invalid. Refresh the page and try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            // DELETE PRODUCT
            if ($action === 'delete') {
                $product_id = filter_input(
                    INPUT_POST,
                    'product_id',
                    FILTER_VALIDATE_INT
                );

                if (!$product_id) {
                    throw new Exception('Invalid product selected.');
                }

                $stmt = $conn->prepare(
                    "SELECT image FROM products
                     WHERE product_id = ? AND farmer_id = ?"
                );
                $stmt->bind_param("ii", $product_id, $farmer_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$product) {
                    throw new Exception('Product not found or you do not own it.');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM products
                     WHERE product_id = ? AND farmer_id = ?"
                );
                $stmt->bind_param("ii", $product_id, $farmer_id);
                $stmt->execute();
                $stmt->close();

                deleteProductImage($product['image']);

                $message = 'Produce listing deleted successfully.';
            }

           
            // ADD OR UPDATE PRODUCT
            elseif ($action === 'save') {
                $product_id = (int) ($_POST['product_id'] ?? 0);
                $product_name = trim($_POST['product_name'] ?? '');
                $category_id = filter_var(
                    $_POST['category_id'] ?? '',
                    FILTER_VALIDATE_INT
                );
                $price_input = trim($_POST['price'] ?? '');
                $unit = trim($_POST['unit'] ?? '');
                $quantity_input = trim($_POST['quantity_available'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $status = $_POST['status'] ?? 'available';

                if ($product_name === '' || strlen($product_name) > 150) {
                    throw new Exception('Enter a product name of up to 150 characters.');
                }

                if (!$category_id || $category_id < 1) {
                    throw new Exception('Please select a valid category.');
                }

                if ($price_input === '' || !is_numeric($price_input) || (float)$price_input < 0) {
                    throw new Exception('Enter a valid price of zero or more.');
                }

                if ($quantity_input === '' || !is_numeric($quantity_input) || (float)$quantity_input < 0) {
                    throw new Exception('Enter a valid quantity of zero or more.');
                }

                if ($unit === '' || strlen($unit) > 50) {
                    throw new Exception('Enter a valid quantity unit.');
                }

                if (strlen($location) > 255) {
                    throw new Exception('Location must not exceed 255 characters.');
                }

                if (!in_array($status, ['available', 'unavailable'], true)) {
                    throw new Exception('Please select a valid availability status.');
                }

                $price = (float)$price_input;
                $quantity = (float)$quantity_input;

                // Confirm category exists
                $stmt = $conn->prepare(
                    "SELECT category_id FROM categories WHERE category_id = ?"
                );
                $stmt->bind_param("i", $category_id);
                $stmt->execute();
                $category_exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$category_exists) {
                    throw new Exception('The selected category does not exist.');
                }

                // Load existing image when editing
                $old_image = '';

                if ($product_id > 0) {
                    $stmt = $conn->prepare(
                        "SELECT image FROM products
                         WHERE product_id = ? AND farmer_id = ?"
                    );
                    $stmt->bind_param("ii", $product_id, $farmer_id);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$existing) {
                        throw new Exception('Product not found or you do not own it.');
                    }

                    $old_image = $existing['image'] ?? '';
                }

                // Handle optional image upload
                $image = $old_image;
                $new_image_path = null;

                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('The photo upload failed. Please try again.');
                    }

                    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                        throw new Exception('The photo must be 5 MB or smaller.');
                    }

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($_FILES['image']['tmp_name']);

                    $allowed_types = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($allowed_types[$mime])) {
                        throw new Exception('Use a JPG, PNG or WEBP photo.');
                    }

                    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                        throw new Exception('Could not create the photo folder.');
                    }

                    $new_filename = bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime];
                    $destination = $upload_dir . $new_filename;

                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                        throw new Exception('Could not save the uploaded photo.');
                    }

                    $image = $new_filename;
                    $new_image_path = $destination;
                }

                if ($product_id > 0) {
                    // Update existing listing
                    $stmt = $conn->prepare(
                        "UPDATE products
                         SET category_id = ?, product_name = ?,
                             price = ?, unit = ?, quantity_available = ?,
                             image = ?, location = ?, status = ?,
                             updated_at = NOW()
                         WHERE product_id = ? AND farmer_id = ?"
                    );

                    $stmt->bind_param(
                        "isdsdsssii",
                        $category_id,
                        $product_name,
                        $price,
                        $unit,
                        $quantity,
                        $image,
                        $location,
                        $status,
                        $product_id,
                        $farmer_id
                    );

                    $stmt->execute();
                    $stmt->close();

                    if ($new_image_path && $old_image !== '') {
                        deleteProductImage($old_image);
                    }

                    $message = 'Your produce listing has been updated.';

                } else {
                    // Insert new produce listing
                    $stmt = $conn->prepare(
                        "INSERT INTO products
                         (farmer_id, category_id, product_name, price,
                          unit, quantity_available, image, location, status,
                          created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                    );

                    $stmt->bind_param(
                        "iisdsdsss",
                        $farmer_id,
                        $category_id,
                        $product_name,
                        $price,
                        $unit,
                        $quantity,
                        $image,
                        $location,
                        $status
                    );

                    $stmt->execute();
                    $product_id = (int)$conn->insert_id;
                    $stmt->close();

                    // Create admin notification
                    $notification_type = "product";
                    $title = "New Produce Listing";
                    $message_text = "A farmer has submitted a new produce listing: " . $product_name . ".";
                    $action_url = "products.php";

                    $notification_stmt = $conn->prepare(
                        "INSERT IGNORE INTO admin_notifications
                         (notification_type, reference_id, title, message, action_url)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    $notification_stmt->bind_param(
                        "sisss",
                        $notification_type,
                        $product_id,
                        $title,
                        $message_text,
                        $action_url
                    );

                    $notification_stmt->execute();
                    $notification_stmt->close();

                    $message = 'Your produce has been added successfully.';
                }

            } else {
                throw new Exception('Unknown action.');
            }

        } catch (Throwable $ex) {
            $message = 'Could not complete the action: ' . $ex->getMessage();
            $message_type = 'danger';
        }
    }
}

// Load categories
$categories = [];

try {
    $result = $conn->query(
        "SELECT category_id, category_name
         FROM categories
         ORDER BY category_name ASC"
    );

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} catch (Throwable $ex) {
    $message = 'Could not load product categories. Check your categories table.';
    $message_type = 'danger';
}

// Load this farmer's produce only
$products = [];

try {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name,  p.price,
                p.unit, p.quantity_available, p.image, p.location, p.status,
                p.created_at, p.category_id, c.category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.category_id
         WHERE p.farmer_id = ?
         ORDER BY p.product_id DESC"
    );

    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();
} catch (Throwable $ex) {
    $message = 'Could not load your listings. ' . $ex->getMessage();
    $message_type = 'danger';
}

$edit_product = null;
$edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($edit_id) {
    foreach ($products as $product) {
        if ((int) $product['product_id'] === (int) $edit_id) {
            $edit_product = $product;
            break;
        }
    }

    if (!$edit_product) {
        $message = 'That listing was not found in your account.';
        $message_type = 'danger';
    }
}

$available_count = 0;

foreach ($products as $product) {
    if ($product['status'] === 'available') {
        $available_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage My Produce | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        :root {
            --green: #39734b;
            --green-dark: #285639;
            --green-light: #edf5ee;
            --cream: #faf9f4;
            --text: #26352b;
            --muted: #718075;
            --border: #e4eae3;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
        }

        .brand {
            color: var(--green-dark);
            font-size: 1.35rem;
            font-weight: 800;
        }

        .page-heading {
            color: var(--green-dark);
            font-weight: 750;
        }

        .soft-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(35, 65, 43, 0.04);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--green-dark);
        }

        .stat-label, .muted {
            color: var(--muted);
        }

        .btn-green {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        .btn-green:hover {
            background: var(--green-dark);
            border-color: var(--green-dark);
            color: #fff;
        }

        .form-control, .form-select {
            border-color: #dce5dc;
            padding: .72rem .8rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #79a985;
            box-shadow: 0 0 0 .2rem rgba(57, 115, 75, .12);
        }

        .produce-photo {
            width: 76px;
            height: 76px;
            object-fit: cover;
            border-radius: 12px;
            background: var(--green-light);
        }

        .empty-state {
            padding: 38px 15px;
            text-align: center;
            color: var(--muted);
        }

        .table th {
            color: #637467;
            font-size: .83rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .status-pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: .78rem;
            font-weight: 700;
        }

        .status-available {
            color: #27643a;
            background: #e7f5e9;
        }

        .status-unavailable {
            color: #855c21;
            background: #fff1d8;
        }

        footer {
            color: var(--muted);
            font-size: .9rem;
        }

        @media (max-width: 576px) {
            .page-heading { font-size: 1.55rem; }
            .stat-number { font-size: 1.5rem; }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg">
    <div class="container py-2">
        <a class="navbar-brand brand" href="Dashboard.php">🌿 AgriConnect</a>

        <div class="d-flex align-items-center gap-2">
            <a href="Dashboard.php" class="btn btn-outline-success btn-sm">Dashboard</a>
            <span class="d-none d-md-inline muted small">
                <?= e($farmer_name) ?>
            </span>
        </div>
    </div>
</nav>

<main class="container py-4 py-md-5">

    <div class="d-flex flex-column flex-md-row justify-content-between
                align-items-md-center gap-3 mb-4">
        <div>
            <p class="text-success fw-semibold mb-1">FARMER WORKSPACE</p>
            <h1 class="page-heading mb-2">Manage My Produce</h1>
            <p class="muted mb-0">
                Add your farm produce, update prices and keep your listings current.
            </p>
        </div>

        <a href="#produce-form" class="btn btn-green px-4 py-2">
            + Add Produce
        </a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($message_type) ?> alert-dismissible fade show"
             role="alert">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"
                    aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <div class="soft-card p-3 p-md-4 h-100">
                <div class="stat-label small mb-2">My Listings</div>
                <div class="stat-number"><?= count($products) ?></div>
            </div>
        </div>

        <div class="col-6 col-lg-4">
            <div class="soft-card p-3 p-md-4 h-100">
                <div class="stat-label small mb-2">Available Produce</div>
                <div class="stat-number"><?= $available_count ?></div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="soft-card p-3 p-md-4 h-100">
                <div class="stat-label small mb-2">Your Account</div>
                <div class="fw-bold mt-2"><?= e($farmer_name) ?></div>
                <div class="muted small">Farmer account</div>
            </div>
        </div>
    </div>

    <div class="soft-card p-3 p-md-4 mb-4" id="produce-form">
        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">
                <?= $edit_product ? 'Edit Produce Listing' : 'Add New Produce' ?>
            </h2>
            <p class="muted small mb-0">
                Fields marked with * are required.
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="product_id"
                   value="<?= e($edit_product['product_id'] ?? 0) ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="product_name" class="form-label fw-semibold">
                        Produce Name *
                    </label>
                    <input type="text" class="form-control" id="product_name"
                           name="product_name" maxlength="150" required
                           placeholder="e.g. Fresh tomatoes"
                           value="<?= e($edit_product['product_name'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label for="category_id" class="form-label fw-semibold">
                        Category *
                    </label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>"
                                <?= isset($edit_product['category_id']) &&
                                    (int) $edit_product['category_id'] ===
                                    (int) $category['category_id']
                                    ? 'selected' : '' ?>>
                                <?= e($category['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="price" class="form-label fw-semibold">
                        Price (KSh) *
                    </label>
                    <input type="number" class="form-control" id="price"
                           name="price" min="0" step="0.01" required
                           placeholder="e.g. 120"
                           value="<?= e($edit_product['price'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label for="quantity_available" class="form-label fw-semibold">
                        Quantity Available *
                    </label>
                    <input type="number" class="form-control"
                           id="quantity_available" name="quantity_available"
                           min="0" step="0.01" required placeholder="e.g. 50"
                           value="<?= e($edit_product['quantity_available'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label for="unit" class="form-label fw-semibold">
                        Quantity Unit *
                    </label>
                    <select class="form-select" id="unit" name="unit" required>
                        <option value="">Select unit</option>
                        <?php
                        $units = ['kg', 'gram', 'bag', 'crate', 'piece',
                                  'bunch', 'litre', 'tonne', 'sack'];
                        $selected_unit = $edit_product['unit'] ?? '';

                        foreach ($units as $unit_option):
                        ?>
                            <option value="<?= e($unit_option) ?>"
                                <?= $selected_unit === $unit_option ? 'selected' : '' ?>>
                                <?= e(ucfirst($unit_option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="location" class="form-label fw-semibold">
                        Farm / Collection Location *
                    </label>
                    <input type="text" class="form-control" id="location"
                           name="location" maxlength="255" required
                           placeholder="e.g. Marani, Kisii County"
                           value="<?= e($edit_product['location'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label for="image" class="form-label fw-semibold">
                        Produce Photo
                    </label>
                    <input type="file" class="form-control" id="image"
                           name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG or WEBP. Maximum 5 MB.</div>

                    <?php if (!empty($edit_product['image'])): ?>
                        <div class="mt-2 small muted">
                            Current photo:
                            <?= e(basename($edit_product['image'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label fw-semibold">
                        Availability *
                    </label>
                    <?php $selected_status = $edit_product['status'] ?? 'available'; ?>
                    <select class="form-select" id="status" name="status" required>
                        <option value="available"
                            <?= $selected_status === 'available' ? 'selected' : '' ?>>
                            Available
                        </option>
                        <option value="unavailable"
                            <?= $selected_status === 'unavailable' ? 'selected' : '' ?>>
                            Unavailable
                        </option>
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="btn btn-green px-4">
                        <?= $edit_product ? 'Save Changes' : 'Add Produce' ?>
                    </button>

                    <?php if ($edit_product): ?>
                        <a href="manage-produce.php"
                           class="btn btn-outline-secondary px-4">Cancel Edit</a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-outline-secondary px-4">
                            Clear Form
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="soft-card p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between
                    gap-2 mb-3">
            <div>
                <h2 class="h4 fw-bold mb-1">My Produce Listings</h2>
                <p class="muted small mb-0">
                    Only listings belonging to your farmer account appear here.
                </p>
            </div>
            <span class="badge text-bg-light align-self-start">
                <?= count($products) ?> listing(s)
            </span>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="fs-1 mb-2">🌱</div>
                <h3 class="h5 fw-bold">No produce listed yet</h3>
                <p class="mb-0">Use the form above to add your first produce listing.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Produce</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php
                                        $photo_name = basename($product['image'] ?? '');
                                        $photo_path = __DIR__ . '/../uploads/products/' . $photo_name;
                                        ?>
                                        <?php if ($photo_name !== '' && is_file($photo_path)): ?>
                                            <img class="produce-photo"
                                                 src="../uploads/products/<?= rawurlencode($photo_name) ?>"
                                                 alt="<?= e($product['product_name']) ?>">
                                        <?php else: ?>
                                            <div class="produce-photo d-flex
                                                        align-items-center justify-content-center fs-3">
                                                🌾
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <div class="fw-bold">
                                                <?= e($product['product_name']) ?>
                                            </div>
                                            <div class="muted small">
                                                ID: <?= (int) $product['product_id'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td><?= e($product['category_name'] ?? 'Other') ?></td>

                                <td class="fw-semibold">
                                    KSh <?= number_format((float) $product['price'], 2) ?>
                                </td>

                                <td>
                                    <?= e($product['quantity_available']) ?>
                                    <?= e($product['unit']) ?>
                                </td>

                                <td><?= e($product['location'] ?: 'Not specified') ?></td>

                                <td>
                                    <span class="status-pill
                                        <?= $product['status'] === 'available'
                                            ? 'status-available'
                                            : 'status-unavailable' ?>">
                                        <?= e(ucfirst($product['status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a class="btn btn-sm btn-outline-success"
                                           href="manage-produce.php?edit=<?= (int) $product['product_id'] ?>#produce-form">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              onsubmit="return confirm('Delete this produce listing? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($csrf_token) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id"
                                                   value="<?= (int) $product['product_id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <footer class="text-center py-4 mt-3">
        © <?= date('Y') ?> AgriConnect · Helping farmers connect with markets.
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
