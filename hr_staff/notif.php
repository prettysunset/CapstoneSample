<?php
$embed = isset($_GET['embed']);
session_start();
require __DIR__ . '/../conn.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	if ($action === 'mark' && isset($_POST['id'])) {
		$nid = (int)$_POST['id'];
		$u = $conn->prepare("UPDATE notification_users SET is_read = 1 WHERE user_id = ? AND notification_id = ?");
		if ($u) { $u->bind_param('ii', $user_id, $nid); $u->execute(); $u->close(); }
		echo json_encode(['ok' => true]);
		exit;
	}
	if ($action === 'mark_all') {
		$u = $conn->prepare("UPDATE notification_users SET is_read = 1 WHERE user_id = ?");
		if ($u) { $u->bind_param('i', $user_id); $u->execute(); $u->close(); }
		echo json_encode(['ok' => true]);
		exit;
	}
}

$notifications = [];
if ($user_id > 0) {
	$q = $conn->prepare("SELECT n.id,n.message,n.created_at,nu.is_read FROM notifications n JOIN notification_users nu ON n.id = nu.notification_id WHERE nu.user_id = ? ORDER BY n.created_at DESC LIMIT 200");
	if ($q) {
		$q->bind_param('i', $user_id);
		$q->execute();
		$res = $q->get_result();
		while ($r = $res->fetch_assoc()) {
			$notifications[] = [
				'id' => (int)$r['id'],
				'title' => mb_substr($r['message'],0,80),
				'text' => $r['message'],
				'time' => $r['created_at'],
				'read' => (bool)$r['is_read']
			];
		}
		$q->close();
	}
}
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Notifications</title>
		<style>
			:root {
				--panel-bg: #ffffff;
				--panel-text: #111827;
				--muted: #6b7280;
				--border: #e5e7eb;
				--shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
				--accent: #2563eb;
				--chip-bg: #f3f4f6;
			}

			* {
				box-sizing: border-box;
			}

			body {
				margin: 0;
				font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
				background: #f8fafc;
				color: var(--panel-text);
				min-height: 100vh;
			}

			body.embed {
				background: transparent;
			}

			body.embed .page-shell,
			body.embed .bell-button,
			body.embed .overlay {
				display: none;
			}

			body.embed .panel {
				position: static;
				width: 100%;
				max-height: 100vh;
				border-radius: 0;
				box-shadow: none;
				opacity: 1;
				transform: none;
				pointer-events: auto;
			}

			.sr-only {
				position: absolute;
				width: 1px;
				height: 1px;
				padding: 0;
				margin: -1px;
				overflow: hidden;
				clip: rect(0, 0, 0, 0);
				white-space: nowrap;
				border: 0;
			}

			.page-shell {
				padding: 24px;
			}

			.bell-button {
				position: fixed;
				top: 20px;
				right: 24px;
				width: 44px;
				height: 44px;
				border-radius: 999px;
				border: 1px solid var(--border);
				background: #ffffff;
				box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
				display: grid;
				place-items: center;
				cursor: pointer;
			}

			.bell-button:focus-visible {
				outline: 3px solid rgba(37, 99, 235, 0.35);
				outline-offset: 2px;
			}

			.bell-icon {
				width: 20px;
				height: 20px;
				display: inline-block;
			}

			.overlay {
				position: fixed;
				inset: 0;
				background: rgba(15, 23, 42, 0.25);
				opacity: 0;
				pointer-events: none;
				transition: opacity 200ms ease;
			}

			.overlay.is-visible {
				opacity: 1;
				pointer-events: auto;
			}

			.panel {
				position: fixed;
				top: 18px;
				right: 18px;
				width: min(360px, calc(100vw - 32px));
				max-height: min(620px, calc(100vh - 36px));
				background: var(--panel-bg);
				border-radius: 16px;
				box-shadow: var(--shadow);
				opacity: 0;
				transform: translateY(-12px);
				transition: opacity 220ms ease, transform 220ms ease;
				pointer-events: none;
				display: flex;
				flex-direction: column;
			}

			.panel.is-visible {
				opacity: 1;
				transform: translateY(0);
				pointer-events: auto;
			}

			.panel-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 18px 18px 10px;
				border-bottom: 1px solid var(--border);
			}

			.panel-title {
				margin: 0;
				font-size: 18px;
				font-weight: 600;
			}

			.close-button {
				width: 32px;
				height: 32px;
				border-radius: 50%;
				border: 1px solid transparent;
				background: #f3f4f6;
				cursor: pointer;
			}

			.close-button:focus-visible {
				outline: 3px solid rgba(37, 99, 235, 0.35);
			}

			.panel-controls {
				padding: 10px 18px 14px;
				border-bottom: 1px solid var(--border);
				display: flex;
				flex-direction: column;
				gap: 10px;
			}

			.tabs {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
			}

			.tab-button {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 6px 12px;
				border-radius: 999px;
				border: 1px solid transparent;
				background: var(--chip-bg);
				color: var(--panel-text);
				font-size: 13px;
				cursor: pointer;
			}

			.tab-button[aria-selected="true"] {
				background: rgba(37, 99, 235, 0.12);
				border-color: rgba(37, 99, 235, 0.25);
				color: var(--accent);
				font-weight: 600;
			}

			.tab-badge {
				min-width: 18px;
				padding: 1px 6px;
				border-radius: 999px;
				font-size: 12px;
				background: #111827;
				color: #ffffff;
				text-align: center;
			}

			.mark-read {
				align-self: flex-start;
				border: none;
				background: none;
				color: var(--accent);
				font-size: 13px;
				cursor: pointer;
				padding: 0;
			}

			.notifications {
				overflow-y: auto;
				padding: 6px 8px 12px;
			}

			.notification {
				position: relative;
				display: flex;
				flex-direction: column;
				gap: 4px;
				padding: 12px 14px;
				margin: 6px 8px;
				border-radius: 12px;
				background: #f9fafb;
				border: 1px solid transparent;
				cursor: pointer;
				transition: background 150ms ease, border 150ms ease;
			}

			.notification:hover {
				background: #ffffff;
				border-color: var(--border);
			}

			.notification.unread {
				background: #eff6ff;
			}

			.notification-title {
				font-weight: 600;
			}

			.notification-text {
				font-size: 13px;
				color: var(--muted);
			}

			.notification-meta {
				display: flex;
				align-items: center;
				gap: 10px;
				font-size: 12px;
				color: var(--muted);
			}

			.notification-category {
				padding: 2px 8px;
				border-radius: 999px;
				background: #e2e8f0;
				color: #1f2937;
				font-size: 11px;
			}

			.unread-dot {
				position: absolute;
				top: 14px;
				right: 16px;
				width: 8px;
				height: 8px;
				border-radius: 50%;
				background: #2563eb;
			}

			.empty-state {
				padding: 20px;
				text-align: center;
				color: var(--muted);
				font-size: 14px;
			}

			@media (max-width: 600px) {
				.panel {
					right: 12px;
					left: 12px;
					width: auto;
				}

				.bell-button {
					right: 14px;
				}
			}
		</style>
	</head>
	<body class="<?php echo $embed ? 'embed' : ''; ?>">
		<div class="page-shell">
			<p>Click the bell icon to view notifications.</p>
		</div>

		<button class="bell-button" id="bellButton" aria-haspopup="dialog" aria-expanded="false" aria-controls="notificationPanel">
			<span class="bell-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
					<path d="M14.5 18.5a2.5 2.5 0 0 1-5 0"></path>
					<path d="M18 8a6 6 0 1 0-12 0v4.2c0 .8-.3 1.6-.9 2.2l-1.1 1.1h16l-1.1-1.1a3.1 3.1 0 0 1-.9-2.2V8"></path>
				</svg>
			</span>
			<span class="sr-only">Open notifications</span>
		</button>

		<div class="overlay" id="overlay" aria-hidden="true"></div>

		<section class="panel" id="notificationPanel" role="dialog" aria-modal="true" aria-label="Notifications">
			<div class="panel-header">
				<h2 class="panel-title">Notifications</h2>
				<button class="close-button" id="closePanel" aria-label="Close notifications">X</button>
			</div>

			<div class="panel-controls">
				<div class="tabs" role="tablist">
					<button class="tab-button" data-filter="all" role="tab" aria-selected="true">
						All <span class="tab-badge" data-count="all">0</span>
					</button>
				</div>
				<button class="mark-read" id="markAll">Mark all as read</button>
			</div>

			<div class="notifications" id="notificationList" role="list"></div>
		</section>

		<script>
			const bellButton = document.getElementById("bellButton");
			const overlay = document.getElementById("overlay");
			const panel = document.getElementById("notificationPanel");
			const closePanel = document.getElementById("closePanel");
			const list = document.getElementById("notificationList");
			const markAll = document.getElementById("markAll");
			const tabs = Array.from(document.querySelectorAll(".tab-button"));
			const isEmbed = document.body.classList.contains("embed");

			const notifications = <?php echo json_encode($notifications, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?> || [];

			async function markRead(id) {
				try {
					await fetch(window.location.href, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({ action: 'mark', id: String(id) })
					});
				} catch (e) {}
			}

			async function markAllServer() {
				try {
					await fetch(window.location.href, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({ action: 'mark_all' })
					});
				} catch (e) {}
			}

			const renderNotifications = () => {
				list.innerHTML = "";
				const filtered = notifications;

				if (!filtered.length) {
					const empty = document.createElement("div");
					empty.className = "empty-state";
					empty.textContent = "No notifications to show.";
					list.appendChild(empty);
					updateCounts();
					return;
				}

				filtered.forEach((item) => {
					const card = document.createElement("div");
					card.className = `notification${item.read ? "" : " unread"}`;
					card.setAttribute("role", "listitem");
					card.dataset.id = item.id;
					card.innerHTML = `
						<span class="notification-title">${item.title}</span>
						<span class="notification-text">${item.text}</span>
						<div class="notification-meta">
							<span>${item.time}</span>
							<span class="notification-category">${item.category}</span>
						</div>
						${item.read ? "" : "<span class=\"unread-dot\" aria-hidden=\"true\"></span>"}
					`;

					card.addEventListener("click", () => {
						if (!item.read) {
							item.read = true;
							markRead(item.id);
							renderNotifications();
						}
					});

					list.appendChild(card);
				});

				updateCounts();
			};

			const updateCounts = () => {
				const all = notifications.length;
				const unread = notifications.filter((item) => !item.read).length;
				document.querySelector("[data-count='all']").textContent = all;
				try {
					localStorage.setItem("notifUnread", String(unread));
				} catch (e) {
					// ignore storage errors
				}
				if (window.parent && typeof window.parent.postMessage === "function") {
					window.parent.postMessage({ type: "notif-count", unread }, "*");
				}
			};

			const openPanel = () => {
				if (!overlay || !panel || !bellButton) return;
				overlay.classList.add("is-visible");
				panel.classList.add("is-visible");
				bellButton.setAttribute("aria-expanded", "true");
				overlay.setAttribute("aria-hidden", "false");
				if (closePanel) closePanel.focus();
			};

			const closePanelUi = () => {
				if (!overlay || !panel || !bellButton) return;
				overlay.classList.remove("is-visible");
				panel.classList.remove("is-visible");
				bellButton.setAttribute("aria-expanded", "false");
				overlay.setAttribute("aria-hidden", "true");
			};

			const requestCloseFromParent = () => {
				if (isEmbed && window.parent && typeof window.parent.closeNotifOverlay === "function") {
					window.parent.closeNotifOverlay();
					return true;
				}
				return false;
			};

			const markAllRead = () => {
				notifications.forEach((item) => {
					item.read = true;
				});
				renderNotifications();
			};

			if (bellButton && overlay && panel) {
				bellButton.addEventListener("click", () => {
					if (panel.classList.contains("is-visible")) {
						closePanelUi();
					} else {
						openPanel();
					}
				});
			}

			if (closePanel) {
				closePanel.addEventListener("click", (event) => {
					event.preventDefault();
					markAllRead();
					markAllServer();
					if (!requestCloseFromParent()) closePanelUi();
				});
			}

			if (overlay) overlay.addEventListener("click", closePanelUi);

			document.addEventListener("keydown", (event) => {
				if (event.key === "Escape") {
					markAllRead();
					if (!requestCloseFromParent()) closePanelUi();
				}
			});

			tabs.forEach((tab) => {
				tab.addEventListener("click", () => {
					tabs.forEach((btn) => btn.setAttribute("aria-selected", "false"));
					tab.setAttribute("aria-selected", "true");
					renderNotifications();
				});
			});

			markAll.addEventListener("click", () => {
				notifications.forEach((item) => {
					item.read = true;
				});
				renderNotifications();
			});

			renderNotifications();
		</script>
	</body>
</html>