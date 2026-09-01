const { app, BrowserWindow, shell, dialog, ipcMain } = require('electron');
const path = require('path');
const { spawn, exec } = require('child_process');
const http = require('http');
const https = require('https');
const fs = require('fs');

let mainWindow = null;
let phpProcess = null;
let activeTargetUrl = null;

const CANDIDATE_URLS = [
    'https://creative-media-hub.test',
    'http://127.0.0.1:8000',
    'http://127.0.0.1:8088',
    'http://localhost:8000',
    'http://localhost:8088',
];

// Configure Electron switches for seamless local development
app.commandLine.appendSwitch('ignore-certificate-errors');
app.commandLine.appendSwitch('allow-insecure-localhost');
app.commandLine.appendSwitch('autoplay-policy', 'no-user-gesture-required');
app.commandLine.appendSwitch('enable-gpu-rasterization');
app.commandLine.appendSwitch('enable-zero-copy');

// Handle certificate errors gracefully for local Herd / test domains
app.on('certificate-error', (event, webContents, url, error, certificate, callback) => {
    if (url.includes('.test') || url.includes('127.0.0.1') || url.includes('localhost')) {
        event.preventDefault();
        callback(true);
    } else {
        callback(false);
    }
});

// Check if a specific URL is responding
function pingUrl(url, timeout = 1200) {
    return new Promise((resolve) => {
        try {
            const client = url.startsWith('https') ? https : http;
            const req = client.get(url, { rejectUnauthorized: false, timeout }, (res) => {
                resolve(res.statusCode >= 200 && res.statusCode < 500);
            });
            req.on('error', () => resolve(false));
            req.on('timeout', () => {
                req.destroy();
                resolve(false);
            });
        } catch (e) {
            resolve(false);
        }
    });
}

// Find an active running server
async function findActiveServer() {
    for (const u of CANDIDATE_URLS) {
        const ok = await pingUrl(u, 1000);
        if (ok) {
            console.log(`[Electron] Connected to active server: ${u}`);
            return u;
        }
    }
    return null;
}

// Locate Project Root Directory containing artisan
function getProjectRootDir() {
    const candidates = [
        process.cwd(),
        path.resolve(__dirname, '..'),
        path.dirname(app.getPath('exe')),
        path.resolve(path.dirname(app.getPath('exe')), '..'),
        'C:\\Users\\hasan\\Herd\\creative-media-hub',
    ];

    for (const dir of candidates) {
        if (fs.existsSync(path.join(dir, 'artisan'))) {
            return dir;
        }
    }
    return null;
}

// Locate PHP binary
function getPhpBinary(rootDir) {
    if (rootDir) {
        const portablePhp = path.join(rootDir, 'php', 'php.exe');
        if (fs.existsSync(portablePhp)) {
            return portablePhp;
        }
    }

    const herdPhp = 'C:\\Users\\hasan\\.config\\herd\\bin\\php.BAT';
    if (fs.existsSync(herdPhp)) {
        return herdPhp;
    }

    return 'php';
}

// Start background PHP server
async function launchBackgroundServer() {
    const rootDir = getProjectRootDir();
    if (!rootDir) {
        console.warn('[Electron] Could not locate project root with artisan');
        return false;
    }

    const phpBin = getPhpBinary(rootDir);
    console.log(`[Electron] Spawning PHP server from ${rootDir} using ${phpBin}`);

    try {
        phpProcess = spawn(phpBin, [
            'artisan',
            'serve',
            '--host=127.0.0.1',
            '--port=8088'
        ], {
            cwd: rootDir,
            windowsHide: true,
            stdio: 'ignore',
            shell: true,
        });

        // Wait up to 10 seconds for port 8088 to respond
        for (let i = 0; i < 20; i++) {
            await new Promise(r => setTimeout(r, 500));
            const ok = await pingUrl('http://127.0.0.1:8088', 800);
            if (ok) {
                return 'http://127.0.0.1:8088';
            }
        }
    } catch (e) {
        console.error('[Electron] Error spawning PHP server:', e);
    }

    return false;
}

// Fallback error / connection HTML screen
function getConnectingHtml(message) {
    return `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Creative Media Hub - Connecting</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background-color: #07090E;
        color: #F8FAFC;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100vh;
        text-align: center;
        padding: 24px;
    }
    .card {
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(6, 182, 212, 0.2);
        padding: 40px;
        border-radius: 20px;
        max-width: 520px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.6);
    }
    .spinner {
        width: 52px;
        height: 52px;
        border: 4px solid rgba(6, 182, 212, 0.2);
        border-top-color: #06B6D4;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 24px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    h1 { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #38BDF8; }
    p { font-size: 14px; color: #94A3B8; line-height: 1.6; margin-bottom: 24px; }
    .btn {
        background: linear-gradient(135deg, #06B6D4, #3B82F6);
        color: white;
        border: none;
        padding: 12px 28px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn:hover { opacity: 0.9; transform: scale(1.02); }
</style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h1>Creative Media Hub</h1>
        <p>${message || 'Establishing connection with the local streaming engine...'}</p>
        <button class="btn" onclick="window.location.reload()">Retry Connection</button>
    </div>
</body>
</html>`;
}

// Create Electron Window
async function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1440,
        height: 900,
        minWidth: 1024,
        minHeight: 680,
        backgroundColor: '#07090E',
        title: 'Creative Media Hub',
        icon: path.join(__dirname, '..', 'public', 'favicon.ico'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            webSecurity: false,
            autoplayPolicy: 'no-user-gesture-required',
        },
        autoHideMenuBar: true,
        show: false,
    });

    mainWindow.maximize();
    mainWindow.show();

    // Step 1: Check if any server is already running (e.g. Herd or artisan)
    let target = await findActiveServer();

    // Step 2: If no server running, attempt to spawn background artisan serve
    if (!target) {
        mainWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(getConnectingHtml('Starting local cinema streaming server in background...')));
        const spawnedUrl = await launchBackgroundServer();
        if (spawnedUrl) {
            target = spawnedUrl;
        } else {
            // One last check across all candidates
            target = await findActiveServer();
        }
    }

    // Step 3: Load target or show informative recovery page
    if (target) {
        activeTargetUrl = target;
        console.log(`[Electron] Successfully loading: ${activeTargetUrl}`);
        mainWindow.loadURL(activeTargetUrl);
    } else {
        mainWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(
            getConnectingHtml('Could not detect a running backend server. Please run <code>Start-CreativeMediaHub.bat</code> or start Laravel Herd, then click Retry.')
        ));
    }

    // Handle external links
    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (activeTargetUrl && !url.startsWith(activeTargetUrl)) {
            shell.openExternal(url);
            return { action: 'deny' };
        }
        return { action: 'allow' };
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

function cleanup() {
    if (phpProcess) {
        try {
            if (process.platform === 'win32') {
                exec(`taskkill /F /T /PID ${phpProcess.pid}`, () => {});
            } else {
                phpProcess.kill();
            }
        } catch (e) {}
    }
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    cleanup();
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('will-quit', cleanup);
app.on('before-quit', cleanup);
