
<?php
require_once "db.php";

$site_name = "AgriConnect";
$search = trim($_GET["search"] ?? "");
$category_id = (int)($_GET["category"] ?? 0);
$county = trim($_GET["county"] ?? "");
$price_range = trim($_GET["price_range"] ?? "");

$allowed_price_ranges = [
    "",
    "0-100",
    "100-500",
    "500-1000",
    "1000-5000",
    "5000+"
];

if (!in_array($price_range, $allowed_price_ranges, true)) {
    $price_range = "";
}
$categories = [];
$products = [];
$error_message = "";

try {
    // Load product categories
    $category_query = $conn->query(
        "SELECT * FROM categories ORDER BY category_name ASC"
    );

    $categories = $category_query->fetch_all(MYSQLI_ASSOC);

    // Load available produce and farmer information
    $sql = "SELECT
                p.product_id,
                p.product_name,
                p.description,
                p.price,
                p.unit,
                p.quantity_available,
                p.image,
                c.category_name,
                fp.farm_name,
                fp.county
            FROM products p
            INNER JOIN categories c
                ON p.category_id = c.category_id
            INNER JOIN farmer_profiles fp
                ON p.farmer_id = fp.farmer_id
                WHERE p.status = 'available'
  AND p.approval_status = 'approved'
  AND p.quantity_available > 0";
            
    $types = "";
    $params = [];

    if ($search !== "") {
        $sql .= " AND (
                    p.product_name LIKE ?
                    OR p.description LIKE ?
                    OR c.category_name LIKE ?
                    OR fp.farm_name LIKE ?
                    OR fp.county LIKE ?
                  )";

        $keyword = "%" . $search . "%";
        $types .= "sssss";

        for ($i = 0; $i < 5; $i++) {
            $params[] = $keyword;
        }
    }
    if ($category_id > 0) {
    $sql .= " AND c.category_id = ?";
    $types .= "i";
    $params[] = $category_id;
}

if ($county !== "") {
    $sql .= " AND fp.county = ?";
    $types .= "s";
    $params[] = $county;
}

switch ($price_range) {
    case "0-100":
        $sql .= " AND p.price < ?";
        $types .= "d";
        $params[] = 100;
        break;

    case "100-500":
        $sql .= " AND p.price >= ? AND p.price < ?";
        $types .= "dd";
        $params[] = 100;
        $params[] = 500;
        break;

    case "500-1000":
        $sql .= " AND p.price >= ? AND p.price < ?";
        $types .= "dd";
        $params[] = 500;
        $params[] = 1000;
        break;

    case "1000-5000":
        $sql .= " AND p.price >= ? AND p.price <= ?";
        $types .= "dd";
        $params[] = 1000;
        $params[] = 5000;
        break;

    case "5000+":
        $sql .= " AND p.price > ?";
        $types .= "d";
        $params[] = 5000;
        break;
}

$sql .= " ORDER BY p.created_at DESC";

   
    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Throwable $e) {
    error_log("AgriConnect catalogue error: " . $e->getMessage());
    $error_message = "We could not load the catalogue. Please check your database setup.";
}

function escapeHTML($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Produce Catalogue | <?php echo escapeHTML($site_name); ?></title>

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
            --soft-green: #EFF6E9;
            --pale-green: #F7FAF4;
            --white: #FFFFFF;
            --text-dark: #25372D;
            --text-muted: #6B786F;
            --border: #E2E9E1;
            --shadow: 0 8px 25px rgba(7, 91, 61, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text-dark);
            background: var(--white);
        }

        a {
            text-decoration: none;
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

/* WELCOME MARQUEE */
.marquee-bar {
    background: #FFF9C4;
    color: #06452F;
    overflow: hidden;
    white-space: nowrap;
    height: 40px;
    display: flex;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1040;
    font-size: 14px;
    font-weight: 700;
}

.marquee-track {
    display: flex;
    width: max-content;
    animation: agriMarquee 28s linear infinite;
}

.marquee-group {
    display: flex;
    flex-shrink: 0;
    align-items: center;
}

.marquee-group span {
    padding: 0 30px;
    flex-shrink: 0;
}

/* Continuous movement */
@keyframes agriMarquee {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-50%);
    }
}

/* Keep marquee visible during scrolling */
body {
    padding-top: 40px;
}

/* Navbar stays below marquee */
.main-navbar {
    position: sticky;
    top: 40px !important;
    z-index: 1030;
}

        /* NAVBAR */
        .main-navbar {
            background: var(--dark-green);
            padding: 14px 0;
        }

       .navbar-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--white) !important;
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -0.5px;
}

.brand-logo {
    width: 30px;
    height: 30px;
    object-fit: contain;
    display: block;
    flex-shrink: 0;
}
        .brand-name span {
            color: var(--yellow);
        }

        .main-navbar .nav-link {
            color: rgba(255,255,255,.9);
            font-weight: 600;
            font-size: 14px;
            padding: 10px 12px !important;
        }

        .main-navbar .nav-link:hover,
        .main-navbar .nav-link.active {
            color: var(--yellow) !important;
        }

        .main-navbar .nav-login {
            display: inline-block;
            background: var(--deep-green);
            color: var(--white) !important;
            padding: 10px 16px !important;
            border: 1px solid var(--main-green);
            border-radius: 8px;
            font-weight: 600;
        }

        .main-navbar .nav-register {
            display: inline-block;
            background: var(--yellow);
            color: var(--deep-green) !important;
            padding: 10px 16px !important;
            border: 1px solid var(--yellow);
            border-radius: 8px;
            font-weight: 700;
        }

        .main-navbar .dropdown-menu {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            min-width: 210px;
            box-shadow: var(--shadow);
        }

        .main-navbar .dropdown-header {
            color: var(--dark-green);
            font-weight: 700;
        }

        .main-navbar .dropdown-item {
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--text-dark);
        }

        .main-navbar .dropdown-item:hover {
            background: var(--soft-green);
            color: var(--dark-green);
        }

        /* CATALOGUE HERO */
        .catalogue-hero {
            background: var(--pale-green);
            padding: 55px 0 65px;
        }

        .eyebrow {
            display: inline-block;
            background: var(--soft-green);
            color: var(--dark-green);
            padding: 7px 15px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .catalogue-hero h1 {
            color: var(--deep-green);
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
        }

        .catalogue-hero h1 span {
            color: var(--main-green);
        }

        .catalogue-hero p {
            color: var(--text-muted);
            font-size: 16px;
            max-width: 650px;
            margin-bottom: 25px;
        }

        .search-panel {
            background: var(--white);
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .search-panel .form-control,
        .search-panel .form-select {
            min-height: 48px;
            border-color: var(--border);
            border-radius: 8px;
            color: var(--text-dark);
        }

        .search-panel .form-control:focus,
        .search-panel .form-select:focus {
            border-color: var(--main-green);
            box-shadow: 0 0 0 3px rgba(19,128,79,.12);
        }

        .search-btn {
            min-height: 48px;
            background: var(--dark-green);
            color: var(--white);
            border: 0;
            border-radius: 8px;
            padding: 10px 22px;
            font-weight: 700;
        }

        .search-btn:hover {
            background: var(--main-green);
            color: var(--white);
        }

        /* CATALOGUE */
        .catalogue-section {
            padding: 65px 0 75px;
        }

        .section-title {
            color: var(--deep-green);
            font-size: 30px;
            font-weight: 800;
        }

        .section-subtitle {
            color: var(--text-muted);
        }

        .product-card {
            height: 100%;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: transform .25s, box-shadow .25s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .product-image {
            height: 210px;
            width: 100%;
            object-fit: cover;
            background: var(--soft-green);
        }

        .product-placeholder {
            height: 210px;
            background: var(--soft-green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--main-green);
            font-size: 55px;
        }

        .product-body {
            padding: 20px;
        }

        .category-badge {
            display: inline-block;
            background: var(--soft-green);
            color: var(--dark-green);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .product-name {
            color: var(--deep-green);
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .product-description {
            color: var(--text-muted);
            font-size: 14px;
            min-height: 42px;
            margin-bottom: 14px;
        }

        .product-price {
            color: var(--main-green);
            font-size: 23px;
            font-weight: 800;
        }

        .product-unit {
            color: var(--text-muted);
            font-size: 13px;
        }

        .product-info {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 12px;
        }

        .product-info i {
            color: var(--main-green);
            margin-right: 5px;
        }

        .enquire-btn {
            display: block;
            text-align: center;
            background: var(--dark-green);
            color: var(--white);
            padding: 11px 14px;
            border-radius: 8px;
            font-weight: 700;
            margin-top: 17px;
        }

        .enquire-btn:hover {
            background: var(--main-green);
            color: var(--white);
        }

        .empty-state {
            text-align: center;
            padding: 55px 20px;
            border: 1px dashed var(--border);
            border-radius: 12px;
            background: var(--pale-green);
        }

        .empty-state i {
            font-size: 45px;
            color: var(--main-green);
        }

        .empty-state h3 {
            color: var(--deep-green);
            font-weight: 800;
            margin-top: 15px;
        }

        /* CTA */
        .catalogue-cta {
            background: var(--dark-green);
            border-radius: 12px;
            padding: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .catalogue-cta h3 {
            color: var(--white);
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .catalogue-cta p {
            color: #E5F2E9;
            margin: 0;
        }

        .yellow-btn {
            display: inline-block;
            background: var(--yellow);
            color: var(--deep-green);
            padding: 13px 20px;
            border-radius: 8px;
            font-weight: 700;
            white-space: nowrap;
        }

        .yellow-btn:hover {
            background: #E8BD20;
            color: var(--deep-green);
        }

        /* FOOTER */
        .site-footer {
            background: var(--deep-green);
            color: var(--white);
            padding: 50px 0 20px;
        }

        .site-footer h5 {
            color: var(--yellow);
            font-weight: 800;
            margin-bottom: 16px;
        }

        .site-footer p,
        .site-footer a {
            color: rgba(255,255,255,.8);
            font-size: 14px;
        }

        .site-footer a:hover {
            color: var(--yellow);
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 9px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,.15);
            margin-top: 30px;
            padding-top: 18px;
        }

        @media (max-width: 991.98px) {
            .main-navbar .nav-login,
            .main-navbar .nav-register {
                margin-top: 8px;
            }
        }
    </style>
</head>

<body>
    <!-- WELCOME MARQUEE -->
<div class="marquee-bar">
    <div class="marquee-track">

        <div class="marquee-group">
            <span>🌾 Welcome to AgriConnect — Connecting Farmers to Markets!</span>
            <span>🌱 Support local farmers and enjoy fresh produce!</span>
            <span>🚜 Growing Together, Connecting Markets!</span>
        </div>

        <div class="marquee-group" aria-hidden="true">
            <span>🌾 Welcome to AgriConnect — Connecting Farmers to Markets!</span>
            <span>🌱 Support local farmers and enjoy fresh produce!</span>
            <span>🚜 Growing Together, Connecting Markets!</span>
        </div>

    </div>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark main-navbar sticky-top">
    <div class="container">

       <a class="navbar-brand" href="index.php">
    <img src="images/logo.png" alt="AgriConnect Logo" class="brand-logo">
    <span class="brand-name">Agri<span>Connect</span></span>
</a>
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavigation"
                aria-controls="mainNavigation"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav ms-auto align-items-lg-center">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="about.php">About Us</a>
                </li>

                
<li class="nav-item">
    <a class="nav-link active" href="produce-catalogue.php">
        Produce Catalogue
    </a>
</li>

<li class="nav-item">
    <a class="nav-link" href="buyer/cart.php">
        <i class="bi bi-cart3 me-1"></i> My Cart
    </a>
</li>


                <li class="nav-item">
                    <a class="nav-link" href="farmer-profiles.php">Farmer Profiles</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact Us</a>
                </li>

                <li class="nav-item dropdown ms-lg-2">
                    <a class="nav-link dropdown-toggle nav-login"
                       href="#" id="loginDropdown"
                       role="button" data-bs-toggle="dropdown"
                       aria-expanded="false">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end"
                        aria-labelledby="loginDropdown">
                        <li><h6 class="dropdown-header">Login as</h6></li>
                        <li><a class="dropdown-item" href="admin/login.php">
                            <i class="bi bi-shield-lock me-2"></i>Admin</a></li>
                        <li><a class="dropdown-item" href="farmer/login.php">
                            <i class="bi bi-person-workspace me-2"></i>Farmer</a></li>
                        <li><a class="dropdown-item" href="buyer/login.php">
                            <i class="bi bi-person me-2"></i>Buyer</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown ms-lg-2">
                    <a class="nav-link dropdown-toggle nav-register"
                       href="#" id="registerDropdown"
                       role="button" data-bs-toggle="dropdown"
                       aria-expanded="false">
                        <i class="bi bi-person-plus"></i> Register
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end"
                        aria-labelledby="registerDropdown">
                        <li><h6 class="dropdown-header">Create an account</h6></li>
                        <li><a class="dropdown-item" href="farmer/register.php">
                            <i class="bi bi-person-workspace me-2"></i>Register as Farmer</a></li>
                        <li><a class="dropdown-item" href="buyer/register.php">
                            <i class="bi bi-person me-2"></i>Register as Buyer</a></li>
                    </ul>
                </li>

            </ul>
        </div>
    </div>
</nav>

<!-- CATALOGUE HERO -->
<section class="catalogue-hero">
    <div class="container">

        <span class="eyebrow">
            <i class="bi bi-basket2 me-1"></i>
            Fresh from Our Farmers
        </span>

        <h1>Explore Our <span>Produce Catalogue</span></h1>

        <p>
            Discover available agricultural produce, compare prices,
            and find farmers offering the products you need.
        </p>

        <!-- SEARCH AND CATEGORY FILTER -->
        <form action="produce-catalogue.php" method="GET"
              class="search-panel">

            
<div class="row g-3">

    <!-- Search Produce -->
   <div class="col-lg-4 col-md-6"> 
        <label for="search" class="form-label fw-semibold">
            Search Produce
        </label>
        <input type="search"
               class="form-control"
               id="search"
               name="search"
               placeholder="e.g. maize, tomatoes..."
               value="<?php echo escapeHTML($search); ?>">
    </div>

    <!-- Category -->
    <div class="col-lg-3 col-md-6">
        <label for="category" class="form-label fw-semibold">
            Category
        </label>
        <select class="form-select" id="category" name="category">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int)$category['category_id']; ?>"
                    <?php echo $category_id === (int)$category['category_id'] ? 'selected' : ''; ?>>
                    <?php echo escapeHTML($category['category_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- County -->
    <div class="col-lg-3 col-md-6">
        <label for="county" class="form-label fw-semibold">
            County
        </label>
        <select class="form-select" id="county" name="county">
            <option value="">All Counties</option>
            <?php
            $counties = [
                "Baringo", "Bomet", "Bungoma", "Busia", "Elgeyo-Marakwet",
                "Embu", "Garissa", "Homa Bay", "Isiolo", "Kajiado",
                "Kakamega", "Kericho", "Kiambu", "Kilifi", "Kirinyaga",
                "Kisii", "Kisumu", "Kitui", "Kwale", "Laikipia",
                "Lamu", "Machakos", "Makueni", "Mandera", "Marsabit",
                "Meru", "Migori", "Mombasa", "Murang'a", "Nairobi",
                "Nakuru", "Nandi", "Narok", "Nyamira", "Nyandarua",
                "Nyeri", "Samburu", "Siaya", "Taita-Taveta", "Tana River",
                "Tharaka-Nithi", "Trans Nzoia", "Turkana", "Uasin Gishu",
                "Vihiga", "Wajir", "West Pokot"
            ];
            foreach ($counties as $county_option):
            ?>
                <option value="<?php echo escapeHTML($county_option); ?>"
                    <?php echo $county === $county_option ? 'selected' : ''; ?>>
                    <?php echo escapeHTML($county_option); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!--  Price Range -->
    <div class="col-lg-4 col-md-6">
    <label for="price_range" class="form-label">Price Range</label>

    <select name="price_range" id="price_range" class="form-select">
        <option value="" <?= $price_range === "" ? "selected" : "" ?>>
            All Prices
        </option>

        <option value="0-100" <?= $price_range === "0-100" ? "selected" : "" ?>>
            Below KSh 100
        </option>

        <option value="100-500" <?= $price_range === "100-500" ? "selected" : "" ?>>
            KSh 100 - 500
        </option>

        <option value="500-1000" <?= $price_range === "500-1000" ? "selected" : "" ?>>
            KSh 500 - 1,000
        </option>

        <option value="1000-5000" <?= $price_range === "1000-5000" ? "selected" : "" ?>>
            KSh 1,000 - 5,000
        </option>

        <option value="5000+" <?= $price_range === "5000+" ? "selected" : "" ?>>
            Above KSh 5,000
        </option>
    </select>
</div>

    <!-- Buttons -->
    <div class="col-lg-3 col-md-6 d-flex align-items-end gap-2">
        <button type="submit" class="search-btn flex-grow-1">
            <i class="bi bi-search me-1"></i> Search
        </button>

        <a href="produce-catalogue.php"
           class="btn btn-outline-success"
           style="min-height:48px; display:flex; align-items:center; border-radius:8px;">
            Clear
        </a>
    </div>

</div>
</form>
</section>

            

<!-- PRODUCTS -->
<section class="catalogue-section">
    <div class="container">
        
<div class="row align-items-center mb-4">
    <!-- LEFT: Heading and product count -->
    <div class="col-md-7">
        <h2 class="section-title mb-1">Available Produce</h2>
        <p class="section-subtitle mb-0">
            <?php echo count($products); ?> product(s) found
        </p>
    </div>

    <!-- RIGHT: Clear Filters -->
<div class="col-md-5 d-flex justify-content-md-end align-items-center gap-2 mt-3 mt-md-0 flex-wrap">

    <?php if (
        $search !== "" ||
        $category_id > 0 ||
        $county !== "" ||
        $price_range !== ""
    ): ?>

        <a href="produce-catalogue.php"
           class="btn btn-outline-success"
           style="border-radius: 8px;">

            <i class="bi bi-arrow-counterclockwise me-1"></i>
            Clear Filters

        </a>

    <?php endif; ?>

</div>
</div>
        <?php if ($error_message !== ""): ?>

    <div class="alert alert-warning" role="alert">
        <?php echo escapeHTML($error_message); ?>
    </div>

<?php elseif (empty($products)): ?>

            <div class="empty-state">
                <i class="bi bi-basket"></i>

                <h3>No Produce Found</h3>

                <p class="text-muted">
                    Try another search term or select a different category.
                </p>

                <a href="produce-catalogue.php"
                   class="btn btn-success mt-2"
                   style="background: #075B3D; border: none; border-radius: 8px;">
                    View All Produce
                </a>
            </div>

        <?php else: ?>

            <div class="row g-4">

                <?php foreach ($products as $product): ?>

                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <article class="product-card">

                            <?php
$image_path = trim((string)($product['image'] ?? ''));

// Get only the filename stored in the database.
$image_filename = basename(str_replace('\\', '/', $image_path));

// Build the correct path to the uploaded image.
$image_file = __DIR__ . "/uploads/products/" . $image_filename;
$image_url = "uploads/products/" . rawurlencode($image_filename);

// Check whether the actual image exists.
$valid_image = $image_filename !== ""
    && is_file($image_file);
    
?>

<?php if ($valid_image): ?>
    <img
    src="<?php echo escapeHTML($image_url); ?>"
    alt="<?php echo escapeHTML($product['product_name']); ?>"
    class="product-image">
<?php else: ?>
    <div class="product-placeholder">
        <i class="bi bi-image"></i>
        <span style="font-size:14px; margin-left:8px;">
            Product image coming soon
        </span>
    </div>
<?php endif; ?>
                            <div class="product-body">

                                <span class="category-badge">
                                    <?php echo escapeHTML($product['category_name']); ?>
                                </span>

                                <h3 class="product-name">
                                    <?php echo escapeHTML($product['product_name']); ?>
                                </h3>

                                <p class="product-description">
                                    <?php
                                    $description = trim((string)($product['description'] ?? ''));
                                    echo escapeHTML(
                                        $description !== ""
                                            ? mb_strimwidth($description, 0, 85, "...")
                                            : "Fresh agricultural produce from a local farmer."
                                    );
                                    ?>
                                </p>

                                <div>
                                    <span class="product-price">
                                        KSh <?php echo number_format((float)$product['price'], 2); ?>
                                    </span>

                                    <span class="product-unit">
                                        / <?php echo escapeHTML($product['unit']); ?>
                                    </span>
                                </div>

                                <div class="product-info">
                                    <div class="mb-2">
                                        <i class="bi bi-box-seam"></i>
                                        Available:
                                        <?php echo number_format((float)$product['quantity_available'], 2); ?>
                                        <?php echo escapeHTML($product['unit']); ?>
                                    </div>

                                    <div class="mb-2">
                                        <i class="bi bi-shop"></i>
                                        <?php echo escapeHTML($product['farm_name']); ?>
                                    </div>

                                    <div>
                                        <i class="bi bi-geo-alt"></i>
                                        <?php echo escapeHTML($product['county'] ?: 'Kenya'); ?>
                                    </div>
                                </div>

                                <form action="buyer/cart.php" method="POST" class="mt-3">

    <input type="hidden" name="action" value="add">

    <input type="hidden"
           name="product_id"
           value="<?php echo (int)$product['product_id']; ?>">

    <label for="quantity_<?php echo (int)$product['product_id']; ?>"
           class="form-label fw-semibold">
        Quantity (<?php echo escapeHTML($product['unit']); ?>)
    </label>

    <input type="number"
           class="form-control"
           id="quantity_<?php echo (int)$product['product_id']; ?>"
           name="quantity"
           min="1"
           max="<?php echo (float)$product['quantity_available']; ?>"
           value="1"
           step="1"
           required>

    <button type="submit" class="enquire-btn w-100">
        <i class="bi bi-cart-plus me-1"></i>
        Add to Cart
    </button>

</form>

                            </div>
                        </article>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
</section>

<!-- CTA BEFORE FOOTER -->
<section class="container mb-5">
    <div class="catalogue-cta">

        <div style="display:flex; align-items:center; gap:16px;">
            <img src="images/farm-hero.jpg"
                 alt="Local farming"
                 style="width:80px; height:75px; object-fit:cover;
                        border-radius:8px;">

            <div>
                <h3>Ready to Connect with Farmers?</h3>
                <p>Discover fresh produce and explore new opportunities.</p>
            </div>
        </div>

        <a href="farmer-profiles.php" class="yellow-btn">
            Meet Our Farmers <i class="bi bi-arrow-right ms-2"></i>
        </a>

    </div>
</section>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="container">

        <div class="row g-4">

            <div class="col-lg-4 col-md-6">
                <h5><i class="bi bi-leaf-fill me-2"></i>AgriConnect</h5>
                <p>
                    Connecting farmers and buyers through a convenient
                    agricultural marketplace.
                </p>
            </div>

            <div class="col-lg-2 col-md-6">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="produce-catalogue.php">Produce Catalogue</a></li>
                    <li><a href="farmer-profiles.php">Farmer Profiles</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5>For Our Users</h5>
                <ul class="footer-links">
                    <li><a href="farmer/register.php">Register as Farmer</a></li>
                    <li><a href="buyer/register.php">Register as Buyer</a></li>
                    <li><a href="farmer/login.php">Farmer Login</a></li>
                    <li><a href="buyer/login.php">Buyer Login</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5>Our Purpose</h5>
                <p>
                    Helping farmers showcase produce and buyers discover
                    available agricultural products.
                </p>
            </div>

        </div>

        <div class="footer-bottom text-center">
            <p class="mb-0">
                &copy; <?php echo date("Y"); ?> AgriConnect.
                All rights reserved.
            </p>
        </div>

    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
