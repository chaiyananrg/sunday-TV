<?php
// =================================================================
// SECTION 1: PHP SERVER-SIDE LOGIC
// =================================================================
session_start();

// ตรวจสอบว่ามีโฟลเดอร์ vendor หรือไม่
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("<h1>เกิดข้อผิดพลาดร้ายแรง</h1><p>ไม่พบไฟล์ vendor/autoload.php กรุณารันคำสั่ง 'composer install' ใน Terminal ก่อน</p>");
}
require __DIR__ . '/vendor/autoload.php';

use LiveKit\AccessToken;
use LiveKit\VideoGrant;

// --- API Endpoint Simulation ---
// ถ้ามีการร้องขอ Token ให้สร้างและส่งกลับไป แล้วหยุดการทำงาน
if (isset($_GET['action']) && $_GET['action'] === 'generate-token') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['username'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    $roomName = isset($_GET['room']) ? $_GET['room'] : 'default-room';
    $participantName = $_SESSION['username'];
    
    $apiKey = 'APIG6ZjPHKfdoPq';
    $apiSecret = 'b6SMZCXSjgE2ee0XfkZi870J4c8Gt2cdqViAlKff2gPE';

    $token = new AccessToken($apiKey, $apiSecret, ['identity' => $participantName]);
    $grant = new VideoGrant();
    $grant->setRoomJoin(true)
          ->setRoom($roomName)
          ->setCanPublish(true)
          ->setCanSubscribe(true)
          ->setCanPublishData(true);
    $token->addGrant($grant);

    echo json_encode(['token' => $token->toJwt()]);
    exit();
}

// --- Login Form Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim(htmlspecialchars($_POST['username']));
    if (!empty($username)) {
        $_SESSION['username'] = $username;
        $_SESSION['coins'] = 100;
        header('Location: index.php');
        exit();
    }
}

// =================================================================
// SECTION 2: HTML DOCUMENT STRUCTURE
// =================================================================
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunday Live - All-in-One</title>
    
    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- CSS Styles -->
    <style>
        :root {
            --sunrise-start: #ffc371; --sunrise-end: #ff5f6d;
            --sky-blue: #89f7fe; --sky-end: #66a6ff;
            --text-dark: #2c3e50; --text-light: #fdfdfd;
            --card-bg: rgba(255, 255, 255, 0.85);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --radius: 16px;
        }
        body {
            font-family: 'Kanit', sans-serif; margin: 0;
            background: linear-gradient(135deg, var(--sky-blue), var(--sky-end));
            color: var(--text-dark); overflow: hidden;
        }
        .page { position: absolute; top: 0; left: 0; width: 100%; height: 100%; box-sizing: border-box; padding: 24px; opacity: 0; visibility: hidden; transition: opacity 0.4s ease, visibility 0.4s ease; }
        .page.active { opacity: 1; visibility: visible; z-index: 10; }
        #login-page { display: flex; justify-content: center; align-items: center; }
        .login-box { background: var(--card-bg); backdrop-filter: blur(10px); padding: 40px 50px; border-radius: var(--radius); box-shadow: var(--shadow); text-align: center; animation: fadeIn 0.8s ease-out; }
        .login-box h1 { font-size: 3rem; font-weight: 700; background: -webkit-linear-gradient(45deg, var(--sunrise-end), #66a6ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px; }
        #displayNameInput { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 10px; margin-top: 20px; font-size: 1rem; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border: none; border-radius: 12px; cursor: pointer; font-size: 1rem; font-weight: 500; transition: all 0.3s ease; margin-top: 20px; }
        .btn-primary { background: linear-gradient(45deg, var(--sunrise-end), var(--sunrise-start)); color: var(--text-light); }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .btn-success { background: linear-gradient(45deg, #2ed573, #1e90ff); color: white; }
        
        #home-page header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        #home-page .header-card { background: var(--card-bg); backdrop-filter: blur(5px); padding: 12px 20px; border-radius: var(--radius); box-shadow: var(--shadow); }
        #coin-area input { padding: 8px; border-radius: 8px; border: 1px solid #ccc; width: 80px; }
        .live-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; margin-top: 30px; }
        .live-card { background: white; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); cursor: pointer; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .live-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
        .live-card .thumbnail { width: 100%; height: 180px; background: linear-gradient(45deg, #eee, #ddd); display: flex; justify-content: center; align-items: center; }
        .live-card-info { padding: 15px; }
        .live-card-info h4 { margin: 0 0 5px 0; }
        
        #live-room-page { background: #111; padding: 0; top: 100%; transition: top 0.5s cubic-bezier(0.77, 0, 0.175, 1); }
        #live-room-page.active { top: 0; z-index: 20; }
        .live-room-header { position: absolute; top: 0; left: 0; width: 100%; padding: 20px; box-sizing: border-box; z-index: 30; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(180deg, rgba(0,0,0,0.5), transparent); }
        .live-room-header h3 { color: white; margin: 0; display: flex; align-items: center; gap: 10px; }
        .live-badge { background-color: #ff4757; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.9rem; font-weight: 500; animation: pulse 1.5s infinite; }
        .video-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .video-container video { width: 100%; height: 100%; object-fit: cover; }
        .live-overlay { position: absolute; bottom: 0; left: 0; width: 100%; padding: 20px; box-sizing: border-box; z-index: 30; display: flex; flex-direction: column; gap: 10px; background: linear-gradient(0deg, rgba(0,0,0,0.5), transparent); }
        .chat-box { color: white; height: 200px; overflow-y: auto; text-shadow: 1px 1px 2px black; }
        .chat-message { margin-bottom: 8px; }
        .chat-message span { font-weight: bold; color: var(--sunrise-start); }
        .chat-input-area { display: flex; gap: 10px; }
        #chat-input { flex-grow: 1; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); border-radius: 10px; padding: 10px; color: white; outline: none; }
        .main-controls { display: flex; justify-content: space-between; align-items: center; }
        .gift-shop { display: flex; gap: 15px; }
        .icon-btn { background: rgba(255,255,255,0.2); border: none; border-radius: 50%; width: 50px; height: 50px; display: flex; justify-content: center; align-items: center; cursor: pointer; transition: background 0.3s; color: white; flex-shrink: 0; }
        .icon-btn:hover { background: rgba(255,255,255,0.4); }
        
        #streamer-controls { display: none; gap: 15px; }
        #streamer-controls.active { display: flex; }
        
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(255, 71, 87, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); } }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['username'])): ?>
    <!-- ถ้ายังไม่ล็อกอิน ให้แสดงหน้าล็อกอิน -->
    <div id="login-page" class="page active">
        <div class="login-box">
            <h1>Sunday Live</h1>
            <p>ไลฟ์วันสบายๆ สไตล์คุณ</p>
            <form action="index.php" method="POST">
                <input type="text" name="username" id="displayNameInput" placeholder="กรอกชื่อของคุณ..." required>
                <button type="submit" class="btn btn-primary"><i data-lucide="log-in"></i><span>เข้าสู่ระบบ</span></button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- ถ้าล็อกอินแล้ว ให้แสดงแอปพลิเคชันหลัก -->
    <div id="home-page" class="page active">
        <header>
            <div id="userInfo" class="header-card"><h3>สวัสดี, <?php echo $_SESSION['username']; ?>!</h3></div>
            <div id="coin-area" class="header-card">
                <i data-lucide="coins"></i>
                <span>ยอดเหรียญ: <span id="coinBalance"><?php echo $_SESSION['coins']; ?></span></span>
                <input type="number" id="rechargeAmount" placeholder="จำนวนเงิน">
                <button id="recharge-btn" class="btn">เติม</button>
            </div>
        </header>
        <div class="home-actions">
            <button id="start-live-btn" class="btn btn-success"><i data-lucide="video"></i><span>เริ่มไลฟ์สด</span></button>
        </div>
        <h2 style="margin-top: 30px;">ช่องที่กำลังไลฟ์สด</h2>
        <div class="live-grid" id="live-grid">
             <div id="no-live-streams" style="text-align: center; grid-column: 1 / -1; padding: 20px;">ยังไม่มีใครไลฟ์สดเลย... คุณเป็นคนแรกสิ!</div>
        </div>
    </div>

    <div id="live-room-page" class="page">
        <div class="live-room-header">
            <h3 id="live-room-title"></h3>
            <button id="leave-room-btn" class="icon-btn"><i data-lucide="x"></i></button>
        </div>
        <div class="video-container" id="video-container">[ กำลังโหลดวิดีโอ... ]</div>
        <div class="live-overlay">
            <div class="chat-box" id="chat-box"></div>
            <div class="chat-input-area">
                <input type="text" id="chat-input" placeholder="พิมพ์ข้อความ...">
                <button id="send-chat-btn" class="icon-btn"><i data-lucide="send"></i></button>
            </div>
            <div class="main-controls">
                <div id="streamer-controls">
                    <button id="mic-btn" class="icon-btn"><i id="mic-icon" data-lucide="mic"></i></button>
                    <button id="cam-btn" class="icon-btn"><i id="cam-icon" data-lucide="video"></i></button>
                </div>
                <div class="gift-shop">
                    <button class="icon-btn" data-gift="heart" data-cost="1"><i data-lucide="heart"></i></button>
                    <button class="icon-btn" data-gift="sun" data-cost="99"><i data-lucide="sun"></i></button>
                    <button class="icon-btn" data-gift="rocket" data-cost="500"><i data-lucide="rocket"></i></button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- =================================================================
SECTION 3: JAVASCRIPT CLIENT-SIDE LOGIC
================================================================= -->
<script>
    const App = {
        // --- Configuration and State ---
        LIVEKIT_URL: 'wss://sunday-group-cy6t1e76.livekit.cloud',
        PROFANITY_LIST: ['เหี้ย', 'สัส', 'ควย', 'หี', 'เย็ด', 'ไอ้สัส', 'อีดอก'],
        
        // รับค่าจาก PHP Session
        currentUsername: <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>,
        coinBalance: <?php echo isset($_SESSION['coins']) ? $_SESSION['coins'] : 0; ?>,
        
        currentRoom: null,
        liveRooms: [],
        currentLiveIndex: -1,
        isSwitching: false,
        streamerWarnings: 0,
        isBlocked: false,
        textEncoder: new TextEncoder(),
        textDecoder: new TextDecoder(),

        elements: {},

        async init() {
            // รอให้ libraries พร้อมใช้งาน
            if (typeof window.livekit === 'undefined' || typeof window.lucide === 'undefined') {
                console.error("Libraries not loaded yet, retrying...");
                setTimeout(() => this.init(), 100);
                return;
            }
            
            // ถ้าไม่ได้ล็อกอิน ไม่ต้องทำอะไรต่อ
            if (!this.currentUsername) {
                lucide.createIcons();
                return;
            }

            this.cacheDOMElements();
            this.attachEventListeners();
            lucide.createIcons();
            this.updateCoinDisplay();
        },

        cacheDOMElements() {
            this.elements.pages = document.querySelectorAll('.page');
            this.elements.usernameDisplay = document.getElementById('usernameDisplay');
            this.elements.coinBalanceDisplay = document.getElementById('coinBalance');
            this.elements.chatBox = document.getElementById('chat-box');
            this.elements.liveRoomTitle = document.getElementById('live-room-title');
            this.elements.videoContainer = document.getElementById('video-container');
            this.elements.liveGrid = document.getElementById('live-grid');
            this.elements.streamerControls = document.getElementById('streamer-controls');
            this.elements.chatInput = document.getElementById('chat-input');
        },

        attachEventListeners() {
            document.getElementById('recharge-btn').addEventListener('click', () => this.rechargeCoins());
            document.getElementById('start-live-btn').addEventListener('click', () => this.startMyOwnLive());
            document.getElementById('leave-room-btn').addEventListener('click', () => this.leaveLiveRoom(true));
            document.getElementById('live-room-page').addEventListener('wheel', (e) => this.handleLiveScroll(e));
            document.getElementById('mic-btn').addEventListener('click', () => this.toggleMic());
            document.getElementById('cam-btn').addEventListener('click', () => this.toggleCam());
            document.getElementById('send-chat-btn').addEventListener('click', () => this.sendChatMessage());
            this.elements.chatInput.addEventListener('keyup', e => { if (e.key === 'Enter') this.sendChatMessage(); });
            document.querySelectorAll('.gift-shop .icon-btn[data-gift]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const gift = btn.dataset.gift;
                    const cost = parseInt(btn.dataset.cost, 10);
                    this.sendGift(gift, cost);
                });
            });
        },

        showPage(pageId) {
            this.elements.pages.forEach(page => page.classList.remove('active'));
            const targetPage = document.getElementById(pageId);
            if(targetPage) targetPage.classList.add('active');
        },

        updateCoinDisplay() {
            if (this.elements.coinBalanceDisplay) this.elements.coinBalanceDisplay.textContent = this.coinBalance;
        },

        rechargeCoins() { /* ...เหมือนเดิม... */ },
        containsProfanity(text) { /* ...เหมือนเดิม... */ },

        async generateToken(roomName) {
            try {
                const response = await fetch(`index.php?action=generate-token&room=${encodeURIComponent(roomName)}`);
                if (!response.ok) throw new Error('Failed to fetch token from server.');
                const data = await response.json();
                return data.token;
            } catch (error) {
                console.error(error);
                Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถขอ Token สำหรับเข้าห้องไลฟ์ได้', 'error');
                return null;
            }
        },

        async connectToLiveKit(roomName, isStreamer) {
            if (this.currentRoom) await this.leaveLiveRoom(false);
            
            const { Room, RoomEvent } = window.livekit;
            const token = await this.generateToken(roomName);
            if (!token) return;

            this.currentRoom = new Room();
            // ... (โค้ดส่วนที่เหลือใน connectToLiveKit เหมือนเดิมทุกประการ) ...
        },

        // ... (ฟังก์ชันที่เหลือทั้งหมดเหมือนเดิมทุกประการ) ...
        
        async startMyOwnLive() { /* ... */ },
        joinLive(roomName) { /* ... */ },
        async leaveLiveRoom(switchToHome = true) { /* ... */ },
        addNewLiveCard(roomName, streamerName) { /* ... */ },
        renderLiveCards() { /* ... */ },
        handleLiveScroll(event) { /* ... */ },
        sendChatMessage() { /* ... */ },
        appendChatMessage(user, message) { /* ... */ },
        sendGift(giftType, cost) { /* ... */ },
        toggleMic() { /* ... */ },
        toggleCam() { /* ... */ },

    };

    // เริ่มการทำงานของ App
    document.addEventListener('DOMContentLoaded', () => {
        App.init();
    });
</script>

</body>
</html>
