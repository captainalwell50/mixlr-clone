/**
 * Minimal preload — Studio runs entirely on soundmix.live.
 * Reserved for future native bridges (device picker, deep links).
 */
const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('soundmixDesktop', {
  isDesktop: true,
  platform: process.platform,
});
