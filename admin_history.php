<?php
session_start();
include "db.php";

// Admin access check
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin.php");
    exit();
}

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

// Filters & Search
$filter_service = trim($_GET["service"] ?? "");
$filter_status  = trim($_GET["status"] ?? "");
$search         = trim($_GET["search"] ?? "");

// Build Query
$sql = "
    SELECT r.request_id, r.service, r.purpose, r.status, r.created_at,
           res.resident_id, res.full_name, res.email, res.mobile
    FROM requests r
    JOIN residents res ON res.resident_id = r.resident_id
    WHERE 1=1
";
$params = [];
$types  = "";

if ($filter_service !== "" && isset($services_map[$filter_service])) {
    $sql .= " AND r.service = ?";
    $params[] = $filter_service;
    $types .= "s";
}

if ($filter_status !== "" && isset($badge_map[$filter_status])) {
    $sql .= " AND r.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($search !== "") {
    $sql .= " AND (res.full_name LIKE ? OR res.email LIKE ? OR r.request_id = ? OR r.purpose LIKE ?)";
    $like = "%" . $search . "%";
    $search_id = is_numeric($search) ? (int)$search : 0;
    $params[] = $like;
    $params[] = $like;
    $params[] = $search_id;
    $params[] = $like;
    $types .= "ssis";
}

$sql .= " ORDER BY r.created_at DESC";

// Execute
$requests = [];
$stats = [
    "total"    => 0,
    "pending"  => 0,
    "approved" => 0,
    "ready"    => 0,
    "released" => 0,
    "rejected" => 0,
];

try {
    if ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        // Get overall stats
        $c_stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM requests GROUP BY status");
        while ($c = $c_stmt->fetch()) {
            $st = strtolower($c["status"]);
            if (isset($stats[$st])) {
                $stats[$st] = (int)$c["cnt"];
            }
            $stats["total"] += (int)$c["cnt"];
        }
    } elseif ($conn) {
        if (!empty($params)) {
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $requests[] = $row;
            }
        } else {
            $res = mysqli_query($conn, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $requests[] = $row;
                }
            }
        }

        $c_res = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM requests GROUP BY status");
        if ($c_res) {
            while ($c = mysqli_fetch_assoc($c_res)) {
                $st = strtolower($c["status"]);
                if (isset($stats[$st])) {
                    $stats[$st] = (int)$c["cnt"];
                }
                $stats["total"] += (int)$c["cnt"];
            }
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talaan at Kasaysayan ng Lahat ng Requests - BarangayGo Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .history-card {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .mini-stat {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 12px;
            text-align: center;
        }
        .mini-stat .num {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--focus);
        }
        .mini-stat .lbl {
            font-size: 0.75rem;
            color: var(--text-soft);
            text-transform: uppercase;
        }
        .filter-bar {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .filter-bar select, .filter-bar input[type="text"] {
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 12px;
            border-radius: var(--radius);
            font-size: 0.88rem;
        }
        .filter-bar input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .history-table th {
            background: rgba(255, 255, 255, 0.04);
            padding: 12px 14px;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-soft);
            text-align: left;
            border-bottom: 2px solid var(--border);
        }
        .history-table td {
            padding: 12px 14px;
            font-size: 0.88rem;
            border-bottom: 1px solid var(--border);
        }
        .history-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
    </style>
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_history.php" class="active">Lahat ng Requests</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top">
        <div class="history-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                <div>
                    <h1 style="margin: 0;">Talaan at Kasaysayan ng Requests</h1>
                    <p class="page-sub" style="margin: 4px 0 0 0;">Lahat ng kahilingan mula sa mga residente para sa opisyal na rekord.</p>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="stats-row">
                <div class="mini-stat">
                    <span class="num"><?= $stats["total"]; ?></span>
                    <span class="lbl">Kabuuang Request</span>
                </div>
                <div class="mini-stat">
                    <span class="num" style="color: var(--warn);"><?= $stats["pending"]; ?></span>
                    <span class="lbl">Pending</span>
                </div>
                <div class="mini-stat">
                    <span class="num" style="color: var(--ok);"><?= $stats["approved"]; ?></span>
                    <span class="lbl">Approved</span>
                </div>
                <div class="mini-stat">
                    <span class="num" style="color: var(--info);"><?= $stats["ready"]; ?></span>
                    <span class="lbl">Ready</span>
                </div>
                <div class="mini-stat">
                    <span class="num" style="color: #60a5fa;"><?= $stats["released"]; ?></span>
                    <span class="lbl">Released</span>
                </div>
                <div class="mini-stat">
                    <span class="num" style="color: var(--danger);"><?= $stats["rejected"]; ?></span>
                    <span class="lbl">Rejected</span>
                </div>
            </div>

            <!-- Filters & Search Form -->
            <form method="GET" action="" class="filter-bar">
                <input type="text" name="search" placeholder="Maghanap ayon sa Pangalan, Email, ID, Layunin..." value="<?= htmlspecialchars($search, ENT_QUOTES); ?>">

                <select name="service">
                    <option value="">Lahat ng Serbisyo</option>
                    <?php foreach ($services_map as $key => $title): ?>
                        <option value="<?= $key; ?>" <?= ($filter_service === $key) ? "selected" : ""; ?>>
                            <?= htmlspecialchars($title, ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="">Lahat ng Status</option>
                    <?php foreach ($badge_map as $st => $cls): ?>
                        <option value="<?= $st; ?>" <?= ($filter_status === $st) ? "selected" : ""; ?>>
                            <?= htmlspecialchars($st, ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn" style="padding: 8px 18px;">I-filter</button>
                <?php if ($filter_service !== "" || $filter_status !== "" || $search !== ""): ?>
                    <a href="admin_history.php" class="btn btn-outline" style="padding: 8px 14px;">I-reset</a>
                <?php endif; ?>
            </form>

            <!-- Table of Requests -->
            <?php if (empty($requests)): ?>
                <div class="view-panel" style="text-align: center; padding: 40px;">
                    <p style="color: var(--text-soft); font-size: 1rem; margin-bottom: 8px;">Walang nahanap na record sa kasalukuyang filter o search.</p>
                    <a href="admin_history.php" class="btn btn-outline">I-reset ang Filter</a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Residente</th>
                                <th>Serbisyo</th>
                                <th>Layunin (Purpose)</th>
                                <th>Petsa</th>
                                <th>Status</th>
                                <th>Aksyon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <?php $s_name = $services_map[$r["service"]] ?? ucwords(str_replace("_", " ", $r["service"])); ?>
                                <tr>
                                    <td style="font-weight: bold;">#<?= $r["request_id"]; ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($r["full_name"], ENT_QUOTES); ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--text-soft);">
                                            <?= htmlspecialchars($r["mobile"] ?: $r["email"], ENT_QUOTES); ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($s_name, ENT_QUOTES); ?></td>
                                    <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($r['purpose'], ENT_QUOTES); ?>">
                                        <?= htmlspecialchars($r["purpose"], ENT_QUOTES); ?>
                                    </td>
                                    <td style="white-space: nowrap; color: var(--text-soft); font-size: 0.82rem;">
                                        <?= date("M d, Y", strtotime($r["created_at"])); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge_map[$r['status']] ?? 'badge-pending'; ?>">
                                            <?= htmlspecialchars($r["status"], ENT_QUOTES); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin_view.php?id=<?= $r['request_id']; ?>" class="btn" style="padding: 4px 10px; font-size: 0.78rem;">
                                            Tingnan
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
