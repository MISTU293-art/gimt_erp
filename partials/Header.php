<nav class="gimt-header-wrapper">
    <!-- Top Brand & Logos Bar -->
    <div class="container py-2 py-lg-3">
        <div class="row align-items-center g-3">

            <!-- Left & Center: Logo + Title + Subtext -->
            <div class="col-lg-8 col-12 d-flex align-items-center gap-2 gap-sm-3 text-start">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS6fl6HJwnR00yvYnEkcHN40chI3gK2pcMsmjf4GaiePt7T-xTC0Kac4Tg&s=10" alt="GIMT logo" class="gimt-logo">
                <div class="brand-text">
                    <a class="navbar-brand text-decoration-none p-0 m-0 d-block" href="index.php">
                        GLOBAL INSTITUTE OF MANAGEMENT &amp; TECHNOLOGY
                    </a>
                    <span class="sub-title d-block">An Unit of National Centre for Development of Technical Education (NCDTE)</span>
                    <span class="affiliations d-block">AICTE Approved | Affiliated to MAKAUT, WB &amp; WBSCT &amp; VE &amp; SD</span>
                </div>
            </div>

            <!-- Right: Accreditation / Approval Logos -->
            <div class="col-lg-4 col-12 d-flex align-items-center justify-content-center justify-content-lg-end gap-2 gap-sm-3 accreditation-logos">
                <img src="https://gimt-india.com/wp-content/uploads/2023/03/AICTE.png" alt="AICTE">
                <img src="https://gimt-india.com/wp-content/uploads/2023/03/MAKAUT.png" alt="MAKAUT">
                <img src="https://gimt-india.com/wp-content/uploads/2023/03/wbscete.png" alt="WBSCT">
            </div>

        </div>
    </div>

    <!-- Main Navigation Menu Bar -->
    <div class="gimt-navbar-bar navbar navbar-expand-lg navbar-light">
        <div class="container">
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler my-2 ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu"
                aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu Items -->
            <div class="collapse navbar-collapse justify-content-center" id="navbarMenu">
                <ul class="navbar-nav align-items-lg-center w-100 justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">HOME</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">ABOUT</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#programs">ACADEMICS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#recruiters">TRAINING &amp; PLACEMENT</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">CONTACT</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_login.php">ADMIN LOGIN</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<style>
    /* --- General Wrapper --- */
    .gimt-header-wrapper {
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    }

    /* --- Logo & Brand Typography --- */
    .gimt-logo {
        width: 75px;
        height: 75px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .brand-text {
        min-width: 0;
    }

    .brand-text .navbar-brand {
        color: #f97316;
        font-size: clamp(1.1rem, 2.2vw, 1.65rem);
        font-weight: 800;
        letter-spacing: -0.01em;
        line-height: 1.15;
        white-space: normal;
    }

    .brand-text .navbar-brand:hover {
        color: #c2410c;
    }

    .brand-text .sub-title {
        color: #2b2b2b;
        font-size: clamp(0.7rem, 1.2vw, 0.82rem);
        font-weight: 700;
        margin-top: 2px;
        line-height: 1.2;
    }

    .brand-text .affiliations {
        color: #111;
        font-size: clamp(0.68rem, 1.1vw, 0.78rem);
        font-weight: 700;
        line-height: 1.2;
    }

    .accreditation-logos img {
        max-width: 30%;
        height: 52px;
        width: auto;
        object-fit: contain;
        flex: 0 1 auto;
    }

    /* --- Navigation Bar --- */
    .gimt-navbar-bar {
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        padding: 0;
    }

    .gimt-navbar-bar .navbar-nav .nav-link {
        color: #f97316;
        font-weight: 700;
        font-size: 0.84rem;
        padding: 12px 10px;
        white-space: nowrap;
        transition: all 0.2s ease;
    }

    .gimt-navbar-bar .navbar-nav .nav-link:hover,
    .gimt-navbar-bar .navbar-nav .nav-link.active,
    .gimt-navbar-bar .navbar-nav .show>.nav-link {
        color: #c2410c;
        background-color: rgb(249 115 22 / 8%);
    }

    .gimt-navbar-bar .dropdown-menu {
        border-radius: 6px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid #fed7aa;
        margin-top: 0;
    }

    .gimt-navbar-bar .dropdown-item {
        font-size: 0.82rem;
        font-weight: 600;
        color: #382418;
        padding: 8px 16px;
    }

    .gimt-navbar-bar .dropdown-item:hover {
        background-color: #fff7ed;
        color: #c2410c;
    }

    /* --- Desktop Hover Functionality --- */
    @media (min-width: 992px) {
        .gimt-navbar-bar .dropdown:hover .dropdown-menu {
            display: block;
        }
    }

    /* --- Mobile & Tablet Responsive Adjustments --- */
    @media (max-width: 991.98px) {
        .gimt-logo {
            width: 55px;
            height: 55px;
        }

        .brand-text .navbar-brand {
            font-size: clamp(1rem, 3vw, 1.4rem);
        }

        .accreditation-logos img {
            height: 38px;
        }

        .gimt-navbar-bar {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
        }

        .gimt-navbar-bar .navbar-collapse {
            padding: 10px 0;
        }

        .gimt-navbar-bar .navbar-nav .nav-link {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }

        .gimt-navbar-bar .dropdown-menu {
            border: none;
            box-shadow: none;
            background: #fff7ed;
            padding-left: 15px;
        }

        .gimt-navbar-bar .dropdown-item {
            padding: 8px 15px;
        }
    }

    @media (max-width: 575.98px) {
        .gimt-header-wrapper>.container {
            padding-right: 1rem;
            padding-left: 1rem;
        }

        .gimt-header-wrapper .row>div:first-child {
            align-items: flex-start !important;
        }

        .gimt-logo {
            width: 48px;
            height: 48px;
        }

        .brand-text {
            text-align: left;
        }

        .brand-text .navbar-brand {
            font-size: 1rem;
            line-height: 1.2;
        }

        .brand-text .sub-title,
        .brand-text .affiliations {
            font-size: 0.68rem;
            line-height: 1.35;
        }

        .accreditation-logos {
            border-top: 1px dashed #e2e8f0;
            padding: 8px 0 2px;
            flex-wrap: wrap;
        }

        .accreditation-logos img {
            max-width: 28%;
            height: 32px;
        }

        .gimt-navbar-bar>.container {
            padding-right: 1rem;
            padding-left: 1rem;
        }

        .gimt-navbar-bar .navbar-toggler {
            margin-top: 0.5rem !important;
            margin-bottom: 0.5rem !important;
        }

        .gimt-navbar-bar .navbar-nav {
            align-items: stretch !important;
        }

        .gimt-navbar-bar .navbar-nav .nav-link {
            white-space: normal;
        }

        .gimt-navbar-bar .dropdown-menu {
            width: 100%;
        }
    }
</style>