
<?php
include('./middleware/isLoggedIn.php');
include('./config/db.config.php');

$role = 'teacher';

/* Flash messages */
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'info';

unset($_SESSION['flash_message'], $_SESSION['flash_type']); 

/* Fetch teachers */
$teacherStmt = $conn->prepare(
    "SELECT name FROM users
     WHERE role = ?
     ORDER BY name ASC"
);
$teacherStmt->bind_param("s", $role);
$teacherStmt->execute();
$teachers = $teacherStmt->get_result();

/* Fetch departments */
$departmentStmt = $conn->prepare(
    "SELECT dept_name FROM department
     ORDER BY dept_name ASC"
);
$departmentStmt->execute();
$departments = $departmentStmt->get_result();

/* Handle HOD assignment */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $hodName = trim($_POST['hod_name'] ?? '');
    $deptName = trim($_POST['dept_name'] ?? '');

    if ($hodName === '' || $deptName === '') {
        $_SESSION['flash_message'] =
            'Please select both a department and a teacher.';
        $_SESSION['flash_type'] = 'danger';

        header('Location: hod_assign.php');
        exit;
    }

    /* Validate teacher */
    $checkTeacher = $conn->prepare(
        "SELECT name FROM users
         WHERE name = ? AND role = ?"
    );
    $checkTeacher->bind_param("ss", $hodName, $role);
    $checkTeacher->execute();

    if ($checkTeacher->get_result()->num_rows === 0) {
        $_SESSION['flash_message'] = 'Invalid teacher selected.';
        $_SESSION['flash_type'] = 'danger';

        header('Location: hod_assign.php');
        exit;
    }

    /* Validate department */
    $checkDepartment = $conn->prepare(
        "SELECT dept_name FROM department
         WHERE dept_name = ?"
    );
    $checkDepartment->bind_param("s", $deptName);
    $checkDepartment->execute();

    if ($checkDepartment->get_result()->num_rows === 0) {
        $_SESSION['flash_message'] = 'Invalid department selected.';
        $_SESSION['flash_type'] = 'danger';

        header('Location: hod_assign.php');
        exit;
    }

    /* Check whether this department already has an HOD */
    $checkHod = $conn->prepare(
        "SELECT dept_name FROM hod
         WHERE dept_name = ?"
    );
    $checkHod->bind_param("s", $deptName);
    $checkHod->execute();

    if ($checkHod->get_result()->num_rows > 0) {
        $_SESSION['flash_message'] =
            'This department already has an assigned HOD.';
        $_SESSION['flash_type'] = 'warning';

        header('Location: hod_assign.php');
        exit;
    }

    /* Insert HOD assignment */
    $insertHod = $conn->prepare(
        "INSERT INTO hod (dept_name, hod_name)
         VALUES (?, ?)"
    );
    $insertHod->bind_param("ss", $deptName, $hodName);

    if ($insertHod->execute()) {
        $_SESSION['flash_message'] =
            'HOD assigned successfully.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] =
            'Assignment failed. Please try again.';
        $_SESSION['flash_type'] = 'danger';
    }

    header('Location: hod_assign.php');
    exit;
}

/* Fetch existing HOD assignments */
$hodStmt = $conn->prepare(
    "SELECT dept_name, hod_name
     FROM hod
     ORDER BY dept_name ASC"
);
$hodStmt->execute();
$hodResult = $hodStmt->get_result();

$existingHods = [];
while ($hod = $hodResult->fetch_assoc()) {
    $existingHods[] = $hod;
}

$hodCount = count($existingHods);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>HOD Management | College ERP</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f7fb;
            color: #202938;
            font-family: Arial, sans-serif;
        }

        .page-layout {
            display: flex;
            min-height: 100vh;
        }

        .dashboard-main {
            flex: 1;
            min-width: 0;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .breadcrumb-text {
            color: #7b8799;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .page-header h1 {
            font-size: 27px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .page-header p {
            color: #788397;
            font-size: 14px;
            margin: 0;
        }

        .summary-card,
        .content-card {
            background: #fff;
            border: 1px solid #e7ebf2;
            border-radius: 13px;
            box-shadow: 0 4px 18px rgba(20, 35, 60, .04);
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eef2ff;
            color: #405be5;
            font-size: 23px;
        }

        .summary-label {
            color: #788397;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .summary-number {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.2;
        }

        .content-card {
            height: 100%;
            overflow: hidden;
        }

        .content-card-header {
            padding: 21px 23px;
            border-bottom: 1px solid #edf0f5;
        }

        .content-card-header h2 {
            font-size: 17px;
            font-weight: 700;
            margin: 0 0 6px;
        }

        .content-card-header p {
            color: #788397;
            font-size: 12px;
            margin: 0;
        }

        .content-card-body {
            padding: 23px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .form-select {
            min-height: 46px;
            border-radius: 8px;
            border-color: #dce2eb;
            font-size: 14px;
        }

        .form-select:focus {
            border-color: #6679f5;
            box-shadow: 0 0 0 3px rgba(102, 121, 245, .12);
        }

        .field-help {
            margin-top: 7px;
            color: #8490a3;
            font-size: 12px;
        }

        .btn-assign {
            min-height: 43px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .table-responsive {
            width: 100%;
        }

        .hod-table {
            margin: 0;
            white-space: nowrap;
        }

        .hod-table thead th {
            background: #f8f9fc;
            color: #647084;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 15px 18px;
            border-bottom: 1px solid #e9edf4;
        }

        .hod-table tbody td {
            padding: 16px 18px;
            font-size: 13px;
            vertical-align: middle;
            border-bottom: 1px solid #edf0f5;
        }

        .hod-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .teacher-avatar {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #edf2ff;
            color: #405be5;
            font-weight: 700;
            margin-right: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #e8f7ee;
            color: #198754;
            font-size: 11px;
            font-weight: 700;
        }

        .empty-state {
            padding: 45px 20px;
            text-align: center;
            color: #7b8799;
        }

        .empty-state i {
            display: block;
            font-size: 35px;
            margin-bottom: 12px;
            color: #aab4c5;
        }

        .empty-state h3 {
            color: #344054;
            font-size: 16px;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 13px;
            margin-bottom: 0;
        }

        @media (max-width: 991.98px) {
            .dashboard-main {
                padding: 76px 20px 25px;
            }
        }

        @media (max-width: 575.98px) {
            .dashboard-main {
                padding: 72px 14px 22px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .content-card-header,
            .content-card-body {
                padding: 18px;
            }

            .summary-card {
                padding: 16px;
            }

            .form-actions {
                display: flex;
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="page-layout">

    <?php include('./partials/superAdmin_sidebar.php'); ?>

    <main class="dashboard-main">

        <div class="container-fluid p-0">

            <header class="page-header">
                <div class="breadcrumb-text">
                    Dashboard / Departments / HOD Management
                </div>

                <h1>
                    <i class="bi bi-person-badge text-primary me-2"></i>
                    HOD Management
                </h1>

                <p>
                    Assign department heads and manage existing assignments.
                </p>
            </header>

            <!-- Flash message -->
            <?php if ($flashMessage !== ''): ?>
                <div
                    class="alert alert-<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show"
                    role="alert">

                    <?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close">
                    </button>
                </div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="row">
                <div class="col-12 col-sm-6 col-xl-4">

                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="bi bi-people"></i>
                        </div>

                        <div>
                            <div class="summary-label">
                                Total Assigned HODs
                            </div>

                            <div class="summary-number">
                                <?= $hodCount ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Assignment form and existing HOD list -->
            <div class="row g-4">

                <!-- Assign HOD -->
                <div class="col-12 col-xl-5">

                    <section class="content-card">

                        <div class="content-card-header">
                            <h2>
                                <i class="bi bi-person-plus text-primary me-2"></i>
                                Assign New HOD
                            </h2>

                            <p>
                                Select a department and its teacher.
                            </p>
                        </div>

                        <div class="content-card-body">

                            <form action="" method="POST">

                                <div class="mb-4">
                                    <label for="dept_name" class="form-label">
                                        Department
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select
                                        name="dept_name"
                                        id="dept_name"
                                        class="form-select"
                                        required>

                                        <option value="">
                                            Select a department
                                        </option>

                                        <?php while ($department = $departments->fetch_assoc()): ?>
                                            <option
                                                value="<?= htmlspecialchars($department['dept_name'], ENT_QUOTES, 'UTF-8') ?>">

                                                <?= htmlspecialchars($department['dept_name'], ENT_QUOTES, 'UTF-8') ?>

                                            </option>
                                        <?php endwhile; ?>

                                    </select>

                                    <div class="field-help">
                                        Choose the department needing an HOD.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="hod_name" class="form-label">
                                        Teacher Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select
                                        name="hod_name"
                                        id="hod_name"
                                        class="form-select"
                                        required>

                                        <option value="">
                                            Select a teacher
                                        </option>

                                        <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                            <option
                                                value="<?= htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8') ?>">

                                                <?= htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8') ?>

                                            </option>
                                        <?php endwhile; ?>

                                    </select>

                                    <div class="field-help">
                                        Only users with the teacher role are listed.
                                    </div>
                                </div>

                                <div class="form-actions d-flex flex-wrap gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-assign">

                                        <i class="bi bi-check-circle me-2"></i>
                                        Assign HOD
                                    </button>

                                    <button
                                        type="reset"
                                        class="btn btn-light border btn-assign">

                                        <i class="bi bi-arrow-counterclockwise me-2"></i>
                                        Reset
                                    </button>

                                </div>

                            </form>

                        </div>
                    </section>

                </div>

                <!-- Existing HOD details -->
                <div class="col-12 col-xl-7">

                    <section class="content-card">

                        <div class="content-card-header">
                            <h2>
                                <i class="bi bi-list-check text-primary me-2"></i>
                                Existing HOD Assignments
                            </h2>

                            <p>
                                View the current department leadership assignments.
                            </p>
                        </div>

                        <?php if ($hodCount > 0): ?>

                            <div class="table-responsive">

                                <table class="table hod-table">

                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Department</th>
                                            <th>HOD Name</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        <?php foreach ($existingHods as $index => $hod): ?>

                                            <tr>
                                                <td>
                                                    <?= $index + 1 ?>
                                                </td>

                                                <td>
                                                    <strong>
                                                        <?= htmlspecialchars($hod['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <span class="teacher-avatar">
                                                        <?= htmlspecialchars(
                                                            strtoupper(substr(trim($hod['hod_name']), 0, 1)),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </span>

                                                    <?= htmlspecialchars($hod['hod_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>

                                                <td>
                                                    <span class="status-badge">
                                                        <i class="bi bi-check-circle me-1"></i>
                                                        Assigned
                                                    </span>
                                                </td>
                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div class="empty-state">
                                <i class="bi bi-person-exclamation"></i>

                                <h3>No HOD Assignments Yet</h3>

                                <p>
                                    Existing assignments will appear here after
                                    an HOD has been assigned.
                                </p>
                            </div>

                        <?php endif; ?>

                    </section>

                </div>

            </div>

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
$teacherStmt->close();
$departmentStmt->close();
$hodStmt->close();
?>