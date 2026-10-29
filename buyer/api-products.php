
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AgriConnect | API Products</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --dark-green: #075B3D;
            --fresh-green: #13804F;
            --cream: #F7FAF4;
            --soft-green: #EFF6E9;
            --text: #25372D;
            --yellow: #F5CF32;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: var(--dark-green);
        }

        .hero {
            background: var(--soft-green);
            padding: 35px 20px;
            border-radius: 16px;
        }

        .text-green {
            color: var(--dark-green);
        }

        .product-card {
            background: white;
            border: 1px solid #e1e9df;
            border-radius: 14px;
            height: 100%;
            overflow: hidden;
        }

        .product-card img {
            width: 100%;
            height: 190px;
            object-fit: cover;
            background: var(--soft-green);
        }

        .product-card .card-body {
            padding: 18px;
        }

        .btn-green {
            background: var(--dark-green);
            color: white;
            border: none;
        }

        .btn-green:hover {
            background: var(--fresh-green);
            color: white;
        }

        .price {
            color: var(--fresh-green);
            font-weight: bold;
            font-size: 1.15rem;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            AgriConnect
        </a>
        <span class="text-white">Products API Demo</span>
    </div>
</nav>

<main class="container py-4">

    <section class="hero mb-4">
        <h1 class="text-green fw-bold">Fresh Farm Products</h1>

        <p class="mb-3">
            Explore available agricultural products retrieved directly
            from the AgriConnect backend API.
        </p>

        <button class="btn btn-green" id="loadProducts">
            Load Products
        </button>
    </section>

    <div id="apiStatus" class="alert alert-info" role="status">
        Click "Load Products" to retrieve products from the API.
    </div>

    <div class="row g-4" id="productList"></div>

</main>

<script>
const apiUrl = "../api/products.php";

const loadButton = document.getElementById("loadProducts");
const apiStatus = document.getElementById("apiStatus");
const productList = document.getElementById("productList");

loadButton.addEventListener("click", loadProducts);

async function loadProducts() {
    loadButton.disabled = true;
    apiStatus.className = "alert alert-info";
    apiStatus.textContent = "Loading products from the backend API...";
    productList.replaceChildren();

    try {
        const response = await fetch(apiUrl);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Could not retrieve products.");
        }

        apiStatus.className = "alert alert-success";
        apiStatus.textContent =
            `API connection successful. ${data.count} product(s) retrieved.`;

        if (data.products.length === 0) {
            apiStatus.className = "alert alert-warning";
            apiStatus.textContent =
                "The API connected successfully, but no approved products are available.";
            return;
        }

        data.products.forEach(product => {
            const column = document.createElement("div");
            column.className = "col-12 col-sm-6 col-lg-4";

            const card = document.createElement("div");
            card.className = "product-card";

            const image = document.createElement("img");
            image.alt = product.product_name;
            image.src = product.image
                ? "../" + product.image.replace(/^\/+/, "")
                : "";
            image.addEventListener("error", () => {
                image.hidden = true;
            });

            const body = document.createElement("div");
            body.className = "card-body";

            const name = document.createElement("h5");
            name.className = "text-green fw-bold";
            name.textContent = product.product_name;

            const description = document.createElement("p");
            description.textContent =
                product.description || "No description provided.";

            const price = document.createElement("p");
            price.className = "price";
            price.textContent =
                `KSh ${Number(product.price).toLocaleString()} / ${product.unit}`;

            const quantity = document.createElement("p");
            quantity.textContent =
                `Available: ${product.quantity_available} ${product.unit}`;

            const location = document.createElement("p");
            location.textContent =
                `Location: ${product.location || "Not specified"}`;

            body.append(name, description, price, quantity, location);
            card.append(image, body);
            column.append(card);
            productList.append(column);
        });

    } catch (error) {
        apiStatus.className = "alert alert-danger";
        apiStatus.textContent =
            "API request failed: " + error.message +
            " Check that Apache, MySQL and the API file are working.";
    } finally {
        loadButton.disabled = false;
    }
}
</script>

</body>
</html>
