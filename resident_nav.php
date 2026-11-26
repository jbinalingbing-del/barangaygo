<?php
// Naka-lock na navigation bar para sa lahat ng resident pages
// Gumamit ng $active_nav bago i-include ('services', 'my_requests', 'notifications', 'profile')
$active_nav = $active_nav ?? '';

// Kunin ang bilang ng hindi pa nababasang notifications
$unread_count = 0;
if (isset($_SESSION["resident_id"])) {
    if (empty($pdo) && empty($conn)) {
        include_once __DIR__ . "/db.php";
    }
    try {
        $rid = (int)$_SESSION["resident_id"];
        if (!empty($pdo)) {
            $ns = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE resident_id = ? AND is_read = 0");
            $ns->execute([$rid]);
            $unread_count = (int)$ns->fetchColumn();
        } elseif (!empty($conn)) {
            $ns = mysqli_prepare($conn, "SELECT COUNT(*) FROM notifications WHERE resident_id = ? AND is_read = 0");
            if ($ns) {
                mysqli_stmt_bind_param($ns, "i", $rid);
                mysqli_stmt_execute($ns);
                mysqli_stmt_bind_result($ns, $unread_count);
                mysqli_stmt_fetch($ns);
                mysqli_stmt_close($ns);
            }
        }
    } catch (Exception $e) {}
}
?>
<nav class="frame-nav">
    <a href="services.php" class="brand">
        <img src="assets/community-connect-logo.png" alt="BarangayGo Logo" class="brand-nav-logo">
        <span>BarangayGo</span>
    </a>
    <div class="nav-links">
        <a href="services.php"<?= $active_nav === 'services' ? ' class="active"' : ''; ?>>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            Services
        </a>
        <a href="dashboard.php"<?= $active_nav === 'dashboard' ? ' class="active"' : ''; ?>>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Dashboard
        </a>
        <a href="my_requests.php"<?= $active_nav === 'my_requests' ? ' class="active"' : ''; ?>>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path><rect x="9" y="3" width="6" height="4" rx="1"></rect><line x1="9" y1="12" x2="15" y2="12"></line><line x1="9" y1="16" x2="15" y2="16"></line></svg>
            My Requests
        </a>
        <a href="notifications.php" id="navNotifLink" style="position:relative;"<?= $active_nav === 'notifications' ? ' class="active"' : ''; ?>>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            Notifications<?php if ($unread_count > 0): ?><span id="navNotifBadge" style="position:absolute;top:-6px;right:-10px;background:#ef4444;color:#fff;font-size:0.65rem;font-weight:700;border-radius:999px;padding:1px 5px;min-width:16px;text-align:center;"><?= $unread_count; ?></span><?php endif; ?>
        </a>
        <a href="profile.php"<?= $active_nav === 'profile' ? ' class="active"' : ''; ?>>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            Profile
        </a>
        <a href="logout.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            Logout
        </a>
    </div>
</nav>
<?php if (isset($_SESSION["resident_id"])): ?>
<script>
// Awtomatikong i-update ang unread notification badge nang hindi nagre-refresh ng page
(function () {
    if (window.__bgNotifNavInit) return;
    window.__bgNotifNavInit = true;

    window.updateNavNotifBadge = function (count) {
        var link = document.getElementById('navNotifLink');
        if (!link) return;
        var badge = document.getElementById('navNotifBadge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'navNotifBadge';
                badge.style.cssText = 'position:absolute;top:-6px;right:-10px;background:#ef4444;color:#fff;font-size:0.65rem;font-weight:700;border-radius:999px;padding:1px 5px;min-width:16px;text-align:center;';
                link.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    };

    setInterval(function () {
        if (document.hidden) return;
        fetch('notifications_poll.php', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && typeof data.unread === 'number') {
                    window.updateNavNotifBadge(data.unread);
                }
            })
            .catch(function () {});
    }, 4000);
})();
</script>
<?php endif; ?>
