<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f97316">
    <title>500 - Internal Server Error | GIMT</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="/php/images/images.jpg">
    <link rel="apple-touch-icon" href="/php/images/images.jpg">

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --gimt-orange: #f97316;
            --gimt-orange-dark: #c2410c;
            --gimt-ink: #382418;
            --gimt-muted: #735f50;
        }

        body {
            color: var(--gimt-ink);
            background-color: #fffaf5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-x: hidden;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .error-container {
            padding: 3rem 1rem;
            max-width: 650px;
            margin: auto;
        }

        .error-code {
            font-size: clamp(5rem, 15vw, 8rem);
            font-weight: 900;
            color: var(--gimt-orange-dark);
            line-height: 1;
            letter-spacing: -0.04em;
            text-shadow: 0 10px 20px rgba(194, 65, 12, 0.1);
        }

        .error-icon-wrapper {
            display: inline-grid;
            width: 5rem;
            height: 5rem;
            place-items: center;
            background: #ffedd5;
            color: var(--gimt-orange-dark);
            border-radius: 50%;
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(249, 115, 22, 0.15);
        }

        .btn-orange {
            color: #fff;
            background: var(--gimt-orange-dark);
            border-color: var(--gimt-orange-dark);
            font-weight: 700;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            transition: all 0.2s ease;
        }

        .btn-orange:hover,
        .btn-orange:focus {
            color: #fff;
            background: #9a3412;
            border-color: #9a3412;
            box-shadow: 0 5px 15px rgba(154, 52, 18, 0.25);
        }

        .btn-outline-orange {
            color: var(--gimt-orange-dark);
            border-color: #fed7aa;
            background: #ffffff;
            font-weight: 700;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            transition: all 0.2s ease;
        }

        .btn-outline-orange:hover {
            background: #fff7ed;
            color: var(--gimt-orange-dark);
            border-color: #f97316;
        }

        footer {
            color: var(--gimt-muted);
            background: #ffffff;
            border-top: 1px solid #ffedd5;
            font-size: 0.88rem;
        }
    </style>
</head>

<body>

    <!-- Header Section -->
    <header class="py-3 bg-white border-bottom">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none">
                <img src="/php/images/images.jpg" alt="GIMT Logo" style="height: 48px; width: auto; object-fit: contain;">
                <span class="fw-bold text-dark d-none d-sm-inline fs-6">Global Institute of Management &amp; Technology</span>
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-orange">
                <i class="bi bi-house me-1"></i> Home
            </a>
        </div>
    </header>

    <!-- Error Main Content -->
    <main class="error-container text-center">
        <div class="error-icon-wrapper">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>

        <div class="error-code">500</div>

        <h1 class="h3 fw-bold mt-3 mb-2 text-dark">Internal Server Error</h1>
        <p class="text-muted fs-6 mb-4">
            Oops! Something went wrong on our end. Our technical team has been notified and is working to resolve the issue.
        </p>

        <!-- Call to Action Buttons -->
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <button onclick="window.location.reload();" class="btn btn-orange">
                <i class="bi bi-arrow-clockwise me-1"></i> Try Refreshing
            </button>
            <a href="index.php" class="btn btn-outline-orange">
                <i class="bi bi-arrow-left me-1"></i> Back to Homepage
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-3 text-center">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Global Institute of Management &amp; Technology. All rights reserved.</p>
        </div>
    </footer>

</body>

</html>