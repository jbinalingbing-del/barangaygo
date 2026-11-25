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
    "Pending"  => "Your request has been received and is being reviewed by the barangay.",
    "Approved" => "Your request has been approved. The document is being prepared.",
    "Ready"    => "Your document is ready! You can now download and print it or claim it at the barangay hall.",
    "Released" => "Your document has been released. Request process completed.",
    "Rejected" => "Your request has been declined. Please see the remarks below.",
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
    $error = "Unable to load your requests: " . $e->getMessage();
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
    <title>My Requests - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ── Container ── */
        .hist-wrap {
            width: 100%;
            max-width: 1100px;
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

        /* ── Custom Modal Backdrop & Card ── */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 9999;
            animation: modalFadeIn 0.2s ease-out;
        }
        .modal-card {
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 480px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.18);
            overflow: hidden;
            animation: modalSlideUp 0.2s ease-out;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
        }
        .modal-body {
            padding: 20px 22px;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes modalSlideUp {
            from { transform: translateY(14px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>

<div class="frame full">
    <?php $active_nav = 'my_requests'; include 'resident_nav.php'; ?>

    <main class="page-main top">
        <a href="services.php" class="back-link">&larr; Back</a>
        <div class="hist-wrap">

            <!-- Page Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
                <div>
                    <h1 style="margin:0; font-size:1.75rem;">My Request History</h1>
                    <p class="page-sub" style="margin:5px 0 0;">Track the progress and review all your document requests.</p>
                </div>
                <a href="services.php" class="btn-new-request">+ New Request</a>
            </div>

            <?php if (isset($_GET["msg"]) && $_GET["msg"] === "updated"): ?>
                <div class="message" style="background:#ecfdf5; border-color:#10b981; color:#065f46; margin-bottom:20px;">
                    ✅ Your request (Request #<?= (int)($_GET["req_id"] ?? 0); ?>) has been successfully updated!
                </div>
            <?php elseif (isset($_GET["msg"]) && $_GET["msg"] === "deleted"): ?>
                <div class="message" style="background:#fef2f2; border-color:#ef4444; color:#991b1b; margin-bottom:20px;">
                    🗑️ Your request (Request #<?= (int)($_GET["req_id"] ?? 0); ?>) has been successfully cancelled and removed.
                </div>
            <?php elseif (isset($_GET["error"])): ?>
                <div class="message" role="alert" style="margin-bottom:20px;">
                    ⚠️ <?= htmlspecialchars($_GET["error"] === "already_processed" ? "This request can no longer be edited or cancelled as it has already been processed." : $_GET["error"], ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
            <?php endif; ?>

            <?php if (empty($my_requests)): ?>
                <!-- Empty State -->
                <div class="hist-empty">
                    <div style="font-size:3rem; margin-bottom:14px;">📂</div>
                    <h2 style="margin:0 0 10px;">You have no submitted requests yet</h2>
                    <p style="color:var(--text-soft); margin-bottom:22px;">
                        You can request Barangay Clearance, Certificate of Residency,<br>Indigency, or Business Clearance online anytime.
                    </p>
                    <a href="services.php" class="btn">View Available Services</a>
                </div>

            <?php else: ?>

                <!-- Summary Counts -->
                <div class="hist-summary">
                    <div class="hist-pill"><strong><?= $total; ?></strong> Total Requests</div>
                    <?php if ($pending > 0): ?>
                        <div class="hist-pill"><strong style="color:var(--warn);"><?= $pending; ?></strong> Pending</div>
                    <?php endif; ?>
                    <?php if ($released > 0): ?>
                        <div class="hist-pill"><strong><?= $released; ?></strong> Released</div>
                    <?php endif; ?>
                    <?php if ($rejected > 0): ?>
                        <div class="hist-pill"><strong style="color:var(--bad);"><?= $rejected; ?></strong> Declined</div>
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
                        if ($date_key === $today)          $display_date = "Today · " . date("F j, Y", strtotime($date_key));
                        elseif ($date_key === $yesterday)  $display_date = "Yesterday · " . date("F j, Y", strtotime($date_key));
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
                                <small>Purpose</small>
                                <span><?= htmlspecialchars($r["purpose"] ?: "N/A", ENT_QUOTES); ?></span>
                            </div>
                            <?php if (!empty($r["id_type"])): ?>
                            <div class="tl-info-item">
                                <small>ID Type</small>
                                <span><?= htmlspecialchars($r["id_type"], ENT_QUOTES); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($r["business_name"])): ?>
                            <div class="tl-info-item">
                                <small>Business</small>
                                <span><?= htmlspecialchars($r["business_name"], ENT_QUOTES); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($r["years_of_residency"])): ?>
                            <div class="tl-info-item">
                                <small>Residency</small>
                                <span><?= (int)$r["years_of_residency"]; ?> years</span>
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
                            <strong>💬 Barangay Remarks / Notice:</strong><br>
                            <?= nl2br(htmlspecialchars($r["remarks"], ENT_QUOTES)); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Document Download -->
                        <?php if (!empty($r["document_file"])): ?>
                        <div style="
                            margin-top: 14px;
                            padding: 14px 18px;
                            background: rgba(34,197,94,0.07);
                            border: 1px solid var(--ok);
                            border-radius: var(--radius);
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            flex-wrap: wrap;
                            gap: 10px;
                        ">
                            <div>
                                <strong style="color: var(--ok); font-size: 0.95rem;">Your document is ready!</strong>
                                <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-soft);">
                                    Download and print your official certificate.
                                </p>
                            </div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="view_document.php?id=<?= (int)$r["request_id"]; ?>"
                                   class="btn-new-request"
                                   style="padding: 8px 18px; font-size: 0.85rem; text-decoration: none;">
                                    View & Print
                                </a>
                                <a href="uploads/documents/<?= htmlspecialchars($r["document_file"], ENT_QUOTES); ?>"
                                   download
                                   class="btn"
                                   style="padding: 8px 18px; font-size: 0.85rem; background: #16a34a; border-color: #16a34a; color: #fff; text-decoration: none;">
                                    Download
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- CRUD Actions para sa Resident -->
                        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div style="font-size: 0.78rem; color: var(--text-soft);">
                                <?php if ($st === "Pending"): ?>
                                    ⏳ You can still edit or cancel this request while it is Pending.
                                <?php else: ?>
                                    🔒 This request has already been processed by the barangay.
                                <?php endif; ?>
                            </div>
                            <?php if ($st === "Pending"): ?>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <a href="edit_request.php?id=<?= (int)$r["request_id"]; ?>" 
                                   class="btn-new-request" 
                                   style="padding: 6px 14px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    ✏️ Edit
                                </a>
                                <button type="button" 
                                        onclick="openCancelModal(<?= (int)$r['request_id']; ?>, '<?= addslashes(htmlspecialchars($s_name, ENT_QUOTES)); ?>')"
                                        class="btn" 
                                        style="padding: 6px 14px; font-size: 0.8rem; background: #ef4444; border-color: #ef4444; color: #fff; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                    🗑️ Cancel
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div><!-- end tl-card -->
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div><!-- end timeline -->

            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Custom Cancel Request Modal Dialog -->
<div id="cancelModal" class="modal-backdrop" style="display: none;">
    <div class="modal-card" style="max-width: 490px; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.22); font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
        <div class="modal-header" style="background: #ffffff; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; border-radius: 6px; background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">
                    ⚠️
                </div>
                <div>
                    <h3 style="margin: 0; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 1.05rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; color: #0f172a;">
                        Cancel Request
                    </h3>
                    <span id="modalTargetService" style="font-size: 0.82rem; color: #64748b; font-weight: 500;">
                        Request #0 · Document
                    </span>
                </div>
            </div>
            <button type="button" onclick="closeCancelModal()" style="background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; line-height: 1; padding: 0 4px;" title="Close">&times;</button>
        </div>

        <div class="modal-body" style="padding: 22px 24px;">
            <!-- Real Human Government Alert Box -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #b91c1c; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; text-align: left;">
                <div style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.04em; color: #b91c1c; text-transform: uppercase; margin-bottom: 8px;">
                    Important Notice
                </div>
                <ul style="margin: 0; padding-left: 18px; font-size: 0.82rem; color: #334155; line-height: 1.6;">
                    <li><strong>Permanent deletion:</strong> This request will be removed from the barangay queue and cannot be undone.</li>
                    <li><strong>Incorrect details?</strong> You can choose <strong>"Edit"</strong> instead if you only need to correct submitted information.</li>
                    <li><strong>Re-applying anytime:</strong> The service remains open if you need to submit a new request later.</li>
                </ul>
            </div>

            <!-- Cancellation Form -->
            <form id="cancelForm" action="delete_request.php" method="POST">
                <input type="hidden" name="request_id" id="modalRequestId" value="">

                <!-- Reason selection (Optional) -->
                <div style="margin-bottom: 18px; text-align: left;">
                    <label for="cancelReason" style="display: block; font-size: 0.84rem; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                        Why do you want to cancel? (Optional):
                    </label>
                    <select id="cancelReason" name="cancel_reason" style="width: 100%; padding: 8px 12px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; background: #ffffff;">
                        <option value="">Select a reason (Optional)</option>
                        <option value="Incorrect details submitted">Incorrect details submitted</option>
                        <option value="No longer needed">No longer needed</option>
                        <option value="Will submit a new request">Will submit a new request</option>
                        <option value="Other reason">Other reason</option>
                    </select>
                </div>

                <!-- Agreement Checkbox -->
                <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.84rem; color: #1e293b; cursor: pointer; user-select: none; margin-bottom: 24px; text-align: left;">
                    <input type="checkbox" id="confirmAgree" onchange="toggleCancelBtn()" style="margin-top: 3px; width: 17px; height: 17px; accent-color: #b91c1c; flex-shrink: 0; cursor: pointer;">
                    <span style="line-height: 1.45;">I understand the notice and wish to permanently cancel this request.</span>
                </label>

                <!-- Actions -->
                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                    <button type="button" onclick="closeCancelModal()" 
                            style="padding: 10px 18px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #334155; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; transition: all 0.15s ease;">
                        Keep Request
                    </button>
                    <button type="submit" id="submitCancelBtn" disabled
                            style="padding: 10px 22px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #ffffff; background: #94a3b8; border: 1px solid #94a3b8; border-radius: 6px; cursor: not-allowed; transition: all 0.15s ease;">
                        Yes, Cancel Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCancelModal(reqId, serviceName) {
    document.getElementById("modalRequestId").value = reqId;
    document.getElementById("modalTargetService").innerText = "Request #" + reqId + " · " + serviceName;
    document.getElementById("confirmAgree").checked = false;
    toggleCancelBtn();
    document.getElementById("cancelModal").style.display = "flex";
}

function closeCancelModal() {
    document.getElementById("cancelModal").style.display = "none";
}

function toggleCancelBtn() {
    const agree = document.getElementById("confirmAgree").checked;
    const btn = document.getElementById("submitCancelBtn");
    btn.disabled = !agree;
    if (agree) {
        btn.style.background = "#b91c1c";
        btn.style.borderColor = "#b91c1c";
        btn.style.color = "#ffffff";
        btn.style.cursor = "pointer";
    } else {
        btn.style.background = "#94a3b8";
        btn.style.borderColor = "#94a3b8";
        btn.style.color = "#ffffff";
        btn.style.cursor = "not-allowed";
    }
}

// Close if clicked on backdrop
window.addEventListener("click", function(e) {
    const modal = document.getElementById("cancelModal");
    if (e.target === modal) {
        closeCancelModal();
    }
});
</script>

</body>
</html>
