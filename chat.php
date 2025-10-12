<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/chat-helpers.php';

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserName = $_SESSION['username'] ?? '';
$currentUserEmail = $_SESSION['email'] ?? '';

$userStmt = $mysqli->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $currentUserId);
    $userStmt->execute();
    $result = $userStmt->get_result();
    $currentUser = $result ? $result->fetch_assoc() : null;
    $userStmt->close();
}

if (empty($currentUser)) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pdo = hh_db();

$initialUsers = [];
$initialGroups = [];

try {
    $userQuery = $pdo->prepare(<<<SQL
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.role,
            up.last_seen,
            CASE
                WHEN up.last_seen IS NOT NULL AND up.last_seen >= (NOW() - INTERVAL 90 SECOND) THEN 1
                ELSE 0
            END AS is_online,
            dc.id AS conversation_id,
            COALESCE(unread.unread_count, 0) AS unread_count
        FROM users u
        LEFT JOIN user_presence up ON up.user_id = u.id
        LEFT JOIN chat_conversations dc ON dc.direct_key = SHA2(CONCAT(LEAST(u.id, :me), ':', GREATEST(u.id, :me)), 256)
        LEFT JOIN (
            SELECT
                m.conversation_id,
                COUNT(*) AS unread_count
            FROM chat_messages m
            INNER JOIN chat_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = :me
            WHERE m.sender_id <> :me
              AND (cp.last_read_message_id IS NULL OR m.id > cp.last_read_message_id)
            GROUP BY m.conversation_id
        ) unread ON unread.conversation_id = dc.id
        WHERE u.id <> :me
        ORDER BY u.full_name ASC
    SQL);
    $userQuery->execute(['me' => $currentUserId]);

    while ($row = $userQuery->fetch()) {
        $initialUsers[] = [
            'id'              => (int) $row['id'],
            'name'            => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
            'email'           => $row['email'],
            'role'            => $row['role'],
            'is_online'       => (bool) $row['is_online'],
            'last_seen'       => $row['last_seen'],
            'conversation_id' => $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
            'unread_count'    => (int) ($row['unread_count'] ?? 0),
        ];
    }

    $groupQuery = $pdo->prepare(<<<SQL
        SELECT
            c.id,
            c.name,
            c.created_at,
            COALESCE(unread.unread_count, 0) AS unread_count,
            lm.id AS last_message_id,
            lm.body AS last_message_body,
            lm.created_at AS last_message_at,
            sender.full_name AS last_message_sender
        FROM chat_conversations c
        INNER JOIN chat_participants me_participant ON me_participant.conversation_id = c.id AND me_participant.user_id = :me
        LEFT JOIN (
            SELECT
                inner_m.conversation_id,
                inner_m.id,
                inner_m.body,
                inner_m.created_at,
                inner_m.sender_id
            FROM chat_messages inner_m
            WHERE inner_m.id IN (
                SELECT MAX(inner_m2.id)
                FROM chat_messages inner_m2
                WHERE inner_m2.conversation_id = inner_m.conversation_id
            )
        ) lm ON lm.conversation_id = c.id
        LEFT JOIN users sender ON sender.id = lm.sender_id
        LEFT JOIN (
            SELECT
                m.conversation_id,
                COUNT(*) AS unread_count
            FROM chat_messages m
            INNER JOIN chat_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = :me
            WHERE m.sender_id <> :me
              AND (cp.last_read_message_id IS NULL OR m.id > cp.last_read_message_id)
            GROUP BY m.conversation_id
        ) unread ON unread.conversation_id = c.id
        WHERE c.is_group = 1
        ORDER BY COALESCE(lm.created_at, c.created_at) DESC, c.id DESC
    SQL);

    $groupQuery->execute(['me' => $currentUserId]);
    $groups = $groupQuery->fetchAll();

    $groupIds = array_map(static fn ($group) => (int) $group['id'], $groups);
    $groupParticipants = [];

    if (count($groupIds) > 0) {
        $inPlaceholders = implode(',', array_fill(0, count($groupIds), '?'));
        $participantQuery = $pdo->prepare(
            'SELECT cp.conversation_id, u.id, u.full_name, u.email
             FROM chat_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.conversation_id IN (' . $inPlaceholders . ')
             ORDER BY u.full_name'
        );
        $participantQuery->execute($groupIds);

        while ($row = $participantQuery->fetch()) {
            $conversationId = (int) $row['conversation_id'];
            $groupParticipants[$conversationId][] = [
                'id'    => (int) $row['id'],
                'name'  => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
                'email' => $row['email'],
            ];
        }
    }

    foreach ($groups as $group) {
        $conversationId = (int) $group['id'];
        $initialGroups[] = [
            'id'                => $conversationId,
            'name'              => $group['name'] !== null && $group['name'] !== '' ? $group['name'] : 'Group Chat',
            'unread_count'      => (int) ($group['unread_count'] ?? 0),
            'last_message'      => $group['last_message_body'],
            'last_message_at'   => $group['last_message_at'],
            'last_message_from' => $group['last_message_sender'],
            'participants'      => $groupParticipants[$conversationId] ?? [],
        ];
    }
} catch (Throwable $sidebarError) {
    // If fetching sidebar data fails we fall back to an empty state.
    $initialUsers = [];
    $initialGroups = [];
}

$initialUsersJson = htmlspecialchars(json_encode($initialUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
$initialGroupsJson = htmlspecialchars(json_encode($initialGroups, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

$pageTitle = 'Team Chat | TeleCRM';
$additionalStyles = array_merge($additionalStyles ?? [], [hh_asset('assets/css/chat.css')]);
$pageScriptFiles = array_merge($pageScriptFiles ?? [], [hh_asset('assets/js/chat.js')]);

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content chat-page">
        <div
            id="telecrm-chat-app"
            class="chat-app"
            data-current-user-id="<?= htmlspecialchars((string) $currentUser['id'], ENT_QUOTES, 'UTF-8') ?>"
            data-current-user-name="<?= htmlspecialchars($currentUser['full_name'] !== '' ? $currentUser['full_name'] : $currentUser['email'], ENT_QUOTES, 'UTF-8') ?>"
            data-empty-illustration="<?= htmlspecialchars(hh_asset('assets/images/chat-empty-state.svg'), ENT_QUOTES, 'UTF-8') ?>"
            data-sidebar-url="<?= htmlspecialchars(hh_url('api/chat/sidebar.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-conversation-url="<?= htmlspecialchars(hh_url('api/chat/conversation.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-create-url="<?= htmlspecialchars(hh_url('api/chat/create_conversation.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-send-url="<?= htmlspecialchars(hh_url('api/chat/send_message.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-typing-url="<?= htmlspecialchars(hh_url('api/chat/typing.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-mark-read-url="<?= htmlspecialchars(hh_url('api/chat/mark_read.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-poll-url="<?= htmlspecialchars(hh_url('api/chat/poll.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-heartbeat-url="<?= htmlspecialchars(hh_url('api/chat/heartbeat.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-initial-users="<?= $initialUsersJson ?>"
            data-initial-groups="<?= $initialGroupsJson ?>"
        >
            <aside class="chat-sidebar">
                <div class="chat-sidebar-header">
                    <div>
                        <h2 class="chat-sidebar-title">Team Chat</h2>
                        <p class="chat-sidebar-subtitle">Connect with your team in real-time</p>
                    </div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newGroupModal">
                        <i class="bx bx-user-plus"></i>
                        <span>Create Group</span>
                    </button>
                </div>

                <div class="chat-sidebar-search">
                    <i class="bx bx-search"></i>
                    <input type="search" id="chatSearchInput" placeholder="Search people or groups" class="form-control" autocomplete="off">
                </div>

                <div class="chat-sidebar-section" id="chatUsersSection">
                    <div class="chat-section-heading">Direct Messages</div>
                    <div class="chat-list" id="chatUserList">
                        <?php if (!empty($initialUsers)): ?>
                            <?php foreach ($initialUsers as $user): ?>
                                <?php
                                $displayName = $user['name'];
                                $initials = strtoupper(substr($displayName, 0, 2));
                                $statusClass = $user['is_online'] ? 'online' : 'offline';
                                $statusLabel = $user['is_online'] ? 'Online' : 'Offline';
                                ?>
                                <div class="chat-list-item" data-user-id="<?= htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars(strtolower($displayName), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="chat-avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="chat-list-body">
                                        <div class="chat-list-name">
                                            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($user['role'])): ?>
                                                <small><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="chat-list-preview"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                    <div class="chat-status <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <span><span class="status-dot"></span><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php if (!empty($user['unread_count'])): ?>
                                        <div class="chat-badge"><?= htmlspecialchars((string) $user['unread_count'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="chat-placeholder">No teammates available right now.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chat-sidebar-section" id="chatGroupsSection">
                    <div class="chat-section-heading">Groups</div>
                    <div class="chat-list" id="chatGroupList">
                        <?php if (!empty($initialGroups)): ?>
                            <?php foreach ($initialGroups as $group): ?>
                                <?php
                                $groupName = $group['name'];
                                $groupInitials = strtoupper(substr($groupName, 0, 2));
                                $previewText = '';
                                if (!empty($group['last_message'])) {
                                    $from = $group['last_message_from'] ? $group['last_message_from'] . ': ' : '';
                                    $previewText = $from . $group['last_message'];
                                } elseif (!empty($group['participants'])) {
                                    $previewText = count($group['participants']) . ' members';
                                }
                                ?>
                                <div class="chat-list-item" data-conversation-id="<?= htmlspecialchars((string) $group['id'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars(strtolower($groupName), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="chat-avatar"><?= htmlspecialchars($groupInitials, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="chat-list-body">
                                        <div class="chat-list-name"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="chat-list-preview"><?= htmlspecialchars($previewText !== '' ? $previewText : (count($group['participants']) . ' members'), ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                    <?php if (!empty($group['unread_count'])): ?>
                                        <div class="chat-badge"><?= htmlspecialchars((string) $group['unread_count'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="chat-placeholder">No groups yet. Create one to get started!</div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

            <section class="chat-main">
                <header class="chat-main-header">
                    <div class="chat-header-user">
                        <div class="chat-avatar" id="chatHeaderAvatar"><?= strtoupper(substr($currentUserName !== '' ? $currentUserName : $currentUserEmail, 0, 1)) ?></div>
                        <div class="chat-header-text">
                            <h3 id="chatHeaderTitle">Select a conversation</h3>
                            <p id="chatHeaderSubtitle">Choose a teammate or group to start chatting</p>
                        </div>
                    </div>
                    <div class="chat-header-actions">
                        <button class="chat-action-btn" type="button" title="Start audio call" disabled>
                            <i class="bx bx-phone"></i>
                        </button>
                        <button class="chat-action-btn" type="button" title="Start video call" disabled>
                            <i class="bx bx-video"></i>
                        </button>
                        <button class="chat-action-btn" type="button" title="More options" disabled>
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                    </div>
                </header>

                <div class="chat-main-body" id="chatMessageList">
                    <div class="chat-empty-state">
                        <img src="<?= htmlspecialchars(hh_asset('assets/images/chat-empty-state.svg'), ENT_QUOTES, 'UTF-8') ?>" alt="Start chatting illustration" onerror="this.style.display='none'">
                        <h4>Start a conversation</h4>
                        <p>Select any teammate or create a group to begin messaging instantly.</p>
                    </div>
                </div>

                <div class="chat-typing" id="chatTypingIndicator" hidden>
                    <span class="chat-typing-dot"></span>
                    <span class="chat-typing-dot"></span>
                    <span class="chat-typing-dot"></span>
                    <span class="chat-typing-text">Someone is typing…</span>
                </div>

                <form class="chat-input" id="chatMessageForm" autocomplete="off">
                    <input type="hidden" name="conversation_id" id="chatConversationId" value="">
                    <textarea id="chatMessageInput" name="message" rows="1" placeholder="Type a message" disabled></textarea>
                    <div class="chat-input-actions">
                        <button type="submit" class="btn btn-primary" id="chatSendButton" disabled>
                            <span>Send</span>
                            <i class="bx bx-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</div>

<div class="modal fade" id="newGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create a group chat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newGroupForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newGroupName" class="form-label">Group name</label>
                        <input type="text" class="form-control" id="newGroupName" name="name" placeholder="e.g. Sales Squad" required>
                    </div>
                    <div class="group-select">
                        <div class="group-select-header">Pick teammates</div>
                        <div class="group-user-list" id="groupUserOptions">
                            <div class="text-muted small">Loading users…</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
