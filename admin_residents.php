<?php
session_start();
include "db.php";

// Admin access check
if (!isset($_SESSION["admin_id"])) {
    header("Location: admin.php");
    exit();
}

$valid_barangays = [
    "Abella","Bagumbayan Norte","Bagumbayan Sur","Balatas",
    "Calauag","Cararayan","Carolina","Concepcion Grande","Concepcion Pequena",
    "Dayangdang","Del Rosario","Dinaga","Igualdad Interior",
    "Lerma","Liboton","Mabolo","Pacol","Panicuason",
    "Penafrancia","Sabang","San Felipe","San Francisco",
    "San Isidro","Sta. Cruz","Tabuco","Tinago","Triangulo"
];

// Search & Filters
$search          = trim($_GET["search"] ?? "");
$filter_barangay = trim($_GET["barangay"] ?? "");
$filter_sex      = trim($_GET["sex"] ?? "");
$filter_status   = trim($_GET["civil_status"] ?? "");

// Build SQL Query
$sql = "
    SELECT r.*, COUNT(req.request_id) AS total_requests
    FROM residents r
    LEFT JOIN requests req ON req.resident_id = r.resident_id
    WHERE 1=1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (r.full_name LIKE ? OR r.email LIKE ? OR r.mobile LIKE ? OR r.street LIKE ? OR r.resident_id = ?)";
    $like = "%" . $search . "%";
    $search_id = is_numeric($search) ? (int)$search : 0;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $search_id;
}

if ($filter_barangay !== "" && in_array($filter_barangay, $valid_barangays, true)) {
    $sql .= " AND r.barangay = ?";
    $params[] = $filter_barangay;
}

if ($filter_sex !== "" && in_array($filter_sex, ["Male", "Female"], true)) {
    $sql .= " AND r.sex = ?";
    $params[] = $filter_sex;
}

if ($filter_status !== "") {
    $sql .= " AND r.civil_status = ?";
    $params[] = $filter_status;
}

$sql .= " GROUP BY r.resident_id ORDER BY r.created_at DESC";

$residents = [];
$stats = [
    "total"  => 0,
    "male"   => 0,
    "female" => 0,
    "new"    => 0,
];

try {
    if ($pdo) {
        // Stats
        $stats["total"]  = (int)$pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
        $stats["male"]   = (int)$pdo->query("SELECT COUNT(*) FROM residents WHERE sex = 'Male'")->fetchColumn();
        $stats["female"] = (int)$pdo->query("SELECT COUNT(*) FROM residents WHERE sex = 'Female'")->fetchColumn();
        $stats["new"]    = (int)$pdo->query("SELECT COUNT(*) FROM residents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

        // Residents List
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $residents = $stmt->fetchAll();
    } elseif ($conn) {
        // Stats
        $r = mysqli_query($conn, "SELECT COUNT(*) FROM residents");
        if ($r) $stats["total"] = (int)mysqli_fetch_row($r)[0];

        $r = mysqli_query($conn, "SELECT COUNT(*) FROM residents WHERE sex = 'Male'");
        if ($r) $stats["male"] = (int)mysqli_fetch_row($r)[0];

        $r = mysqli_query($conn, "SELECT COUNT(*) FROM residents WHERE sex = 'Female'");
        if ($r) $stats["female"] = (int)mysqli_fetch_row($r)[0];

        $r = mysqli_query($conn, "SELECT COUNT(*) FROM residents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($r) $stats["new"] = (int)mysqli_fetch_row($r)[0];

        // Residents List
        $stmt = mysqli_prepare($conn, $sql);
        if (!empty($params)) {
            $types = str_repeat("s", count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $residents[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "DB Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Management - BarangayGo Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .res-container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 8px;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .mini-stat {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 18px 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .mini-stat .num {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a !important; /* Authentic solid black/neutral */
            margin-bottom: 6px;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
            line-height: 1;
        }
        .mini-stat .lbl {
            font-size: 0.74rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .filter-bar {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 16px 18px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .filter-bar input[type="text"],
        .filter-bar select {
            height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            color: #0f172a;
            font-size: 0.85rem;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .filter-bar input[type="text"] {
            flex: 1;
            min-width: 220px;
        }
        .res-table-wrap {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow-x: auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .res-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
            text-align: left;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .res-table th {
            background: #f8fafc;
            padding: 14px 16px;
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            border-bottom: 2px solid #cbd5e1;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .res-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .res-table tr:hover {
            background: #f8fafc;
        }
        .res-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
            border: 1px solid #cbd5e1;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
</head>
<body>

<div class="frame full">
    <nav class="frame-nav">
        <a href="admin_dashboard.php" class="brand">BarangayGo</a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_residents.php" class="active">Residents</a>
            <a href="admin_history.php">All Requests</a>
            <a href="admin_logout.php">Logout</a>
        </div>
    </nav>

    <main class="page-main top" style="justify-content: flex-start;">
        <div class="res-container">
            <a href="admin_dashboard.php" class="back-link">&larr; Back to Dashboard</a>

            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 22px;">
                <div>
                    <h1 style="margin: 0; font-size: 1.75rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">👥 Resident Management</h1>
                    <p class="page-sub" style="margin: 4px 0 0;">List, search, edit, and manage registered residents.</p>
                </div>
                <a href="admin_resident_add.php" class="btn" style="background: #1e293b; border-color: #1e293b; color: #fff; padding: 10px 20px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.02em;">
                    + Add Resident
                </a>
            </div>

            <!-- Flash Alerts -->
            <?php if (isset($_GET["msg"]) && $_GET["msg"] === "added"): ?>
                <div class="message" style="background:#ecfdf5; border-color:#10b981; color:#065f46; margin-bottom:20px;">
                    ✅ Resident <?= isset($_GET['name']) ? '(' . htmlspecialchars($_GET['name'], ENT_QUOTES) . ')' : ''; ?> was successfully added!
                </div>
            <?php elseif (isset($_GET["msg"]) && $_GET["msg"] === "updated"): ?>
                <div class="message" style="background:#ecfdf5; border-color:#10b981; color:#065f46; margin-bottom:20px;">
                    ✅ Resident information <?= isset($_GET['name']) ? '(' . htmlspecialchars($_GET['name'], ENT_QUOTES) . ')' : ''; ?> was successfully updated!
                </div>
            <?php elseif (isset($_GET["msg"]) && $_GET["msg"] === "deleted"): ?>
                <div class="message" style="background:#fef2f2; border-color:#ef4444; color:#991b1b; margin-bottom:20px;">
                    🗑️ Resident has been deleted from the database.
                </div>
            <?php elseif (isset($_GET["error"])): ?>
                <div class="message" role="alert" style="margin-bottom:20px;">
                    ⚠️ <?= htmlspecialchars($_GET["error"], ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Row with Solid Black Numbers -->
            <div class="stats-row">
                <div class="mini-stat">
                    <span class="num"><?= $stats["total"]; ?></span>
                    <span class="lbl">Total Residents</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["male"]; ?></span>
                    <span class="lbl">Male</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["female"]; ?></span>
                    <span class="lbl">Female</span>
                </div>
                <div class="mini-stat">
                    <span class="num"><?= $stats["new"]; ?></span>
                    <span class="lbl">New (Last 30 Days)</span>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <form action="admin_residents.php" method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="Search by name, email, mobile, or street..."
                       value="<?= htmlspecialchars($search, ENT_QUOTES); ?>">

                <select name="barangay">
                    <option value="">All Barangays</option>
                    <?php foreach ($valid_barangays as $b): ?>
                        <option value="<?= htmlspecialchars($b, ENT_QUOTES); ?>" <?= ($filter_barangay === $b) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($b, ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="sex">
                    <option value="">All Genders</option>
                    <option value="Male" <?= ($filter_sex === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?= ($filter_sex === 'Female') ? 'selected' : ''; ?>>Female</option>
                </select>

                <select name="civil_status">
                    <option value="">All Civil Statuses</option>
                    <option value="Single" <?= ($filter_status === 'Single') ? 'selected' : ''; ?>>Single</option>
                    <option value="Married" <?= ($filter_status === 'Married') ? 'selected' : ''; ?>>Married</option>
                    <option value="Widowed" <?= ($filter_status === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                    <option value="Separated" <?= ($filter_status === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                </select>

                <button type="submit" class="btn" style="padding: 0 18px; height: 40px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; font-weight: 700;">Search</button>
                <?php if ($search !== "" || $filter_barangay !== "" || $filter_sex !== "" || $filter_status !== ""): ?>
                    <a href="admin_residents.php" class="btn-new-request" style="text-decoration: none; padding: 0 16px; height: 40px; line-height: 40px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem;">Reset</a>
                <?php endif; ?>
            </form>

            <!-- Table of Residents -->
            <div class="res-table-wrap">
                <?php if (empty($residents)): ?>
                    <div style="text-align: center; padding: 50px 20px;">
                        <div style="font-size: 2.8rem; margin-bottom: 10px;">👥</div>
                        <h3 style="margin: 0 0 6px; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">No residents found</h3>
                        <p style="color: var(--text-soft); margin: 0;">Try adjusting your search filters or add a new resident.</p>
                    </div>
                <?php else: ?>
                    <table class="res-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Resident</th>
                                <th>Age / Gender</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Requests</th>
                                <th>Registration Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($residents as $res): ?>
                                <?php
                                    $initials = strtoupper(substr($res["first_name"] ?: "R", 0, 1) . substr($res["last_name"] ?: "", 0, 1));
                                ?>
                                <tr>
                                    <td>
                                        <span style="font-weight: 700; color: #0f172a;">#<?= $res["resident_id"]; ?></span>
                                    </td>
                                    <td>
                                        <div class="user-cell">
                                            <?php if (!empty($res["profile_pic"]) && file_exists(__DIR__ . "/uploads/" . $res["profile_pic"])): ?>
                                                <img src="uploads/<?= htmlspecialchars($res["profile_pic"], ENT_QUOTES); ?>" alt="Profile" class="res-avatar">
                                            <?php else: ?>
                                                <div class="res-avatar"><?= $initials; ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="color: #0f172a;"><?= htmlspecialchars($res["full_name"], ENT_QUOTES); ?></strong>
                                                <div style="font-size: 0.78rem; color: #64748b;"><?= htmlspecialchars($res["civil_status"], ENT_QUOTES); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><strong style="color: #0f172a;"><?= (int)$res["age"]; ?></strong> yrs old</div>
                                        <span style="font-size: 0.78rem; color: #64748b;">
                                            <?= htmlspecialchars($res["sex"], ENT_QUOTES); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>📞 <?= htmlspecialchars($res["mobile"] ?: "N/A", ENT_QUOTES); ?></div>
                                        <div style="font-size: 0.78rem; color: #64748b;">✉️ <?= htmlspecialchars($res["email"], ENT_QUOTES); ?></div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($res["street"] ?: "N/A", ENT_QUOTES); ?></div>
                                        <div style="font-size: 0.78rem; color: #64748b;"><?= htmlspecialchars($res["barangay"] . ", " . $res["city"], ENT_QUOTES); ?></div>
                                    </td>
                                    <td>
                                        <a href="admin_history.php?search=<?= urlencode($res['email']); ?>" 
                                           style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #0f172a;">
                                            📄 <?= (int)$res["total_requests"]; ?> requests
                                        </a>
                                    </td>
                                    <td style="color: #64748b; font-size: 0.82rem; white-space: nowrap;">
                                        <?= date("M d, Y", strtotime($res["created_at"])); ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; gap: 6px;">
                                            <a href="admin_resident_edit.php?id=<?= $res['resident_id']; ?>" 
                                               class="btn" 
                                               style="padding: 6px 12px; font-size: 0.78rem; text-decoration: none; font-family: 'Verdana', Geneva, Tahoma, sans-serif; display: inline-flex; align-items: center; gap: 4px;">
                                                ✏️ Edit
                                            </a>
                                            <form action="admin_resident_delete.php" method="POST" style="margin: 0; display: inline;"
                                                  onsubmit="return confirm('ARE YOU SURE? This will permanently delete resident <?= addslashes(htmlspecialchars($res['full_name'], ENT_QUOTES)); ?> along with all their requests. This action cannot be undone!');">
                                                <input type="hidden" name="resident_id" value="<?= $res['resident_id']; ?>">
                                                <button type="submit" 
                                                        class="btn" 
                                                        style="padding: 6px 12px; font-size: 0.78rem; background: #ef4444; border-color: #ef4444; color: #fff; cursor: pointer; font-family: 'Verdana', Geneva, Tahoma, sans-serif; display: inline-flex; align-items: center; gap: 4px;"
                                                        title="Delete Resident">
                                                    🗑️ Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
