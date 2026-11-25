<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="frame full">
    <?php $active_nav = 'services'; include 'resident_nav.php'; ?>

    <main class="page-main">
        <div class="form-box" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif;">
            <div style="font-size: 3rem; margin-bottom: 12px; color: var(--ok);">✓</div>
            <h1 style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">REQUEST SUBMITTED!</h1>
            <p class="sub">Your request has been successfully submitted.</p>

            <div class="summary">
                <div class="summary-row">
                    <span>Status</span>
                    <span class="badge badge-pending">Pending</span>
                </div>
                <div class="summary-row">
                    <span>Processing Time</span>
                    <span>1-3 Working Days</span>
                </div>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-soft); margin-bottom: 24px;">
                You will receive a notification once your request has been reviewed and is ready.
            </p>

            <div class="actions" style="display: flex; flex-direction: column; gap: 10px;">
                <a href="my_requests.php" class="btn btn-block" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.84rem; font-weight: 700;">View My Requests</a>
                <a href="services.php" class="btn btn-outline btn-block" style="font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-size: 0.84rem;">Back to Services</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>
