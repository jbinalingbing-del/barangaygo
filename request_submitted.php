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
<body class="page-center">

<div class="form-box">
    <div style="font-size: 3rem; margin-bottom: 12px; color: var(--ok);">✓</div>
    <h1>REQUEST SUBMITTED!</h1>
    <p class="sub">Matagumpay na naipadala ang iyong kahilingan.</p>

    <div class="summary">
        <div class="summary-row">
            <span>Status</span>
            <span class="badge badge-pending">Pending</span>
        </div>
        <div class="summary-row">
            <span>Proseso</span>
            <span>1-3 Araw ng Trabaho</span>
        </div>
    </div>

    <p style="font-size: 0.85rem; color: var(--text-soft); margin-bottom: 24px;">
        Makakatanggap ka ng abiso kapag na-review at handa na para kunin ang iyong dokumento.
    </p>

    <div class="actions" style="display: flex; flex-direction: column; gap: 10px;">
        <a href="my_requests.php" class="btn btn-block">Tingnan ang Aking mga Request</a>
        <a href="services.php" class="btn btn-outline btn-block">Bumalik sa mga Serbisyo</a>
    </div>
</div>

</body>
</html>
