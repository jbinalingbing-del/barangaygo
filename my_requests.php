<?php
session_start();
include "db.php";

// Resident auth check
if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$resident_id = (int)$_SESSION["resident_id"];

$services_map = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Barangay Business Clearance",
];

$badge_map = [
    "Pending"  => "badge-pending",
    "Approved" => "badge-approved",
    "Ready"    => "badge-ready",
    "Released" => "badge-released",
    "Rejected" => "badge-rejected",
];

$status_desc = [
    "Pending"  => "Tinatanggap at sinusuri ng barangay ang iyong kahilingan.",
    "Approved" => "Aprubado na ang iyong kahilingan. Inihahanda ang dokumento.",
    "Ready"    => "Handa na ang iyong dokumento! Maaari mo nang kunin sa barangay hall.",
    "Released" => "Naibigay na ang iyong dokumento. Natapos na ang kahilingan.",
    "Rejected" => "Tinanggihan ang iyong kahilingan. Tingnan ang paalala sa ibaba.",
];

$my_requests = [];
$error = "";

try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE resident_id = ? ORDER BY created_at DESC");
        $stmt->execute([$resident_id]);
        $my_requests = $stmt->fetchAll();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM requests WHERE resident_id = ? ORDER BY created_at DESC");
        mysqli_stmt_bind_param($stmt, "i", $resident_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $my_requests[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Hindi ma-load ang iyong mga kahilingan: " . $e->getMessage();
}

$total      = count($my_requests);
$pending    = count(array_filter($my_requests, fn($r) => $r["status"] === "Pending"));
$released   = count(array_filter($my_requests, fn($r) => $r["status"] === "Released"));
$rejected   = count(array_filter($my_requests, fn($r) => $r["status"] === "Rejected"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aking mga Kahilingan - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ── Container ── */
        .hist-wrap {
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
            padding: 0 4px;
        }

        /* ── Summary badges ── */
        .hist-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }
        .hist-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 7px 18px;
            font-size: 0.85rem;
            color: var(--text-soft);
        }
        .hist-pill strong {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }

        /* ── Timeline wrapper ── */
        .timeline {
            position: relative;
            padding-left: 36px;
        }
        .timeline::before {
            content: "";
            position: absolute;
            left: 11px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border);
            border-radius: 2px;
        }

        /* ── Date group header ── */
        .tl-date-group {
            position: relative;
            margin-bottom: 6px;
            margin-top: 28px;
        }
        .tl-date-group:first-child { margin-top: 0; }
        .tl-date-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-soft);
            background: var(--bg);
            padding: 2px 10px 2px 0;
        }
        .tl-date-label::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--border);
            border: 2px solid var(--bg);
            flex-shrink: 0;
            margin-left: -40px;
            margin-right: 6px;
        }

        /* ── Individual request card ── */
        .tl-card {
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            margin-bottom: 12px;
            transition: border-color 0.18s, transform 0.18s;
            position: relative;
        }
        .tl-card:hover {
            border-color: var(--focus);
            transform: translateX(3px);
        }

        /* left accent line based on status */
        .tl-card.status-Pending  { border-left: 4px solid var(--warn); }
        .tl-card.status-Approved { border-left: 4px solid var(--ok); }
        .tl-card.status-Ready    { border-left: 4px solid var(--info); }
        .tl-card.status-Released { border-left: 4px solid #60a5fa; }
        .tl-card.status-Rejected { border-left: 4px solid var(--bad); }

        /* ── Card top row ── */
        .tl-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .tl-service {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        .tl-id {
            font-size: 0.75rem;
            color: var(--text-soft);
            margin-top: 2px;
        }

        /* ── Date & time stamp ── */
        .tl-datetime {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--text-soft);
            margin-bottom: 10px;
        }
        .tl-datetime svg {
            width: 13px;
            height: 13px;
            fill: var(--text-soft);
            flex-shrink: 0;
        }

        /* ── Info row (purpose, ID type) ── */
        .tl-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        .tl-info-item small {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-soft);
            margin-bottom: 2px;
        }
        .tl-info-item span {
            color: var(--text);
        }

        /* ── Status description ── */
        .tl-status-msg {
            margin-top: 8px;
            font-size: 0.82rem;
            color: var(--text-soft);
            font-style: italic;
        }

        /* ── Remarks box ── */
        .tl-remarks {
            margin-top: 10px;
            padding: 10px 14px;
            background: rgba(234, 179, 8, 0.07);
            border-left: 3px solid var(--warn);
            border-radius: 4px;
            font-size: 0.84rem;
            color: var(--text);
        }

        /* ── Empty state ── */
        .hist-empty {
            text-align: center;
            padding: 56px 20px;
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
        }

        @media (max-width: 600px) {
            .timeline { padding-left: 24px; }
            .tl-date-label::before { margin-left: -28px; }
        }
    </style>
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="services.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="services.php">Mga Serbisyo</a>
            <a href="my_requests.php" class="active">My Requests</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top">
        <div class="hist-wrap">

            <!-- Page Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
                <div>
                    <h1 style="margin:0; font-size:1.5rem;">📋 Kasaysayan ng Aking mga Kahilingan</h1>
                    <p class="page-sub" style="margin:5px 0 0;">Lahat ng iyong mga request at ang kanilang kasalukuyang estado.</p>
                </div>
                <a href="services.php" class="btn" style="padding:8px 18px;">+ Bagong Request</a>
            </div>

            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <?php if (empty($my_requests)): ?>
                <!-- Empty State -->
                <div class="hist-empty">
                    <div style="font-size:3rem; margin-bottom:14px;">📂</div>
                    <h2 style="margin:0 0 10px;">Wala ka pang naisusumiteng kahilingan</h2>
                    <p style="color:var(--text-soft); margin-bottom:22px;">
                        Maaari kang humiling ng Barangay Clearance, Residency,<br>Indigency, o Business Clearance online anumang oras.
                    </p>
                    <a href="services.php" class="btn">Pumunta sa mga Serbisyo</a>
                </div>

            <?php else: ?>

                <!-- Summary Counts -->
                <div class="hist-summary">
                    <div class="hist-pill"><strong><?= $total; ?></strong> Kabuuang Request</div>
                    <?php if ($pending > 0): ?>
                        <div class="hist-pill"><strong style="color:var(--warn);"><?= $pending; ?></strong> Pending</div>
                    <?php endif; ?>
                    <?php if ($released > 0): ?>
                        <div class="hist-pill"><strong style="color:#60a5fa;"><?= $released; ?></strong> Naibigay na</div>
                    <?php endif; ?>
                    <?php if ($rejected > 0): ?>
                        <div class="hist-pill"><strong style="color:var(--bad);"><?= $rejected; ?></strong> Tinanggihan</div>
                    <?php endif; ?>
                </div>

                <!-- Timeline -->
                <div class="timeline">
                    <?php
                    // Group requests by date
                    $grouped = [];
                    foreach ($my_requests as $r) {
                        $date_key = date("Y-m-d", strtotime($r["created_at"]));
                        $grouped[$date_key][] = $r;
                    }

                    foreach ($grouped as $date_key => $items):
                        $display_date = date("F j, Y", strtotime($date_key));
                        // Human-friendly label
                        $today     = date("Y-m-d");
                        $yesterday = date("Y-m-d", strtotime("-1 day"));
                        if ($date_key === $today)          $display_date = "Ngayon · " . date("F j, Y", strtotime($date_key));
                        elseif ($date_key === $yesterday)  $display_date = "Kahapon · " . date("F j, Y", strtotime($date_key));
                    ?>

                    <!-- Date group -->
                    <div class="tl-date-group">
                        <span class="tl-date-label"><?= $display_date; ?></span>
                    </div>

                    <?php foreach ($items as $r):
                        $s_name = $services_map[$r["service"]] ?? ucwords(str_replace("_", " ", $r["service"]));
                        $st     = $r["status"];
                        $time   = date("g:i A", strtotime($r["created_at"]));
                    ?>
                    <div class="tl-card status-<?= htmlspecialchars($st, ENT_QUOTES); ?>">

                        <!-- Top: service name + badge -->
                        <div class="tl-top">
                            <div>
                                <p class="tl-service"><?= htmlspecialchars($s_name, ENT_QUOTES); ?></p>
                                <span class="tl-id">Request #<?= $r["request_id"]; ?></span>
                            </div>
                            <span class="badge <?= $badge_map[$st] ?? 'badge-pending'; ?>" style="font-size:0.8rem; padding:4px 12px;">
                                <?= htmlspecialchars($st, ENT_QUOTES); ?>
                            </span>
                        </div>

                        <!-- Date & Time -->
                        <div class="tl-datetime">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                            </svg>
                            <?= $display_date; ?> &nbsp;·&nbsp; <?= $time; ?>
                        </div>

                        <!-- Details -->
                        <div class="tl-info">
                            <div class="tl-info-item">
                                <small>Layunin (Purpose)</small>
                                <span><?= htmlspecialchars($r["purpose"] ?: "—", ENT_QUOTES); ?></span>
                            </div>
                            <?php if (!empty($r["id_type"])): ?>
                            <div class="tl-info-item">
                                <small>Uri ng ID</small>
                                <span><?= htmlspecialchars($r["id_type"], ENT_QUOTES); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($r["business_name"])): ?>
                            <div class="tl-info-item">
                                <small>Negosyo</small>
                                <span><?= htmlspecialchars($r["business_name"], ENT_QUOTES); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($r["years_of_residency"])): ?>
                            <div class="tl-info-item">
                                <small>Paninirahan</small>
                                <span><?= (int)$r["years_of_residency"]; ?> taon</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Status description -->
                        <p class="tl-status-msg">
                            <?= htmlspecialchars($status_desc[$st] ?? "", ENT_QUOTES); ?>
                        </p>

                        <!-- Remarks from barangay -->
                        <?php if (!empty($r["remarks"])): ?>
                        <div class="tl-remarks">
                            <strong>💬 Paalala / Tugon mula sa Barangay:</strong><br>
                            <?= nl2br(htmlspecialchars($r["remarks"], ENT_QUOTES)); ?>
                        </div>
                        <?php endif; ?>

                    </div><!-- end tl-card -->
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div><!-- end timeline -->

            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
