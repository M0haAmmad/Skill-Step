<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login/login.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);
$new_chat_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Skill-Step</title>
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@400;700;800&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="chat.css">
    <link rel="stylesheet" href="../Main/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="alert-system.css?v=<?php echo time(); ?>">

</head>

<body>

    <nav>
        <a href="../Main/index.php" class="logo">
            <img src="../images/logo.png" alt="Skill-Step" width="50" height="50" onerror="this.style.display='none';">
            Skill-Step
        </a>
        <a href="../Main/index.php" class="btn-action" style="width:auto; padding: 10px 20px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Platform
        </a>
    </nav>


    <div class="chat-container">
        <!-- Sidebar -->
        <div class="contacts-sidebar" id="contactsSidebar">
            <div class="sidebar-header">
                <div><i class="fa-solid fa-comments text-accent-blue"></i> Conversations</div>
                <div class="sidebar-unread-badge" id="sidebarUnreadCount" style="display:none;">0</div>
            </div>
            <div class="contacts-list" id="contactsList">
                <div style="padding: 20px; text-align: center; color: var(--text-muted);"><i
                        class='fa-solid fa-spinner fa-spin'></i> Loading...</div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area" id="chatArea">
            <div class="empty-state" id="emptyState">
                <i class="fa-solid fa-paper-plane"></i>
                <h2>Welcome to the Message Center</h2>
                <p>Select a conversation from the list to connect with instructors or peers</p>
            </div>

            <!-- Active Chat Elements (Hidden initially) -->
            <div id="activeChatWrapper" style="display: none; height: 100%; flex-direction: column;">
                <div class="chat-header">
                    <div class="contact-avatar" id="activeChatAvatar"></div>
                    <div>
                        <div class="contact-name" id="activeChatName">Name</div>
                        <div style="font-size:0.8rem; color:var(--accent-teal);"><i class="fa-solid fa-circle"
                                style="font-size:0.6rem;"></i> Online</div>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will be injected here -->
                </div>

                <div class="chat-input-area">
                    <input type="text" class="chat-input" id="messageInput" placeholder="Type your message here..."
                        onkeypress="handleEnter(event)">
                    <button class="send-btn" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Injection -->
    <script src="alert-system.js?v=<?php echo time(); ?>"></script>
    <script>
        const CURRENT_USER_ID = <?php echo $user_id; ?>;
        const NEW_CHAT_ID = <?php echo $new_chat_id; ?>;

        let activeContactId = null;
        let pollingInterval = null;
        const sidebarUnreadCountEl = document.getElementById('sidebarUnreadCount');

        document.addEventListener('DOMContentLoaded', () => {
            loadContacts();

            // Poll for messages every 3 seconds if active chat is open
            pollingInterval = setInterval(() => {
                if (activeContactId) {
                    fetchMessages(activeContactId, true); // true = seamless update
                }
            }, 3000);
        });

        function loadContacts() {
            fetch('chat_api.php?action=get_contacts&new_chat_id=' + NEW_CHAT_ID)
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('contactsList');
                    list.innerHTML = '';
                    let totalUnread = 0;

                    if (data.success && data.contacts.length > 0) {
                        data.contacts.forEach(c => {
                            totalUnread += c.unread_count || 0;
                            const avatarHTML = c.profile_pic && c.profile_pic !== 'default.png' && c.profile_pic !== 'images/avatar1.png' && c.profile_pic !== '../images/avatar1.png'
                                ? `<img src="../profile/uploads/${c.profile_pic}" alt="avatar" onerror="this.outerHTML='${c.User_name.charAt(0)}'">`
                                : c.User_name.charAt(0);

                            const item = document.createElement('div');
                            item.className = 'contact-item' + (activeContactId == c.User_id ? ' active' : '');
                            item.onclick = (e) => openChat(c.User_id, c.User_name, avatarHTML, e);
                            const unreadHtml = c.unread_count > 0 ? `<span class="unread-badge">${c.unread_count}</span>` : '';
                            item.innerHTML = `
                            <div class="contact-avatar">${avatarHTML}</div>
                            <div class="contact-info">
                                <div class="contact-name">${c.User_name} ${unreadHtml}</div>
                                <div class="contact-lastmsg">${c.last_msg || 'No messages yet'}</div>
                            </div>
                        `;
                            list.appendChild(item);
                        });
                        if (totalUnread > 0) {
                            sidebarUnreadCountEl.style.display = 'inline-flex';
                            sidebarUnreadCountEl.textContent = totalUnread;
                        } else {
                            sidebarUnreadCountEl.style.display = 'none';
                        }

                        // If a new chat target was supplied via URL, open it!
                        if (NEW_CHAT_ID > 0 && activeContactId === null) {
                            const newContact = data.contacts.find(x => x.User_id == NEW_CHAT_ID);
                            if (newContact) {
                                const avatar = newContact.profile_pic && newContact.profile_pic !== 'default.png' && newContact.profile_pic !== 'images/avatar1.png' && newContact.profile_pic !== '../images/avatar1.png'
                                    ? `<img src="../profile/uploads/${newContact.profile_pic}" onerror="this.outerHTML='${newContact.User_name.charAt(0)}'">`
                                    : newContact.User_name.charAt(0);
                                openChat(NEW_CHAT_ID, newContact.User_name, avatar);
                            }
                        }

                    } else {
                        list.innerHTML = '<div style="padding:20px; color:var(--text-muted); text-align:center;">No previous conversations.</div>';
                        sidebarUnreadCountEl.style.display = 'none';
                    }
                })
                .catch(err => console.error(err));
        }

        function openChat(contactId, contactName, avatarHTML, event = null) {
            activeContactId = contactId;
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('activeChatWrapper').style.display = 'flex';

            document.getElementById('activeChatName').innerText = contactName;
            document.getElementById('activeChatAvatar').innerHTML = avatarHTML;

            // Highlight active in sidebar
            document.querySelectorAll('.contact-item').forEach(el => el.classList.remove('active'));
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            } else {
                loadContacts();
            }

            fetchMessages(contactId, false);
        }

        let msgCount = 0; // tracking count to autoscroll only when new messages arrive
        function fetchMessages(contactId, isPolling) {
            fetch(`chat_api.php?action=fetch&contact_id=${contactId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const cBox = document.getElementById('chatMessages');

                        // Prevent repainting if counts match and it's polling
                        if (isPolling && data.messages.length === msgCount) return;

                        msgCount = data.messages.length;
                        cBox.innerHTML = '';

                        data.messages.forEach(m => {
                            const isSent = m.sender_id == CURRENT_USER_ID;
                            const div = document.createElement('div');
                            div.className = 'message ' + (isSent ? 'sent' : 'received');
                            div.innerHTML = `
                            ${m.message}
                            <span class="message-time">${m.time}</span>
                        `;
                            cBox.appendChild(div);
                        });

                        if (!isPolling || cBox.scrollTop + cBox.clientHeight >= cBox.scrollHeight - 100) {
                            cBox.scrollTop = cBox.scrollHeight;
                        }
                    }
                });
        }

        function handleEnter(e) {
            if (e.key === 'Enter') sendMessage();
        }

        function showToast(message) {
            if (document.getElementById('chatToast')) return;
            const toast = document.createElement('div');
            toast.id = 'chatToast';
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.right = '20px';
            toast.style.background = 'rgba(15, 23, 42, 0.95)';
            toast.style.color = 'white';
            toast.style.padding = '12px 18px';
            toast.style.borderRadius = '12px';
            toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.3)';
            toast.style.zIndex = '2000';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        function sendMessage() {
            if (!activeContactId) return;
            const input = document.getElementById('messageInput');
            const txt = input.value.trim();
            if (txt === '') return;

            // Optimistic UI update
            const cBox = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = 'message sent';
            div.innerHTML = `${txt} <span class="message-time">Sending...</span>`;
            cBox.appendChild(div);
            cBox.scrollTop = cBox.scrollHeight;
            input.value = '';

            fetch('chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'send', receiver_id: activeContactId, message: txt })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message sent successfully');
                        fetchMessages(activeContactId, false); // force re-sync
                        loadContacts(); // Update "Last msg" in sidebar
                    } else {
                        Alert.error('Error sending message.');
                        div.style.background = 'red';
                    }
                })
                .catch(err => console.error(err));
        }

    </script>
</body>

</html>