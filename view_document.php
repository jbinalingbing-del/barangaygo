<?php
session_start();
include "db.php";

// Resident lang
if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$resident_id = (int)$_SESSION["resident_id"];
$request_id  = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($request_id <= 0) {
    header("Location: my_requests.php");
    exit();
}

// Kunin ang request — dapat sa kanya lang para hindi makita ng iba
$req = null;
try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT r.request_id, r.service, r.document_file, r.status, res.full_name
                               FROM requests r
                               JOIN residents res ON res.resident_id = r.resident_id
                               WHERE r.request_id = ? AND r.resident_id = ?");
        $stmt->execute([$request_id, $resident_id]);
        $req = $stmt->fetch();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "SELECT r.request_id, r.service, r.document_file, r.status, res.full_name
                                       FROM requests r
                                       JOIN residents res ON res.resident_id = r.resident_id
                                       WHERE r.request_id = ? AND r.resident_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $request_id, $resident_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $req = mysqli_fetch_assoc($res);
    }
} catch (Exception $e) {}

if (!$req || empty($req["document_file"])) {
    header("Location: my_requests.php");
    exit();
}

$services_map = [
    "barangay_clearance"       => "Barangay Clearance",
    "certificate_of_residency" => "Certificate of Residency",
    "certificate_of_indigency" => "Certificate of Indigency",
    "business_clearance"       => "Barangay Business Clearance",
];
$svc_name = $services_map[$req["service"]] ?? ucwords(str_replace("_", " ", $req["service"]));

$file_path = "uploads/documents/" . $req["document_file"];
$ext       = strtolower(pathinfo($req["document_file"], PATHINFO_EXTENSION));
$is_image  = in_array($ext, ["jpg", "jpeg", "png", "webp"]);
$is_pdf    = $ext === "pdf";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($svc_name, ENT_QUOTES); ?> | Document</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .doc-wrap {
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
        }
        .doc-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 16px;
        }
        .doc-toolbar .doc-info h2 {
            margin: 0;
            font-size: 1.1rem;
        }
        .doc-toolbar .doc-info p {
            margin: 4px 0 0;
            font-size: 0.8rem;
            color: var(--text-soft);
        }
        .doc-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .doc-viewer {
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            min-height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .doc-viewer img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .doc-viewer iframe {
            width: 100%;
            height: 80vh;
            border: none;
            display: block;
        }
        @media print {
            nav, .doc-toolbar, .back-link { display: none !important; }
            .doc-viewer { border: none; border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="frame full">
    <?php $active_nav = 'my_requests'; include 'resident_nav.php'; ?>

    <main class="page-main top" style="justify-content: flex-start;">
        <div class="doc-wrap">

            <a href="my_requests.php" class="back-link" style="margin-bottom: 16px; display: inline-block;">← Back to My Requests</a>

            <!-- Toolbar -->
            <div class="doc-toolbar">
                <div class="doc-info">
                    <h2><?= htmlspecialchars($svc_name, ENT_QUOTES); ?></h2>
                    <p>Request #<?= $req["request_id"]; ?> · <?= htmlspecialchars($req["full_name"], ENT_QUOTES); ?></p>
                </div>
                <div class="doc-actions">
                    <!-- Download -->
                    <a href="<?= htmlspecialchars($file_path, ENT_QUOTES); ?>"
                       download="<?= htmlspecialchars($svc_name . "_Request" . $req["request_id"], ENT_QUOTES); ?>.<?= $ext; ?>"
                       class="btn"
                       style="background: #16a34a; border-color: #16a34a; color: #fff; text-decoration: none; padding: 10px 20px;">
                        Download
                    </a>
                    <!-- Print -->
                    <button onclick="printDoc()" class="btn" style="padding: 10px 20px;">
                        Print
                    </button>
                </div>
            </div>

            <!-- Document Viewer -->
            <div class="doc-viewer">
                <?php if ($is_image): ?>
                    <img src="<?= htmlspecialchars($file_path, ENT_QUOTES); ?>"
                         alt="<?= htmlspecialchars($svc_name, ENT_QUOTES); ?> Document"
                         id="docImg">
                <?php elseif ($is_pdf): ?>
                    <iframe src="<?= htmlspecialchars($file_path, ENT_QUOTES); ?>#toolbar=1&navpanes=0"
                            title="<?= htmlspecialchars($svc_name, ENT_QUOTES); ?>">
                    </iframe>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-soft);">
                        <div style="font-size: 3rem; margin-bottom: 16px;">📎</div>
                        <p>Unable to preview this file in the browser.</p>
                        <a href="<?= htmlspecialchars($file_path, ENT_QUOTES); ?>"
                           download class="btn" style="margin-top: 12px; display: inline-block;">
                        ⬇️ Download
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
function printDoc() {
    <?php if ($is_image): ?>
        // Para sa image — buksan sa bagong tab at i-print
        var w = window.open("<?= htmlspecialchars($file_path, ENT_QUOTES); ?>", "_blank");
        w.onload = function() { w.print(); };
    <?php elseif ($is_pdf): ?>
        // Para sa PDF — gamitin ang built-in browser print
        document.querySelector("iframe").contentWindow.print();
    <?php else: ?>
        window.print();
    <?php endif; ?>
}
</script>

</body>
</html>
