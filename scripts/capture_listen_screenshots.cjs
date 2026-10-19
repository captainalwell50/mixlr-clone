#!/usr/bin/env node

const http = require('http');
const fs = require('fs');
const path = require('path');
const { execFile } = require('child_process');

const PORT = 8096;
const CHROME_PORT = 9340;
const PUBLIC_DIR = path.resolve(__dirname, '../public');
const ARTIFACT_DIR = '/Users/mac_1/.gemini/antigravity/brain/e67337df-e7ad-4c61-a425-d9b70f99e343/screenshots';
const LOCAL_DIR = path.resolve(__dirname, '../tmp-verify/screenshots');

fs.mkdirSync(ARTIFACT_DIR, { recursive: true });
fs.mkdirSync(LOCAL_DIR, { recursive: true });

// 1. Static file server
const server = http.createServer((req, res) => {
    const rawPath = req.url.split('?')[0];
    const safePath = path.normalize(rawPath).replace(/^(\.\.[\/\\])+/, '');
    const filePath = path.join(PUBLIC_DIR, safePath);

    if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
        const ext = path.extname(filePath).toLowerCase();
        const mimeTypes = {
            '.html': 'text/html; charset=utf-8',
            '.css': 'text/css; charset=utf-8',
            '.js': 'application/javascript; charset=utf-8',
            '.json': 'application/json',
            '.png': 'image/png',
            '.jpg': 'image/jpeg',
            '.jpeg': 'image/jpeg',
            '.svg': 'image/svg+xml',
            '.woff2': 'font/woff2',
            '.webmanifest': 'application/manifest+json'
        };
        res.writeHead(200, { 'Content-Type': mimeTypes[ext] || 'application/octet-stream' });
        fs.createReadStream(filePath).pipe(res);
    } else {
        res.writeHead(404);
        res.end('Not Found: ' + rawPath);
    }
});

server.listen(PORT, '127.0.0.1', async () => {
    console.log(`Static server ready on http://127.0.0.1:${PORT}`);

    // 2. Launch headless Chrome with debugging port
    const chrome = execFile('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
        '--headless=new',
        '--hide-scrollbars',
        `--remote-debugging-port=${CHROME_PORT}`
    ]);

    await new Promise(r => setTimeout(r, 1200));

    try {
        const verRes = await fetch(`http://127.0.0.1:${CHROME_PORT}/json/version`);
        const verData = await verRes.json();
        const browserWs = new WebSocket(verData.webSocketDebuggerUrl);

        let msgId = 1;
        const bSend = (method, params = {}) => new Promise(resolve => {
            const cur = msgId++;
            const handler = (evt) => {
                const msg = JSON.parse(evt.data);
                if (msg.id === cur) {
                    browserWs.removeEventListener('message', handler);
                    resolve(msg.result);
                }
            };
            browserWs.addEventListener('message', handler);
            browserWs.send(JSON.stringify({ id: cur, method, params }));
        });

        await new Promise(r => browserWs.addEventListener('open', r));

        const shots = [
            // 1. Exact Section 12 Specification Shots
            { name: 'listen_1440_no_scripture', path: 'listen_desktop_live_no_scripture.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_1440_scripture_active', path: 'listen_desktop_live_scripture_active.html', width: 1440, height: 900, mobile: false, dark: false },
            {
                name: 'listen_1440_scrolled_sticky',
                path: 'listen_desktop_live_scripture_active.html',
                width: 1440,
                height: 900,
                mobile: false,
                dark: false,
                evalJs: `window.scrollTo(0, 750); document.getElementById('mobile-sticky-player')?.classList.add('is-visible');`
            },
            { name: 'listen_1440_dark', path: 'listen_dark_shell_scripture_active.html', width: 1440, height: 900, mobile: false, dark: true },
            { name: 'listen_1024', path: 'listen_desktop_live_scripture_active.html', width: 1024, height: 768, mobile: false, dark: false },
            { name: 'listen_768', path: 'listen_desktop_live_scripture_active.html', width: 768, height: 1024, mobile: false, dark: false },
            { name: 'listen_390_no_scripture', path: 'listen_mobile_live_no_scripture.html', width: 390, height: 844, mobile: true, dark: false },
            { name: 'listen_390_scripture_active', path: 'listen_mobile_live_scripture_active.html', width: 390, height: 844, mobile: true, dark: false },
            {
                name: 'listen_390_scrolled_sticky',
                path: 'listen_mobile_live_scripture_active.html',
                width: 390,
                height: 844,
                mobile: true,
                dark: false,
                evalJs: `window.scrollTo(0, 680); document.getElementById('mobile-sticky-player')?.classList.add('is-visible');`
            },
            { name: 'listen_390_dark', path: 'listen_dark_shell_scripture_active.html', width: 390, height: 844, mobile: true, dark: true },

            // 2. Responsive Viewports & Additional Context Shots
            { name: 'listen_1440_light_live', path: 'listen_desktop_live_scripture_active.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_1440_dark_live', path: 'listen_desktop_live_scripture_active.html', width: 1440, height: 900, mobile: false, dark: true },
            { name: 'listen_1024_live', path: 'listen_desktop_live_scripture_active.html', width: 1024, height: 768, mobile: false, dark: false },
            { name: 'listen_768_live', path: 'listen_desktop_live_scripture_active.html', width: 768, height: 1024, mobile: false, dark: false },
            { name: 'listen_390_light_live', path: 'listen_desktop_live_scripture_active.html', width: 390, height: 844, mobile: true, dark: false },
            { name: 'listen_390_dark_live', path: 'listen_desktop_live_scripture_active.html', width: 390, height: 844, mobile: true, dark: true },

            // Below-the-fold Interactions
            {
                name: 'listen_desktop_scrolled_chat',
                path: 'listen_desktop_live_scripture_active.html',
                width: 1440,
                height: 900,
                mobile: false,
                dark: false,
                evalJs: `window.scrollTo(0, 680);`
            },
            {
                name: 'listen_desktop_scripture_expanded',
                path: 'listen_desktop_live_scripture_active.html',
                width: 1440,
                height: 900,
                mobile: false,
                dark: false,
                evalJs: `
                    window.scrollTo(0, 680);
                    const trigger = document.querySelector('.listen-accordion-trigger[data-accordion-tab="scripture"]');
                    if (trigger) trigger.click();
                `
            },
            {
                name: 'listen_desktop_gallery_expanded',
                path: 'listen_desktop_live_scripture_active.html',
                width: 1440,
                height: 900,
                mobile: false,
                dark: false,
                evalJs: `
                    window.scrollTo(0, 680);
                    const trigger = document.querySelector('.listen-accordion-trigger[data-accordion-tab="gallery"]');
                    if (trigger) trigger.click();
                `
            },
            {
                name: 'listen_mobile_scrolled_secondary',
                path: 'listen_desktop_live_scripture_active.html',
                width: 390,
                height: 844,
                mobile: true,
                dark: false,
                evalJs: `window.scrollTo(0, 620);`
            },

            // Operational States
            { name: 'listen_paused', path: 'listen_paused.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_ended', path: 'listen_ended_desktop.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_private_locked', path: 'listen_private_locked.html', width: 1440, height: 900, mobile: false, dark: false },

            // Retained QA suite
            { name: 'listen_desktop_live_no_scripture', path: 'listen_desktop_live_no_scripture.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_desktop_live_scripture_active', path: 'listen_desktop_live_scripture_active.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_desktop_live_scripture_updated', path: 'listen_desktop_live_scripture_updated.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_desktop_live_scripture_cleared', path: 'listen_desktop_live_scripture_cleared.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_mobile_live_no_scripture', path: 'listen_mobile_live_no_scripture.html', width: 390, height: 844, mobile: true, dark: false },
            { name: 'listen_mobile_live_scripture_active', path: 'listen_mobile_live_scripture_active.html', width: 390, height: 844, mobile: true, dark: false },
            { name: 'listen_mobile_live_scripture_cleared', path: 'listen_mobile_live_scripture_cleared.html', width: 390, height: 844, mobile: true, dark: false },
            { name: 'listen_light_shell_scripture_active', path: 'listen_light_shell_scripture_active.html', width: 1440, height: 900, mobile: false, dark: false },
            { name: 'listen_dark_shell_scripture_active', path: 'listen_dark_shell_scripture_active.html', width: 1440, height: 900, mobile: false, dark: true },
        ];

        for (const item of shots) {
            console.log(`Capturing ${item.name} (${item.width}x${item.height}, dark: ${item.dark}, mobile: ${item.mobile})...`);
            const target = await bSend('Target.createTarget', { url: `http://127.0.0.1:${PORT}/previews/${item.path}` });
            await new Promise(r => setTimeout(r, 600));

            const targets = await (await fetch(`http://127.0.0.1:${CHROME_PORT}/json`)).json();
            const pageTarget = targets.find(t => t.id === target.targetId);
            const pageWs = new WebSocket(pageTarget.webSocketDebuggerUrl);
            await new Promise(r => pageWs.addEventListener('open', r));

            const pSend = (method, params = {}) => new Promise(resolve => {
                const cur = msgId++;
                const handler = (evt) => {
                    const msg = JSON.parse(evt.data);
                    if (msg.id === cur) {
                        pageWs.removeEventListener('message', handler);
                        resolve(msg.result);
                    }
                };
                pageWs.addEventListener('message', handler);
                pageWs.send(JSON.stringify({ id: cur, method, params }));
            });

            await pSend('Page.enable');
            await pSend('Runtime.enable');
            await pSend('Emulation.setDeviceMetricsOverride', {
                width: item.width,
                height: item.height,
                deviceScaleFactor: item.mobile ? 2 : 1,
                mobile: item.mobile
            });

            if (item.dark) {
                await pSend('Runtime.evaluate', {
                    expression: `
                        document.documentElement.classList.add('dark');
                        document.documentElement.setAttribute('data-theme', 'dark');
                        document.documentElement.setAttribute('data-theme-preference', 'dark');
                        if (window.SoundMixTheme && window.SoundMixTheme.syncButtons) {
                            window.SoundMixTheme.syncButtons('dark');
                        }
                    `
                });
            } else {
                await pSend('Runtime.evaluate', {
                    expression: `
                        document.documentElement.classList.remove('dark');
                        document.documentElement.setAttribute('data-theme', 'light');
                        document.documentElement.setAttribute('data-theme-preference', 'light');
                        if (window.SoundMixTheme && window.SoundMixTheme.syncButtons) {
                            window.SoundMixTheme.syncButtons('light');
                        }
                    `
                });
            }

            if (item.evalJs) {
                await pSend('Runtime.evaluate', { expression: item.evalJs });
                await new Promise(r => setTimeout(r, 250));
            }

            await new Promise(r => setTimeout(r, 450));

            const snap = await pSend('Page.captureScreenshot', {
                format: 'png',
                captureBeyondViewport: false
            });

            const buf = Buffer.from(snap.data, 'base64');
            const dest1 = path.join(ARTIFACT_DIR, `${item.name}.png`);
            const dest2 = path.join(LOCAL_DIR, `${item.name}.png`);
            fs.writeFileSync(dest1, buf);
            fs.writeFileSync(dest2, buf);
            console.log(`  ✓ Saved ${item.name} (${buf.length} bytes)`);

            await pSend('Page.close');
            await bSend('Target.closeTarget', { targetId: target.targetId });
        }

        console.log('All listen screenshots captured successfully!');
        browserWs.close();
        chrome.kill();
        server.close();
        process.exit(0);

    } catch (err) {
        console.error('Capture failed:', err);
        chrome.kill();
        server.close();
        process.exit(1);
    }
});
