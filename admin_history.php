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
    <title>All Requests - BarangayGo Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .history-card {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .mini-stat {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 14px 12px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .mini-stat .num {
            display: block;
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f172a !important; /* Authentic solid black/neutral */
            margin-bottom: 4px;
            line-height: 1;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .mini-stat .lbl {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #475569;
            text-transform: uppercase;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .filter-bar {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .filter-bar select, .filter-bar input[type="text"] {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.84rem;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .filter-bar input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .history-table th {
            background: #f8fafc;
            padding: 12px 14px;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            font-weight: 700;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .history-table td {
            padding: 12px 14px;
            font-size: 0.84rem;
            border-bottom: 1px solid #e2e8f0;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .history-table tr:hover td {
            background: #f8fafc;
        }
    </style>
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_residents.php">Residents</a>
            <a href="admin_history.php" class="active">All Requests</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top" style="justify-content: flex-start;">
        <a href="admin_dashboard.php" class="back-link">&larr; Back to Dashboard</a>
        <div class="history-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                <div>
                    <h1 style="margin: 0; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 1.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">Records & Request History</h1>
                    <p class="page-sub" style="margin: 4px 0 0 0;">All document requests from residents for official record keeping.</p>
                </div>
            </div>

            <?php if (isset($_GET["msg"]) && $_GET["msg"] === "deleted"): ?>
                <div class="message" style="background:#ecfdf5; border-color:#10b981; color:#065f46; margin-bottom:16px;">
                    ✅ Request #<?= (int)($_GET["req_id"] ?? 0); ?> was successfully deleted from records.
                </div>
            <?php endif; ?>

            <!-- Stats Bar -->
            <div class="stats-row">
                <div class="mini-stat">
                    <span class="num"><?= $stats["total"]; ?></span>
                    <span class="lbl">Total Requests</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["pending"]; ?></span>
                    <span class="lbl">Pending</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["approved"]; ?></span>
                    <span class="lbl">Approved</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["ready"]; ?></span>
                    <span class="lbl">Ready</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["released"]; ?></span>
                    <span class="lbl">Released</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["rejected"]; ?></span>
                    <span class="lbl">Rejected</span>
                </div>
            </div>

            <!-- Filters & Search Form -->
            <form method="GET" action="" class="filter-bar">
                <input type="text" name="search" placeholder="Search by Name, Email, ID, Purpose..." value="<?= htmlspecialchars($search, ENT_QUOTES); ?>">

                <select name="service">
                    <option value="">All Services</option>
                    <?php foreach ($services_map as $key => $title): ?>
                        <option value="<?= $key; ?>" <?= ($filter_service === $key) ? "selected" : ""; ?>>
                            <?= htmlspecialchars($title, ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($badge_map as $st => $cls): ?>
                        <option value="<?= $st; ?>" <?= ($filter_status === $st) ? "selected" : ""; ?>>
                            <?= htmlspecialchars($st, ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn" style="padding: 8px 18px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; font-weight: 700;">Filter</button>
                <?php if ($filter_service !== "" || $filter_status !== "" || $search !== ""): ?>
                    <a href="admin_history.php" class="btn btn-outline" style="padding: 8px 14px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem;">Reset</a>
                <?php endif; ?>
            </form>

            <!-- Table of Requests -->
            <?php if (empty($requests)): ?>
                <div class="view-panel" style="text-align: center; padding: 40px; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
                    <p style="color: var(--text-soft); font-size: 1rem; margin-bottom: 8px;">No records found matching the current filter or search criteria.</p>
                    <a href="admin_history.php" class="btn btn-outline">Reset Filter</a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Resident</th>
                                <th>Service</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <?php $s_name = $services_map[$r["service"]] ?? ucwords(str_replace("_", " ", $r["service"])); ?>
                                <tr>
                                    <td>
                                        <span style="
                                            display: inline-flex;
                                            align-items: center;
                                            justify-content: center;
                                            background: #1e293b;
                                            color: #fff;
                                            font-size: 0.78rem;
                                            font-weight: 700;
                                            border-radius: 4px;
                                            padding: 3px 8px;
                                            min-width: 32px;
                                        ">#<?= $r["request_id"]; ?></span>
                                    </td>
                                    <td>
                                        <strong style="color: #0f172a;"><?= htmlspecialchars($r["full_name"], ENT_QUOTES); ?></strong>
                                        <div style="font-size: 0.75rem; color: #64748b;">
                                            <?= htmlspecialchars($r["mobile"] ?: $r["email"], ENT_QUOTES); ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($s_name, ENT_QUOTES); ?></td>
                                    <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($r['purpose'], ENT_QUOTES); ?>">
                                        <?= htmlspecialchars($r["purpose"], ENT_QUOTES); ?>
                                    </td>
                                    <td style="white-space: nowrap; color: #64748b; font-size: 0.82rem;">
                                        <?= date("M d, Y", strtotime($r["created_at"])); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge_map[$r['status']] ?? 'badge-pending'; ?>">
                                            <?= htmlspecialchars($r["status"], ENT_QUOTES); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center;">
                                            <a href="admin_view.php?id=<?= $r['request_id']; ?>" class="btn" style="padding: 4px 10px; font-size: 0.78rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; display: inline-flex; align-items: center; gap: 4px;">
                                                👁️ View
                                            </a>
                                            <form action="delete_request.php" method="POST" style="margin: 0; display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete Request #<?= $r['request_id']; ?>? This action cannot be undone.');">
                                                <input type="hidden" name="request_id" value="<?= $r['request_id']; ?>">
                                                <button type="submit" class="btn" style="padding: 4px 10px; font-size: 0.78rem; background: #ef4444; border-color: #ef4444; color: #fff; font-family: 'Verdana', Geneva, Tahoma, sans-serif; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;" title="Delete Request">
                                                    🗑️ Delete
                                                </button>
                                            </form>
                                        </div>
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
