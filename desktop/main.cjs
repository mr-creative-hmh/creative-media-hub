const { app, BrowserWindow, shell, dialog } = require('electron');
const path = require('path');
const { spawn, exec } = require('child_process');
const http = require('http');

let mainWindow = null;
let phpProcess = null;
const SERVER_PORT = 8088;
const SERVER_HOST = '127.0.0.1';
const SERVER_URL = `http://${SERVER_HOST}:${SERVER_PORT}`;

// Locate PHP binary (portable or system)
function getPhpBinary() {
    const rootDir = path.resolve(__dirname, '..');
    const portablePhp = path.join(rootDir, 'php', 'php.exe');
    const fs = require('fs');
    if (fs.existsSync(portablePhp)) {
        return portablePhp;
    }
    return 'php';
}

// Check if PHP server is responding
function checkServerReady(retries = 30, delay = 500) {
    return new Promise((resolve) => {
        let attempts = 0;
        const interval = setInterval(() => {
            attempts++;
            const req = http.get(SERVER_URL, (res) => {
                clearInterval(interval);
                resolve(true);
            });

            req.on('error', () => {
                if (attempts >= retries) {
                    clearInterval(interval);
                    resolve(false);
                }
            });

            req.end();
        }, delay);
    });
}

// Start background PHP server
function startPhpServer() {
    return new Promise((resolve) => {
        const rootDir = path.resolve(__dirname, '..');
        const phpBin = getPhpBinary();

        console.log(`[*] Starting background PHP server with: ${phpBin}`);

        phpProcess = spawn(phpBin, [
            'artisan',
            'serve',
            `--host=${SERVER_HOST}`,
            `--port=${SERVER_PORT}`
        ], {
            cwd: rootDir,
            windowsHide: true,
            stdio: 'pipe',
            shell: true,
        });

        phpProcess.on('close', (code) => {
            console.log(`[PHP] Server process exited with code ${code}`);
        });

        resolve();
    });
}

// Create Electron Desktop Window
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
            webSecurity: true,
            autoplayPolicy: 'no-user-gesture-required',
        },
        autoHideMenuBar: true,
        show: false,
    });

    mainWindow.maximize();
    mainWindow.show();

    // Check if server is already running
    const isReady = await checkServerReady(3, 300);
    if (!isReady) {
        await startPhpServer();
        const serverStarted = await checkServerReady(35, 400);
        if (!serverStarted) {
            dialog.showErrorBox(
                'Creative Media Hub - Server Error',
                'Failed to start the local backend server. Please ensure PHP is installed or bundled in the portable php/ folder.'
            );
        }
    }

    // Load Creative Media Hub URL
    mainWindow.loadURL(SERVER_URL);

    // Open external links in default browser
    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (!url.startsWith(SERVER_URL)) {
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
