(function () {
    const app = document.getElementById('telecrm-chat-app');
    if (!app) {
        return;
    }

    const elements = {
        userList: document.getElementById('chatUserList'),
        groupList: document.getElementById('chatGroupList'),
        messageList: document.getElementById('chatMessageList'),
        messageForm: document.getElementById('chatMessageForm'),
        messageInput: document.getElementById('chatMessageInput'),
        sendButton: document.getElementById('chatSendButton'),
        conversationField: document.getElementById('chatConversationId'),
        headerTitle: document.getElementById('chatHeaderTitle'),
        headerSubtitle: document.getElementById('chatHeaderSubtitle'),
        headerAvatar: document.getElementById('chatHeaderAvatar'),
        searchInput: document.getElementById('chatSearchInput'),
        emptyState: document.getElementById('chatEmptyState'),
        groupModal: document.getElementById('newGroupModal'),
        groupForm: document.getElementById('newGroupForm'),
        groupUserList: document.getElementById('groupUserOptions'),
    };

    const endpoints = {
        sidebar: app.dataset.sidebarUrl,
        conversation: app.dataset.conversationUrl,
        create: app.dataset.createUrl,
        send: app.dataset.sendUrl,
        markRead: app.dataset.markReadUrl,
    };

    const state = {
        currentUserId: parseInt(app.dataset.currentUserId, 10),
        currentUserName: app.dataset.currentUserName || '',
        activeEntity: null,
        currentConversationId: null,
        lastMessageId: 0,
        messageIds: new Set(),
        sidebarData: {
            users: parseJson(app.dataset.initialUsers) ?? [],
            groups: parseJson(app.dataset.initialGroups) ?? [],
        },
        sidebarTimer: null,
        conversationTimer: null,
    };

    function parseJson(value) {
        if (!value) {
            return [];
        }
        try {
            return JSON.parse(value);
        } catch (err) {
            console.warn('Failed to parse JSON payload', err);
            return [];
        }
    }

    function formatTime(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    }

    function createElement(tag, options = {}) {
        const element = document.createElement(tag);
        if (options.className) {
            element.className = options.className;
        }
        if (options.text !== undefined) {
            element.textContent = options.text;
        }
        if (options.attrs) {
            Object.entries(options.attrs).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    element.setAttribute(key, value);
                }
            });
        }
        return element;
    }

    function renderSidebar() {
        renderEntityList(elements.userList, state.sidebarData.users, 'user');
        renderEntityList(elements.groupList, state.sidebarData.groups, 'group');
        updateActiveEntityClasses();
        applySearchFilter();
    }

    function renderEntityList(container, items, type) {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            const empty = createElement('div', { className: 'chat-empty' });
            empty.textContent = type === 'user' ? 'No teammates available yet.' : 'No groups yet. Create one to get started!';
            container.appendChild(empty);
            return;
        }

        const fragment = document.createDocumentFragment();

        items.forEach((item) => {
            const entity = createEntityNode(item, type);
            fragment.appendChild(entity);
        });

        container.appendChild(fragment);

        const searchEmpty = createElement('div', {
            className: 'chat-empty',
            text: 'No matches found.',
            attrs: { 'data-role': 'search-empty', hidden: 'hidden' },
        });
        container.appendChild(searchEmpty);
    }

    function createEntityNode(item, type) {
        const entity = createElement('div', { className: 'chat-entity' });
        entity.dataset.entity = type;
        entity.dataset.search = `${(item.name || '').toLowerCase()} ${(item.email || '').toLowerCase()}`.trim();

        if (type === 'user') {
            entity.dataset.userId = String(item.id);
            if (item.is_self) {
                entity.classList.add('chat-entity--self');
                entity.setAttribute('aria-disabled', 'true');
            }
            if (item.conversation_id) {
                entity.dataset.conversationId = String(item.conversation_id);
            }
        } else {
            entity.dataset.conversationId = String(item.id);
        }

        const initials = (item.name || item.email || '?').trim().slice(0, 2).toUpperCase();
        const avatar = createElement('div', { className: 'chat-entity__avatar', text: initials });
        avatar.setAttribute('aria-hidden', 'true');

        const details = createElement('div', { className: 'chat-entity__details' });
        const nameRow = createElement('div', { className: 'chat-entity__name' });
        nameRow.appendChild(createElement('span', { text: item.name || 'Conversation' }));

        if (type === 'user' && item.role) {
            nameRow.appendChild(createElement('small', { text: item.role }));
        }

        const meta = createElement('div', { className: 'chat-entity__meta' });
        if (type === 'user') {
            meta.textContent = item.email || '';
        } else {
            if (item.last_message) {
                const previewSender = item.last_message_from ? `${item.last_message_from}: ` : '';
                meta.textContent = `${previewSender}${item.last_message}`;
            } else if (Array.isArray(item.participants)) {
                meta.textContent = `${item.participants.length} members`;
            } else {
                meta.textContent = '';
            }
        }

        details.appendChild(nameRow);
        details.appendChild(meta);

        entity.appendChild(avatar);
        entity.appendChild(details);

        if (type === 'user') {
            const status = createElement('div', { className: 'chat-entity__status' });
            const statusClass = item.is_self ? 'is-online' : (item.is_online ? 'is-online' : 'is-offline');
            status.classList.add(statusClass);
            status.appendChild(createElement('span', { className: 'status-dot' }));
            status.appendChild(createElement('span', { text: item.is_self ? 'You' : (item.is_online ? 'Online' : 'Offline') }));
            entity.appendChild(status);
        }

        if (item.unread_count) {
            const badge = createElement('span', {
                className: 'chat-entity__badge',
                text: String(item.unread_count),
            });
            entity.appendChild(badge);
        }

        return entity;
    }

    function updateActiveEntityClasses() {
        const entities = app.querySelectorAll('.chat-entity');
        entities.forEach((entity) => {
            const type = entity.dataset.entity;
            const conversationId = entity.dataset.conversationId ? parseInt(entity.dataset.conversationId, 10) : null;
            const userId = entity.dataset.userId ? parseInt(entity.dataset.userId, 10) : null;

            let isActive = false;

            if (state.activeEntity) {
                if (state.activeEntity.type === 'group' && type === 'group') {
                    isActive = Boolean(state.activeEntity.conversationId && conversationId === state.activeEntity.conversationId);
                } else if (state.activeEntity.type === 'user' && type === 'user') {
                    if (state.activeEntity.conversationId && conversationId === state.activeEntity.conversationId) {
                        isActive = true;
                    } else if (!state.activeEntity.conversationId && !conversationId && state.activeEntity.userId && state.activeEntity.userId === userId) {
                        isActive = true;
                    }
                }
            }

            entity.classList.toggle('active', isActive);
        });
    }

    function applySearchFilter() {
        if (!elements.searchInput) {
            return;
        }
        const term = elements.searchInput.value.trim().toLowerCase();
        [elements.userList, elements.groupList].forEach((container) => {
            if (!container) {
                return;
            }
            const items = Array.from(container.querySelectorAll('.chat-entity'));
            if (items.length === 0) {
                return;
            }
            let visibleCount = 0;
            items.forEach((item) => {
                const haystack = item.dataset.search || '';
                const match = term === '' || haystack.includes(term);
                item.classList.toggle('is-hidden', !match);
                if (match) {
                    visibleCount += 1;
                }
            });
            const searchEmpty = container.querySelector('[data-role="search-empty"]');
            if (searchEmpty) {
                searchEmpty.hidden = term === '' || visibleCount > 0;
            }
        });
    }

    function handleEntityClick(event) {
        const target = event.target.closest('.chat-entity');
        if (!target || target.classList.contains('chat-entity--self')) {
            return;
        }

        const type = target.dataset.entity;
        const conversationId = target.dataset.conversationId ? parseInt(target.dataset.conversationId, 10) : null;
        const userId = target.dataset.userId ? parseInt(target.dataset.userId, 10) : null;

        state.activeEntity = {
            type,
            userId: type === 'user' ? userId : null,
            conversationId: conversationId,
        };

        updateActiveEntityClasses();

        if (conversationId) {
            openConversation(conversationId);
        } else if (type === 'user' && userId) {
            startDirectConversation(userId, target);
        }
    }

    function startDirectConversation(userId, element) {
        showLoadingState('Creating conversation…');
        fetch(endpoints.create, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ participants: [userId] }),
        })
            .then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })))
            .then((data) => {
                if (!data || data.error || !data.conversation) {
                    throw new Error(data && data.error ? data.error : 'Unable to start the conversation.');
                }

                const conversationId = parseInt(data.conversation.id, 10);
                element.dataset.conversationId = String(conversationId);

                const sidebarUser = state.sidebarData.users.find((user) => parseInt(user.id, 10) === userId);
                if (sidebarUser) {
                    sidebarUser.conversation_id = conversationId;
                }

                state.activeEntity.conversationId = conversationId;
                openConversation(conversationId);
                refreshSidebar();
            })
            .catch((error) => {
                showErrorState(error.message || 'Unable to start the conversation.');
            });
    }

    function openConversation(conversationId) {
        if (!conversationId) {
            return;
        }

        state.currentConversationId = conversationId;
        state.lastMessageId = 0;
        state.messageIds.clear();
        showLoadingState('Loading messages…');

        const url = new URL(endpoints.conversation, window.location.origin);
        url.searchParams.set('conversation_id', String(conversationId));
        url.searchParams.set('limit', '200');

        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            },
        })
            .then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })))
            .then((data) => {
                if (!data || data.error) {
                    throw new Error(data && data.error ? data.error : 'Unable to load conversation.');
                }

                renderConversation(data);
                clearUnread(conversationId);
                if (data.last_message_id) {
                    markConversationRead(data.last_message_id);
                }
            })
            .catch((error) => {
                showErrorState(error.message || 'Unable to load conversation.');
            });
    }

    function renderConversation(payload) {
        if (!elements.messageList) {
            return;
        }

        elements.messageList.innerHTML = '';

        if (payload.conversation) {
            updateHeader(payload.conversation);
        }

        const messages = Array.isArray(payload.messages) ? payload.messages : [];

        if (messages.length === 0) {
            const empty = createElement('div', { className: 'chat-empty' });
            empty.textContent = 'Say hello to start the conversation!';
            elements.messageList.appendChild(empty);
        } else {
            messages.forEach((message) => appendMessage(message, { scroll: false }));
            scrollMessagesToBottom();
        }

        state.lastMessageId = payload.last_message_id ? parseInt(payload.last_message_id, 10) : 0;
        enableComposer(true);
    }

    function updateHeader(conversation) {
        if (!conversation) {
            return;
        }
        if (elements.headerTitle) {
            elements.headerTitle.textContent = conversation.name || 'Conversation';
        }
        if (elements.headerSubtitle) {
            if (conversation.is_group) {
                const participants = Array.isArray(conversation.participants) ? conversation.participants.length : 0;
                elements.headerSubtitle.textContent = `${participants} participant${participants === 1 ? '' : 's'}`;
            } else if (Array.isArray(conversation.participants)) {
                const partner = conversation.participants.find((user) => parseInt(user.id, 10) !== state.currentUserId);
                elements.headerSubtitle.textContent = partner ? partner.email : '';
            }
        }
        if (elements.headerAvatar) {
            const source = conversation.name || elements.headerTitle.textContent || '';
            elements.headerAvatar.textContent = (source || '?').trim().slice(0, 2).toUpperCase();
        }
    }

    function appendMessage(message, options = {}) {
        if (!elements.messageList || !message) {
            return;
        }
        const messageId = parseInt(message.id, 10);
        if (state.messageIds.has(messageId)) {
            return;
        }
        state.messageIds.add(messageId);
        state.lastMessageId = Math.max(state.lastMessageId, messageId);

        const wrapper = createElement('div', { className: 'chat-message' });
        const isMine = Boolean(message.is_mine || parseInt(message.sender_id, 10) === state.currentUserId);
        if (isMine) {
            wrapper.classList.add('chat-message--mine');
        }

        const meta = createElement('div', { className: 'chat-message__meta' });
        meta.appendChild(createElement('span', { text: message.sender || (isMine ? 'You' : 'Teammate') }));
        meta.appendChild(createElement('span', { text: formatTime(message.created_at) }));

        const body = createElement('div', { className: 'chat-message__body' });
        body.textContent = message.body || '';

        wrapper.appendChild(meta);
        wrapper.appendChild(body);
        elements.messageList.appendChild(wrapper);

        if (!options || options.scroll !== false) {
            scrollMessagesToBottom();
        }
    }

    function scrollMessagesToBottom() {
        if (!elements.messageList) {
            return;
        }
        elements.messageList.scrollTop = elements.messageList.scrollHeight;
    }

    function enableComposer(enabled) {
        if (elements.messageInput) {
            elements.messageInput.disabled = !enabled;
        }
        if (elements.sendButton) {
            const hasText = elements.messageInput && elements.messageInput.value.trim() !== '';
            elements.sendButton.disabled = !enabled || !hasText;
        }
        if (elements.conversationField) {
            elements.conversationField.value = enabled && state.currentConversationId ? String(state.currentConversationId) : '';
        }
        if (!enabled && elements.messageInput) {
            elements.messageInput.value = '';
        }
        if (enabled) {
            handleComposerInput();
        }
    }

    function showLoadingState(message) {
        if (!elements.messageList) {
            return;
        }
        elements.messageList.innerHTML = '';
        const loading = createElement('div', { className: 'chat-empty', text: message || 'Loading…' });
        elements.messageList.appendChild(loading);
        enableComposer(false);
    }

    function showErrorState(message) {
        if (!elements.messageList) {
            return;
        }
        elements.messageList.innerHTML = '';
        const error = createElement('div', { className: 'chat-empty' });
        error.textContent = message || 'Something went wrong. Please try again later.';
        elements.messageList.appendChild(error);
        enableComposer(false);
    }

    function handleComposerInput() {
        if (!elements.sendButton || !elements.messageInput) {
            return;
        }
        const hasText = elements.messageInput.value.trim() !== '';
        elements.sendButton.disabled = !hasText;
    }

    function handleMessageSubmit(event) {
        event.preventDefault();
        if (!elements.messageInput) {
            return;
        }

        const messageBody = elements.messageInput.value.trim();
        if (!messageBody || !state.currentConversationId) {
            return;
        }

        elements.sendButton.disabled = true;

        const payload = new URLSearchParams();
        payload.append('conversation_id', String(state.currentConversationId));
        payload.append('message', messageBody);

        fetch(endpoints.send, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: payload.toString(),
        })
            .then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })))
            .then((data) => {
                if (!data || data.error || !data.message) {
                    throw new Error(data && data.error ? data.error : 'Unable to send the message.');
                }

                elements.messageInput.value = '';
                handleComposerInput();
                appendMessage(data.message, { scroll: true });
                markConversationRead(data.message.id);
                refreshSidebar();
            })
            .catch((error) => {
                elements.sendButton.disabled = false;
                alert(error.message || 'Unable to send the message.');
            });
    }

    function markConversationRead(lastMessageId) {
        if (!state.currentConversationId || !lastMessageId) {
            return;
        }
        const payload = new URLSearchParams();
        payload.append('conversation_id', String(state.currentConversationId));
        payload.append('last_message_id', String(lastMessageId));

        fetch(endpoints.markRead, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: payload.toString(),
        }).catch(() => {
            // Ignore read failures.
        });
    }

    function clearUnread(conversationId) {
        if (!conversationId) {
            return;
        }

        state.sidebarData.users = state.sidebarData.users.map((user) => {
            if (parseInt(user.conversation_id, 10) === conversationId) {
                return Object.assign({}, user, { unread_count: 0 });
            }
            return user;
        });

        state.sidebarData.groups = state.sidebarData.groups.map((group) => {
            if (parseInt(group.id, 10) === conversationId) {
                return Object.assign({}, group, { unread_count: 0 });
            }
            return group;
        });

        const badge = app.querySelector(`.chat-entity[data-conversation-id="${conversationId}"] .chat-entity__badge`);
        if (badge && badge.parentNode) {
            badge.parentNode.removeChild(badge);
        }
    }

    function refreshConversation() {
        if (!state.currentConversationId) {
            return;
        }
        const url = new URL(endpoints.conversation, window.location.origin);
        url.searchParams.set('conversation_id', String(state.currentConversationId));
        url.searchParams.set('limit', '200');

        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })))
            .then((data) => {
                if (!data || data.error) {
                    return;
                }
                const messages = Array.isArray(data.messages) ? data.messages : [];
                const newMessages = messages.filter((message) => !state.messageIds.has(parseInt(message.id, 10)));
                if (newMessages.length > 0) {
                    newMessages.forEach((message) => appendMessage(message, { scroll: true }));
                    if (data.last_message_id) {
                        markConversationRead(data.last_message_id);
                    }
                }
            })
            .catch(() => {
                // Ignore polling errors.
            });
    }

    function refreshSidebar() {
        fetch(endpoints.sidebar, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })))
            .then((data) => {
                if (!data || data.error) {
                    return;
                }
                state.sidebarData.users = Array.isArray(data.users) ? data.users : [];
                state.sidebarData.groups = Array.isArray(data.groups) ? data.groups : [];
                renderSidebar();
            })
            .catch(() => {
                // Ignore sidebar refresh errors.
            });
    }

    function populateGroupModal() {
        if (!elements.groupUserList) {
            return;
        }
        const users = state.sidebarData.users.filter((user) => !user.is_self);
        if (users.length === 0) {
            elements.groupUserList.innerHTML = '<div class="text-muted small">No teammates available.</div>';
            return;
        }

        const fragment = document.createDocumentFragment();
        users.forEach((user) => {
            const formCheck = createElement('div', { className: 'form-check' });
            const input = createElement('input', {
                className: 'form-check-input',
                attrs: {
                    type: 'checkbox',
                    value: String(user.id),
                    id: `group-user-${user.id}`,
                    name: 'participants[]',
                },
            });
            const label = createElement('label', {
                className: 'form-check-label',
                attrs: { for: `group-user-${user.id}` },
            });
            label.innerHTML = `<strong>${user.name}</strong><br><span class="text-muted small">${user.email}</span>`;
            formCheck.appendChild(input);
            formCheck.appendChild(label);
            fragment.appendChild(formCheck);
        });
        elements.groupUserList.innerHTML = '';
        elements.groupUserList.appendChild(fragment);
    }

    function handleGroupSubmit(event) {
        event.preventDefault();
        if (!elements.groupForm) {
            return;
        }

        const formData = new FormData(elements.groupForm);
        const name = (formData.get('name') || '').toString().trim();
        const participants = Array.from(elements.groupUserList.querySelectorAll('input[name="participants[]"]:checked')).map((input) => parseInt(input.value, 10));

        if (!name) {
            alert('Please provide a group name.');
            return;
        }
        if (participants.length === 0) {
            alert('Please select at least one teammate.');
            return;
        }

        elements.groupForm.querySelector('button[type="submit"]').disabled = true;

        fetch(endpoints.create, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name, participants }),
        })
            .then((response) => response.json().catch(() => ({ error: 'Invalid server response.' })))
            .then((data) => {
                if (!data || data.error || !data.conversation) {
                    throw new Error(data && data.error ? data.error : 'Unable to create the group.');
                }

                const conversationId = parseInt(data.conversation.id, 10);
                const newGroup = {
                    id: conversationId,
                    name: data.conversation.name || name,
                    unread_count: 0,
                    last_message: null,
                    last_message_at: null,
                    last_message_from: null,
                    participants: Array.isArray(data.participants) ? data.participants : [],
                };

                state.sidebarData.groups.unshift(newGroup);
                state.activeEntity = {
                    type: 'group',
                    conversationId,
                    userId: null,
                };

                renderSidebar();

                const modalInstance = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(elements.groupModal) : null;
                if (modalInstance) {
                    modalInstance.hide();
                }
                elements.groupForm.reset();
                elements.groupForm.querySelector('button[type="submit"]').disabled = false;

                openConversation(conversationId);
            })
            .catch((error) => {
                elements.groupForm.querySelector('button[type="submit"]').disabled = false;
                alert(error.message || 'Unable to create the group.');
            });
    }

    function initialise() {
        renderSidebar();

        app.addEventListener('click', handleEntityClick);

        if (elements.searchInput) {
            elements.searchInput.addEventListener('input', applySearchFilter);
        }

        if (elements.messageInput) {
            elements.messageInput.addEventListener('input', handleComposerInput);
        }

        if (elements.messageForm) {
            elements.messageForm.addEventListener('submit', handleMessageSubmit);
        }

        if (elements.groupModal) {
            elements.groupModal.addEventListener('show.bs.modal', populateGroupModal);
        }

        if (elements.groupForm) {
            elements.groupForm.addEventListener('submit', handleGroupSubmit);
        }

        state.sidebarTimer = window.setInterval(refreshSidebar, 15000);
        state.conversationTimer = window.setInterval(refreshConversation, 5000);
    }

    initialise();
})();
