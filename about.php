
<?php
require_once "db.php";

$site_name = "AgriConnect";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | <?php echo $site_name; ?></title>

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
            line-height: 1.7;
        }

        a {
            text-decoration: none;
        }

        /* NAVBAR */
        .main-navbar {
            background: var(--dark-green);
            padding: 14px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--white) !important;
            font-size: 23px;
            font-weight: 800;
        }

        .brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: var(--yellow);
            color: var(--deep-green);
        }

        .brand-name span {
            color: var(--yellow);
        }

        .main-navbar .nav-link {
            color: rgba(255, 255, 255, 0.9);
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

        /* PAGE HERO */
        .about-hero {
            background: var(--pale-green);
            padding: 75px 0;
            overflow: hidden;
        }

        .eyebrow {
            display: inline-block;
            background: var(--soft-green);
            color: var(--dark-green);
            border-radius: 30px;
            padding: 7px 15px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .about-hero h1 {
            font-size: clamp(34px, 5vw, 52px);
            font-weight: 800;
            line-height: 1.2;
            color: var(--deep-green);
            margin-bottom: 20px;
        }

        .about-hero h1 span {
            color: var(--main-green);
        }

        .about-hero p {
            color: var(--text-muted);
            font-size: 17px;
            max-width: 560px;
            margin-bottom: 25px;
        }

        .hero-image {
            width: 100%;
            height: 370px;
            object-fit: cover;
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .primary-btn {
            display: inline-block;
            background: var(--dark-green);
            color: var(--white);
            border-radius: 8px;
            padding: 13px 22px;
            font-weight: 700;
            transition: 0.3s;
        }

        .primary-btn:hover {
            background: var(--main-green);
            color: var(--white);
        }

        .yellow-btn {
            display: inline-block;
            background: var(--yellow);
            color: var(--deep-green);
            border-radius: 8px;
            padding: 13px 22px;
            font-weight: 700;
            transition: 0.3s;
        }

        .yellow-btn:hover {
            background: #E8BD20;
            color: var(--deep-green);
        }

        /* GENERAL SECTIONS */
        .section-padding {
            padding: 75px 0;
        }

        .section-label {
            color: var(--main-green);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
        }

        .section-title {
            color: var(--deep-green);
            font-size: clamp(28px, 4vw, 38px);
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 18px;
        }

        .section-description {
            color: var(--text-muted);
            max-width: 700px;
            margin: 0 auto;
        }

        .story-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 14px;
        }

        .story-text {
            color: var(--text-muted);
        }

        /* MISSION AND VISION */
        .purpose-section {
            background: var(--pale-green);
        }

        .purpose-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 30px;
            height: 100%;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }

        .purpose-card:hover {
            transform: translateY(-5px);
        }

        .purpose-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 55px;
            height: 55px;
            border-radius: 12px;
            background: var(--soft-green);
            color: var(--dark-green);
            font-size: 25px;
            margin-bottom: 20px;
        }

        .purpose-card h3 {
            color: var(--deep-green);
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .purpose-card p {
            color: var(--text-muted);
            margin-bottom: 0;
        }

        /* VALUES */
        .value-card {
            padding: 25px 20px;
            border: 1px solid var(--border);
            border-radius: 12px;
            height: 100%;
            background: var(--white);
        }

        .value-card i {
            color: var(--main-green);
            font-size: 30px;
            margin-bottom: 15px;
            display: inline-block;
        }

        .value-card h4 {
            color: var(--deep-green);
            font-size: 18px;
            font-weight: 800;
        }

        .value-card p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 0;
        }

        /* CTA */
        .about-cta {
            background: var(--dark-green);
            border-radius: 12px;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .about-cta h3 {
            color: var(--white);
            font-size: 23px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .about-cta p {
            color: #E5F2E9;
            margin-bottom: 0;
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
            color: rgba(255, 255, 255, 0.8);
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
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            margin-top: 30px;
            padding-top: 18px;
        }

        @media (max-width: 991.98px) {
            .main-navbar .nav-login,
            .main-navbar .nav-register {
                margin-top: 8px;
            }

            .about-hero {
                padding: 50px 0;
            }

            .hero-image {
                height: 300px;
            }

            .story-image {
                height: 320px;
            }
        }

        @media (max-width: 575.98px) {
            .section-padding {
                padding: 50px 0;
            }

            .about-cta {
                padding: 22px;
            }

            .about-cta h3 {
                font-size: 20px;
            }

            .hero-image {
                height: 250px;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
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
                    <a class="nav-link" href="index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="about.php">About Us</a>
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

<!-- ABOUT HERO -->
<section class="about-hero">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-6">
                <span class="eyebrow">
                    <i class="bi bi-leaf me-1"></i>
                    About AgriConnect
                </span>

                <h1>
                    Connecting Farmers to
                    <span>Better Opportunities</span>
                </h1>

                <p>
                    AgriConnect is an agricultural marketplace designed to
                    help farmers showcase their produce and connect with
                    buyers looking for fresh agricultural products.
                </p>

                <p>
                    We aim to make it easier for farmers and buyers to discover
                    one another through a convenient online platform.
                </p>

                <a href="products.php" class="primary-btn">
                    Explore Produce <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>

            <div class="col-lg-6">
                <img src="images/hero_farmer.jpg"
                     alt="Agriculture and farming"
                     class="hero-image">
            </div>

        </div>
    </div>
</section>

<!-- OUR STORY -->
<section class="section-padding">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-6">
                <img src="images/hero.jpg"
                     alt="Fresh agriculture and farming"
                     class="story-image">
            </div>

            <div class="col-lg-6">
                <div class="section-label">Our Story</div>

                <h2 class="section-title">
                    Making Agricultural Connections Easier
                </h2>

                <p class="story-text">
                    Agriculture plays an important role in food supply,
                    employment and livelihoods. However, farmers and buyers
                    may find it difficult to discover suitable trading
                    opportunities and communicate with one another.
                </p>

                <p class="story-text">
                    AgriConnect was designed to help bridge this gap by
                    providing a digital space where farmers can present their
                    produce and buyers can explore available agricultural
                    products.
                </p>

                <p class="story-text">
                    Our goal is to support more convenient connections,
                    improve product visibility and contribute to a more
                    connected agricultural marketplace.
                </p>
            </div>

        </div>
    </div>
</section>

<!-- MISSION AND VISION -->
<section class="purpose-section section-padding">
    <div class="container">

        <div class="text-center mb-5">
            <div class="section-label">Our Purpose</div>
            <h2 class="section-title">What Guides AgriConnect</h2>
            <p class="section-description">
                Our work is guided by the goal of creating better connections
                between agricultural producers and the people who buy their produce.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-md-6">
                <div class="purpose-card">
                    <div class="purpose-icon">
                        <i class="bi bi-bullseye"></i>
                    </div>

                    <h3>Our Mission</h3>

                    <p>
                        To provide an accessible digital marketplace that
                        helps farmers showcase their agricultural products
                        and enables buyers to discover available produce
                        more conveniently.
                    </p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="purpose-card">
                    <div class="purpose-icon">
                        <i class="bi bi-eye"></i>
                    </div>

                    <h3>Our Vision</h3>

                    <p>
                        To become a trusted digital connection point for
                        farmers and buyers, contributing to a more accessible,
                        connected and efficient agricultural marketplace.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- OUR VALUES -->
<section class="section-padding">
    <div class="container">

        <div class="text-center mb-5">
            <div class="section-label">What Matters to Us</div>
            <h2 class="section-title">Our Core Values</h2>
            <p class="section-description">
                The principles that shape the AgriConnect experience.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-sm-6 col-lg-3">
                <div class="value-card">
                    <i class="bi bi-people"></i>
                    <h4>Community</h4>
                    <p>
                        Building meaningful connections between farmers
                        and buyers.
                    </p>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="value-card">
                    <i class="bi bi-shield-check"></i>
                    <h4>Trust</h4>
                    <p>
                        Encouraging clear product information and
                        responsible interactions.
                    </p>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="value-card">
                    <i class="bi bi-lightbulb"></i>
                    <h4>Innovation</h4>
                    <p>
                        Using digital tools to make agricultural
                        connections more convenient.
                    </p>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="value-card">
                    <i class="bi bi-tree"></i>
                    <h4>Sustainability</h4>
                    <p>
                        Supporting the people and communities who
                        contribute to our food systems.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- CALL TO ACTION BEFORE FOOTER -->
<section class="container my-5">
    <div class="about-cta">

        <div style="display: flex; align-items: center; gap: 16px;">
            <img src="images/farm-hero.jpg"
                 alt="Agriculture"
                 style="width: 80px; height: 75px;
                        object-fit: cover; border-radius: 8px;">

            <div>
                <h3>Ready to Connect with Farmers?</h3>
                <p>
                    Discover fresh produce and explore the catalogue.
                </p>
            </div>
        </div>

        <a href="products.php" class="yellow-btn">
            Browse Catalogue <i class="bi bi-arrow-right ms-2"></i>
        </a>

    </div>
</section>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="container">

        <div class="row g-4">

            <div class="col-lg-4 col-md-6">
                <h5>
                    <i class="bi bi-leaf-fill me-2"></i>AgriConnect
                </h5>

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
                    <li><a href="products.php">Produce Catalogue</a></li>
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
                    Helping agricultural producers and buyers discover
                    opportunities to connect.
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
