let currentFriend = null;
let pollInterval = null;
let typingTimer = null;

function byId(id){return document.getElementById(id)}

async function loadContacts(){
    const res = await fetch('contacts_list.php');
    if (!res.ok) return;
    const data = await res.json();
    const el = byId('contacts-list'); el.innerHTML = '';
    data.forEach(c => {
        const div = document.createElement('div');
        div.className = 'contact';
        div.dataset.id = c.id;
        div.innerHTML = `<div><div class="name">${c.username}</div><div class="meta">${c.status}</div></div>`;
        div.addEventListener('click', () => openChat(c.id, c.username));
        el.appendChild(div);
    });
}

function openChat(id, username){
    currentFriend = id;
    byId('friend-id').value = id;
    byId('chat-header').textContent = username;
    loadMessages();
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(loadMessages, 2000);
}

async function loadMessages(){
    if (!currentFriend) return;
    const res = await fetch(`fetch_messages.php?friend_id=${currentFriend}`);
    if (!res.ok) return;
    const obj = await res.json();
    if (!obj.success) return;
    const box = byId('chat-box');
    box.innerHTML = '';
    obj.messages.forEach(m => {
        const d = document.createElement('div');
        d.className = 'bubble ' + (m.sender_id === CURRENT_USER_ID ? 'me' : 'friend');
        d.textContent = m.message_text;
        box.appendChild(d);
    });
    if (obj.friend_typing) {
        const t = document.createElement('div'); t.className='typing'; t.textContent='Typing...'; box.appendChild(t);
    }
    box.scrollTop = box.scrollHeight;
}

document.getElementById('message-form').addEventListener('submit', async function(e){
    e.preventDefault();
    const msg = byId('msg-input').value.trim();
    const friend = byId('friend-id').value;
    if (!msg || !friend) return;
    const fd = new FormData(); fd.append('receiver_id', friend); fd.append('message_text', msg);
    await fetch('send_message.php', {method:'POST', body:fd});
    byId('msg-input').value = '';
    loadMessages();
});

// typing indicator
const msgInput = byId('msg-input');
msgInput.addEventListener('input', function(){
    if (!currentFriend) return;
    fetch(`update_typing.php?status=1`);
    clearTimeout(typingTimer);
    typingTimer = setTimeout(()=>{ fetch(`update_typing.php?status=0`); }, 2500);
});

// heartbeat status
setInterval(()=>{ fetch('update_status.php?status=online'); }, 5000);

// initial load
window.addEventListener('load', ()=>{ loadContacts(); setInterval(loadContacts, 5000); });
