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
        sidebar: {
            users: [],
            groups: [],
        },
        searchTerm: '',
        activeEntity: null,
        currentConversationId: null,
        conversationMeta: null,
        lastMessageId: 0,
        messageIds: new Set(),
        sidebarTimer: null,
        conversationTimer: null,
    };

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

    function parseNumber(value) {
        const parsed = Number.parseInt(value, 10);
        return Number.isNaN(parsed) ? null : parsed;
    }

    function requestJson(url, options = {}, defaultError = 'Something went wrong.') {
        const fetchOptions = Object.assign({
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }, options || {});

        if (fetchOptions.headers && options && options.headers) {
            fetchOptions.headers = Object.assign({}, { 'Accept': 'application/json' }, options.headers);
        }

        return fetch(url, fetchOptions)
            .then(async (response) => {
                const raw = await response.text();
                let data = null;

                if (raw !== '') {
                    try {
                        data = JSON.parse(raw);
                    } catch (err) {
                        throw new Error(defaultError);
                    }
                }

                if (!response.ok) {
                    const message = data && data.error ? data.error : defaultError;
                    throw new Error(message);
                }

                return data || {};
            });
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

    function showSidebarLoading() {
        if (elements.userList) {
            elements.userList.innerHTML = '<div class="chat-empty">Loading teammates…</div>';
        }
        if (elements.groupList) {
            elements.groupList.innerHTML = '<div class="chat-empty">Loading groups…</div>';
        }
    }

    function showConversationPlaceholder(message) {
        if (!elements.messageList) {
            return;
        }
        elements.messageList.innerHTML = '';
        const placeholder = createElement('div', { className: 'chat-empty', text: message || 'Select a conversation to get started.' });
        elements.messageList.appendChild(placeholder);
        enableComposer(false);
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
        const error = createElement('div', { className: 'chat-empty', text: message || 'Something went wrong. Please try again.' });
        elements.messageList.appendChild(error);
        enableComposer(false);
    }

    function enableComposer(enabled) {
        if (elements.messageInput) {
            elements.messageInput.disabled = !enabled;
            if (!enabled) {
                elements.messageInput.value = '';
            }
        }
        if (elements.sendButton) {
            const hasText = elements.messageInput && elements.messageInput.value.trim() !== '';
            elements.sendButton.disabled = !enabled || !hasText;
        }
        if (elements.conversationField) {
            elements.conversationField.value = enabled && state.currentConversationId ? String(state.currentConversationId) : '';
        }
    }

    function renderSidebar() {
        renderEntityList(elements.userList, state.sidebar.users, 'user');
        renderEntityList(elements.groupList, state.sidebar.groups, 'group');
        updateActiveEntityHighlight();
        applySearchFilter();
    }

    function renderEntityList(container, items, type) {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            const emptyMessage = type === 'user' ? 'No teammates available yet.' : 'No groups yet. Create one to get started!';
            container.appendChild(createElement('div', { className: 'chat-empty', text: emptyMessage }));
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
        });
        searchEmpty.dataset.role = 'search-empty';
        searchEmpty.hidden = true;
        container.appendChild(searchEmpty);
    }

    function createEntityNode(item, type) {
        const entity = createElement('div', { className: 'chat-entity' });
        entity.dataset.entity = type;

        const name = item.name || item.email || 'Conversation';
        const initials = name.trim().slice(0, 2).toUpperCase();

        if (type === 'user') {
            entity.dataset.userId = String(item.id);
            if (item.conversation_id) {
                entity.dataset.conversationId = String(item.conversation_id);
            }
            entity.dataset.search = `${(item.name || '').toLowerCase()} ${(item.email || '').toLowerCase()}`.trim();
            if (item.is_self) {
                entity.classList.add('chat-entity--self');
                entity.setAttribute('aria-disabled', 'true');
            }
        } else {
            entity.dataset.conversationId = String(item.id);
            entity.dataset.search = (item.name || '').toLowerCase();
        }

        const avatar = createElement('div', { className: 'chat-entity__avatar', text: initials });
        avatar.setAttribute('aria-hidden', 'true');
        entity.appendChild(avatar);

        const details = createElement('div', { className: 'chat-entity__details' });
        const nameRow = createElement('div', { className: 'chat-entity__name' });
        nameRow.appendChild(createElement('span', { text: name }));
        if (type === 'user' && item.role) {
            nameRow.appendChild(createElement('small', { text: item.role }));
        }
        details.appendChild(nameRow);

        const meta = createElement('div', { className: 'chat-entity__meta' });
        if (type === 'user') {
            meta.textContent = item.email || '';
        } else if (item.last_message) {
            const prefix = item.last_message_from ? `${item.last_message_from}: ` : '';
            meta.textContent = `${prefix}${item.last_message}`;
        } else if (Array.isArray(item.participants)) {
            meta.textContent = `${item.participants.length} member${item.participants.length === 1 ? '' : 's'}`;
        }
        details.appendChild(meta);
        entity.appendChild(details);

        if (type === 'user') {
            const status = createElement('div', { className: 'chat-entity__status' });
            const statusClass = item.is_self || item.is_online ? 'is-online' : 'is-offline';
            status.classList.add(statusClass);
            status.appendChild(createElement('span', { className: 'status-dot' }));
            const label = item.is_self ? 'You' : (item.is_online ? 'Online' : 'Offline');
            status.appendChild(createElement('span', { text: label }));
            entity.appendChild(status);
        }

        if (item.unread_count) {
            entity.appendChild(createElement('span', {
                className: 'chat-entity__badge',
                text: String(item.unread_count),
            }));
        }

        return entity;
    }

    function updateActiveEntityHighlight() {
        if (!state.activeEntity) {
            return;
        }
        const entities = app.querySelectorAll('.chat-entity');
        entities.forEach((entity) => {
            const type = entity.dataset.entity;
            const conversationId = parseNumber(entity.dataset.conversationId || '');
            const userId = parseNumber(entity.dataset.userId || '');
            let isActive = false;

            if (state.activeEntity.type === 'group' && type === 'group') {
                isActive = conversationId !== null && conversationId === state.activeEntity.conversationId;
            } else if (state.activeEntity.type === 'user' && type === 'user') {
                if (state.activeEntity.conversationId && conversationId === state.activeEntity.conversationId) {
                    isActive = true;
                } else if (!state.activeEntity.conversationId && !conversationId && state.activeEntity.userId === userId) {
                    isActive = true;
                }
            }

            entity.classList.toggle('active', Boolean(isActive));
        });
    }

    function applySearchFilter() {
        if (!elements.searchInput) {
            return;
        }
        state.searchTerm = elements.searchInput.value.trim().toLowerCase();
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
                const match = state.searchTerm === '' || haystack.includes(state.searchTerm);
                item.classList.toggle('is-hidden', !match);
                if (match) {
                    visibleCount += 1;
                }
            });
            const empty = container.querySelector('[data-role="search-empty"]');
            if (empty) {
                empty.hidden = state.searchTerm === '' || visibleCount > 0;
            }
        });
    }

    function handleEntityClick(event) {
        const target = event.target.closest('.chat-entity');
        if (!target || target.classList.contains('chat-entity--self')) {
            return;
        }

        const type = target.dataset.entity;
        const conversationId = parseNumber(target.dataset.conversationId || '');
        const userId = parseNumber(target.dataset.userId || '');

        state.activeEntity = {
            type,
            userId: type === 'user' ? userId : null,
            conversationId: conversationId,
        };

        updateActiveEntityHighlight();

        if (conversationId) {
            openConversation(conversationId);
        } else if (type === 'user' && userId) {
            startDirectConversation(userId, target);
        }
    }

    function startDirectConversation(userId, element) {
        showLoadingState('Starting conversation…');
        requestJson(endpoints.create, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ participants: [userId] }),
        }, 'Unable to start the conversation.')
            .then((data) => {
                if (!data || !data.conversation) {
                    throw new Error('Unable to start the conversation.');
                }
                const conversationId = parseNumber(data.conversation.id);
                if (!conversationId) {
                    throw new Error('Invalid conversation identifier.');
                }
                element.dataset.conversationId = String(conversationId);
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
        const requestedConversationId = conversationId;

        requestJson(url.toString(), {}, 'Unable to load conversation.')
            .then((data) => {
                if (state.currentConversationId !== requestedConversationId) {
                    return;
                }
                renderConversation(data);
                if (data && data.last_message_id) {
                    const lastMessageId = parseNumber(data.last_message_id);
                    if (lastMessageId) {
                        state.lastMessageId = lastMessageId;
                        markConversationRead(lastMessageId);
                    }
                }
                clearUnread(requestedConversationId);
            })
            .catch((error) => {
                if (state.currentConversationId !== requestedConversationId) {
                    return;
                }
                showErrorState(error.message || 'Unable to load conversation.');
            });
    }

    function renderConversation(payload) {
        if (!elements.messageList) {
            return;
        }
        elements.messageList.innerHTML = '';

        const conversation = payload && payload.conversation ? payload.conversation : null;
        state.conversationMeta = conversation;

        updateConversationHeader(conversation);

        const messages = Array.isArray(payload && payload.messages) ? payload.messages : [];
        if (messages.length === 0) {
            const empty = createElement('div', { className: 'chat-empty', text: 'Say hello to start the conversation!' });
            elements.messageList.appendChild(empty);
        } else {
            messages.forEach((message) => appendMessage(message, { scroll: false }));
            scrollMessagesToBottom();
        }

        enableComposer(true);
    }

    function updateConversationHeader(conversation) {
        if (!conversation) {
            if (elements.headerTitle) {
                elements.headerTitle.textContent = 'Select a conversation';
            }
            if (elements.headerSubtitle) {
                elements.headerSubtitle.textContent = 'Choose someone from the list to start chatting';
            }
            if (elements.headerAvatar) {
                elements.headerAvatar.textContent = (state.currentUserName || '?').trim().slice(0, 2).toUpperCase();
            }
            return;
        }

        const participants = Array.isArray(conversation.participants) ? conversation.participants : [];
        const partner = participants.find((participant) => parseNumber(participant.id) !== state.currentUserId);

        if (elements.headerTitle) {
            elements.headerTitle.textContent = conversation.name || (partner ? partner.name : 'Conversation');
        }

        if (elements.headerSubtitle) {
            if (conversation.is_group) {
                const count = participants.length;
                elements.headerSubtitle.textContent = `${count} participant${count === 1 ? '' : 's'}`;
            } else {
                elements.headerSubtitle.textContent = partner ? partner.email : '';
            }
        }

        if (elements.headerAvatar) {
            const source = conversation.name || (partner ? partner.name : 'Conversation');
            elements.headerAvatar.textContent = (source || '?').trim().slice(0, 2).toUpperCase();
        }
    }

    function appendMessage(message, options = {}) {
        if (!elements.messageList || !message) {
            return;
        }
        const messageId = parseNumber(message.id);
        if (!messageId) {
            return;
        }
        if (state.messageIds.has(messageId)) {
            return;
        }
        state.messageIds.add(messageId);
        state.lastMessageId = Math.max(state.lastMessageId, messageId);

        const wrapper = createElement('div', { className: 'chat-message' });
        const senderId = parseNumber(message.sender_id);
        const isMine = Boolean(message.is_mine || (senderId !== null && senderId === state.currentUserId));
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
        const body = elements.messageInput.value.trim();
        if (!body || !state.currentConversationId) {
            return;
        }

        elements.sendButton.disabled = true;
        const payload = new URLSearchParams();
        payload.append('conversation_id', String(state.currentConversationId));
        payload.append('message', body);

        requestJson(endpoints.send, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: payload.toString(),
        }, 'Unable to send the message.')
            .then((data) => {
                if (!data || !data.message) {
                    throw new Error('Unable to send the message.');
                }
                elements.messageInput.value = '';
                handleComposerInput();
                appendMessage(data.message, { scroll: true });
                if (data.message && data.message.id) {
                    const messageId = parseNumber(data.message.id);
                    if (messageId) {
                        markConversationRead(messageId);
                    }
                }
                clearUnread(state.currentConversationId);
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

        requestJson(endpoints.markRead, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: payload.toString(),
        }).catch(() => {
            // Ignore errors while marking messages as read.
        });
    }

    function clearUnread(conversationId) {
        if (!conversationId) {
            return;
        }

        let updated = false;
        state.sidebar.users = state.sidebar.users.map((user) => {
            if (parseNumber(user.conversation_id) === conversationId && user.unread_count) {
                updated = true;
                return Object.assign({}, user, { unread_count: 0 });
            }
            return user;
        });

        state.sidebar.groups = state.sidebar.groups.map((group) => {
            if (parseNumber(group.id) === conversationId && group.unread_count) {
                updated = true;
                return Object.assign({}, group, { unread_count: 0 });
            }
            return group;
        });

        if (updated) {
            renderSidebar();
        } else {
            const badge = app.querySelector(`.chat-entity[data-conversation-id="${conversationId}"] .chat-entity__badge`);
            if (badge && badge.parentNode) {
                badge.parentNode.removeChild(badge);
            }
        }
    }

    function refreshConversation() {
        if (!state.currentConversationId) {
            return;
        }
        const conversationId = state.currentConversationId;
        const url = new URL(endpoints.conversation, window.location.origin);
        url.searchParams.set('conversation_id', String(conversationId));
        url.searchParams.set('limit', '200');

        requestJson(url.toString(), {}, 'Unable to refresh the conversation.')
            .then((data) => {
                if (state.currentConversationId !== conversationId) {
                    return;
                }
                if (!data || !Array.isArray(data.messages)) {
                    return;
                }
                data.messages.forEach((message) => {
                    const messageId = parseNumber(message.id);
                    if (!messageId || state.messageIds.has(messageId)) {
                        return;
                    }
                    appendMessage(message, { scroll: true });
                });
                if (data.last_message_id) {
                    const lastMessageId = parseNumber(data.last_message_id);
                    if (lastMessageId && lastMessageId !== state.lastMessageId) {
                        state.lastMessageId = lastMessageId;
                        markConversationRead(lastMessageId);
                    }
                }
            })
            .catch(() => {
                // Ignore polling errors.
            });
    }

    function refreshSidebar() {
        requestJson(endpoints.sidebar, {}, 'Unable to refresh sidebar.')
            .then((data) => {
                if (!data) {
                    return;
                }
                if (data.current_user && data.current_user.name) {
                    state.currentUserName = data.current_user.name;
                }
                state.sidebar.users = Array.isArray(data.users) ? data.users : [];
                state.sidebar.groups = Array.isArray(data.groups) ? data.groups : [];
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
        const teammates = state.sidebar.users.filter((user) => !user.is_self);
        if (teammates.length === 0) {
            elements.groupUserList.innerHTML = '<div class="text-muted small">No teammates available.</div>';
            return;
        }
        const fragment = document.createDocumentFragment();
        teammates.forEach((user) => {
            const wrapper = createElement('div', { className: 'form-check' });
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
            wrapper.appendChild(input);
            wrapper.appendChild(label);
            fragment.appendChild(wrapper);
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
        const selected = Array.from(elements.groupUserList.querySelectorAll('input[name="participants[]"]:checked'))
            .map((input) => parseNumber(input.value))
            .filter((value) => value !== null);

        if (!name) {
            alert('Please provide a group name.');
            return;
        }
        if (selected.length === 0) {
            alert('Please select at least one teammate.');
            return;
        }

        const submitButton = elements.groupForm.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        requestJson(endpoints.create, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name, participants: selected }),
        }, 'Unable to create the group.')
            .then((data) => {
                if (!data || !data.conversation) {
                    throw new Error('Unable to create the group.');
                }
                const conversationId = parseNumber(data.conversation.id);
                if (!conversationId) {
                    throw new Error('Invalid conversation identifier.');
                }
                const newGroup = {
                    id: conversationId,
                    name: data.conversation.name || name,
                    unread_count: 0,
                    last_message: null,
                    last_message_at: null,
                    last_message_from: null,
                    participants: Array.isArray(data.participants) ? data.participants : [],
                };
                state.sidebar.groups.unshift(newGroup);
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
                if (submitButton) {
                    submitButton.disabled = false;
                }
                openConversation(conversationId);
            })
            .catch((error) => {
                if (submitButton) {
                    submitButton.disabled = false;
                }
                alert(error.message || 'Unable to create the group.');
            });
    }

    function initialise() {
        showSidebarLoading();
        showConversationPlaceholder('Select a conversation to get started.');

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

        refreshSidebar();
        state.sidebarTimer = window.setInterval(refreshSidebar, 15000);
        state.conversationTimer = window.setInterval(refreshConversation, 5000);
    }

    initialise();
})();
