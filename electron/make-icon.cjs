// Rasterizes branding/emblem.svg into the PNG sizes electron-builder needs.
// electron-builder generates the .ico/.icns itself from a >=256x256 source PNG.
// Run with: npm run electron:icon
const { app, BrowserWindow } = require('electron');
const path = require('path');
const fs = require('fs');

const root = path.join(__dirname, '..');
const svgPath = path.join(root, 'branding', 'emblem.svg');
const outDir = path.join(root, 'installer', 'assets');
const SIZE = 512;

async function render() {
    const svg = fs.readFileSync(svgPath, 'utf-8');
    const html = `<!doctype html><html><head><style>
        html,body{margin:0;padding:0;background:transparent;overflow:hidden}
        svg{display:block;width:${SIZE}px;height:${SIZE}px}
    </style></head><body>${svg}</body></html>`;

    const win = new BrowserWindow({
        width: SIZE,
        height: SIZE,
        show: false,
        frame: false,
        transparent: true,
        backgroundColor: '#00000000',
        webPreferences: { offscreen: true },
    });

    await win.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(html));
    // Give the compositor a frame to paint the SVG before capturing.
    await new Promise((r) => setTimeout(r, 400));

    const image = await win.capturePage();
    if (image.isEmpty()) throw new Error('capturePage returned an empty image');

    fs.mkdirSync(outDir, { recursive: true });
    fs.writeFileSync(path.join(outDir, 'icon.png'), image.toPNG());
    console.log(`wrote ${path.join(outDir, 'icon.png')} (${SIZE}x${SIZE})`);

    win.destroy();
}

app.disableHardwareAcceleration();
app.whenReady()
    .then(render)
    .then(() => app.exit(0))
    .catch((err) => {
        console.error('icon generation failed:', err);
        app.exit(1);
    });
