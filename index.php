<?php
// Helper functions and AJAX endpoints for modernized UI
function wa_safe_path($path) {
    $realBase = realpath(__DIR__ . '/extracted');
    $userPath = realpath($path);
    return ($userPath !== false && strpos($userPath, $realBase) === 0) ? $userPath : false;
}

function wa_list_chats() {
    $base = __DIR__ . '/extracted';
    if (!is_dir($base)) { return []; }
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    $chats = [];
    foreach ($rii as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'txt') {
            $path = $file->getPathname();
            $rel = substr($path, strlen($base) + 1);
            $name = 'Unknown';
            $firstline = fgets(fopen($path, 'r')) ?: '';
            if (preg_match('/Whatsapp Chat with\s(?<name>.+)/i', $firstline, $m)) {
                $name = trim($m['name']);
            } else if (preg_match('/WhatsApp Chat with\s(?<name>.+)/i', $firstline, $m)) {
                $name = trim($m['name']);
            } else {
                $name = preg_replace('/^WhatsApp Chat with\s*/i','', pathinfo($path, PATHINFO_FILENAME));
            }
            // Get last message preview and time
            $preview = '';
            $ptime = filemtime($path);
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = $lines[$i];
                if (preg_match('/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9: ]+[AP]M)\s+\-\s+(?<rest>.*)/i', $line, $mm)) {
                    $preview = trim($mm['rest']);
                    $ptime = strtotime($mm['time']);
                    break;
                }
            }
            $chats[] = [
                'name' => $name,
                'file' => $rel,
                'mtime' => $ptime,
                'preview' => $preview
            ];
        }
    }
    // sort by mtime desc
    usort($chats, function($a,$b){
        if ($b['mtime'] == $a['mtime']) return 0;
        return ($b['mtime'] < $a['mtime']) ? -1 : 1;
    });
    return $chats;
}

function wa_render_chat_html($filePath) {
    $full = wa_safe_path(__DIR__ . '/extracted/' . $filePath);
    if (!$full || !is_file($full)) { http_response_code(404); return '<div class="message center"><p class="chat">Chat not found</p></div>'; }
    $filearray = file($full);
    $baseFileName = pathinfo($full, PATHINFO_FILENAME);
    $pattern = '/Whatsapp Chat with\s(?<name>[a-zA-Z0-9].+)/i';
    $pattern2 = '/WhatsApp Chat with\s(?<name>.+)/i';
    $string = isset($filearray[0]) ? $filearray[0] : $baseFileName;
    if (preg_match($pattern, $string, $matches) || preg_match($pattern2, $string, $matches)) {
        $name = trim($matches['name']);
    } else {
        $name = preg_replace('/^WhatsApp Chat with\s*/i','', $baseFileName);
    }
    $out = "<h3 class='chat-title'>" . htmlspecialchars($name) . "</h3>";
    foreach ($filearray as $line) {
        $string = $line;
        $pattern = '/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9:APM ]+)\s+\-\s+(?<sender>[A-Za-z0-9 +@._-]+):(?<message>.*)/';
        if (preg_match($pattern, $string, $matches)) {
            $time = htmlspecialchars($matches['time']);
            $sender = htmlspecialchars(trim($matches['sender']));
            $message = htmlspecialchars(trim($matches['message']));
            $isMedia = stripos($message, 'omitted') !== false;
            $icon = $isMedia ? '🖼️' : '';
            $bubbleClass = 'right';
            if (strcasecmp($sender, $name) !== 0) { $bubbleClass = 'left'; }
            $out .= "<div class='message $bubbleClass'>
                        <div class='flex-justify-between'>
                            <p class='sender'>$sender</p>
                            <span class='time'>$time</span>
                        </div>
                        <p class='chat'>$icon $message</p>
                    </div>";
        } else {
            $pattern2 = '/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9: ]+[PA]M)\s+\-\s(?<message>.*)/i';
            if (preg_match($pattern2, $string, $matches)) {
                $time = htmlspecialchars($matches['time']);
                $message = htmlspecialchars(trim($matches['message']));
                $out .= "<div class='message center'>
                            <div class='flex-justify-between'>
                                <span class='text-white time'>$time</span>
                            </div>
                            <p class='chat'>$message</p>
                        </div>";
            }
        }
    }
    return $out;
}

// Handle AJAX actions
if (!function_exists('wa_rrmdir')) {
    function wa_rrmdir($dir) {
        if (!is_dir($dir)) return false;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                wa_rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }
}

if (isset($_GET['action'])) {
    header('Cache-Control: no-store');
    $action = $_GET['action'];
    if ($action === 'list-chats') {
        header('Content-Type: application/json');
        echo json_encode(['chats' => wa_list_chats()]);
        exit;
    }
    if ($action === 'load-chat' && isset($_GET['file'])) {
        header('Content-Type: text/html; charset=utf-8');
        echo wa_render_chat_html($_GET['file']);
        exit;
    }
    if ($action === 'delete-chat' && isset($_POST['file'])) {
        header('Content-Type: application/json');
        $full = wa_safe_path(__DIR__ . '/extracted/' . $_POST['file']);
        $ok = false;
        if ($full && is_file($full)) {
            // delete parent directory of the chat (keeps folder tidy)
            $dir = dirname($full);
            $ok = wa_rrmdir($dir);
        }
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }
    if ($action === 'archive-chat' && isset($_POST['file'])) {
        header('Content-Type: application/json');
        $full = wa_safe_path(__DIR__ . '/extracted/' . $_POST['file']);
        $ok = false;
        if ($full && is_file($full)) {
            $dir = dirname($full);
            $archiveBase = __DIR__ . '/extracted/_archived';
            if (!is_dir($archiveBase)) { @mkdir($archiveBase, 0777, true); }
            $dest = $archiveBase . '/' . basename($dir);
            $ok = @rename($dir, $dest);
        }
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }
}

// Handle uploads (standard form or AJAX)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fupload'])) {
    $uploadFilename = explode('.', $_FILES['fupload']['name']);
    $baseName = isset($uploadFilename[0]) ? $uploadFilename[0] : ('chat_' . time());
    $extractDirectory = 'extracted/' . $baseName . time() . '/';
    $accepted_types = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/s-compressed');
    $isZipMime = in_array($_FILES['fupload']['type'], $accepted_types, true);
    $isZipExt = strtolower(pathinfo($_FILES['fupload']['name'], PATHINFO_EXTENSION)) === 'zip';
    if ($isZipMime || $isZipExt) {
        if (mkdir($extractDirectory, 0777, true)) {
            $chatPath =  $extractDirectory . $baseName . '.zip';
            if (move_uploaded_file($_FILES['fupload']['tmp_name'], $chatPath)) {
                include_once 'extract.php';
                $extracted = extractZIP($chatPath, $extractDirectory);
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => (bool)$extracted]);
                    exit;
                }
            } else {
                $errors['err-upload'] = 'There was a problem. Please try again.';
            }
        } else {
            $errors['server'] = 'Server error. Could not create directory.';
        }
    } else {
        $errors['file_type_mismatch'] = 'Please choose a ZIP file.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>WhatsApp Backup Viewer</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Minimal WhatsApp-like layout and theme toggle */
        body { background: var(--app-bg); color: var(--app-fg); }
        :root { --app-bg: #111; --app-fg: #e8e8e8; --panel: #121b22; --panel-2:#0b141a; --accent:#25d366; --muted:#8696a0; --bubble-in:#005c4b; --bubble-out:#202c33; --border:#23313a; }
        .light { --app-bg: #ece5dd; --app-fg: #111; --panel:#fff; --panel-2:#f0f2f5; --accent:#128c7e; --muted:#54656f; --bubble-in:#d9fdd3; --bubble-out:#fff; --border:#d1d7db; }
        .app { display:flex; height: calc(100vh - 100px); width: min(1200px, 100%); margin: 0 auto; border:1px solid var(--border); border-radius:8px; overflow: hidden; background: var(--panel-2); }
        .sidebar { width: 35%; min-width: 260px; border-right:1px solid var(--border); display:flex; flex-direction:column; background: var(--panel); }
        .sidebar .top { display:flex; align-items:center; gap:.5rem; padding:.5rem; border-bottom:1px solid var(--border); }
        .sidebar input[type="text"] { flex:1; padding:.5rem .75rem; border-radius:999px; border:1px solid var(--border); background: var(--panel-2); color: var(--app-fg); }
        .chat-list { overflow:auto; }
        .chat-item { display:flex; align-items:center; gap:.6rem; padding:.6rem .8rem; cursor:pointer; border-bottom:1px solid var(--border); }
        .chat-item:hover { background: rgba(255,255,255,.05); }
        .avatar { width:36px; height:36px; border-radius:999px; background:#ccc; flex:0 0 auto; }
        .meta { flex:1; min-width:0; }
        .meta .name { font-weight:600; }
        .meta .preview { color: var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:.85rem; }
        .time { color: var(--muted); font-size:.75rem; }
        .unread { width:8px; height:8px; background:#25d366; border-radius:999px; }
        .content { flex:1; display:flex; flex-direction:column; }
        .content .header { display:flex; justify-content:space-between; align-items:center; padding:.6rem .8rem; border-bottom:1px solid var(--border); background: var(--panel); }
        .bubbles { flex:1; overflow:auto; padding: .8rem; background: url('data:image/svg+xml,%3Csvg width%3D%2240%22 height%3D%2240%22 viewBox%3D%220 0 40 40%22 xmlns%3D%22http%3A//www.w3.org/2000/svg%22%3E%3Cg fill%3D%22%2323313a%22 fill-opacity%3D%220.15%22%3E%3Cpath d%3D%22M0 39h1v1H0zM39 0h1v1h-1z%22/%3E%3C/g%3E%3C/svg%3E') repeat; }
        .message { max-width: 70%; padding:.5rem .7rem; margin:.35rem 0; border-radius:8px; position:relative; }
        .left { margin-right:auto; background: var(--bubble-in); color:#fff; }
        .right { margin-left:auto; background: var(--bubble-out); color: var(--app-fg); }
        .center { margin: .5rem auto; background: transparent; }
        .chat-title { margin:0; }
        .actions button { margin-left:.25rem; }
        .hidden { display:none; }
        .dropzone { border:2px dashed var(--border); border-radius:8px; padding:1rem; text-align:center; color: var(--muted); background: var(--panel); }
        .dropzone.dragover { border-color: var(--accent); color: var(--app-fg); }
        .wizard { position:fixed; inset:0; background: rgba(0,0,0,.5); display:none; align-items:center; justify-content:center; }
        .wizard .card { width:min(520px, 92vw); background: var(--panel-2); border:1px solid var(--border); border-radius:8px; padding:1rem; }
        .progress { width:100%; height:6px; background: var(--border); border-radius:999px; overflow:hidden; }
        .progress > div { height:100%; width:0%; background: var(--accent); }
        @media (max-width: 800px) { .sidebar { width: 42%; } }
        @media (max-width: 640px) { .app { flex-direction:column; } .sidebar { width:100%; min-height: 40vh; } }
    </style>
</head>
<body class="light">
<header class="brand">
    <h1>WhatsApp Backup Viewer</h1>
    <div style="margin-top:.5rem;">
        <button id="themeToggle" aria-label="Toggle theme">Toggle Light/Dark</button>
        <button id="openImport" aria-label="Import chats">Import Chats</button>
    </div>
</header>
<main class="app">
    <aside class="sidebar" aria-label="Chat list">
        <div class="top">
            <input type="text" id="searchChats" placeholder="Search or start new chat" aria-label="Search chats">
        </div>
        <div id="chatList" class="chat-list" role="list"></div>
    </aside>
    <section class="content" aria-label="Conversation">
        <div class="header">
            <div>
                <h3 id="currentChatName" class="chat-title">Select a chat</h3>
                <div class="time" id="currentChatTime"></div>
            </div>
            <div class="actions">
                <button id="btnExport" class="hidden">Export</button>
                <button id="btnArchive" class="hidden">Archive</button>
                <button id="btnDelete" class="hidden">Delete</button>
            </div>
        </div>
        <div id="bubbles" class="bubbles"></div>
        <div style="padding:.4rem; border-top:1px solid var(--border); display:flex; gap:.5rem;">
            <input type="text" id="searchMessages" placeholder="Search messages in chat" style="flex:1; padding:.5rem; border-radius:8px; border:1px solid var(--border); background: var(--panel); color: var(--app-fg);">
        </div>
    </section>
</main>
<footer>
    &copy; 2020-2025 All Right Reserved.
    <br>
    Designed with <span class="heart">♥</span> by <a href="mailto:itxshakil@gmail.com" class="text-primary">Shakil Alam</a>
</footer>

<!-- Import wizard -->
<div id="wizard" class="wizard" aria-modal="true" role="dialog" aria-labelledby="wizTitle">
  <div class="card">
    <h3 id="wizTitle">Import Chats</h3>
    <ol style="margin:.5rem 0; color: var(--muted);">
      <li>Drop your WhatsApp ZIP backup or click to select</li>
      <li>Wait for upload and extraction</li>
      <li>Browse chats from the sidebar</li>
    </ol>
    <div id="dropzone" class="dropzone" tabindex="0">Drop ZIP here or click to choose</div>
    <input type="file" id="fileInput" accept=".zip" class="hidden">
    <div class="progress" style="margin-top:.8rem;"><div id="progBar"></div></div>
    <div id="wizError" class="alert" style="display:none;"></div>
    <div style="margin-top:.8rem; display:flex; justify-content:flex-end; gap:.5rem;">
      <button id="closeWizard">Close</button>
    </div>
  </div>
</div>

<script>
(function(){
  const chatListEl = document.getElementById('chatList');
  const searchChatsEl = document.getElementById('searchChats');
  const searchMessagesEl = document.getElementById('searchMessages');
  const bubblesEl = document.getElementById('bubbles');
  const nameEl = document.getElementById('currentChatName');
  const btnDelete = document.getElementById('btnDelete');
  const btnArchive = document.getElementById('btnArchive');
  const btnExport = document.getElementById('btnExport');
  const wizard = document.getElementById('wizard');
  const openImport = document.getElementById('openImport');
  const closeWizard = document.getElementById('closeWizard');
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileInput');
  const progBar = document.getElementById('progBar');
  const wizError = document.getElementById('wizError');
  const themeToggle = document.getElementById('themeToggle');

  let chats = [];
  let currentFile = null;

  function fmtTime(ts){ if(!ts) return ''; const d = new Date(ts*1000); return d.toLocaleString(); }
  function storageKey(file){ return 'lastViewed:' + file; }
  function loadChats(){
    fetch('?action=list-chats').then(r=>r.json()).then(data=>{
      chats = data.chats || [];
      renderChatList();
    });
  }
  function renderChatList(){
    const q = (searchChatsEl.value||'').toLowerCase();
    chatListEl.innerHTML='';
    chats.filter(c => c.name.toLowerCase().includes(q) || c.preview.toLowerCase().includes(q)).forEach(c =>{
      const lastViewed = parseInt(localStorage.getItem(storageKey(c.file) )) || 0;
      const unread = c.mtime > lastViewed;
      const item = document.createElement('div');
      item.className='chat-item';
      item.setAttribute('role','listitem');
      item.innerHTML = `
        <div class="avatar" aria-hidden="true"></div>
        <div class="meta">
          <div style="display:flex; justify-content:space-between; gap:.5rem;">
            <div class="name">${c.name}</div>
            <div class="time">${new Date(c.mtime*1000).toLocaleDateString()}</div>
          </div>
          <div class="preview">${c.preview || ''}</div>
        </div>
        ${unread?'<div class="unread" title="Unread"></div>':''}
      `;
      item.addEventListener('click', ()=> selectChat(c));
      chatListEl.appendChild(item);
    });
  }
  function selectChat(c){
    currentFile = c.file;
    nameEl.textContent = c.name;
    document.getElementById('currentChatTime').textContent = 'Last message: ' + fmtTime(c.mtime);
    btnDelete.classList.remove('hidden');
    btnArchive.classList.remove('hidden');
    btnExport.classList.remove('hidden');
    btnExport.onclick = ()=> window.open('extracted/' + encodeURI(c.file), '_blank');
    fetch(`?action=load-chat&file=${encodeURIComponent(c.file)}`)
      .then(r=>r.text()).then(html=>{
        bubblesEl.innerHTML = html;
        // mark as viewed
        localStorage.setItem(storageKey(c.file), Math.floor(Date.now()/1000));
        renderChatList();
      })
  }
  searchChatsEl.addEventListener('input', renderChatList);
  searchMessagesEl.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase();
    Array.from(bubblesEl.querySelectorAll('.message')).forEach(m=>{
      const text = m.textContent.toLowerCase();
      m.style.display = text.includes(q)?'':'none';
    });
  });

  // Management
  btnDelete.addEventListener('click', ()=>{
    if(!currentFile) return; if(!confirm('Delete this chat?')) return;
    fetch('?action=delete-chat', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'file='+encodeURIComponent(currentFile)})
      .then(r=>r.json()).then(()=>{ currentFile=null; bubblesEl.innerHTML=''; nameEl.textContent='Select a chat'; loadChats(); });
  });
  btnArchive.addEventListener('click', ()=>{
    if(!currentFile) return;
    fetch('?action=archive-chat', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'file='+encodeURIComponent(currentFile)})
      .then(r=>r.json()).then(()=>{ currentFile=null; bubblesEl.innerHTML=''; nameEl.textContent='Select a chat'; loadChats(); });
  });

  // Theme toggle
  function applyTheme(){
    const t = localStorage.getItem('theme') || 'light';
    document.body.className = t;
  }
  themeToggle.addEventListener('click', ()=>{
    const t = (localStorage.getItem('theme')||'light') === 'light' ? 'dark' : 'light';
    localStorage.setItem('theme', t);
    applyTheme();
  });
  applyTheme();

  // Import wizard
  openImport.addEventListener('click', ()=>{ wizard.style.display='flex'; progBar.style.width='0%'; wizError.style.display='none'; });
  closeWizard.addEventListener('click', ()=>{ wizard.style.display='none'; });
  function startUpload(file){
    wizError.style.display='none'; progBar.style.width='0%';
    const xhr = new XMLHttpRequest();
    const form = new FormData();
    form.append('fupload', file);
    form.append('ajax','1');
    xhr.upload.onprogress = (e)=>{ if(e.lengthComputable){ progBar.style.width = ((e.loaded/e.total)*100).toFixed(0)+'%'; } };
    xhr.onreadystatechange = ()=>{
      if(xhr.readyState===4){ if(xhr.status===200){ try{ const res = JSON.parse(xhr.responseText); if(res.success){ loadChats(); wizard.style.display='none'; } else { throw new Error('Upload failed'); } } catch(err){ wizError.textContent= 'Import failed'; wizError.style.display='block'; } } else { wizError.textContent='Network error'; wizError.style.display='block'; } }
    };
    xhr.open('POST', window.location.href);
    xhr.send(form);
  }
  function handleFiles(fs){ if(!fs || fs.length===0) return; const file = fs[0]; if(!/\.zip$/i.test(file.name)){ wizError.textContent='Please choose a ZIP file'; wizError.style.display='block'; return; } startUpload(file); }
  dropzone.addEventListener('click', ()=> fileInput.click());
  fileInput.addEventListener('change', ()=> handleFiles(fileInput.files));
  dropzone.addEventListener('dragover', (e)=>{ e.preventDefault(); dropzone.classList.add('dragover'); });
  dropzone.addEventListener('dragleave', ()=> dropzone.classList.remove('dragover'));
  dropzone.addEventListener('drop', (e)=>{ e.preventDefault(); dropzone.classList.remove('dragover'); handleFiles(e.dataTransfer.files); });

  // Initial load
  loadChats();
})();
</script>
</body>
</html>