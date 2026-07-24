const { app, BrowserWindow, shell, session, Menu } = require('electron');
const path = require('path');

const ORIGIN = (process.env.SOUNDMIX_ORIGIN || 'https://soundmix.live').replace(/\/$/, '');
const PARTITION = 'persist:soundmix';

/** Paths that stay inside the app shell. */
function isAppNavigation(urlString) {
  try {
    const url = new URL(urlString);
    const origin = new URL(ORIGIN);
    if (url.origin !== origin.origin) {
      return false;
    }
    // Keep Studio, auth, creator, and admin surfaces in-app.
    return true;
  } catch {
    return false;
  }
}

function postAuthLanding() {
  // Creator home has the signed Studio link; falls back via middleware when needed.
  return `${ORIGIN}/home`;
}

function loginUrl() {
  return `${ORIGIN}/login`;
}

function createWindow() {
  const ses = session.fromPartition(PARTITION);

  // Mic / media for Studio WHIP publish (same as Chromium).
  ses.setPermissionRequestHandler((_webContents, permission, callback) => {
    const allow = [
      'media',
      'mediaKeySystem',
      'notifications',
      'clipboard-sanitized-write',
    ].includes(permission);
    callback(allow);
  });

  ses.setPermissionCheckHandler((_webContents, permission) => {
    return ['media', 'mediaKeySystem', 'notifications', 'clipboard-sanitized-write'].includes(
      permission,
    );
  });

  const win = new BrowserWindow({
    width: 1280,
    height: 840,
    minWidth: 960,
    minHeight: 640,
    title: 'Sound Mix Live Studio',
    backgroundColor: '#0c1210',
    show: false,
    webPreferences: {
      partition: PARTITION,
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      spellcheck: false,
    },
  });

  win.once('ready-to-show', () => win.show());

  // Prefer returning to creator home when already authenticated.
  win.loadURL(postAuthLanding());

  win.webContents.on('did-navigate', (_event, url) => {
    // If guest lands on marketing home after logout, send them to login for Studio use.
    try {
      const u = new URL(url);
      if (u.origin === new URL(ORIGIN).origin && (u.pathname === '/' || u.pathname === '')) {
        // Only redirect if not intentionally browsing — Studio app should stay on product surfaces.
        // Leave alone; users may open brand home. Login CTA remains available.
      }
    } catch {
      /* ignore */
    }
  });

  win.webContents.on('did-fail-load', (_event, errorCode, errorDescription, validatedURL) => {
    if (errorCode === -3) {
      return; // aborted
    }
    console.error('Load failed', errorCode, errorDescription, validatedURL);
  });

  // Open external (non-app) URLs in the system browser.
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (isAppNavigation(url)) {
      return { action: 'allow' };
    }
    shell.openExternal(url);
    return { action: 'deny' };
  });

  win.webContents.on('will-navigate', (event, url) => {
    if (!isAppNavigation(url)) {
      event.preventDefault();
      shell.openExternal(url);
    }
  });

  return win;
}

function buildMenu() {
  const isMac = process.platform === 'darwin';
  const template = [
    ...(isMac
      ? [
          {
            label: app.name,
            submenu: [
              { role: 'about' },
              { type: 'separator' },
              { role: 'services' },
              { type: 'separator' },
              { role: 'hide' },
              { role: 'hideOthers' },
              { role: 'unhide' },
              { type: 'separator' },
              { role: 'quit' },
            ],
          },
        ]
      : []),
    {
      label: 'File',
      submenu: [
        {
          label: 'Open Studio Home',
          accelerator: 'CmdOrCtrl+Shift+H',
          click: (_item, win) => {
            (win || BrowserWindow.getFocusedWindow())?.loadURL(postAuthLanding());
          },
        },
        {
          label: 'Log in…',
          accelerator: 'CmdOrCtrl+Shift+L',
          click: (_item, win) => {
            (win || BrowserWindow.getFocusedWindow())?.loadURL(loginUrl());
          },
        },
        { type: 'separator' },
        isMac ? { role: 'close' } : { role: 'quit' },
      ],
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo' },
        { role: 'redo' },
        { type: 'separator' },
        { role: 'cut' },
        { role: 'copy' },
        { role: 'paste' },
        { role: 'selectAll' },
      ],
    },
    {
      label: 'View',
      submenu: [
        { role: 'reload' },
        { role: 'forceReload' },
        { role: 'toggleDevTools' },
        { type: 'separator' },
        { role: 'resetZoom' },
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { type: 'separator' },
        { role: 'togglefullscreen' },
      ],
    },
    {
      label: 'Window',
      submenu: [{ role: 'minimize' }, { role: 'zoom' }, ...(isMac ? [{ type: 'separator' }, { role: 'front' }] : [])],
    },
    {
      role: 'help',
      submenu: [
        {
          label: 'Sound Mix Live website',
          click: () => shell.openExternal(ORIGIN),
        },
        {
          label: 'Downloads',
          click: () => shell.openExternal(`${ORIGIN}/downloads`),
        },
      ],
    },
  ];

  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

app.setName('Sound Mix Live Studio');

const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    const win = BrowserWindow.getAllWindows()[0];
    if (win) {
      if (win.isMinimized()) win.restore();
      win.focus();
    }
  });

  app.whenReady().then(() => {
    if (process.platform === 'darwin' && app.dock) {
      try {
        app.dock.setIcon(path.join(__dirname, 'assets', 'icon.png'));
      } catch {
        /* optional */
      }
    }

    buildMenu();
    createWindow();

    app.on('activate', () => {
      if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
      }
    });
  });
}

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
