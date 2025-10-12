(function () {
    const app = document.getElementById('telecrm-chat-app');
    if (!app) {
        return;
    }

    const state = {
        currentConversationId: null,
        currentConversationName: '',
        currentConversationIsGroup: false,
        currentPartnerId: null,
        lastMessageId: 0,
        pollTimer: null,
        sidebarTimer: null,
        heartbeatTimer: null,
        typingTimer: null,
        typingActive: false,
        markReadTimer: null,
        sidebarData: { users: [], groups: [] },
        readMap: new Map(),
        messageContainer: document.getElementById('chatMessageList'),
        typingIndicator: document.getElementById('chatTypingIndicator'),
        searchInput: document.getElementById('chatSearchInput'),
        messageForm: document.getElementById('chatMessageForm'),
        messageInput: document.getElementById('chatMessageInput'),
        sendButton: document.getElementById('chatSendButton'),
        conversationField: document.getElementById('chatConversationId'),
        userList: document.getElementById('chatUserList'),
        groupList: document.getElementById('chatGroupList'),
        headerTitle: document.getElementById('chatHeaderTitle'),
        headerSubtitle: document.getElementById('chatHeaderSubtitle'),
        headerAvatar: document.getElementById('chatHeaderAvatar'),
        headerButtons: Array.from(document.querySelectorAll('.chat-header-actions .chat-action-btn')),
        typingNames: [],
    };

    const endpoints = {
        sidebar: app.dataset.sidebarUrl,
        conversation: app.dataset.conversationUrl,
        create: app.dataset.createUrl,
        send: app.dataset.sendUrl,
        typing: app.dataset.typingUrl,
        markRead: app.dataset['markReadUrl'],
        poll: app.dataset.pollUrl,
        heartbeat: app.dataset.heartbeatUrl,
    };

    const currentUserId = parseInt(app.dataset.currentUserId, 10);
    const currentUserName = app.dataset.currentUserName;

    function formatTime(dateString) {
        const date = new Date(dateString.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return dateString;
        }
        return new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    }

    function formatReadText(readers, isMine) {
        if (!Array.isArray(readers) || readers.length === 0) {
            return '';
        }

        if (!isMine) {
            const selfRead = readers.find((reader) => reader.user_id === currentUserId);
            return selfRead ? 'Read' : '';
        }

        const otherReaders = readers.filter((reader) => reader.user_id !== currentUserId);
        if (otherReaders.length === 0) {
            return 'Delivered';
        }
        const names = otherReaders.map((reader) => reader.name).join(', ');
        return `Seen by ${names}`;
    }

    function createElement(tag, options = {}) {
        const element = document.createElement(tag);
        if (options.className) {
            element.className = options.className;
        }
        if (options.text !== undefined) {
            element.textContent = options.text;
        }
        if (options.html !== undefined) {
            element.innerHTML = options.html;
        }
        if (options.attrs) {
            Object.entries(options.attrs).forEach(([key, value]) => {
                if (value !== null && value !== undefined) {
                    element.setAttribute(key, value);
                }
            });
        }
        return element;
    }

    function httpGet(url, params = {}) {
        const search = new URLSearchParams(params);
        const fetchUrl = search.toString() ? `${url}?${search.toString()}` : url;
        return fetch(fetchUrl, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            },
        }).then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })));
    }

    function httpPost(url, data) {
        const body = new URLSearchParams();
        Object.entries(data).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => body.append(`${key}[]`, item));
            } else {
                body.append(key, value);
            }
        });
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString(),
        }).then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })));
    }

    function resetMessageComposer() {
        state.messageInput.value = '';
        state.messageInput.style.height = 'auto';
        state.sendButton.disabled = true;
        state.typingActive = false;
    }

    function scrollMessagesToBottom() {
        if (!state.messageContainer) {
            return;
        }
        state.messageContainer.scrollTop = state.messageContainer.scrollHeight;
    }

    function clearMessages() {
        if (!state.messageContainer) {
            return;
        }
        state.messageContainer.innerHTML = '';
        state.readMap.clear();
    }

    function renderEmptyState() {
        const empty = createElement('div', { className: 'chat-empty-state' });
        const illustration = createElement('img', {
            attrs: {
                src: app.getAttribute('data-empty-illustration') || '',
                alt: 'Start chatting',
            },
        });
        illustration.addEventListener('error', () => {
            illustration.remove();
        });
        const title = createElement('h4', { text: 'Start a conversation' });
        const subtitle = createElement('p', { text: 'Select any teammate or create a group to begin messaging instantly.' });

        empty.appendChild(illustration);
        empty.appendChild(title);
        empty.appendChild(subtitle);
        state.messageContainer.appendChild(empty);
    }

    function updateTypingIndicator() {
        if (!state.typingIndicator) {
            return;
        }
        if (!Array.isArray(state.typingNames) || state.typingNames.length === 0) {
            state.typingIndicator.hidden = true;
            return;
        }

        const label = state.typingIndicator.querySelector('.chat-typing-text');
        if (label) {
            if (state.typingNames.length === 1) {
                label.textContent = `${state.typingNames[0]} is typing…`;
            } else {
                label.textContent = `${state.typingNames.slice(0, 2).join(', ')} are typing…`;
            }
        }
        state.typingIndicator.hidden = false;
    }

    function renderMessage(message) {
        const messageWrapper = createElement('div', {
            className: `chat-message ${message.is_mine ? 'sent' : 'received'}`,
            attrs: {
                'data-message-id': message.id,
            },
        });

        const header = createElement('div', { className: 'chat-message-header' });
        const sender = createElement('div', { className: 'chat-message-sender', text: message.sender });
        const time = createElement('div', { className: 'chat-message-time', text: formatTime(message.created_at) });
        header.appendChild(sender);
        header.appendChild(time);

        const body = createElement('div', { className: 'chat-message-body', text: message.body });

        const footerText = formatReadText(message.read_by || [], message.is_mine);
        let footer = null;
        if (footerText) {
            footer = createElement('div', { className: 'chat-message-footer', text: footerText });
        }

        messageWrapper.appendChild(header);
        messageWrapper.appendChild(body);
        if (footer) {
            messageWrapper.appendChild(footer);
        }

        state.messageContainer.appendChild(messageWrapper);
        state.readMap.set(message.id, message.read_by || []);
    }

    function renderMessages(messages) {
        if (!state.messageContainer) {
            return;
        }
        state.messageContainer.innerHTML = '';
        if (!messages || messages.length === 0) {
            renderEmptyState();
            return;
        }
        messages.forEach(renderMessage);
        scrollMessagesToBottom();
    }

    function updateReadReceipts(reads) {
        if (!Array.isArray(reads)) {
            return;
        }

        reads.forEach((entry) => {
            const messageId = entry.message_id;
            const existing = state.readMap.get(messageId) || [];
            const already = existing.find((item) => item.user_id === entry.user_id);
            if (!already) {
                existing.push({
                    user_id: entry.user_id,
                    name: entry.name,
                    read_at: entry.read_at,
                });
                state.readMap.set(messageId, existing);
            }
        });

        state.readMap.forEach((value, key) => {
            const messageEl = state.messageContainer.querySelector(`[data-message-id="${key}"]`);
            if (!messageEl) {
                return;
            }
            const isMine = messageEl.classList.contains('sent');
            const text = formatReadText(value, isMine);
            let footer = messageEl.querySelector('.chat-message-footer');
            if (text) {
                if (!footer) {
                    footer = createElement('div', { className: 'chat-message-footer' });
                    messageEl.appendChild(footer);
                }
                footer.textContent = text;
            } else if (footer) {
                footer.remove();
            }
        });
    }

    function scheduleMarkRead(messageId) {
        if (!messageId) {
            return;
        }
        if (state.markReadTimer) {
            clearTimeout(state.markReadTimer);
        }
        state.markReadTimer = setTimeout(() => {
            httpPost(endpoints.markRead, {
                conversation_id: state.currentConversationId,
                last_message_id: messageId,
            }).catch(() => {
                // ignore network errors silently
            });
        }, 400);
    }

    function appendMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) {
            return;
        }
        const wasAtBottom = Math.abs((state.messageContainer.scrollHeight - state.messageContainer.clientHeight) - state.messageContainer.scrollTop) < 30;
        messages.forEach((message) => {
            renderMessage(message);
        });
        if (wasAtBottom) {
            scrollMessagesToBottom();
        }
    }

    function parseDatasetJSON(value) {
        if (!value) {
            return [];
        }
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('Failed to parse chat dataset payload.', error);
            return [];
        }
    }

    function hydrateSidebarFromDataset() {
        const initialUsers = parseDatasetJSON(app.dataset.initialUsers);
        const initialGroups = parseDatasetJSON(app.dataset.initialGroups);
        let hydrated = false;

        if (initialUsers.length > 0) {
            state.sidebarData.users = initialUsers;
            hydrated = true;
        }

        if (initialGroups.length > 0) {
            state.sidebarData.groups = initialGroups;
            hydrated = true;
        }

        if (hydrated) {
            renderSidebar();
        }
    }

    function renderUsers() {
        const list = state.userList;
        if (!list) {
            return;
        }
        list.innerHTML = '';
        const searchTerm = (state.searchInput.value || '').toLowerCase();
        let visibleCount = 0;
        state.sidebarData.users.forEach((user) => {
            const name = user.name || user.email || 'Unknown';
            if (searchTerm && !name.toLowerCase().includes(searchTerm)) {
                return;
            }
            visibleCount += 1;
            const item = createElement('div', {
                className: 'chat-list-item',
                attrs: {
                    'data-user-id': user.id,
                    'data-name': name.toLowerCase(),
                },
            });

            if (state.currentConversationId && user.conversation_id === state.currentConversationId) {
                item.classList.add('active');
            }

            const avatar = createElement('div', { className: 'chat-avatar', text: name.slice(0, 2).toUpperCase() });
            const body = createElement('div', { className: 'chat-list-body' });
            const title = createElement('div', { className: 'chat-list-name' });
            title.textContent = name;
            if (user.role) {
                const roleTag = createElement('small', { text: user.role });
                title.appendChild(roleTag);
            }
            const preview = createElement('div', { className: 'chat-list-preview', text: user.email });
            body.appendChild(title);
            body.appendChild(preview);

            const status = createElement('div', {
                className: `chat-status ${user.is_online ? 'online' : 'offline'}`,
            });
            status.innerHTML = `<span><span class="status-dot"></span>${user.is_online ? 'Online' : 'Offline'}</span>`;

            item.appendChild(avatar);
            item.appendChild(body);
            item.appendChild(status);

            if (user.unread_count && Number(user.unread_count) > 0) {
                const badge = createElement('div', { className: 'chat-badge', text: String(user.unread_count) });
                item.appendChild(badge);
            }

            item.addEventListener('click', () => {
                handleDirectSelection(user);
            });

            list.appendChild(item);
        });

        if (visibleCount === 0) {
            list.innerHTML = '<div class="chat-placeholder">No teammates match your search.</div>';
        }
    }

    function renderGroups() {
        const list = state.groupList;
        if (!list) {
            return;
        }
        list.innerHTML = '';
        const searchTerm = (state.searchInput.value || '').toLowerCase();
        let visibleCount = 0;

        state.sidebarData.groups.forEach((group) => {
            const name = group.name || 'Group Chat';
            const matchesSearch = !searchTerm || name.toLowerCase().includes(searchTerm) || group.participants.some((member) => (member.name || '').toLowerCase().includes(searchTerm));
            if (!matchesSearch) {
                return;
            }
            visibleCount += 1;
            const item = createElement('div', {
                className: 'chat-list-item',
                attrs: {
                    'data-conversation-id': group.id,
                    'data-name': name.toLowerCase(),
                },
            });

            if (state.currentConversationId === group.id) {
                item.classList.add('active');
            }

            const avatar = createElement('div', { className: 'chat-avatar', text: name.slice(0, 2).toUpperCase() });
            const body = createElement('div', { className: 'chat-list-body' });
            const title = createElement('div', { className: 'chat-list-name', text: name });
            const previewText = group.last_message ? `${group.last_message_from ? `${group.last_message_from}: ` : ''}${group.last_message}` : `${group.participants.length} members`;
            const preview = createElement('div', { className: 'chat-list-preview', text: previewText });
            body.appendChild(title);
            body.appendChild(preview);

            item.appendChild(avatar);
            item.appendChild(body);

            if (group.unread_count && Number(group.unread_count) > 0) {
                const badge = createElement('div', { className: 'chat-badge', text: String(group.unread_count) });
                item.appendChild(badge);
            }

            item.addEventListener('click', () => {
                handleGroupSelection(group);
            });

            list.appendChild(item);
        });

        if (visibleCount === 0) {
            list.innerHTML = '<div class="chat-placeholder">No groups found. Try another search.</div>';
        }
    }

    function renderSidebar() {
        renderUsers();
        renderGroups();
    }

    function handleDirectSelection(user) {
        state.currentPartnerId = user.id;
        if (user.conversation_id) {
            openConversation(user.conversation_id, {
                name: user.name,
                isGroup: false,
            });
        } else {
            httpPost(endpoints.create, {
                participants: [user.id],
            }).then((response) => {
                if (response && response.conversation) {
                    user.conversation_id = response.conversation.id;
                    loadSidebar();
                    openConversation(response.conversation.id, {
                        name: user.name,
                        isGroup: false,
                    });
                }
            });
            return;
        }

        user.conversation_id = user.conversation_id || state.currentConversationId;
    }

    function handleGroupSelection(group) {
        openConversation(group.id, {
            name: group.name,
            isGroup: true,
        });
    }

    function updateHeader(conversation) {
        state.headerTitle.textContent = conversation.name || 'Conversation';
        if (conversation.is_group) {
            const participantNames = (conversation.participants || [])
                .filter((participant) => participant.id !== currentUserId)
                .map((participant) => participant.name)
                .join(', ');
            state.headerSubtitle.textContent = participantNames || 'Group chat';
        } else {
            const participant = (conversation.participants || []).find((item) => item.id !== currentUserId);
            const sidebarUser = state.sidebarData.users.find((user) => user.id === (participant ? participant.id : null));
            if (sidebarUser) {
                state.headerSubtitle.textContent = sidebarUser.is_online ? 'Online' : 'Offline';
            } else {
                state.headerSubtitle.textContent = participant ? participant.email : '';
            }
        }
        if (conversation.name) {
            state.headerAvatar.textContent = conversation.name.slice(0, 2).toUpperCase();
        } else {
            const participant = (conversation.participants || []).find((item) => item.id !== currentUserId);
            const base = participant ? participant.name || participant.email || '' : currentUserName;
            state.headerAvatar.textContent = base.slice(0, 2).toUpperCase();
        }
    }

    function startPolling() {
        stopPolling();
        state.pollTimer = setInterval(() => {
            if (!state.currentConversationId) {
                return;
            }
            httpGet(endpoints.poll, {
                conversation_id: state.currentConversationId,
                after_id: state.lastMessageId,
            }).then((response) => {
                if (!response || response.error) {
                    return;
                }
                if (Array.isArray(response.messages) && response.messages.length > 0) {
                    appendMessages(response.messages);
                }
                if (Array.isArray(response.reads)) {
                    updateReadReceipts(response.reads);
                }
                state.typingNames = Array.isArray(response.typing) ? response.typing.map((item) => item.name) : [];
                updateTypingIndicator();
                if (response.last_message_id && response.last_message_id > state.lastMessageId) {
                    state.lastMessageId = response.last_message_id;
                    scheduleMarkRead(state.lastMessageId);
                }
            });
        }, 3500);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function openConversation(conversationId, meta = {}) {
        if (!conversationId) {
            return;
        }
        state.currentConversationId = conversationId;
        state.conversationField.value = conversationId;
        state.sendButton.disabled = false;
        state.messageInput.disabled = false;
        state.messageInput.focus();
        state.currentConversationName = meta.name || '';
        state.currentConversationIsGroup = Boolean(meta.isGroup);
        state.headerButtons.forEach((button) => {
            button.disabled = false;
        });

        stopPolling();
        state.messageContainer.innerHTML = '<div class="chat-placeholder">Loading conversation…</div>';

        httpGet(endpoints.conversation, {
            conversation_id: conversationId,
        }).then((response) => {
            if (!response || response.error) {
                state.headerButtons.forEach((button) => {
                    button.disabled = true;
                });
                return;
            }
            state.sidebarData.users.forEach((user) => {
                if (user.conversation_id === conversationId) {
                    user.unread_count = 0;
                }
            });
            state.sidebarData.groups.forEach((group) => {
                if (group.id === conversationId) {
                    group.unread_count = 0;
                }
            });
            renderSidebar();

            updateHeader(response.conversation);
            state.typingNames = Array.isArray(response.typing) ? response.typing.map((item) => item.name) : [];
            updateTypingIndicator();

            renderMessages(response.messages || []);
            state.lastMessageId = response.last_message_id || 0;
            if (state.lastMessageId) {
                scheduleMarkRead(state.lastMessageId);
            }
            startPolling();
        });
    }

    function loadSidebar() {
        httpGet(endpoints.sidebar).then((response) => {
            if (!response || response.error) {
                return;
            }
            state.sidebarData.users = response.users || [];
            state.sidebarData.groups = response.groups || [];
            renderSidebar();
        });
    }

    function startSidebarRefresh() {
        loadSidebar();
        if (state.sidebarTimer) {
            clearInterval(state.sidebarTimer);
        }
        state.sidebarTimer = setInterval(loadSidebar, 20000);
    }

    function startHeartbeat() {
        const beat = () => {
            httpPost(endpoints.heartbeat, {}).catch(() => {
                // ignore
            });
        };
        beat();
        if (state.heartbeatTimer) {
            clearInterval(state.heartbeatTimer);
        }
        state.heartbeatTimer = setInterval(beat, 30000);
    }

    function handleSend(event) {
        event.preventDefault();
        const message = state.messageInput.value.trim();
        if (!message || !state.currentConversationId) {
            return;
        }

        state.sendButton.disabled = true;
        httpPost(endpoints.send, {
            conversation_id: state.currentConversationId,
            message,
        }).then((response) => {
            state.sendButton.disabled = false;
            if (!response || response.error) {
                return;
            }
            resetMessageComposer();
            reportTyping(false);
            if (response.message) {
                appendMessages([response.message]);
                state.lastMessageId = Math.max(state.lastMessageId, response.message.id);
                scheduleMarkRead(state.lastMessageId);
            }
        });
    }

    function reportTyping(isTyping) {
        if (!state.currentConversationId) {
            return;
        }
        if (isTyping) {
            if (state.typingActive) {
                return;
            }
            state.typingActive = true;
            httpPost(endpoints.typing, {
                conversation_id: state.currentConversationId,
                is_typing: '1',
            }).catch(() => {});
        } else {
            if (!state.typingActive) {
                return;
            }
            state.typingActive = false;
            httpPost(endpoints.typing, {
                conversation_id: state.currentConversationId,
                is_typing: '0',
            }).catch(() => {});
        }
    }

    function setupMessageInput() {
        state.messageInput.addEventListener('input', () => {
            state.messageInput.style.height = 'auto';
            state.messageInput.style.height = `${Math.min(state.messageInput.scrollHeight, 180)}px`;
            const hasValue = state.messageInput.value.trim().length > 0;
            state.sendButton.disabled = !hasValue;

            reportTyping(true);
            if (state.typingTimer) {
                clearTimeout(state.typingTimer);
            }
            state.typingTimer = setTimeout(() => {
                reportTyping(false);
            }, 2000);
        });

        state.messageInput.addEventListener('blur', () => {
            reportTyping(false);
        });
    }

    function populateGroupModal() {
        const container = document.getElementById('groupUserOptions');
        if (!container) {
            return;
        }
        container.innerHTML = '';
        state.sidebarData.users.forEach((user) => {
            const option = createElement('div', { className: 'group-user-option' });
            const checkbox = createElement('input', {
                attrs: {
                    type: 'checkbox',
                    value: user.id,
                    id: `group-user-${user.id}`,
                },
            });
            const label = createElement('label', {
                attrs: {
                    for: `group-user-${user.id}`,
                },
                text: user.name,
            });
            option.appendChild(checkbox);
            option.appendChild(label);
            container.appendChild(option);
        });
        if (!container.children.length) {
            container.innerHTML = '<div class="text-muted small">No teammates available to add.</div>';
        }
    }

    function setupGroupModal() {
        const modalElement = document.getElementById('newGroupModal');
        if (!modalElement) {
            return;
        }
        modalElement.addEventListener('show.bs.modal', populateGroupModal);

        const form = document.getElementById('newGroupForm');
        if (!form) {
            return;
        }
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const name = (formData.get('name') || '').toString().trim();
            const participants = Array.from(form.querySelectorAll('input[type="checkbox"]:checked')).map((input) => parseInt(input.value, 10));

            if (!name) {
                alert('Please enter a group name.');
                return;
            }
            if (participants.length < 2) {
                alert('Pick at least two teammates to create a group chat.');
                return;
            }

            httpPost(endpoints.create, {
                name,
                participants,
            }).then((response) => {
                if (!response || response.error) {
                    return;
                }
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
                form.reset();
                loadSidebar();
                openConversation(response.conversation.id, {
                    name: response.conversation.name || name,
                    isGroup: true,
                });
            });
        });
    }

    function initSearch() {
        if (!state.searchInput) {
            return;
        }
        state.searchInput.addEventListener('input', () => {
            renderSidebar();
        });
    }

    function init() {
        setupMessageInput();
        setupGroupModal();
        initSearch();
        state.messageForm.addEventListener('submit', handleSend);
        hydrateSidebarFromDataset();
        startSidebarRefresh();
        startHeartbeat();
    }

    init();
})();
