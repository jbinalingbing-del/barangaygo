<?php
session_start();
include "db.php";

if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit();
}

$resident_id = (int)$_SESSION["resident_id"];

// Delete single notification (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_notif_id"])) {
    $nid = (int)$_POST["delete_notif_id"];
    if ($nid > 0) {
        try {
            if ($pdo) {
                $pdo->prepare("DELETE FROM notifications WHERE notif_id = ? AND resident_id = ?")->execute([$nid, $resident_id]);
            } elseif ($conn) {
                $del = mysqli_prepare($conn, "DELETE FROM notifications WHERE notif_id = ? AND resident_id = ?");
                mysqli_stmt_bind_param($del, "ii", $nid, $resident_id);
                mysqli_stmt_execute($del);
            }
        } catch (Exception $e) {}
    }
    header("Location: notifications.php");
    exit();
}

// Clear all notifications (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["clear_all_notifs"])) {
    try {
        if ($pdo) {
            $pdo->prepare("DELETE FROM notifications WHERE resident_id = ?")->execute([$resident_id]);
        } elseif ($conn) {
            $del = mysqli_prepare($conn, "DELETE FROM notifications WHERE resident_id = ?");
            mysqli_stmt_bind_param($del, "i", $resident_id);
            mysqli_stmt_execute($del);
        }
    } catch (Exception $e) {}
    header("Location: notifications.php");
    exit();
}

// Markahan bilang nabasa ang isang notification at i-redirect sa kaugnay na page kapag binuksan
if (isset($_GET["open"])) {
    $nid = (int)$_GET["open"];
    $target = "notifications.php";
    if ($nid > 0) {
        try {
            $notif_row = null;
            if ($pdo) {
                $q = $pdo->prepare("SELECT * FROM notifications WHERE notif_id = ? AND resident_id = ?");
                $q->execute([$nid, $resident_id]);
                $notif_row = $q->fetch();
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND resident_id = ?")->execute([$nid, $resident_id]);
            } elseif ($conn) {
                $q = mysqli_prepare($conn, "SELECT * FROM notifications WHERE notif_id = ? AND resident_id = ?");
                mysqli_stmt_bind_param($q, "ii", $nid, $resident_id);
                mysqli_stmt_execute($q);
                $res = mysqli_stmt_get_result($q);
                $notif_row = mysqli_fetch_assoc($res);
                $u = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND resident_id = ?");
                mysqli_stmt_bind_param($u, "ii", $nid, $resident_id);
                mysqli_stmt_execute($u);
            }
            if ($notif_row && !empty($notif_row["request_id"])) {
                if (stripos($notif_row["message"], "dokumento") !== false || stripos($notif_row["message"], "document") !== false) {
                    $target = "view_document.php?id=" . (int)$notif_row["request_id"];
                } else {
                    $target = "my_requests.php";
                }
            }
        } catch (Exception $e) {}
    }
    header("Location: " . $target);
    exit();
}

// Markahan bilang nabasa ang isang notification (gamit ang AJAX o POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_read_id"])) {
    $nid = (int)$_POST["mark_read_id"];
    if ($nid > 0) {
        try {
            if ($pdo) {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND resident_id = ?")->execute([$nid, $resident_id]);
            } elseif ($conn) {
                $u = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND resident_id = ?");
                mysqli_stmt_bind_param($u, "ii", $nid, $resident_id);
                mysqli_stmt_execute($u);
            }
        } catch (Exception $e) {}
    }
    if (!empty($_POST["ajax"])) {
        header("Content-Type: application/json");
        echo json_encode(["ok" => true]);
        exit();
    }
    header("Location: notifications.php");
    exit();
}

// Markahan lahat ng notifications bilang nabasa (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_all_read"])) {
    try {
        if ($pdo) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE resident_id = ?")->execute([$resident_id]);
        } elseif ($conn) {
            $u = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE resident_id = ?");
            mysqli_stmt_bind_param($u, "i", $resident_id);
            mysqli_stmt_execute($u);
        }
    } catch (Exception $e) {}
    header("Location: notifications.php");
    exit();
}

// Kunin lahat ng notifications
$notifs = [];
try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE resident_id = ? ORDER BY created_at DESC");
        $stmt->execute([$resident_id]);
        $notifs = $stmt->fetchAll();
    } elseif ($conn) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM notifications WHERE resident_id = ? ORDER BY created_at DESC");
        mysqli_stmt_bind_param($stmt, "i", $resident_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $notifs[] = $row;
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BarangayGo</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .notif-wrap {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .notif-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 12px;
            transition: border-color 0.18s;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .notif-item.unread {
            border-color: #bfdbfe;
            border-left: 4px solid #2563eb;
            background: #eff6ff;
            cursor: pointer;
        }
        .notif-item.unread .notif-msg {
            font-weight: 700;
        }
        .notif-unread-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #2563eb;
            flex-shrink: 0;
            align-self: center;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
        }
        .notif-icon {
            font-size: 1rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .notif-body {
            flex: 1;
        }
        .notif-msg {
            font-size: 0.92rem;
            color: var(--text);
            margin: 0 0 6px;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .notif-meta {
            font-size: 0.75rem;
            color: var(--text-soft);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .notif-link {
            font-size: 0.8rem;
            color: var(--focus);
            text-decoration: none;
            font-weight: 600;
        }
        .notif-link:hover { text-decoration: underline; }
        .notif-empty {
            text-align: center;
            padding: 56px 20px;
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            color: var(--text-soft);
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .notif-delete-btn {
            background: none;
            border: none;
            color: var(--text-soft);
            cursor: pointer;
            font-size: 1rem;
            padding: 4px 8px;
            border-radius: 6px;
            line-height: 1;
            transition: all 0.15s ease;
            opacity: 0.45;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 8px;
        }
        .notif-delete-btn:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.08);
            opacity: 1;
        }
        .clear-all-btn {
            background: var(--box);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-soft);
            padding: 6px 14px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Verdana', Geneva, Tahoma, sans-serif;
        }
        .clear-all-btn:hover {
            color: #ef4444;
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }
    </style>
</head>
<body>

<div class="frame full">
    <?php $active_nav = 'notifications'; include 'resident_nav.php'; ?>

    <main class="page-main top" style="justify-content: flex-start;">
        <a href="services.php" class="back-link">&larr; Back</a>
        <div class="notif-wrap">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                <div>
                    <h1 style="margin: 0; font-size: 1.75rem; font-family: 'Verdana', Geneva, Tahoma, sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">Notifications</h1>
                    <p class="page-sub" style="margin: 5px 0 0;">Updates from the barangay regarding your requests.</p>
                </div>
                <?php if (!empty($notifs)):
                    $has_unread = false;
                    foreach ($notifs as $check_n) {
                        if (empty($check_n["is_read"])) { $has_unread = true; break; }
                    }
                ?>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php if ($has_unread): ?>
                            <form action="notifications.php" method="POST" style="margin: 0;">
                                <input type="hidden" name="mark_all_read" value="1">
                                <button type="submit" class="clear-all-btn" style="color: #2563eb; border-color: #bfdbfe;">
                                    ✓ Mark All as Read
                                </button>
                            </form>
                        <?php endif; ?>
                        <form action="notifications.php" method="POST" style="margin: 0;"
                              onsubmit="return confirm('Are you sure you want to clear ALL your notifications?');">
                            <input type="hidden" name="clear_all_notifs" value="1">
                            <button type="submit" class="clear-all-btn">
                                🗑️ Clear All
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($notifs)): ?>
                <div class="notif-empty" id="notifEmpty">
                    <div style="font-size: 3rem; margin-bottom: 14px;">🔕</div>
                    <h2 style="margin: 0 0 10px; font-family: 'Verdana', Geneva, Tahoma, sans-serif;">No notifications yet</h2>
                    <p>You will be notified whenever there is an update on your requests.</p>
                    <a href="services.php" class="btn" style="margin-top: 16px; display: inline-block;">Go to Services</a>
                </div>
                <div id="notifList"></div>
            <?php else: ?>
                <div id="notifList">
                <?php foreach ($notifs as $n):
                    $is_unread = !$n["is_read"];
                    $icon = "📋";
                    if (strpos($n["message"], "Approved") !== false)  $icon = "✅";
                    if (strpos($n["message"], "Ready") !== false)      $icon = "📄";
                    if (strpos($n["message"], "Released") !== false)   $icon = "🤝";
                    if (strpos($n["message"], "Rejected") !== false)   $icon = "❌";
                    $clean_msg = preg_replace('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\s]+/u', '', $n["message"]);

                    // Dynamic translation for existing Tagalog messages
                    $clean_msg = preg_replace('/Handa na ang iyong dokumento para sa (.+?)[!.]* Maaari mo na itong i-download at i-print\.?/i', 'Your document for $1 is ready! You can now download and print it.', $clean_msg);
                    $clean_msg = preg_replace('/Ang iyong request para sa (.+?) ay naging [\'"]?([^\'"]+)[\'"]?\.?/i', 'Your request for $1 has been marked as \'$2\'.', $clean_msg);
                    $clean_msg = preg_replace('/Nai-upload na ang iyong dokumento para sa (.+?)\.?/i', 'Your document for $1 has been uploaded.', $clean_msg);
                    $clean_msg = preg_replace('/Ang iyong request #?(\d+) ay naaprubahan na\.?/i', 'Your request #$1 has been approved.', $clean_msg);
                    $clean_msg = preg_replace('/Ang iyong request #?(\d+) ay nai-release na\.?/i', 'Your request #$1 has been released.', $clean_msg);
                    $clean_msg = preg_replace('/Ang iyong request #?(\d+) ay tinanggihan\.?/i', 'Your request #$1 was rejected.', $clean_msg);
                    $clean_msg = str_ireplace('Dahilan/Paalala:', 'Remarks:', $clean_msg);
                    $clean_msg = str_ireplace('Paalala:', 'Reminder:', $clean_msg);
                    $clean_msg = str_ireplace('Dahilan:', 'Reason:', $clean_msg);
                ?>
                <div class="notif-item <?= $is_unread ? 'unread' : ''; ?>" data-notif-id="<?= (int)$n["notif_id"]; ?>">
                    <div class="notif-icon"><?= $icon; ?></div>
                    <div class="notif-body">
                        <p class="notif-msg"><?= htmlspecialchars($clean_msg, ENT_QUOTES); ?></p>
                        <div class="notif-meta">
                            <span>🕐 <?= date("F j, Y · g:i A", strtotime($n["created_at"])); ?></span>
                            <?php if (!empty($n["request_id"])): ?>
                                <?php if (stripos($n["message"], "dokumento") !== false || stripos($n["message"], "document") !== false): ?>
                                    <a href="notifications.php?open=<?= (int)$n["notif_id"]; ?>" class="notif-link">View Document</a>
                                <?php else: ?>
                                    <a href="notifications.php?open=<?= (int)$n["notif_id"]; ?>" class="notif-link">View Request #<?= (int)$n["request_id"]; ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($is_unread): ?>
                        <span class="notif-unread-dot" title="Unread"></span>
                    <?php endif; ?>
                    <form action="notifications.php" method="POST" style="margin: 0;"
                          onsubmit="return confirm('Delete this notification?');">
                        <input type="hidden" name="delete_notif_id" value="<?= (int)$n["notif_id"]; ?>">
                        <button type="submit" class="notif-delete-btn" title="Delete notification">
                            ✕
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// Kapag pinindot ang unread notification card, alisin ang blue dot at i-save sa database na nabasa na
function bindUnreadClick(item) {
    if (!item || item.dataset.boundUnread) return;
    item.dataset.boundUnread = '1';
    item.addEventListener('click', function (e) {
        if (e.target.closest('button') || e.target.closest('a')) return;
        var nid = item.getAttribute('data-notif-id');
        if (!nid || !item.classList.contains('unread')) return;

        item.classList.remove('unread');
        var dot = item.querySelector('.notif-unread-dot');
        if (dot) dot.remove();

        var badge = document.getElementById('navNotifBadge') || document.querySelector('.nav-links a[href="notifications.php"] span');
        if (badge) {
            var count = parseInt(badge.textContent, 10) - 1;
            if (window.updateNavNotifBadge) window.updateNavNotifBadge(Math.max(0, count));
            else if (count <= 0) badge.remove();
            else badge.textContent = count;
        }

        var fd = new FormData();
        fd.append('mark_read_id', nid);
        fd.append('ajax', '1');
        fetch('notifications.php', { method: 'POST', body: fd });
    });
}

document.querySelectorAll('.notif-item.unread').forEach(bindUnreadClick);

function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

// Live poll para lumabas agad ang mga bagong notifications kahit hindi mag-refresh
setInterval(function () {
    if (document.hidden) return;
    fetch('notifications_poll.php?with_list=1', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
            if (!data || !Array.isArray(data.items)) return;
            if (window.updateNavNotifBadge && typeof data.unread === 'number') {
                window.updateNavNotifBadge(data.unread);
            }
            var listEl = document.getElementById('notifList');
            if (!listEl) return;

            var existingIds = {};
            listEl.querySelectorAll('.notif-item[data-notif-id]').forEach(function (el) {
                existingIds[el.getAttribute('data-notif-id')] = true;
            });

            var newItems = data.items.filter(function (it) {
                return !existingIds[String(it.notif_id)];
            });

            if (newItems.length > 0) {
                var emptyEl = document.getElementById('notifEmpty');
                if (emptyEl) emptyEl.remove();

                newItems.reverse().forEach(function (it) {
                    var linkHtml = '';
                    if (it.request_id > 0) {
                        var linkText = it.is_doc ? 'View Document' : ('View Request #' + it.request_id);
                        linkHtml = '<a href="notifications.php?open=' + it.notif_id + '" class="notif-link">' + linkText + '</a>';
                    }
                    var card = document.createElement('div');
                    card.className = 'notif-item' + (it.is_unread ? ' unread' : '');
                    card.setAttribute('data-notif-id', it.notif_id);
                    card.innerHTML =
                        '<div class="notif-icon">' + escapeHtml(it.icon) + '</div>' +
                        '<div class="notif-body">' +
                            '<p class="notif-msg">' + escapeHtml(it.message) + '</p>' +
                            '<div class="notif-meta"><span>🕐 ' + escapeHtml(it.time) + '</span>' + linkHtml + '</div>' +
                        '</div>' +
                        (it.is_unread ? '<span class="notif-unread-dot" title="Unread"></span>' : '') +
                        '<form action="notifications.php" method="POST" style="margin: 0;" onsubmit="return confirm(\'Delete this notification?\');">' +
                            '<input type="hidden" name="delete_notif_id" value="' + it.notif_id + '">' +
                            '<button type="submit" class="notif-delete-btn" title="Delete notification">✕</button>' +
                        '</form>';
                    listEl.insertBefore(card, listEl.firstChild);
                    if (it.is_unread) bindUnreadClick(card);
                });
            }
        })
        .catch(function () {});
}, 4000);
</script>

</body>
</html>
