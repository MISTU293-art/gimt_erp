<?php

/*
 * Edit the data here. Everything below is rendered from these arrays.
 * Notices and announcements come from your recording; replace them as needed.
 */
$notices = [
    ['date' => '2026-10-14', 'code' => 'GIMT/OC/NOTICE/2026/012', 'title' => 'B.Tech Regular Direct Admission Notice 2026', 'link' => '#'],
    ['date' => '2026-09-28', 'code' => '', 'title' => 'Campus Recruitment Drive Subros', 'link' => '#'],
    ['date' => '2026-09-28', 'code' => '', 'title' => 'Campus Drive Qspiders', 'link' => '#'],
    ['date' => '2026-09-25', 'code' => '', 'title' => 'Pre-placement Session', 'link' => '#'],
    ['date' => '2026-09-21', 'code' => '', 'title' => 'Internship at Euphoria Genx / Web Exponent', 'link' => '#'],
    ['date' => '2026-07-23', 'code' => 'GIMT/OC/LET/2026/006', 'title' => 'Three-Day Workshop on “Internet of Things (IoT) and Machine Learning”', 'link' => '#'],
];

$announcements = [
    ['date' => '2024-01-15', 'code' => 'ASDAD34543', 'title' => 'Placement Status 2022', 'link' => '#'],
];

// Testimonials: add the full quote text in 'quote' and a photo path in 'photo'.
$testimonials = [
    ['name' => 'Yash Singh', 'detail' => 'CSE Department – 2023-26', 'note' => '', 'quote' => '', 'photo' => ''],
    ['name' => 'Abhijit Kirtania', 'detail' => 'ECE, 2021-24', 'note' => 'GATE Qualified', 'quote' => '', 'photo' => ''],
    ['name' => 'Bidit Krishna Pal', 'detail' => 'EE, Pass-out Batch 2024', 'note' => 'KYC Motorcycle Suspension India Pvt. Ltd.', 'quote' => '', 'photo' => ''],
];

// Recruiters: add a logo path in 'logo' to show an image instead of the name.
$recruiters = [
    ['name' => 'Biswa Games','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/biswa-games.png'],
    ['name' => 'CloudKaptan','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/cloudkaptan.png'],
    ['name' => 'Wipro','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/wipro.png'],
    ['name' => 'Cognizant','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/Cognizant.png'],
    ['name' => 'TCS','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/tcs.png'],
    ['name' => 'BAAR','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/baar.png'],
    ['name' => 'Shrachi','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/shrachi.png'],
    ['name' => 'Electrosteel','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/electrosteel.png'],
    ['name' => 'CEAT','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/ceat.png'],
    ['name' => 'Exide', 'logo' => ''],
    ['name' => 'Kirloskar','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/Kirloskar.png'],
    ['name' => 'Cummins','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/Cummins-India.png'],
    ['name' => 'Thermax','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/Thermax.png'],
    ['name' => 'JBM','logo'=>'https://gimt-india.com/wp-content/uploads/2026/06/jbm.png'],
];

$institutions = [
    'Global Hiramoni Memorial Nursing Institution',
    'Global College of Pharmaceutical Technology',
    'Global College of Science and Technology',
    'Global Institute of Education',
    'Krishnagar Public School',
    'Global Private ITI',
    'Government ITI Chapra',
    'Government ITI Harishchandrapur',
    'Government ITI Gopiballavpur II',
    'Government ITI Sankrail',
];

$phones = ['+91-7596056174', '+91-9083640444', '+91-7596056175', '+91-7407080088', '+91-7407042244', '+91-7407095678', '+91-8695649386', '+91-7407132345', '+91-7407114567'];
$whatsapp = '917596056174';

function fmt_date($d)
{
    $timestamp = strtotime((string) $d);

    return $timestamp === false
        ? htmlspecialchars((string) $d, ENT_QUOTES, 'UTF-8')
        : strtoupper(date('l, F j, Y', $timestamp));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e8731f">
    <meta name="description" content="Global Institute of Management and Technology (GIMT), Krishnagar, Nadia. B.Tech and Diploma engineering programs affiliated to MAKAUT and approved by AICTE.">
    <title>GIMT | Global Institute of Management &amp; Technology</title>
    <link rel="icon" type="image/jpeg" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS6fl6HJwnR00yvYnEkcHN40chI3gK2pcMsmjf4GaiePt7T-xTC0Kac4Tg&s=10">
    <link rel="apple-touch-icon" href="/php/images/images.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --orange: #e8731f;
            --orange-dark: #c2410c;
            --green: #3fbf3f;
            --ink: #1a1a1a;
            --navy: #0a0f33;
            --line: #cfcfcf;
            --bs-body-font-family: "Montserrat", "Segoe UI", Arial, sans-serif;
            --bs-body-color: var(--ink);
            --bs-link-color: var(--orange);
            --bs-link-hover-color: var(--orange-dark);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            overflow-x: hidden;
            line-height: 1.6;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .carousel-item {
                transition: none;
            }
        }

        .section-space {
            padding: clamp(2.5rem, 5vw, 4.5rem) 0;
        }

        .section-title {
            font-weight: 700;
            color: var(--ink);
        }

        .btn-orange {
            --bs-btn-color: #fff;
            --bs-btn-bg: var(--orange);
            --bs-btn-border-color: var(--orange);
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: var(--orange-dark);
            --bs-btn-hover-border-color: var(--orange-dark);
            --bs-btn-focus-shadow-rgb: 232, 115, 31;
            --bs-btn-border-radius: 0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: .85rem;
            padding: .55rem 1.5rem;
        }

        /* Banner */
        .hero-carousel .carousel-item img {
            width: 100%;
            height: auto;
            max-height: 520px;
            object-fit: cover;
        }

        @media (max-width: 767.98px) {
            .hero-carousel .carousel-item img {
                min-height: 200px;
                max-height: 320px;
            }
        }

        /* About */
        .rule {
            width: 56px;
            height: 4px;
            margin: 0 0 1.5rem;
            background: var(--orange);
            border: 0;
            opacity: 1;
        }

        .facts {
            border-left: 4px solid var(--orange);
            padding-left: 1.25rem;
        }

        .facts dt {
            font-weight: 700;
            color: var(--orange-dark);
        }

        .facts dd {
            margin-bottom: 1rem;
        }

        /* Programs band */
        .programs-band {
            background: var(--orange-dark);
            color: #fff;
        }

        .programs-band .rule {
            background: #fff;
        }

        .programs-band ul {
            padding-left: 1.1rem;
            margin-bottom: 0;
        }

        .programs-band li {
            margin-bottom: .35rem;
        }

        .programs-band .col-md-6+.col-md-6 {
            border-top: 1px solid rgb(255 255 255 / 40%);
        }

        @media (min-width: 768px) {
            .programs-band .col-md-6+.col-md-6 {
                border-top: 0;
                border-left: 1px solid rgb(255 255 255 / 40%);
            }
        }

        /* Notice board + announcement (matches recording) */
        .board-card {
            height: 100%;
            padding: 1.25rem 1.25rem 1.5rem;
            background: #fafafa;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgb(0 0 0 / 35%);
        }

        .board-card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.1rem;
        }

        .board-list {
            list-style: none;
            margin: 0 0 1rem;
            padding: 0;
        }

        .board-list li {
            padding: .45rem 0 .55rem;
            border-bottom: 1px solid var(--line);
        }

        .board-list li+li {
            padding-top: .5rem;
        }

        .board-date {
            display: flex;
            align-items: center;
            gap: .45rem;
            color: var(--green);
            font-size: .8rem;
            font-weight: 500;
        }

        .board-date i {
            color: var(--orange);
            font-size: 1.05rem;
        }

        .board-code {
            font-size: .85rem;
            color: var(--ink);
        }

        .board-link {
            color: var(--orange);
            text-decoration: none;
            font-size: .95rem;
        }

        .board-link:hover {
            color: var(--orange-dark);
            text-decoration: underline;
        }

        .board-more {
            text-align: right;
        }

        /* Testimonials */
        .testimonials {
            background: #fff;
        }

        .t-item {
            text-align: center;
            padding: 0 1rem;
        }

        .t-quote {
            min-height: 7rem;
            font-size: .95rem;
        }

        .t-photo {
            width: 72px;
            height: 72px;
            margin: 1rem auto .6rem;
            border-radius: 50%;
            border: 3px solid var(--orange);
            padding: 3px;
            object-fit: cover;
            background: #fde9d8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--orange);
            font-size: 1.8rem;
        }

        .t-name {
            color: var(--orange);
            font-weight: 700;
            margin: 0;
        }

        .t-detail {
            color: #1ea01e;
            font-weight: 600;
            font-size: .9rem;
            margin: 0;
        }

        .t-note {
            font-weight: 600;
            font-size: .85rem;
            margin: .3rem 0 0;
        }

        .testimonials .carousel-indicators {
            position: static;
            margin: 1.5rem 0 0;
        }

        .testimonials .carousel-indicators [data-bs-target] {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 0;
            background: #e5c3a6;
            opacity: 1;
        }

        .testimonials .carousel-indicators .active {
            background: var(--orange);
        }

        /* Recruiters */
        .recruiters {
            background: #f4f7fb;
        }

        .logo-tile {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 112px;
            padding: 1rem;
            background: #fff;
            border: 1px solid #e6ebf2;
            font-weight: 700;
            color: #333;
            text-align: center;
        }

        .logo-tile img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .recruiters .carousel-control-prev,
        .recruiters .carousel-control-next {
            width: 2.5rem;
            color: var(--orange-dark);
        }

        .recruiters .carousel-control-prev-icon,
        .recruiters .carousel-control-next-icon {
            padding: .75rem;
            border-radius: 50%;
            background-color: var(--orange-dark);
            background-size: 55%;
        }

        .recruiters .carousel-inner {
            margin: 0 2rem;
            width: auto;
        }

        @media (max-width: 575.98px) {
            .logo-tile {
                height: 92px;
                padding: .7rem;
            }

            .recruiters .carousel-inner {
                margin: 0 1.75rem;
            }
        }

        /* Footer */
        .site-footer {
            background: var(--navy);
            color: #fff;
            font-size: .9rem;
        }

        .site-footer h3 {
            font-size: 1rem;
            font-weight: 700;
            padding-bottom: .5rem;
            border-bottom: 1px solid rgb(255 255 255 / 45%);
            margin-bottom: 1rem;
        }

        .site-footer a {
            color: #fff;
            text-decoration: none;
        }

        .site-footer a:hover {
            color: #ffb27a;
        }

        .site-footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .site-footer li {
            margin-bottom: .35rem;
        }

        .footer-phones {
            border-top: 1px solid rgb(255 255 255 / 30%);
            border-bottom: 1px solid rgb(255 255 255 / 30%);
            padding: .9rem 0;
        }

        .footer-phones a {
            white-space: nowrap;
        }

        /* Floating controls */
        .enquiry-tab {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1030;
            padding: 1.2rem .7rem;
            background: var(--orange-dark);
            color: #fff;
            border: 0;
            border-radius: 0 6px 6px 0;
            writing-mode: vertical-rl;
            transform-origin: center;
            font-weight: 700;
            letter-spacing: .06em;
        }

        .enquiry-tab:hover {
            background: var(--orange);
            color: #fff;
        }

        .float-actions {
            position: fixed;
            right: 0;
            top: 60%;
            z-index: 1030;
            display: flex;
            flex-direction: column;
        }

        .float-actions a {
            width: 48px;
            height: 42px;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.15rem;
            text-decoration: none;
        }

        .float-actions .fa-arrow {
            background: #000;
        }

        .float-actions .fa-wa {
            background: #25d366;
        }

        .float-actions .fa-ph {
            background: var(--orange-dark);
        }

        .to-top {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            z-index: 1030;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid var(--orange);
            background: #fff;
            color: var(--orange);
            display: none;
            place-items: center;
            cursor: pointer;
        }

        .to-top.show {
            display: grid;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--orange);
            outline-offset: 2px;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/partials/Header.php'; ?>

    <main>
        <!-- Banner -->
        <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-touch="true" aria-label="GIMT campus banners">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="https://gimt-india.com/wp-content/uploads/2024/01/New-Project4.png" class="d-block w-100" alt="GIMT banner 1">
                </div>
                <div class="carousel-item">
                    <img src="https://gimt-india.com/wp-content/uploads/2024/03/SLIDER101.jpg" class="d-block w-100" alt="GIMT banner 2" loading="lazy">
                </div>
                <div class="carousel-item">
                    <img src="https://gimt-india.com/wp-content/uploads/2024/01/New-Project2.png" class="d-block w-100" alt="GIMT banner 3" loading="lazy">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span>
            </button>
        </div>

        <!-- About -->
        <section id="about" class="section-space">
            <div class="container">
                <div class="row g-5">
                    <div class="col-lg-7">
                        <h1 class="section-title h2">Global Institute of Management &amp; Technology</h1>
                        <hr class="rule">
                        <p>GIMT is an engineering college in Krishnagar, the district headquarters of Nadia, West Bengal. It was founded by Mr. Naresh Ch. Das and established in 2009 by the trust National Centre for Development &amp; Technical Education (NCDTE).</p>
                        <p>The college is affiliated to MAKAUT (Maulana Abul Kalam Azad University of Technology, formerly WBUT) and approved by the All India Council for Technical Education (AICTE).</p>
                        <p class="mb-0">GIMT works on continuous improvement in teaching, training and student services.</p>
                    </div>
                    <div class="col-lg-5">
                        <dl class="facts mb-0">
                            <dt>Established</dt>
                            <dd>2009</dd>
                            <dt>Location</dt>
                            <dd>Krishnagar, Nadia, West Bengal</dd>
                            <dt>Affiliation</dt>
                            <dd>MAKAUT</dd>
                            <dt>Approval</dt>
                            <dd>AICTE</dd>
                            <dt>Trust</dt>
                            <dd class="mb-0">National Centre for Development &amp; Technical Education (NCDTE)</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        <!-- Programs -->
        <section id="programs" class="section-space programs-band">
            <div class="container">
                <h2 class="h2 fw-bold">Programs</h2>
                <hr class="rule">
                <div class="row g-4">
                    <div class="col-md-6 pe-md-5">
                        <h3 class="h5 fw-bold mb-3">B.Tech (Undergraduate)</h3>
                        <ul>
                            <li>Computer Science &amp; Engineering</li>
                            <li>Civil Engineering</li>
                            <li>Mechanical Engineering</li>
                            <li>Electrical Engineering</li>
                            <li>Electronics &amp; Communication Engineering</li>
                        </ul>
                    </div>
                    <div class="col-md-6 ps-md-5 pt-4 pt-md-0">
                        <h3 class="h5 fw-bold mb-3">Diploma</h3>
                        <ul>
                            <li>Civil Engineering</li>
                            <li>Mechanical Engineering</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Notice board and announcement -->
        <section id="notice" class="section-space">
            <div class="container">
                <div class="row g-4 justify-content-center">
                    <div class="col-lg-5 col-md-6">
                        <div class="board-card">
                            <h2>Notice Board</h2>
                            <ul class="board-list">
                                <?php foreach ($notices as $n): ?>
                                    <li>
                                        <div class="board-date"><i class="bi bi-calendar3" aria-hidden="true"></i><?php echo fmt_date($n['date']); ?></div>
                                        <?php if (!empty($n['code'])): ?><div class="board-code"><?php echo htmlspecialchars($n['code'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                                        <?php if (!empty($n['link']) && $n['link'] !== '#'): ?>
                                            <a class="board-link" href="<?php echo htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                        <?php else: ?>
                                            <span class="board-link"><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-5 col-md-6">
                        <div class="board-card">
                            <h2>Announcement</h2>
                            <ul class="board-list">
                                <?php foreach ($announcements as $n): ?>
                                    <li>
                                        <div class="board-date"><i class="bi bi-calendar3" aria-hidden="true"></i><?php echo fmt_date($n['date']); ?></div>
                                        <?php if (!empty($n['code'])): ?><div class="board-code"><?php echo htmlspecialchars($n['code'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                                        <?php if (!empty($n['link']) && $n['link'] !== '#'): ?>
                                            <a class="board-link" href="<?php echo htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                                        <?php else: ?>
                                            <span class="board-link"><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why GIMT -->
        <section id="why-gimt" class="section-space pt-0">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <h2 class="section-title h2">Why choose GIMT?</h2>
                        <hr class="rule">
                        <div class="accordion" id="whyGimtAccordion">
                            <?php
                            $faq = [
                                ['bi-award-fill', 'What recognitions or awards has GIMT received?', 'GIMT received the Education Excellence Award, presented by Zee Network and 24 Ghanta TV News, for its work in skill development and industry engagement.'],
                                ['bi-briefcase-fill', 'What does the Training &amp; Placement Cell do?', 'The Training and Placement Cell prepares students for work. It offers soft-skills training, including spoken English, and Japanese language learning modules.'],
                                ['bi-person-badge-fill', 'Who teaches at GIMT?', 'The faculty includes Ph.D. holders from institutions such as the IITs, NITs, Jadavpur University (JU) and IIEST.'],
                                ['bi-journal-code', 'Can students work on projects or with industry?', 'Yes. GIMT has technical collaborations (MoUs) with industry partners, and students take part in a project scheme from their second year.'],
                                ['bi-cash-coin', 'Are scholarships available?', 'GIMT offers scholarship schemes for incoming students.'],
                                ['bi-shield-check', 'How does GIMT look after student safety?', 'The campus follows a strict anti-ragging policy. As required by AICTE guidelines, all enrolled students have group insurance cover.'],
                            ];
                            foreach ($faq as $i => $f): $id = 'faq' . ($i + 1);
                                $open = $i === 0; ?>
                                <div class="accordion-item">
                                    <h3 class="accordion-header">
                                        <button class="accordion-button<?php echo $open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $id; ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo $id; ?>">
                                            <i class="bi <?php echo $f[0]; ?> me-3 text-orange" style="color:var(--orange)"></i><?php echo $f[1]; ?>
                                        </button>
                                    </h3>
                                    <div id="<?php echo $id; ?>" class="accordion-collapse collapse<?php echo $open ? ' show' : ''; ?>" data-bs-parent="#whyGimtAccordion">
                                        <div class="accordion-body"><?php echo $f[2]; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Student voices -->
        <section id="testimonials" class="section-space testimonials">
            <div class="container">
                <h2 class="section-title h2 text-center mb-4">Student voices</h2>
                <div id="tCarousel" class="carousel slide" data-bs-ride="carousel" aria-label="Student testimonials">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <div class="row g-4">
                                <?php foreach ($testimonials as $t): ?>
                                    <div class="col-md-4 t-item">
                                        <p class="t-quote"><?php echo htmlspecialchars($t['quote']); ?></p>
                                        <?php if ($t['photo']): ?>
                                            <img class="t-photo" src="<?php echo htmlspecialchars($t['photo']); ?>" alt="<?php echo htmlspecialchars($t['name']); ?>">
                                        <?php else: ?>
                                            <div class="t-photo" aria-hidden="true"><i class="bi bi-person-fill"></i></div>
                                        <?php endif; ?>
                                        <p class="t-name"><?php echo htmlspecialchars($t['name']); ?></p>
                                        <p class="t-detail"><?php echo htmlspecialchars($t['detail']); ?></p>
                                        <?php if ($t['note']): ?><p class="t-note"><?php echo htmlspecialchars($t['note']); ?></p><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Add more <div class="carousel-item"> slides above, then one indicator per slide below -->
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#tCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Recruiters -->
        <section id="recruiters" class="section-space recruiters">
            <div class="container">
                <h2 class="section-title h2 text-center mb-4">Our recruiters</h2>
                <div id="recruitersCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000" data-bs-pause="hover" data-bs-wrap="true" aria-label="Recruiter logos">
                    <div class="carousel-inner">
                        <?php foreach (array_chunk($recruiters, 4) as $slideIndex => $recruiterSlide): ?>
                            <div class="carousel-item<?php echo $slideIndex === 0 ? ' active' : ''; ?>">
                                <div class="row row-cols-2 row-cols-md-4 g-2">
                                    <?php foreach ($recruiterSlide as $r): ?>
                                        <div class="col">
                                            <div class="logo-tile">
                                                <?php if (!empty($r['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($r['logo']); ?>" alt="<?php echo htmlspecialchars($r['name']); ?>" loading="lazy">
                                                <?php else: echo htmlspecialchars($r['name']);
                                                endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#recruitersCarousel" data-bs-slide="prev" aria-label="Previous recruiter logos">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#recruitersCarousel" data-bs-slide="next" aria-label="Next recruiter logos">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer id="contact" class="site-footer pt-5 pb-3">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-2 col-md-3">
                    <img src="https://gimt-india.com/wp-content/uploads/2026/08/GIMT_Logo_white-scaled.png" alt="GIMT logo" class="img-fluid" style="max-width:140px">
                </div>
                <div class="col-lg-3 col-md-9">
                    <h3>Location</h3>
                    <p class="d-flex gap-2"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        <span>Palpara More, NH-12 (Formerly known as NH-34)<br>Bhatjangla, Krishnagar<br>West Bengal - 741102</span>
                    </p>
                    <p class="mb-1"><a href="mailto:admission@gimt-india.com">admission@gimt-india.com</a></p>
                    <p><a href="mailto:admissions@gimt-india.com">admissions@gimt-india.com</a></p>
                </div>
                <div class="col-lg-7">
                    <h3>Our Various Institutions Under Global Knowledge Campus</h3>
                    <ul class="row row-cols-1 row-cols-md-2 g-0">
                        <?php foreach ($institutions as $inst): ?><li class="col"><?php echo htmlspecialchars($inst); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="footer-phones d-flex flex-wrap justify-content-center gap-3 my-4">
                <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                <?php foreach ($phones as $p): ?><a href="tel:<?php echo preg_replace('/[^+\d]/', '', $p); ?>"><?php echo $p; ?></a><?php endforeach; ?>
            </div>

            <p class="text-center mb-0">&copy; <?php echo date('Y'); ?> Global Institute Of Management &amp; Technology | All Rights Reserved</p>
        </div>
    </footer>

    <!-- Floating controls -->
    <a href="#contact" class="enquiry-tab text-decoration-none">ENQUIRY</a>
    <div class="float-actions">
        <a href="#programs" class="fa-arrow" aria-label="View programs"><i class="bi bi-arrow-right"></i></a>
        <a href="https://wa.me/<?php echo $whatsapp; ?>" class="fa-wa" aria-label="Chat on WhatsApp"><i class="bi bi-whatsapp"></i></a>
        <a href="tel:+917596056174" class="fa-ph" aria-label="Call admissions"><i class="bi bi-telephone-fill"></i></a>
    </div>
    <button type="button" class="to-top" id="toTop" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            var btn = document.getElementById('toTop');
            window.addEventListener('scroll', function() {
                btn.classList.toggle('show', window.scrollY > 400);
            }, {
                passive: true
            });
            btn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        })();
    </script>
</body>

</html>