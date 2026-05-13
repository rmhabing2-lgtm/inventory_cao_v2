<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once __DIR__ . '/db_connect.php';

$me = $_SESSION['id'];
$me_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Me';

// Sync current user status
$u = $conn->prepare("UPDATE messenger_users SET status='online', last_active=NOW() WHERE id = ?");
if ($u) { $u->bind_param('i', $me); $u->execute(); $u->close(); }
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr">
<head>
    <meta charset="utf-8" />
    <title>Messenger | Professional</title>
    <link rel="stylesheet" href="/inventory_cao_v2/assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="/inventory_cao_v2/assets/vendor/css/core.css" />
    <link rel="stylesheet" href="/inventory_cao_v2/assets/vendor/css/theme-default.css" />
    <style>
        .messenger-wrapper { display: flex; height: 75vh; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        /* Column 1: Contacts */
        .contacts-sidebar { width: 320px; border-right: 1px solid #eee; display: flex; flex-direction: column; }
        .sidebar-search { padding: 15px; border-bottom: 1px solid #eee; }
        .search-input { background: #f0f2f5; border: none; border-radius: 20px; padding: 8px 15px; width: 100%; outline: none; font-size: 14px; }
        .search-input::placeholder { color: #99999b; }
        .contacts-list { flex: 1; overflow-y: auto; }
        .contact-item { padding: 12px 15px; display: flex; align-items: center; cursor: pointer; transition: 0.2s; border-radius: 8px; margin: 4px; }
        .contact-item:hover { background: #f5f5f5; }
        .contact-item.active { background: #e7f3ff; }
        
        /* Sidebar Notification Badge */
        .unread-badge {
            background-color: #0084ff;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            margin-left: auto;
        }

        .contact-item.has-unread .contact-item-name {
            font-weight: 700;
            color: #000;
        }

        /* Enhanced Badge Styling */
        .unread-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
        }

        /* Highlight active contact with unread */
        .contact-item.active.has-unread { background: #d4e9ff; }

        /* Hover state for unread */
        .contact-item.has-unread:hover { background: #f0f7ff; }
        
        /* Column 2: Chat */
        .chat-main { flex: 1; display: flex; flex-direction: column; border-right: 1px solid #eee; }
        .chat-header { padding: 10px 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; }
        .chat-box { flex: 1; overflow-y: auto; padding: 20px; background: #fff; display: flex; flex-direction: column; gap: 5px; }
        
        /* Professional Bubbles */
        .msg { max-width: 65%; padding: 8px 12px; border-radius: 18px; font-size: 14px; position: relative; word-wrap: break-word; }
        .msg.sent { align-self: flex-end; background: #0084ff; color: white; border-bottom-right-radius: 4px; margin-bottom: 2px; }
        .msg.received { align-self: flex-start; background: #e4e6eb; color: #050505; border-bottom-left-radius: 4px; }
        
        .chat-footer { padding: 15px; border-top: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
        .chat-input { flex: 1; background: #f0f2f5; border: none; border-radius: 20px; padding: 10px 15px; outline: none; font-size: 14px; }
        .chat-input::placeholder { color: #99999b; }
        .send-btn { border: none; background: none; color: #0084ff; font-size: 24px; cursor: pointer; padding: 0; }

        /* Column 3: Details */
        .details-sidebar { width: 280px; padding: 20px; text-align: center; border-left: 1px solid #eee; }
        .avatar-circle { width: 50px; height: 50px; border-radius: 50%; background: #0084ff; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; position: relative; flex-shrink: 0; }
        .online-dot { width: 12px; height: 12px; background: #31a24c; border-radius: 50%; position: absolute; bottom: 2px; right: 2px; border: 2px solid #fff; }

        /* Empty State */
        #welcome-screen { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #bcc0c4; }
        #welcome-screen i { font-size: 80px; margin-bottom: 20px; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #999; }
    </style>
</head>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>
            <!-- <div class="layout-page"> -->
                <?php include __DIR__ . '/../includes/navbar.php'; ?>
                <div class="content-wrapper">
                    <div class="container-xxl grow container-p-y">
                        <div class="messenger-wrapper">
                            <!-- Left: Contacts Sidebar -->
                            <div class="contacts-sidebar">
                                <div class="sidebar-search">
                                    <input type="text" id="search-contacts" class="search-input" placeholder="Search Messenger">
                                </div>
                                <div id="contacts-list" class="contacts-list"></div>
                            </div>

                            <!-- Middle: Chat Area -->
                            <div class="chat-main" id="chat-area" style="display:none;">
                                <div class="chat-header">
                                    <div style="display:flex; align-items:center; flex:1;">
                                        <div id="h-avatar" class="avatar-circle" style="width:40px; height:40px; font-size:16px;"></div>
                                        <div style="margin-left:10px;">
                                            <div id="h-name" style="font-weight:600; font-size:15px;">Name</div>
                                            <div id="h-typing" style="font-size:11px; color:#0084ff; display:none; font-style:italic;">is typing...</div>
                                        </div>
                                    </div>
                                    <div style="color:#0084ff; font-size:20px;">
                                        <i class="bx bxs-phone-call" style="cursor:pointer; opacity:0.6; margin-right:15px;"></i>
                                        <i class="bx bxs-video" style="cursor:pointer; opacity:0.6;"></i>
                                    </div>
                                </div>
                                <div id="chat-box" class="chat-box"></div>
                                <form id="chat-form" class="chat-footer">
                                    <input type="hidden" id="active-id">
                                    <input type="text" id="msg-input" class="chat-input" placeholder="Aa" autocomplete="off">
                                    <button type="submit" class="send-btn"><i class="bx bxs-send"></i></button>
                                </form>
                            </div>

                            <!-- Welcome Screen -->
                            <div id="welcome-screen">
                                <i class="bx bx-message-rounded-dots"></i>
                                <p style="font-size:16px;">Select a chat to start</p>
                            </div>

                            <!-- Right: User Details -->
                            <div class="details-sidebar" id="details-area" style="display:none;">
                                <div id="d-avatar" class="avatar-circle" style="width:80px; height:80px; margin:0 auto 15px; font-size:30px;"></div>
                                <h5 id="d-name" style="font-size:16px; font-weight:600; margin:0 0 4px;">User Name</h5>
                                <p style="font-size:13px; color:#65676b; margin:0;">Active Now</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    let currentFriend = null;
    let messageRefreshInterval;
    const myId = <?= (int)$me ?>;

    function getColor(name) {
        let hash = 0;
        for (let i = 0; i < name.length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        return `hsl(${Math.abs(hash) % 360}, 65%, 45%)`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function loadContacts(searchTerm = '') {
        const query = searchTerm ? `?search=${encodeURIComponent(searchTerm)}` : '';
        fetch(`contacts_list.php${query}`)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                const list = document.getElementById('contacts-list');
                if (!Array.isArray(data) || data.length === 0) {
                    list.innerHTML = '<div style="padding: 20px; text-align: center; color: #99999b; font-size: 13px;">No contacts found</div>';
                    return;
                }
                list.innerHTML = data.map(u => {
                    // Determine if we should show a badge
                    const unreadCount = u.unread_count || 0;
                    const unreadBadge = unreadCount > 0 
                        ? `<div class="unread-badge" title="${unreadCount} unread message${unreadCount > 1 ? 's' : ''}">` + (unreadCount > 99 ? '99+' : unreadCount) + `</div>` 
                        : '';
                    
                    return `
                        <div class="contact-item ${currentFriend == u.id ? 'active' : ''} ${unreadCount > 0 ? 'has-unread' : ''}" onclick="selectUser(${u.id}, '${u.username.replace(/'/g, "\\'")}')">
                            <div class="avatar-circle" style="background:${getColor(u.username)}; width:40px; height:40px; font-size:16px;">
                                ${u.username[0].toUpperCase()}
                                ${u.status == 'online' ? '<div class="online-dot"></div>' : ''}
                            </div>
                            <div style="margin-left:12px; flex:1; text-align:left;">
                                <div class="contact-item-name" style="font-weight:500; font-size:14px;">${u.username}</div>
                                <div style="font-size:12px; color:#65676b;">${u.status == 'online' ? 'Active now' : 'Offline'}</div>
                            </div>
                            ${unreadBadge}
                        </div>
                    `;
                }).join('');
            })
            .catch(err => {
                console.error('Error loading contacts:', err);
                document.getElementById('contacts-list').innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f; font-size: 13px;">Error loading contacts</div>';
            });
    }

    function selectUser(id, name) {
        currentFriend = id;
        document.getElementById('welcome-screen').style.display = 'none';
        document.getElementById('chat-area').style.display = 'flex';
        document.getElementById('details-area').style.display = 'block';
        document.getElementById('active-id').value = id;
        
        document.getElementById('h-name').innerText = name;
        document.getElementById('d-name').innerText = name;
        document.getElementById('h-avatar').innerText = name[0].toUpperCase();
        document.getElementById('h-avatar').style.background = getColor(name);
        document.getElementById('d-avatar').innerText = name[0].toUpperCase();
        document.getElementById('d-avatar').style.background = getColor(name);

        // Clear old interval
        if (messageRefreshInterval) clearInterval(messageRefreshInterval);
        
        loadMessages();
        loadContacts();
        
        // Auto-refresh messages every 2 seconds
        messageRefreshInterval = setInterval(loadMessages, 2000);
    }

    function loadMessages() {
        if (!currentFriend) return;
        fetch(`fetch_messages.php?friend_id=${currentFriend}`)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                // Handle structured response
                if (data.success === false) {
                    console.error('Error:', data.error);
                    const box = document.getElementById('chat-box');
                    box.innerHTML = '<div style="margin: auto; text-align: center; color: #d32f2f;">Error: ' + (data.error || 'Failed to load messages') + '</div>';
                    return;
                }

                // FIX: Access data.messages correctly
                const messages = data.messages || [];
                const typing = data.friend_typing || 0;
                
                const box = document.getElementById('chat-box');
                if (messages.length === 0) {
                    box.innerHTML = '<div style="margin: auto; text-align: center; color: #99999b;">No messages yet. Start the conversation!</div>';
                } else {
                    box.innerHTML = messages.map(m => `
                        <div class="msg ${m.sender_id == myId ? 'sent' : 'received'}">${escapeHtml(m.message_text)}</div>
                    `).join('');
                }
                box.scrollTop = box.scrollHeight;
                
                // Show/hide typing indicator
                const typingDiv = document.getElementById('h-typing');
                typingDiv.style.display = typing ? 'block' : 'none';
            })
            .catch(err => {
                console.error('Error loading messages:', err);
                const box = document.getElementById('chat-box');
                if (box) {
                    box.innerHTML = '<div style="margin: auto; text-align: center; color: #d32f2f;">Error loading messages</div>';
                }
            });
    }

    // Send Message
    document.getElementById('chat-form').onsubmit = function(e) {
        e.preventDefault();
        const text = document.getElementById('msg-input').value.trim();
        if (!text || !currentFriend) return;

        const body = new FormData();
        body.append('receiver_id', currentFriend);
        body.append('message_text', text);

        fetch('send_message.php', { method: 'POST', body })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('msg-input').value = '';
                    loadMessages();
                    updateTyping(0);
                }
            })
            .catch(err => console.error('Error sending message:', err));
    };

    // Search Contacts
    document.getElementById('search-contacts').addEventListener('input', (e) => {
        loadContacts(e.target.value);
    });

    // Typing Status
    let typingTimer;
    document.getElementById('msg-input').addEventListener('input', () => {
        if (currentFriend) {
            clearTimeout(typingTimer);
            updateTyping(1);
            typingTimer = setTimeout(() => updateTyping(0), 1500);
        }
    });

    function updateTyping(status) {
        if (currentFriend) {
            fetch(`update_typing.php?status=${status}&friend_id=${currentFriend}`)
                .catch(err => console.error('Typing update error:', err));
        }
    }

    // Initial load
    loadContacts();
</script>

</body>
</html>
