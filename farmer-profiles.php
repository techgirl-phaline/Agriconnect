
<?php
require_once "db.php";

/*
|--------------------------------------------------------------------------
| AgriConnect - Public Farmer Profiles
|--------------------------------------------------------------------------
| Uses the existing users and farmer_profiles tables.
| Assumes db.php creates a MySQLi connection named $conn.
*/

function escapeHTML($value)
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$search = trim($_GET["search"] ?? "");
$county = trim($_GET["county"] ?? "");
$error_message = "";
$farmers = [];

$sql = "SELECT
            fp.farmer_id,
            fp.user_id,
            fp.farm_name,
            fp.county,
            fp.sub_county,
            fp.farm_location,
            fp.bio,
            fp.created_at,
            u.full_name
        FROM farmer_profiles AS fp
        INNER JOIN users AS u ON fp.user_id = u.user_id
        WHERE u.role = 'farmer'";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (
        u.full_name LIKE ?
        OR fp.farm_name LIKE ?
        OR fp.county LIKE ?
        OR fp.sub_county LIKE ?
        OR fp.farm_location LIKE ?
    )";

    $keyword = "%" . $search . "%";

    $params = array_merge($params, [
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ]);

    $types .= "sssss";
}

if ($county !== "") {
    $sql .= " AND fp.county = ?";
    $params[] = $county;
    $types .= "s";
}

$sql .= " ORDER BY fp.created_at DESC";

try {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception("Could not prepare the farmer profiles query.");
    }

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Could not retrieve farmer profiles.");
    }

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $farmers[] = $row;
    }

    mysqli_stmt_close($stmt);

    // Get available counties for the filter.
    $county_result = mysqli_query(
        $conn,
        "SELECT DISTINCT county
         FROM farmer_profiles
         WHERE county IS NOT NULL AND TRIM(county) <> ''
         ORDER BY county ASC"
    );

    $counties = [];

    if ($county_result) {
        while ($row = mysqli_fetch_assoc($county_result)) {
            $counties[] = $row["county"];
        }
    }
} catch (Throwable $e) {
    error_log("AgriConnect farmer profiles: " . $e->getMessage());
    $error_message = "We could not load farmer profiles. Please check your database connection and try again.";
    $counties = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Farmer Profiles | AgriConnect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
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
            background: var(--pale-green);
            color: var(--text-dark);
            font-family: Arial, Helvetica, sans-serif;
        }
        .marquee-bar {
    background: #FFF9C4;
    color: var(--deep-green);
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

         .navbar {
    background: var(--dark-green);
    padding: 8px 0;
    min-height: 60px;
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--white) !important;
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -0.5px;
    padding: 0;
    line-height: 1;
}

.brand-logo {
    width: 30px;
    height: 30px;
    object-fit: contain;
    display: block;
    flex-shrink: 0;
}
        .navbar-brand:hover {
            color: var(--yellow);
        }

        .navbar .nav-link {
            color: var(--white);
            margin-left: 12px;
            font-weight: 500;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            color: var(--yellow);
        }

        .hero {
            background: linear-gradient(135deg, #06452F, #13804F);
            color: white;
            padding: 65px 20px;
            text-align: center;
        }

        .hero-label {
            display: inline-block;
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            padding: 8px 15px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .hero h1 {
            font-weight: 800;
            font-size: clamp(30px, 5vw, 46px);
            margin-bottom: 15px;
        }

        .hero p {
            max-width: 680px;
            margin: auto;
            color: #E4F0E8;
            line-height: 1.8;
        }

        .main-content {
            padding-top: 45px;
            padding-bottom: 55px;
        }

        .section-title {
            color: var(--dark-green);
            font-weight: 800;
        }

        .search-panel {
            background: white;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-color: var(--border);
            border-radius: 9px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--main-green);
            box-shadow: 0 0 0 0.2rem rgba(19, 128, 79, 0.12);
        }

        .btn-green {
            background: var(--main-green);
            border: 1px solid var(--main-green);
            color: white;
            border-radius: 9px;
            padding: 11px 18px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-green:hover {
            background: var(--deep-green);
            border-color: var(--deep-green);
            color: white;
        }

        .btn-outline-green {
            background: white;
            border: 1px solid var(--main-green);
            color: var(--main-green);
            border-radius: 9px;
            padding: 10px 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-outline-green:hover {
            background: var(--soft-green);
            color: var(--deep-green);
        }

        .farmer-card {
            height: 100%;
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .farmer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 13px 32px rgba(7, 91, 61, 0.13);
        }

        .card-top {
            background: linear-gradient(135deg, #EFF6E9, #E0F0DF);
            padding: 26px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }

        .farmer-avatar {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            margin: 0 auto 13px;
            background: white;
            color: var(--main-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            border: 3px solid white;
            box-shadow: 0 3px 12px rgba(7, 91, 61, 0.1);
        }

        .farm-name {
            color: var(--dark-green);
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .farmer-name {
            color: var(--text-muted);
            font-size: 14px;
        }

        .card-body-custom {
            padding: 23px;
        }

        .location-line {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .location-icon {
            color: var(--main-green);
            font-weight: bold;
        }

        .county-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: var(--soft-green);
            color: var(--dark-green);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 13px;
        }

        .farm-bio {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.8;
            overflow-wrap: anywhere;
            white-space: pre-line;
        }

        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 20px;
        }

        .card-actions a {
            flex: 1 1 120px;
        }

        .empty-state {
            background: white;
            text-align: center;
            border: 1px dashed #B9CFBE;
            border-radius: 16px;
            padding: 50px 20px;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .error-box {
            border-radius: 10px;
            padding: 15px;
            background: #FFF3CD;
            color: #664D03;
        }

        footer {
            background: var(--deep-green);
            color: #E4F0E8;
            padding: 25px 15px;
            text-align: center;
        }

        footer strong {
            color: white;
        }

        @media (max-width: 576px) {
            .hero {
                padding: 45px 17px;
            }

            .main-content {
                padding-top: 30px;
            }

            .search-panel {
                padding: 16px;
            }

            .card-actions a {
                flex-basis: 100%;
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
       <a class="navbar-brand" href="index.php">
    <img src="images/logo.png" alt="AgriConnect Logo" class="brand-logo">
    <span>AgriConnect</span>
</a>
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="produce-catalogue.php">
                        Produce Catalogue
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="farmer-profiles.php">
                        Farmer Profiles
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="about.php">About Us</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<header class="hero">
    <div class="container">
        <div class="hero-label">GROWING TOGETHER, CONNECTING MARKETS</div>

        <h1>Meet Our Farmers</h1>

        <p>
            Discover the people behind your fresh produce.
            Explore local farms, learn about our farming community,
            and find farmers across Kenya.
        </p>
    </div>
</header>

<main class="container main-content">

    <div class="d-flex justify-content-between align-items-md-end
                flex-wrap gap-3 mb-4">
        <div>
            <h2 class="section-title mb-2">Our Farmer Community</h2>
            <p class="text-secondary mb-0">
                Find a farm by name, farmer or location.
            </p>
        </div>

        <a href="produce-catalogue.php" class="btn-green">
            Browse Produce →
        </a>
    </div>

    <?php if ($error_message !== ""): ?>

        <div class="error-box mb-4" role="alert">
            <?php echo escapeHTML($error_message); ?>
        </div>

    <?php else: ?>

        <section class="search-panel" aria-label="Search farmer profiles">
            <form method="GET" action="farmer-profiles.php">
                <div class="row g-3 align-items-end">

                    <div class="col-12 col-md-6">
                        <label for="search" class="form-label fw-semibold">
                            Search farmers
                        </label>

                        <input
                            type="search"
                            class="form-control"
                            id="search"
                            name="search"
                            placeholder="Farmer name, farm name or location"
                            value="<?php echo escapeHTML($search); ?>">
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="county" class="form-label fw-semibold">
                            Filter by county
                        </label>

                        <select class="form-select" id="county" name="county">
                            <option value="">All counties</option>

                            <?php foreach ($counties as $county_option): ?>
                                <option
                                    value="<?php echo escapeHTML($county_option); ?>"
                                    <?php echo $county === $county_option ? "selected" : ""; ?>>
                                    <?php echo escapeHTML($county_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-grid">
                        <button type="submit" class="btn-green">
                            Search
                        </button>
                    </div>

                </div>

                <?php if ($search !== "" || $county !== ""): ?>
                    <div class="mt-3">
                        <a href="farmer-profiles.php" class="text-success">
                            Clear search and filters
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </section>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="mb-0 text-secondary">
                <?php echo count($farmers); ?>
                farmer profile<?php echo count($farmers) === 1 ? "" : "s"; ?> found
            </p>
        </div>

        <?php if (count($farmers) > 0): ?>

            <div class="row g-4">

                <?php foreach ($farmers as $farmer): ?>
                    <?php
                    $farmerName = trim((string)$farmer["full_name"]);
                    $farmName = trim((string)($farmer["farm_name"] ?? ""));
                    $initial = $farmerName !== ""
                        ? strtoupper(substr($farmerName, 0, 1))
                        : "F";

                    $locationParts = array_filter([
                        $farmer["farm_location"] ?? "",
                        $farmer["sub_county"] ?? "",
                        $farmer["county"] ?? ""
                    ], function ($part) {
                        return trim((string)$part) !== "";
                    });

                    $locationText = !empty($locationParts)
                        ? implode(", ", $locationParts)
                        : "Location not provided";

                    $farmBio = trim((string)($farmer["bio"] ?? ""));
                    ?>

                    <div class="col-12 col-md-6 col-lg-4">
                        <article class="farmer-card">

                            <div class="card-top">
                                <div class="farmer-avatar">
                                    <?php echo escapeHTML($initial); ?>
                                </div>

                                <h3 class="h5 farm-name mb-2">
                                    <?php echo escapeHTML(
                                        $farmName !== "" ? $farmName : "Farm Name Not Provided"
                                    ); ?>
                                </h3>

                                <p class="farmer-name mb-0">
                                    Farmer: <?php echo escapeHTML(
                                        $farmerName !== "" ? $farmerName : "Name not provided"
                                    ); ?>
                                </p>
                            </div>

                            <div class="card-body-custom">

                                <div class="location-line">
                                    <span class="location-icon">📍</span>
                                    <span><?php echo escapeHTML($locationText); ?></span>
                                </div>

                                <?php if (!empty($farmer["county"])): ?>
                                    <span class="county-badge">
                                        <?php echo escapeHTML($farmer["county"]); ?> County
                                    </span>
                                <?php endif; ?>

                                <p class="farm-bio mb-0">
                                    <?php echo escapeHTML(
                                        $farmBio !== ""
                                            ? $farmBio
                                            : "This farmer has not added a farm description yet."
                                    ); ?>
                                </p>

                                <div class="card-actions">
                                    <a
                                        href="produce-catalogue.php?farmer_id=<?php
                                            echo (int)$farmer["user_id"];
                                        ?>"
                                        class="btn-green">
                                        View Produce
                                    </a>
                                </div>

                            </div>
                        </article>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <div class="empty-icon">🌾</div>

                <h3 class="section-title mb-3">
                    <?php echo ($search !== "" || $county !== "")
                        ? "No Matching Farmers Found"
                        : "No Farmer Profiles Yet"; ?>
                </h3>

                <p class="text-secondary mb-4">
                    <?php if ($search !== "" || $county !== ""): ?>
                        Try another search term or select a different county.
                    <?php else: ?>
                        Farmer profiles will appear here when registered farmers
                        complete and save their farm information.
                    <?php endif; ?>
                </p>

                <?php if ($search !== "" || $county !== ""): ?>
                    <a href="farmer-profiles.php" class="btn-green">
                        View All Farmers
                    </a>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</main>

<footer>
    <p class="mb-1">
        <strong>AgriConnect</strong> — Connecting Farmers to Markets
    </p>
    <small>
        &copy; <?php echo date("Y"); ?> AgriConnect. All rights reserved.
    </small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
