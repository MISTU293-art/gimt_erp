<?php
// partials/makaut_header.php - Standard MAKAUT University Enterprise Header & Academic Session Switcher
$hdrUser = current_user();
$hdrRole = $hdrUser['role'] ?? 'guest';
$curScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$curSession = get_academic_session();
$curTerm = get_academic_term();
$curSem = get_academic_semester();
?>
<!-- MAKAUT Enterprise University Header -->
<header class="makaut-topbar shadow-sm">
    <div class="container-fluid px-3 px-lg-4">
        <div class="d-flex align-items-center justify-content-between py-2 flex-wrap gap-2">
            <!-- University & College Branding -->
            <div class="d-flex align-items-center gap-3">
                <div class="makaut-crest-box">
                    <i class="bi bi-mortarboard-fill fs-4 text-warning"></i>
                </div>
                <div>
                    <div class="makaut-univ-name">MAULANA ABUL KALAM AZAD UNIVERSITY OF TECHNOLOGY, WEST BENGAL</div>
                    <div class="makaut-college-sub">
                        <span class="text-warning fw-bold">GIMT</span> &bull; Global Institute of Management &amp; Technology &bull; <span class="badge bg-warning text-dark font-monospace fw-bold">College Code: 249</span>
                    </div>
                </div>
            </div>

            <!-- Session & Context Controls -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Academic Session Badge & Modal Switcher -->
                <button type="button" class="btn btn-sm btn-outline-light border-light-subtle d-flex align-items-center gap-2 px-3 py-1" data-bs-toggle="modal" data-bs-target="#sessionSwitchModal" title="Click to Switch Academic Session or Semester">
                    <i class="bi bi-calendar3 text-warning"></i>
                    <span class="d-none d-md-inline small">Session:</span>
                    <strong class="font-monospace small"><?= e($curSession) ?></strong>
                    <span class="badge bg-light text-dark font-monospace">Sem <?= $curSem ?></span>
                    <i class="bi bi-chevron-down small opacity-75"></i>
                </button>

                <!-- Role Badge -->
                <span class="badge bg-primary text-uppercase px-2 py-1 fs-7 d-none d-sm-inline">
                    <?= e($hdrRole) ?>
                </span>

                <!-- Quick Logout -->
                <a href="logout.php" class="btn btn-sm btn-outline-danger text-light border-0" title="Sign Out">
                    <i class="bi bi-power fs-5"></i>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Academic Session / Semester Switcher Modal -->
<div class="modal fade" id="sessionSwitchModal" tabindex="-1" aria-labelledby="sessionSwitchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="post" action="session_switch.php">
                <?= csrf_field() ?>
                <input type="hidden" name="return_url" value="<?= e($curScript) ?>">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="sessionSwitchModalLabel">
                        <i class="bi bi-sliders text-warning me-2"></i>Academic Session &amp; Semester Switcher
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Switch the active working session and semester cycle for curriculum, continuous evaluations, marks sheets, and attendance registers.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Academic Session (Year)</label>
                        <select name="academic_session" class="form-select">
                            <option value="2026-2027" <?= $curSession === '2026-2027' ? 'selected' : '' ?>>2026-2027 (Current Academic Session)</option>
                            <option value="2025-2026" <?= $curSession === '2025-2026' ? 'selected' : '' ?>>2025-2026 (Previous Academic Session)</option>
                            <option value="2024-2025" <?= $curSession === '2024-2025' ? 'selected' : '' ?>>2024-2025 (Archived Session)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Academic Term Cycle</label>
                        <select name="academic_term" class="form-select">
                            <option value="Odd Semester (July - Dec 2026)" <?= str_contains($curTerm, 'Odd') ? 'selected' : '' ?>>Odd Semester (July &ndash; Dec 2026 &bull; Sem 1, 3, 5, 7)</option>
                            <option value="Even Semester (Jan - June 2027)" <?= str_contains($curTerm, 'Even') ? 'selected' : '' ?>>Even Semester (Jan &ndash; June 2027 &bull; Sem 2, 4, 6, 8)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Active Semester Focus</label>
                        <select name="academic_semester" class="form-select">
                            <?php for ($s = 1; $s <= 8; $s++): ?>
                                <option value="<?= $s ?>" <?= $curSem === $s ? 'selected' : '' ?>>Semester <?= $s ?> (<?= $s % 2 === 1 ? 'Odd' : 'Even' ?> Term)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-arrow-repeat me-1"></i> Apply Academic Context
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.makaut-topbar {
    background: linear-gradient(135deg, #082338 0%, #0b3c5d 100%);
    border-bottom: 3px solid #f59e0b;
    color: #ffffff;
    position: relative;
    z-index: 1030;
}
.makaut-crest-box {
    width: 44px;
    height: 44px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.4);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.makaut-univ-name {
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #ffffff;
    line-height: 1.2;
}
.makaut-college-sub {
    font-size: 0.73rem;
    color: rgba(255, 255, 255, 0.85);
    letter-spacing: 0.3px;
}
@media (max-width: 768px) {
    .makaut-univ-name { font-size: 0.72rem; }
    .makaut-college-sub { font-size: 0.65rem; }
}
</style>
