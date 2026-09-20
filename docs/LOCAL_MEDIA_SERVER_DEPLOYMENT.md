# Creative Media Hub — Local Network Deployment Guide

A complete step-by-step guide for hosting **Creative Media Hub** on a dedicated local machine (Windows PC, mini PC, Intel NUC, or home server) so that any laptop, iPhone, iPad, Android phone, tablet, or Smart TV on the same Wi-Fi or LAN network can access and stream movies and series smoothly.

---

## Architecture Overview

```
                         [ Home Wi-Fi / Router ]
                                    │
         ┌──────────────────────────┼──────────────────────────┐
         ▼                          ▼                          ▼
  [ iPhone / iPad ]        [ Android / Tablet ]       [ Laptop / Smart TV ]
  http://192.168.1.50:8088 http://192.168.1.50:8088   http://192.168.1.50:8088
         │                          │                          │
         └──────────────────────────┼──────────────────────────┘
                                    ▼
               ┌────────────────────────────────────────┐
               │        CREATIVE MEDIA HUB (PC)         │
               │         Host IP: 192.168.1.50          │
               │                                        │
               │  • Windows Firewall: Port 8088 Open    │
               │  • Host Binding: 0.0.0.0:8088          │
               │  • Built Assets: public/build/         │
               │  • Database: SQLite (database.sqlite)  │
               │  • Media Drives: Local HDDs / NAS / SMB│
               └────────────────────────────────────────┘
```

---

## Step 1: Assign a Static Local IP (or DHCP Reservation)

To make sure your server's IP never changes after a router restart:

1. **Find your server's current local IP**:
   Open PowerShell on the server machine and run:
   ```powershell
   ipconfig
   ```
   Look for the `IPv4 Address` under your active Wi-Fi or Ethernet adapter (e.g. `192.168.1.50` or `10.0.0.15`).

2. **(Recommended) Reserve the IP in your router**:
   - Access your home router's admin page (usually `http://192.168.1.1` or `http://192.168.0.1`).
   - Navigate to **DHCP Settings / Address Reservation**.
   - Bind your server machine's MAC address to its current local IP (e.g. `192.168.1.50`).

---

## Step 2: Open Port 8088 in Windows Defender Firewall

By default, Windows blocks incoming connections from other network devices. Allow port `8088` through the firewall:

1. Open **PowerShell as Administrator** (Right-click Start Menu -> *Terminal (Admin)* or *PowerShell (Admin)*).
2. Run this command:
   ```powershell
   New-NetFirewallRule -DisplayName "Creative Media Hub (LAN Port 8088)" -Direction Inbound -LocalPort 8088 -Protocol TCP -Action Allow
   ```
3. To verify:
   ```powershell
   Get-NetFirewallRule -DisplayName "Creative Media Hub*" | Select-Object DisplayName, Direction, Action, Enabled
   ```

---

## Step 3: Configure `.env` on Creative Media Hub

In your application directory (`C:\Users\hasan\Herd\creative-media-hub\.env`), set the following:

```ini
APP_NAME="Creative Media Hub"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.50:8088

# Database (SQLite is portable and fast)
DB_CONNECTION=sqlite

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

> **Note**: Replace `192.168.1.50` with your actual server IP from Step 1.

---

## Step 4: Build Optimized Frontend Assets for Production

Running `npm run build` bundles all Vue components, styles, and scripts into `public/build/`. Client devices will receive high-speed static assets directly from PHP without needing Node.js or Vite dev server running in the background.

```powershell
cd C:\Users\hasan\Herd\creative-media-hub
npm run build
```

---

## Step 5: Launch Creative Media Hub for LAN Access

The project includes `Start-CreativeMediaHub.bat` configured to bind to `0.0.0.0:8088`:

```cmd
set "PORT=8088"
set "HOST=0.0.0.0"
php artisan serve --host=0.0.0.0 --port=8088
```

Simply double-click `Start-CreativeMediaHub.bat` on the server machine.
You will see:
```text
[*] Starting Creative Media Hub for Local Network on port 8088 ...
[*] Access locally at: http://localhost:8088
[*] Access from phone/tablet/TV at: http://<your-lan-ip>:8088
```

---

## Step 6: Configure Autostart on Server Boot (Headless Operation)

To have Creative Media Hub start automatically when the computer boots up (without needing to manually log in and click the batch file):

### Method A: Windows Startup Folder (Easiest)
1. Press `Win + R`, type `shell:startup`, and press Enter.
2. Right-click inside the folder -> **New** -> **Shortcut**.
3. Browse to `C:\Users\hasan\Herd\creative-media-hub\Start-CreativeMediaHub.bat`.
4. Click **Next** -> **Finish**.

### Method B: Windows Task Scheduler (Runs in Background)
1. Press `Win + R`, type `taskschd.msc`, and press Enter.
2. Click **Create Basic Task**.
   - **Name**: `Creative Media Hub`
   - **Trigger**: `When the computer starts`
   - **Action**: `Start a program`
   - **Program/script**: `C:\Users\hasan\Herd\creative-media-hub\Start-CreativeMediaHub.bat`
   - **Start in**: `C:\Users\hasan\Herd\creative-media-hub\`
3. Check **Open the Properties dialog** and select **Run whether user is logged on or not**.

---

## Step 7: Connecting from Client Devices

Any device connected to the same Wi-Fi network can now connect:

### From Laptop / Desktop (Windows, macOS, Linux)
Open Chrome, Edge, Safari, or Firefox and go to:
```
http://192.168.1.50:8088
```
Bookmark the URL for quick access.

### From iPhone / iPad (Safari)
1. Open **Safari** and go to `http://192.168.1.50:8088`.
2. Tap the **Share** button (box with arrow pointing up).
3. Scroll down and tap **Add to Home Screen**.
4. A native-feeling app icon will be added to your home screen with fullscreen cinema playback, gesture brightness/volume swipe controls, and mobile subtitle sheets.

### From Android Phone / Tablet (Chrome)
1. Open **Chrome** and go to `http://192.168.1.50:8088`.
2. Tap the **Three Dots Menu** (top right) -> **Install app** (or **Add to Home screen**).
3. Launch it directly from your app drawer.

### From Smart TV (Samsung Tizen, LG webOS, Android TV, Google TV)
1. Open the TV's built-in **Web Browser** application.
2. Type in `http://192.168.1.50:8088`.
3. Save the page as a bookmark / favorite on your TV home screen.

---

## Troubleshooting & Tips

| Issue | Solution |
|---|---|
| **Phone says "Site cannot be reached"** | 1. Ensure phone is on the **same Wi-Fi** (not mobile 4G/5G data).<br>2. Confirm port 8088 is allowed in Windows Firewall (Step 2).<br>3. Check if your Wi-Fi router has "AP Isolation" or "Client Isolation" turned ON (turn it OFF in router settings). |
| **Video playback or streaming is slow** | Ensure media files are on a fast SSD or local drive. If streaming 4K over Wi-Fi, connect the server via an Ethernet cable to your router. |
| **Subtitle files (.srt/.vtt) not showing** | The scanner indexes `.srt` or `.vtt` files alongside the media files. Run a scan via `/scanner` in the web interface. |
| **Server IP changed after restart** | Complete Step 1 (DHCP Reservation in your router) so the server always gets the same IP address. |
