
<?php
require_once "db.php";
$site_name = "AgriConnect";

/*
 * Load featured products from MySQL.
 * If the products table is empty, sample products are displayed.
 */
$products = [];
$database_error = false;

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
          AND p.quantity_available > 0
        ORDER BY p.created_at DESC
        LIMIT 8";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    $database_error = true;
}

/*
 * Temporary sample products for the initial homepage.
 * These are only displayed when no real products are available.
 * They are NOT inserted into the database.
 */
if (empty($products)) {
    $products = [
        [
            "product_id" => 0,
            "product_name" => "Fresh Maize",
            "description" => "Quality maize directly from the farm.",
            "price" => "65.00",
            "unit" => "kg",
            "quantity_available" => "100",
            "image" => "images/maize.jpeg",
            "category_name" => "Cereals",
            "farm_name" => "Sample Farm",
            "county" => "Trans Nzoia"
        ],
        [
            "product_id" => 0,
            "product_name" => "Fresh Tomatoes",
            "description" => "Fresh, ripe tomatoes for your kitchen.",
            "price" => "80.00",
            "unit" => "kg",
            "quantity_available" => "50",
            "image" => "images/tomatoes .jpeg",
            "category_name" => "Vegetables",
            "farm_name" => "Sample Farm",
            "county" => "Nakuru"
        ],
        [
            "product_id" => 0,
            "product_name" => "Irish Potatoes",
            "description" => "Freshly harvested quality potatoes.",
            "price" => "55.00",
            "unit" => "kg",
            "quantity_available" => "80",
            "image" => "images/irish.jpeg",
            "category_name" => "Tubers",
            "farm_name" => "Sample Farm",
            "county" => "Nyandarua"
        ],
        [
            "product_id" => 0,
            "product_name" => "Sukuma Wiki",
            "description" => "Fresh green leafy vegetables.",
            "price" => "30.00",
            "unit" => "bunch",
            "quantity_available" => "40",
            "image" => "images/sukuma wiki.jpeg",
            "category_name" => "Vegetables",
            "farm_name" => "Sample Farm",
            "county" => "Kiambu"
        ]
    ];
}

/*
 * Load approved testimonials.
 */
$testimonials = [];

$testimonial_sql = "
    SELECT t.message, t.rating, u.full_name
    FROM testimonials t
    INNER JOIN users u ON t.user_id = u.user_id
    WHERE t.status = 'approved'
    ORDER BY t.created_at DESC
    LIMIT 3
";

$testimonial_result = $conn->query($testimonial_sql);

if ($testimonial_result) {
    while ($row = $testimonial_result->fetch_assoc()) {
        $testimonials[] = $row;
    }
}

/*
 * Escape text before displaying database values in HTML.
 */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AgriConnect | Fresh Produce, Direct from Farmers</title>

    <meta name="description"
          content="AgriConnect connects farmers and buyers through a simple agricultural marketplace. Discover fresh produce from Kenyan farms.">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
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

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--white);
            color: var(--text-dark);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        img {
            max-width: 100%;
        }

        .container {
            max-width: 1200px;
        }

        /* NAVIGATION */

        .main-navbar {
            background: var(--dark-green);
            padding: 13px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--white) !important;
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--yellow);
            color: var(--dark-green);
            border-radius: 12px;
            font-size: 25px;
        }

        .brand-name span {
            color: var(--yellow);
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 13px !important;
            transition: color 0.2s;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--yellow) !important;
        }

        .nav-register {
            background: var(--yellow);
            color: var(--dark-green) !important;
            border-radius: 7px;
            padding: 10px 17px !important;
            margin-left: 8px;
            text-align: center;
        }

        .nav-register:hover {
            background: #FFE36B;
            color: var(--dark-green) !important;
        }

        .navbar-toggler {
            border: 1px solid rgba(255,255,255,0.4);
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .navbar-toggler-icon {
            filter: brightness(0) invert(1);
        }
        
/* WELCOME MARQUEE */

.marquee-bar {
    background: var(--yellow);
    color: var(--deep-green);
    overflow: hidden;
    white-space: nowrap;
    padding: 12px 0;
    font-size: 14px;
    font-weight: 700;
}

.marquee-track {
    display: flex;
    width: max-content;
    animation: agriMarquee 28s linear infinite;
}

.marquee-track span {
    flex-shrink: 0;
    padding-right: 30px;
}

@keyframes agriMarquee {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}

.marquee-bar:hover .marquee-track {
    animation-play-state: paused;
}

@media (prefers-reduced-motion: reduce) {
    .marquee-track {
        animation: none;
    }
}

/* ==========================================
   AGRICONNECT HERO SLIDER
   ZOOM IN + SMOOTH CROSSF ADE
========================================== */

.hero-section {
    position: relative;
    width: 100%;
    height: 72vh;
    min-height: 540px;
    max-height: 760px;
    overflow: hidden;
    isolation: isolate;
    }
   
.hero-slide {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    padding: 60px 8%;
    display: flex;
    align-items: center;
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
    opacity: 0;
    visibility: hidden;
    z-index: 0;
    transition: opacity 1.4s ease-in-out,
                visibility 1.4s ease-in-out;
}

.hero-slide.active {
    opacity: 1;
    visibility: visible;
    z-index: 1;
    animation: heroZoomIn 5.5s ease-out forwards;
}

@keyframes heroZoomIn {
    from {
        background-size: 100% 100%;
    }

    to {
        background-size: 115% 115%;
    }
}

}

/* Each slide */

.hero-slide {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    padding: 60px 8%;
    display: flex;
    align-items: center;
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
    opacity: 0;
    visibility: hidden;
    z-index: 0;
    transition: opacity 1.4s ease-in-out,
                visibility 1.4s ease-in-out;
}

.hero-slide.active {
    opacity: 1;
    visibility: visible;
    z-index: 1;
    animation: heroZoomIn 5.5s ease-out forwards;
}

@keyframes heroZoomIn {
    from {
        background-size: 100% 100%;
    }

    to {
        background-size: 115% 115%;
    }
}


/* Hero buttons */
.hero-content .btn {
    position: relative;
    z-index: 3;
    padding: 13px 25px;
    border-radius: 8px;
    font-weight: 700;

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.hero-content .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

/* Previous and next arrows */
.hero-prev,
.hero-next {
    position: absolute;
    top: 50%;
    z-index: 5;

    width: 48px;
    height: 48px;
    transform: translateY(-50%);

    display: flex;
    align-items: center;
    justify-content: center;

    color: #ffffff;
    background: rgba(68, 64, 64, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 50%;

    cursor: pointer;
    transition:
        background 0.25s ease,
        transform 0.25s ease;
}

.hero-prev {
    left: 24px;
}

.hero-next {
    right: 24px;
}

.hero-prev:hover,
.hero-next:hover {
    background: #13804F;
    transform: translateY(-50%) scale(1.08);
}

/* Slider navigation dots */
.hero-dots {
    position: absolute;
    left: 50%;
    bottom: 25px;
    z-index: 5;
    transform: translateX(-50%);

    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.hero-dot {
    width: 11px;
    height: 11px;
    padding: 0;

    border: 2px solid #ffffff;
    border-radius: 50%;
    background: transparent;
    cursor: pointer;

    transition:
        width 0.25s ease,
        background 0.25s ease,
        border-radius 0.25s ease;
}

.hero-dot.active {
    width: 30px;
    background: #F5CF32;
    border-color: #F5CF32;
    border-radius: 10px;
}

/* Tablet layout */
@media (max-width: 992px) {
    .hero-section {
        height: 65vh;
        min-height: 500px;
    }

    .hero-slide {
        padding: 50px 7%;
    }

    .hero-content {
        max-width: 650px;
    }
}

/* Mobile layout */
@media (max-width: 576px) {
    .hero-section {
        height: 72vh;
        min-height: 520px;
        max-height: 680px;
    }

    .hero-slide {
        padding: 45px 24px 70px;
        background-position: center;
    }

    .hero-content h1 {
        font-size: clamp(2rem, 9vw, 2.8rem);
        line-height: 1.15;
        letter-spacing: -0.5px;
        margin-bottom: 18px;
    }

    .hero-content p {
        font-size: 1rem;
        line-height: 1.7;
        margin-bottom: 24px;
    }

    .hero-content .btn {
        padding: 12px 18px;
        margin-bottom: 8px;
    }

    .hero-prev,
    .hero-next {
        top: auto;
        bottom: 17px;
        width: 38px;
        height: 38px;
        transform: none;
    }

    .hero-prev {
        left: 18px;
    }

    .hero-next {
        right: 18px;
    }

    .hero-prev:hover,
    .hero-next:hover {
        transform: scale(1.05);
    }

    .hero-dots {
        bottom: 30px;
        gap: 7px;
    }

    .hero-dot {
        width: 9px;
        height: 9px;
    }

    .hero-dot.active {
        width: 23px;
    }
}
/* HERO TEXT COLOURS */
.hero-badge {
    color: #F5CF32;
}

.hero-title {
    color: #FFFFFF;
}

.hero-title .highlight {
    color: #F5CF32;
}

.hero-description {
    color: #FFFFFF;
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
    .hero-slide,
    .hero-slide.active,
    .hero-content .btn,
    .hero-prev,
    .hero-next,
    .hero-dot {
        animation: none !important;
        transition: none !important;
    }
}


        /* QUICK BENEFITS */

        .benefit-strip {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 24px 0;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            height: 100%;
        }

        .benefit-icon {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--soft-green);
            color: var(--dark-green);
            border-radius: 50%;
            font-size: 20px;
        }

        .benefit-title {
            color: var(--dark-green);
            font-size: 13px;
            font-weight: 800;
        }

        .benefit-text {
            color: var(--text-muted);
            font-size: 11px;
        }

        /* GENERAL SECTIONS */

        .section-padding {
            padding: 75px 0;
        }

        .section-heading {
            color: var(--dark-green);
            font-size: clamp(27px, 3vw, 36px);
            font-weight: 800;
            letter-spacing: -0.8px;
            margin-bottom: 12px;
        }

        .section-description {
            max-width: 650px;
            margin: 0 auto;
            color: var(--text-muted);
            font-size: 14px;
        }

        .section-label {
            display: inline-block;
            color: var(--main-green);
            background: var(--soft-green);
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 13px;
        }

        /* SEARCH BAR */

        .search-section {
            background: var(--dark-green);
            padding: 30px 0;
        }

        .search-title {
            color: var(--white);
            font-size: 21px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .search-subtitle {
            color: rgba(255,255,255,0.75);
            font-size: 12px;
            margin-bottom: 0;
        }

        .product-search-form {
            display: flex;
            gap: 8px;
            background: var(--white);
            padding: 7px;
            border-radius: 9px;
        }

        .product-search-form input,
        .product-search-form select {
            min-width: 0;
            border: 0;
            outline: none;
            padding: 9px 11px;
            font-size: 13px;
            color: var(--text-dark);
            background: var(--white);
        }

        .product-search-form input {
            flex: 1;
        }

        .product-search-form select {
            max-width: 150px;
            border-left: 1px solid var(--border);
        }

        .search-button {
            border: 0;
            background: var(--yellow);
            color: var(--dark-green);
            border-radius: 6px;
            padding: 10px 19px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .search-button:hover {
            background: #FFE36B;
        }

        /* PRODUCT CARDS */

        .products-section {
            background: var(--pale-green);
        }

        .product-card {
            height: 100%;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
            transition: transform 0.25s, box-shadow 0.25s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .product-image {
            position: relative;
            height: 205px;
            overflow: hidden;
            background: var(--soft-green);
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s;
        }

        .product-card:hover .product-image img {
            transform: scale(1.04);
        }

        .product-category {
            position: absolute;
            top: 12px;
            left: 12px;
            background: var(--white);
            color: var(--dark-green);
            border-radius: 5px;
            padding: 5px 9px;
            font-size: 10px;
            font-weight: 800;
        }

        .product-content {
            padding: 17px;
        }

        .product-name {
            color: var(--dark-green);
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .product-location {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-muted);
            font-size: 11px;
            margin-bottom: 12px;
        }

        .product-description {
            color: var(--text-muted);
            font-size: 12px;
            min-height: 39px;
            margin-bottom: 14px;
        }

        .product-price {
            color: var(--dark-green);
            font-size: 21px;
            font-weight: 800;
        }

        .product-unit {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 400;
        }

        .product-stock {
            color: var(--main-green);
            font-size: 11px;
            font-weight: 700;
        }

        .product-divider {
            border-top: 1px solid var(--border);
            margin: 14px 0;
        }

        .product-button {
            width: 100%;
            display: block;
            text-align: center;
            border: 1px solid var(--dark-green);
            color: var(--dark-green);
            border-radius: 6px;
            padding: 10px;
            font-size: 12px;
            font-weight: 800;
            transition: 0.2s;
        }

        .product-button:hover {
            background: var(--dark-green);
            color: var(--white);
        }

        .product-placeholder {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--dark-green);
            background: var(--soft-green);
            gap: 7px;
        }

        .product-placeholder i {
            font-size: 52px;
            color: var(--main-green);
        }

        .product-placeholder span {
            font-size: 11px;
            font-weight: 700;
        }

        .view-all-button {
            display: inline-block;
            background: var(--dark-green);
            color: var(--white);
            padding: 13px 23px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 800;
        }

        .view-all-button:hover {
            background: var(--deep-green);
            color: var(--white);
        }

        /* HOW IT WORKS */

        .steps-section {
            background: var(--white);
        }

        .step-card {
            height: 100%;
            padding: 27px 22px;
            text-align: center;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .step-number {
            width: 60px;
            height: 60px;
            margin: 0 auto 19px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--yellow);
            color: var(--dark-green);
            border-radius: 17px;
            font-size: 25px;
        }

        .step-card h3 {
            color: var(--dark-green);
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .step-card p {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 0;
        }

        /* ABOUT / CTA */

        .cta-section {
            padding: 55px 0;
            background: var(--soft-green);
        }

        .cta-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            padding: 35px;
            border-radius: 15px;
            background: var(--dark-green);
        }

        .cta-box h2 {
            color: var(--white);
            font-size: 27px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .cta-box p {
            color: rgba(255,255,255,0.78);
            font-size: 13px;
            margin-bottom: 0;
        }

        .btn-yellow {
            flex-shrink: 0;
            background: var(--yellow);
            color: var(--dark-green);
            border: 2px solid var(--yellow);
            padding: 13px 21px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 800;
        }

        .btn-yellow:hover {
            background: #FFE36B;
            color: var(--dark-green);
        }

        /* TESTIMONIALS */

        .testimonials-section {
            background: var(--white);
        }

        .testimonial-card {
            height: 100%;
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
        }

        .testimonial-stars {
            color: #E4B800;
            margin-bottom: 14px;
        }

        .testimonial-message {
            color: #58675D;
            font-size: 13px;
            font-style: italic;
            margin-bottom: 19px;
        }

        .testimonial-name {
            color: var(--dark-green);
            font-size: 13px;
            font-weight: 800;
        }

        .testimonial-role {
            color: var(--text-muted);
            font-size: 11px;
        }

        .testimonial-avatar {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--soft-green);
            color: var(--dark-green);
            border-radius: 50%;
            font-weight: 800;
        }

        /* BRANCHES */

        .branches-section {
            background: var(--pale-green);
        }

        .branch-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            height: 100%;
            padding: 21px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .branch-icon {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--yellow);
            color: var(--dark-green);
            border-radius: 10px;
            font-size: 20px;
        }

        .branch-card h3 {
            color: var(--dark-green);
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .branch-card p {
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: 0;
        }

        /* FOOTER */

        .main-footer {
            background: #043B29;
            color: rgba(255,255,255,0.78);
            padding: 57px 0 0;
        }

        .footer-brand {
            display: inline-block;
            color: var(--white);
            font-size: 23px;
            font-weight: 800;
            margin-bottom: 17px;
        }

        .footer-brand span {
            color: var(--yellow);
        }

        .footer-description {
            max-width: 320px;
            color: rgba(255,255,255,0.68);
            font-size: 13px;
            line-height: 1.9;
        }

        .footer-heading {
            color: var(--white);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 11px;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--yellow);
        }

        .footer-contact {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            margin-bottom: 14px;
        }

        .footer-contact i {
            color: var(--yellow);
            font-size: 16px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.13);
            margin-top: 42px;
            padding: 19px 0;
            font-size: 11px;
            color: rgba(255,255,255,0.62);
        }

        .social-link {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            color: var(--white);
            margin-right: 6px;
        }

        .social-link:hover {
            background: var(--yellow);
            color: var(--dark-green);
        }

        /* BACK TO TOP */

        .back-to-top {
            position: fixed;
            bottom: 22px;
            right: 22px;
            z-index: 1000;
            width: 43px;
            height: 43px;
            display: none;
            align-items: center;
            justify-content: center;
            background: var(--yellow);
            color: var(--dark-green);
            border: 0;
            border-radius: 10px;
            font-size: 20px;
            box-shadow: var(--shadow);
        }

        .back-to-top.show {
            display: flex;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 991px) {
            .navbar-nav {
                padding-top: 15px;
            }

            .navbar-nav .nav-link {
                padding: 10px 5px !important;
            }

            .nav-register {
                margin: 8px 0 0;
                display: inline-block;
                width: fit-content;
            }

            .hero-section {
                padding: 55px 0;
            }

            .hero-image-area {
                margin-top: 35px;
                min-height: 300px;
            }

            .hero-image-frame {
                height: 300px;
            }

            .hero-floating-card {
                left: 0;
            }

            .benefit-item {
                justify-content: flex-start;
            }
        }

        @media (max-width: 767px) {
            .hero-title {
                font-size: 39px;
            }

            .hero-description {
                font-size: 14px;
            }

            .hero-image-area {
                min-height: 255px;
            }

            .hero-image-frame {
                height: 255px;
                border-width: 5px;
            }

            .hero-floating-card {
                left: 5px;
                bottom: 0;
                padding: 10px 13px;
            }

            .section-padding {
                padding: 53px 0;
            }

            .search-title {
                font-size: 19px;
            }

            .product-search-form {
                flex-wrap: wrap;
            }

            .product-search-form input {
                flex-basis: 100%;
            }

            .product-search-form select {
                flex: 1;
                max-width: none;
                border-left: 0;
                border-top: 1px solid var(--border);
            }

            .search-button {
                flex: 1;
            }

            .product-image {
                height: 190px;
            }

            .cta-box {
                align-items: flex-start;
                flex-direction: column;
                padding: 27px;
            }

            .cta-box h2 {
                font-size: 23px;
            }

            .footer-bottom {
                text-align: center;
            }
        }

        @media (max-width: 400px) {
            .hero-title {
                font-size: 33px;
            }

            .navbar-brand {
                font-size: 20px;
            }

            .product-image {
                height: 160px;
            }

            .product-content {
                padding: 13px;
            }
        }
    </style>
</head>

<body>

<!-- NAVIGATION BAR -->
<nav class="navbar navbar-expand-lg navbar-dark main-navbar sticky-top">
    <div class="container">

        <a class="navbar-brand" href="index.php">
            <span class="brand-icon">
                <i class="bi bi-leaf-fill"></i>
            </span>
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
        <a class="nav-link active" href="index.php">Home</a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="about.php">About Us</a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="produce-catalogue.php">Produce Catalogue</a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="farmer-profiles.php">Farmer Profiles</a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="contact.php">Contact Us</a>
    </li>

    <!-- LOGIN DROPDOWN -->
     
<a class="nav-link dropdown-toggle nav-login"
   href="#"
   id="loginDropdown"
   role="button"
   data-bs-toggle="dropdown"
   aria-expanded="false"
   style="background-color: #06452F; color: #FFFFFF; padding: 10px 16px; border: 1px solid #06452F; border-radius: 8px; font-weight: 600; display: inline-block;">
    <i class=""></i> Login
</a>

        <ul class="dropdown-menu dropdown-menu-end"
            aria-labelledby="loginDropdown">

            <li>
                <h6 class="dropdown-header">Login as</h6>
            </li>

            <li>
                <a class="dropdown-item" href="admin/login.php">
                    <i class="bi bi-shield-lock me-2"></i>Admin
                </a>
            </li>

            <li>
                <a class="dropdown-item" href="farmer/login.php">
                    <i class="bi bi-person-workspace me-2"></i>Farmer
                </a>
            </li>

            <li>
                <a class="dropdown-item" href="buyer/login.php">
                    <i class="bi bi-person me-2"></i>Buyer
                </a>
            </li>

        </ul>
    </li>

    <!-- REGISTER DROPDOWN -->
    <li class="nav-item dropdown ms-lg-2">
        <a class="nav-link dropdown-toggle nav-register"
           href="#"
           id="registerDropdown"
           role="button"
           data-bs-toggle="dropdown"
           aria-expanded="false">
            <i class="bi bi-person-plus"></i> Register
        </a>

        <ul class="dropdown-menu dropdown-menu-end"
            aria-labelledby="registerDropdown">

            <li>
                <h6 class="dropdown-header">Create an account</h6>
            </li>

            <li>
                <a class="dropdown-item" href="farmer/register.php">
                    <i class="bi bi-person-workspace me-2"></i>Register as Farmer
                </a>
            </li>

            <li>
                <a class="dropdown-item" href="buyer/register.php">
                    <i class="bi bi-person me-2"></i>Register as Buyer
                </a>
            </li>

        </ul>
    </li>

</ul>
        </div>
    </div>
</nav>

</nav>

<!-- WELCOME MARQUEE -->
<div class="marquee-bar">
    <div class="marquee-track">
        <span>
            🌱 Welcome to AgriConnect! &nbsp; • &nbsp;
            Connecting Farmers and Buyers &nbsp; • &nbsp;
            Fresh Produce, Direct from Farmers &nbsp; • &nbsp;
            Supporting Local Agriculture &nbsp; • &nbsp;
        </span>

        <span aria-hidden="true">
            🌱 Welcome to AgriConnect! &nbsp; • &nbsp;
            Connecting Farmers and Buyers &nbsp; • &nbsp;
            Fresh Produce, Direct from Farmers &nbsp; • &nbsp;
            Supporting Local Agriculture &nbsp; • &nbsp;
        </span>
    </div>
</div>
<!-- ANIMATED HERO SLIDER -->
<section class="hero-section" id="about" aria-label="AgriConnect highlights">

    <!-- SLIDE 1 -->
    <div class="hero-slide active"
         style="background-image: url('images/farm.jpg');"
         aria-hidden="false">

        <div class="container">
            <div class="hero-content">

                <div class="hero-badge">
                    <i class="bi bi-leaf-fill"></i>
                    YOUR TRUSTED FARMING MARKETPLACE
                </div>

                <h1 class="hero-title">
                    Welcome to <span class="highlight">AgriConnect</span>
                </h1>

                <p class="hero-description">
                    Connecting farmers with buyers. Discover fresh
                    agricultural produce and support local farmers
                    across Kenya.
                </p>

                <div class="hero-actions d-flex flex-wrap gap-3">
                    <a href="products.php" class="btn btn-primary-green">
                        Explore Produce
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                    <a href="register.php" class="btn btn-outline-green">
                        Join as a Farmer
                        <i class="bi bi-person-plus ms-1"></i>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- SLIDE 2 -->
    <div class="hero-slide"
         style="background-image: url('images/banana .jpg');"
         aria-hidden="true">

        <div class="container">
            <div class="hero-content">

                <div class="hero-badge">
                    <i class="bi bi-person-workspace"></i>
                    FOR KENYAN FARMERS
                </div>

                <h2 class="hero-title">
                    Your Farm, <span class="highlight">Your Market</span>
                </h2>

                <p class="hero-description">
                    Showcase your produce, reach more potential buyers
                    and grow your farming business with AgriConnect.
                </p>

                <div class="hero-actions d-flex flex-wrap gap-3">
                    <a href="farmer/register.php" class="btn btn-primary-green">
                        Join as a Farmer
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                    <a href="farmer-profiles.php" class="btn btn-outline-green">
                        Meet Our Farmers
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- SLIDE 3 -->
    <div class="hero-slide"
         style="background-image: url('images/farm.jpeg');"
         aria-hidden="true">

        <div class="container">
            <div class="hero-content">

                <div class="hero-badge">
                    <i class="bi bi-basket2-fill"></i>
                    FRESH FROM LOCAL FARMS
                </div>

                <h2 class="hero-title">
                    Fresh from the Farm,
                    <span class="highlight">Straight to You.</span>
                </h2>

                <p class="hero-description">
                    Explore fresh fruits, vegetables and other farm
                    produce listed by farmers on our marketplace.
                </p>

                <div class="hero-actions d-flex flex-wrap gap-3">
                    <a href="products.php" class="btn btn-primary-green">
                        Shop Fresh Produce
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                    <a href="produce-catalogue.php" class="btn btn-outline-green">
                        Browse Catalogue
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- SLIDE 4 -->
    <div class="hero-slide"
         style="background-image: url('images/slide 4.jpeg');"
         aria-hidden="true">

        <div class="container">
            <div class="hero-content">

                <div class="hero-badge">
                    <i class="bi bi-people-fill"></i>
                    GROWING TOGETHER
                </div>

                <h2 class="hero-title">
                    Together, We <span class="highlight">Grow Stronger</span>
                </h2>

                <p class="hero-description">
                    Building connections between farmers and buyers
                    while supporting local agriculture and communities
                    throughout Kenya.
                </p>

                <div class="hero-actions d-flex flex-wrap gap-3">
                    <a href="about.php" class="btn btn-primary-green">
                        Discover Our Mission
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                    <a href="register.php" class="btn btn-outline-green">
                        Get Started
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- PREVIOUS / NEXT CONTROLS -->
    <button type="button"
            class="hero-arrow hero-arrow-prev"
            id="heroPrev"
            aria-label="Previous slide">
        <i class="bi bi-chevron-left"></i>
    </button>

    <button type="button"
            class="hero-arrow hero-arrow-next"
            id="heroNext"
            aria-label="Next slide">
        <i class="bi bi-chevron-right"></i>
    </button>

    <!-- SLIDE DOTS -->
    <div class="hero-indicators" aria-label="Choose a slide">
        <button type="button" class="hero-dot active"
                aria-label="Show slide 1" aria-current="true"></button>

        <button type="button" class="hero-dot"
                aria-label="Show slide 2"></button>

        <button type="button" class="hero-dot"
                aria-label="Show slide 3"></button>

        <button type="button" class="hero-dot"
                aria-label="Show slide 4"></button>
    </div>

</section>
<!-- BENEFITS STRIP -->
<section class="benefit-strip">
    <div class="container">
        <div class="row g-4">

            <div class="col-6 col-lg-3">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <div>
                        <div class="benefit-title">Quality Produce</div>
                        <div class="benefit-text">Fresh farm products</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="benefit-title">Direct Connection</div>
                        <div class="benefit-text">Farmers and buyers</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <div class="benefit-title">Fair Pricing</div>
                        <div class="benefit-text">Clear product prices</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div>
                        <div class="benefit-title">Local Farmers</div>
                        <div class="benefit-text">Across Kenyan counties</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>


<!-- PRODUCT SEARCH -->
<section class="search-section">
    <div class="container">

        <div class="row align-items-center g-4">

            <div class="col-lg-4">
                <h2 class="search-title">Find Fresh Produce</h2>
                <p class="search-subtitle">
                    Search for products available from local farmers.
                </p>
            </div>

            <div class="col-lg-8">

                <form action="products.php" method="GET"
                      class="product-search-form"
                      id="homeSearchForm">

                    <input type="search"
                           name="search"
                           id="productSearch"
                           placeholder="What are you looking for?"
                           aria-label="Search agricultural products">

                    <select name="category" aria-label="Choose product category">
                        <option value="">All Categories</option>
                        <option value="Cereals">Cereals</option>
                        <option value="Vegetables">Vegetables</option>
                        <option value="Fruits">Fruits</option>
                        <option value="Tubers">Tubers</option>
                        <option value="Legumes">Legumes</option>
                        <option value="Other">Other</option>
                    </select>

                    <button type="submit" class="search-button">
                        <i class="bi bi-search me-1"></i> Search
                    </button>

                </form>

            </div>

        </div>

    </div>
</section>


<!-- FEATURED PRODUCTS -->
<section class="section-padding products-section" id="products">
    <div class="container">

        <div class="text-center mb-5">
            <span class="section-label">Our Marketplace</span>

            <h2 class="section-heading">Featured Farm Produce</h2>

            <p class="section-description">
                Discover quality agricultural produce from local farms.
                Browse the products and find what you need.
            </p>
        </div>

        <div class="row g-4" id="productGrid">

            <?php foreach ($products as $product): ?>

                
<?php
    $image_path = trim((string)($product['image'] ?? ''));

    // Get only the image filename, whether stored with slashes or backslashes.
    $image_filename = basename(str_replace('\\', '/', $image_path));

    // Use the correct folder based on the image path.
    if (strpos($image_path, 'uploads/products/') === 0) {
        $image_url = 'uploads/products/' . rawurlencode($image_filename);
    } elseif (strpos($image_path, 'images/') === 0) {
        $image_url = 'images/' . rawurlencode($image_filename);
    } elseif ($image_path !== '' && strpos($image_path, '/') === false
              && strpos($image_path, '\\') === false) {
        // Database stores only the filename.
        $image_url = 'uploads/products/' . rawurlencode($image_filename);
    } else {
        $image_url = '';
    }
?>


                <div class="col-6 col-md-6 col-lg-3 product-item"
                     data-name="<?= e(strtolower($product['product_name'])) ?>"
                     data-category="<?= e(strtolower($product['category_name'])) ?>"
                     data-county="<?= e(strtolower($product['county'])) ?>">

                    <article class="product-card">

                        <div class="product-image">

                            <?php if ($image_url !== ''): ?>

                                <img src="<?= e($image_url) ?>"
                                     alt="<?= e($product['product_name']) ?>"
                                     loading="lazy"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                                <div class="product-placeholder" style="display:none;">
                                    <i class="bi bi-image"></i>
                                    <span>Product image unavailable</span>
                                </div>

                            <?php else: ?>

                                <div class="product-placeholder">
                                    <i class="bi bi-basket2"></i>
                                    <span>Product image coming soon</span>
                                </div>

                            <?php endif; ?>

                            <span class="product-category">
                                <?= e($product['category_name']) ?>
                            </span>

                        </div>

                        <div class="product-content">

                            <h3 class="product-name">
                                <?= e($product['product_name']) ?>
                            </h3>

                            <div class="product-location">
                                <i class="bi bi-geo-alt-fill"></i>
                                <?= e($product['county']) ?> County
                            </div>

                            <p class="product-description">
                                <?= e($product['description'] ?: 'Quality agricultural produce from a local farm.') ?>
                            </p>

                            <div class="product-price">
                                KSh <?= number_format((float)$product['price'], 2) ?>
                                <span class="product-unit">
                                    / <?= e($product['unit']) ?>
                                </span>
                            </div>

                            <div class="product-stock mt-2">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                Available: <?= e($product['quantity_available']) ?>
                                <?= e($product['unit']) ?>
                            </div>

                            <div class="product-divider"></div>

                            <?php if ((int)$product['product_id'] > 0): ?>

                                <a class="product-button"
                                   href="product-details.php?id=<?= (int)$product['product_id'] ?>">
                                    View Details
                                    <i class="bi bi-arrow-right ms-1"></i>
                                </a>

                            <?php else: ?>

                                <a class="product-button" href="products.php">
                                    Browse Products
                                    <i class="bi bi-arrow-right ms-1"></i>
                                </a>

                            <?php endif; ?>

                        </div>

                    </article>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="text-center mt-5">
            <a href="products.php" class="view-all-button">
                View All Products
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>

    </div>
</section>


<!-- HOW IT WORKS -->
<section class="section-padding steps-section" id="how-it-works">

    <div class="container">

        <div class="text-center mb-5">
            <span class="section-label">Simple and Convenient</span>

            <h2 class="section-heading">How AgriConnect Works</h2>

            <p class="section-description">
                Our marketplace makes it easier for farmers to display
                their produce and for buyers to discover available products.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">
                        <i class="bi bi-person-plus"></i>
                    </div>

                    <h3>1. Create an Account</h3>

                    <p>
                        Register as a farmer to list your produce or as a
                        buyer to explore agricultural products.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">
                        <i class="bi bi-search"></i>
                    </div>

                    <h3>2. Discover Produce</h3>

                    <p>
                        Browse available products, compare prices and
                        find produce that suits your needs.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">
                        <i class="bi bi-handshake"></i>
                    </div>

                    <h3>3. Connect and Order</h3>

                    <p>
                        Find product information and use the marketplace's
                        ordering features to request available produce.
                    </p>
                </div>
            </div>

        </div>

    </div>

</section>


<!-- FARMER CALL TO ACTION -->
<section class="cta-section">
    <div class="container">

        <div class="cta-box">

            <div>
                <h2>Are You a Farmer?</h2>

                <p>
                    Showcase your produce, manage your listings and help
                    more buyers discover what your farm has to offer.
                </p>
            </div>

            <a href="register.php" class="btn btn-yellow">
                Join AgriConnect
                <i class="bi bi-arrow-right ms-1"></i>
            </a>

        </div>

    </div>
</section>


<!-- TESTIMONIALS -->
<section class="section-padding testimonials-section" id="testimonials">

    <div class="container">

        <div class="text-center mb-5">
            <span class="section-label">Community Voices</span>

            <h2 class="section-heading">What Our Community Says</h2>

            <p class="section-description">
                We aim to make discovering and trading agricultural
                produce easier for farmers and buyers.
            </p>
        </div>

        <div class="row g-4">

            <?php if (!empty($testimonials)): ?>

                <?php foreach ($testimonials as $testimonial): ?>

                    <div class="col-md-4">

                        <div class="testimonial-card">

                            <div class="testimonial-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi <?= $i <= (int)$testimonial['rating']
                                        ? 'bi-star-fill'
                                        : 'bi-star' ?>"></i>
                                <?php endfor; ?>
                            </div>

                            <p class="testimonial-message">
                                "<?= e($testimonial['message']) ?>"
                            </p>

                            <div class="d-flex align-items-center gap-3">

                                <div class="testimonial-avatar">
                                    <?= e(strtoupper(substr($testimonial['full_name'], 0, 1))) ?>
                                </div>

                                <div>
                                    <div class="testimonial-name">
                                        <?= e($testimonial['full_name']) ?>
                                    </div>
                                    <div class="testimonial-role">
                                        AgriConnect community member
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>

                        <p class="testimonial-message">
                            "AgriConnect aims to make it easier to discover
                            fresh agricultural produce from local farms."
                        </p>

                        <div class="testimonial-name">Our Mission</div>
                        <div class="testimonial-role">Supporting local agriculture</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>

                        <p class="testimonial-message">
                            "Our goal is to help farmers showcase their
                            produce and reach more potential buyers."
                        </p>

                        <div class="testimonial-name">Our Vision</div>
                        <div class="testimonial-role">Connecting farmers and markets</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>

                        <p class="testimonial-message">
                            "We want agricultural marketplace information
                            to be simple, accessible and useful."
                        </p>

                        <div class="testimonial-name">Our Commitment</div>
                        <div class="testimonial-role">A user-friendly marketplace</div>
                    </div>
                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- BRANCH LOCATIONS -->
<section class="section-padding branches-section" id="branches">

    <div class="container">

        <div class="text-center mb-5">
            <span class="section-label">Our Reach</span>

            <h2 class="section-heading">Explore Farming Regions</h2>

            <p class="section-description">
                Discover agricultural produce from different farming
                regions across Kenya.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-md-6 col-lg-4">
                <div class="branch-card">
                    <div class="branch-icon">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div>
                        <h3>Rift Valley</h3>
                        <p>Known for cereals, potatoes and many other crops.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="branch-card">
                    <div class="branch-icon">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div>
                        <h3>Central Kenya</h3>
                        <p>A region with vegetables, fruits and dairy farming.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="branch-card">
                    <div class="branch-icon">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div>
                        <h3>Western Kenya</h3>
                        <p>Agricultural produce including maize, beans and vegetables.</p>
                    </div>
                </div>
            </div>

        </div>

    </div>

</section>

<!-- CONNECT WITH FARMERS CTA -->
<section class="container my-5">
    <div style="background-color: #075B3D;
                border-radius: 12px;
                padding: 34px 40px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                flex-wrap: wrap;">

        <!-- LEFT: SMALL PICTURE AND MESSAGE -->
        <div style="display: flex;
                    align-items: center;
                    gap: 18px;
                    flex: 1;
                    min-width: 240px;">

            <img src="images/farmer.jpeg"
                 alt="Local farmers"
                 style="width: 85px;
                        height: 75px;
                        object-fit: cover;
                        border-radius: 8px;
                        flex-shrink: 0;">

            <div>
                <h3 style="color: #FFFFFF;
                           font-size: 22px;
                           font-weight: 700;
                           margin-bottom: 6px;">
                    Ready to Connect with Farmers?
                </h3>

                <p style="color: #E5F2E9;
                          font-size: 14px;
                          margin-bottom: 0;">
                    Discover fresh produce and connect directly
                    with trusted local farmers.
                </p>
            </div>
        </div>

        <!-- RIGHT: YELLOW BUTTON -->
        <div>
            <a href="products.php"
               style="display: inline-block;
                      background-color: #F5CF32;
                      color: #06452F;
                      padding: 13px 20px;
                      border-radius: 8px;
                      font-weight: 700;
                      text-decoration: none;
                      white-space: nowrap;">
                Browse Catalogue
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>

    </div>
</section>
<!-- FOOTER -->
<footer class="main-footer" id="contact">

    <div class="container">

        <div class="row g-4">

            <div class="col-lg-4 col-md-6">

                <a href="index.php" class="footer-brand">
                    <i class="bi bi-leaf-fill me-2"></i>
                    Agri<span>Connect</span>
                </a>

                <p class="footer-description">
                    Connecting farmers and buyers through a simple
                    agricultural marketplace. Discover fresh produce,
                    explore local farms and support Kenyan agriculture.
                </p>

                <div class="mt-4">
                    <a href="#" class="social-link" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>

                    <a href="#" class="social-link" aria-label="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>

                    <a href="#" class="social-link" aria-label="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                </div>

            </div>

            <div class="col-lg-2 col-md-6">

                <h3 class="footer-heading">Quick Links</h3>

                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php">Browse Products</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#branches">Farming Regions</a></li>
                </ul>

            </div>

            <div class="col-lg-3 col-md-6">

                <h3 class="footer-heading">Account</h3>

                <ul class="footer-links">
                    <li><a href="register.php">Create an Account</a></li>
                    <li><a href="register.php">Register as a Farmer</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="products.php">Explore Produce</a></li>
                </ul>

            </div>

            <div class="col-lg-3 col-md-6">

                <h3 class="footer-heading">Contact Us</h3>

                <div class="footer-contact">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Kenya</span>
                </div>

                <div class="footer-contact">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Contact information coming soon</span>
                </div>

                <div class="footer-contact">
                    <i class="bi bi-clock-fill"></i>
                    <span>Supporting local agricultural trade</span>
                </div>

            </div>

        </div>

        <div class="footer-bottom">
            <div class="row align-items-center g-2">

                <div class="col-md-6">
                    &copy; <?= date('Y') ?> AgriConnect. All rights reserved.
                </div>

                <div class="col-md-6 text-md-end">
                    Connecting Farmers. Growing Opportunities.
                </div>

            </div>
        </div>

    </div>

</footer>


<!-- BACK TO TOP BUTTON -->
<button type="button" class="back-to-top" id="backToTop"
        aria-label="Back to top">
    <i class="bi bi-arrow-up"></i>
</button>


<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Back-to-top button
    const backToTop = document.getElementById("backToTop");

    window.addEventListener("scroll", function () {
        if (window.scrollY > 350) {
            backToTop.classList.add("show");
        } else {
            backToTop.classList.remove("show");
        }
    });

    backToTop.addEventListener("click", function () {
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });

    // Simple live filtering of the featured cards currently on the homepage.
    // The main Search button submits to products.php for full catalogue search.
    const productSearch = document.getElementById("productSearch");
    const productItems = document.querySelectorAll(".product-item");

    productSearch.addEventListener("input", function () {
        const searchTerm = this.value.trim().toLowerCase();

        productItems.forEach(function (item) {
            const name = item.dataset.name || "";
            const category = item.dataset.category || "";
            const county = item.dataset.county || "";

            item.style.display =
                name.includes(searchTerm) ||
                category.includes(searchTerm) ||
                county.includes(searchTerm)
                    ? ""
                    : "none";
        });
    });
    // AgriConnect animated hero slider
(function () {
    const hero = document.querySelector(".hero-section");
    if (!hero) return;

    const slides = Array.from(hero.querySelectorAll(".hero-slide"));
    const dots = Array.from(hero.querySelectorAll(".hero-dot"));
    const previousButton = document.getElementById("heroPrev");
    const nextButton = document.getElementById("heroNext");

    if (slides.length === 0) return;

    let currentSlide = 0;
    let sliderTimer = null;

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    );

    function showSlide(index) {
        currentSlide = (index + slides.length) % slides.length;

        slides.forEach(function (slide, i) {
            const isActive = i === currentSlide;

            slide.classList.toggle("active", isActive);
            slide.setAttribute("aria-hidden", String(!isActive));
        });

        dots.forEach(function (dot, i) {
            const isActive = i === currentSlide;

            dot.classList.toggle("active", isActive);

            if (isActive) {
                dot.setAttribute("aria-current", "true");
            } else {
                dot.removeAttribute("aria-current");
            }
        });
    }

    function stopAutoSlide() {
        if (sliderTimer !== null) {
            clearInterval(sliderTimer);
            sliderTimer = null;
        }
    }

    function startAutoSlide() {
        stopAutoSlide();

        if (reducedMotion.matches || document.hidden) return;

        sliderTimer = setInterval(function () {
            showSlide(currentSlide + 1);
        }, 5500);
    }

    previousButton.addEventListener("click", function () {
        showSlide(currentSlide - 1);
        startAutoSlide();
    });

    nextButton.addEventListener("click", function () {
        showSlide(currentSlide + 1);
        startAutoSlide();
    });

    dots.forEach(function (dot, index) {
        dot.addEventListener("click", function () {
            showSlide(index);
            startAutoSlide();
        });
    });

    hero.addEventListener("mouseenter", stopAutoSlide);
    hero.addEventListener("mouseleave", startAutoSlide);

    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            stopAutoSlide();
        } else {
            startAutoSlide();
        }
    });

    reducedMotion.addEventListener("change", startAutoSlide);

    showSlide(0);
    startAutoSlide();
})();
</script>

</body>
</html>
