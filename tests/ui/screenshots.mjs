// Responsive check of the rendered fixtures (see render-fixtures.php):
//   node tests/ui/screenshots.mjs /tmp/arbk-ui
// Saves full-page screenshots per viewport and fails when a page scrolls horizontally.
import { spawn } from 'node:child_process';
import { readdirSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let playwright;
try { playwright = require('playwright'); } catch { playwright = require(process.env.PLAYWRIGHT_MODULE || '/opt/node-tools/node_modules/playwright'); }

const dir = process.argv[2] || '/tmp/arbk-ui';
const pages = readdirSync(dir).filter((f) => f.endsWith('.html')).map((f) => f.replace(/\.html$/, ''));
const viewports = { phone: [360, 740], tablet: [768, 1024], desktop: [1280, 900] };
const port = 8099;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', dir], { stdio: 'ignore' });
await new Promise((resolve) => setTimeout(resolve, 600));
const browser = await playwright.chromium.launch();
const failures = [];
try {
  for (const [name, [width, height]] of Object.entries(viewports)) {
    mkdirSync(`${dir}/shots/${name}`, { recursive: true });
    const context = await browser.newContext({ viewport: { width, height } });
    const page = await context.newPage();
    for (const file of pages) {
      await page.goto(`http://127.0.0.1:${port}/${file}.html`);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      if (overflow > 1) failures.push(`${file} @ ${name}: scrolls horizontally by ${overflow}px`);
      await page.screenshot({ path: `${dir}/shots/${name}/${file}.png`, fullPage: true });
    }
    await context.close();
  }
} finally {
  await browser.close();
  server.kill();
}
if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
console.log(`Checked ${pages.length} pages at ${Object.keys(viewports).length} viewports without horizontal scrolling.`);
