<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

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
                        <div class="chat-placeholder">Loading users…</div>
                    </div>
                </div>

                <div class="chat-sidebar-section" id="chatGroupsSection">
                    <div class="chat-section-heading">Groups</div>
                    <div class="chat-list" id="chatGroupList">
                        <div class="chat-placeholder">No groups yet. Create one to get started!</div>
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
