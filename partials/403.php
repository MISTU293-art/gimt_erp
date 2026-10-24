<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden Access | GIMT ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; background: #0f172a; color: #f8fafc; display: grid; place-content: center; height: 100vh; margin: 0; text-align: center; }
        .error-code { font-size: 5rem; font-weight: 800; color: #ef4444; line-height: 1; }
        .error-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 40px; max-width: 480px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code mb-2">403</div>
        <h1 class="h4 fw-bold mb-3">Access Restricted</h1>
        <p class="text-secondary mb-4">Your current role does not have authorization to view or perform operations in this ERP sector.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="javascript:history.back()" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Go Back</a>
            <a href="/public/admin_login.php" class="btn btn-primary"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
        </div>
    </div>
</body>
</html>
