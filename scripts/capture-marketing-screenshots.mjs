/**
 * Marketing screenshots (Playwright) — does not depend on the IDE browser tab.
 *
 * Prereqs:
 *   scripts/e2e-serve.sh 8099
 *   php artisan phare:demo:seed && php artisan phare:demo:simulate --once (e2e env)
 *
 * Usage:
 *   npx playwright@latest install chromium
 *   node scripts/capture-marketing-screenshots.mjs
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from 'playwright';

const BASE = process.env.PHARE_SCREENSHOT_URL ?? 'http://127.0.0.1:8099';
const OUT = join(import.meta.dirname, '../docs/media/screenshots');
const VIEWPORT = {
    width: Number(process.env.PHARE_SCREENSHOT_WIDTH ?? 1920),
    height: Number(process.env.PHARE_SCREENSHOT_HEIGHT ?? 1080),
};

function loadDemoAuth() {
    const manifestPath = join(
        import.meta.dirname,
        '../storage/app/phare-demo.json',
    );
    try {
        const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
        return {
            email: manifest.login?.demo_email ?? 'demo@phare.test',
            password: manifest.login?.demo_password ?? 'password',
            wallboardToken: manifest.wallboard,
        };
    } catch {
        return {
            email: 'demo@phare.test',
            password: 'password',
            wallboardToken: null,
        };
    }
}

async function login(page, email, password) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    if (page.url().includes('/dashboard')) {
        return;
    }
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 });
}

async function shot(page, name) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    await page.screenshot({
        path: join(OUT, name),
        fullPage: false,
    });
    console.log(`wrote ${name} (${VIEWPORT.width}x${VIEWPORT.height})`);
}

const { email, password, wallboardToken } = loadDemoAuth();

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 1,
    colorScheme: 'dark',
});
const page = await context.newPage();

try {
    await login(page, email, password);

    await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
    await shot(page, '02-dashboard.png');

    await page.goto(`${BASE}/checks`, { waitUntil: 'networkidle' });
    await shot(page, '03-monitors.png');

    await page.goto(`${BASE}/monitor/new`, { waitUntil: 'networkidle' });
    await shot(page, '04-add-monitor-wizard.png');

    await page.getByRole('button', { name: /A website/i }).click();
    await page.getByLabel('Website address').fill('laravel.com');
    await page.waitForTimeout(800);
    await shot(page, '05-website-form.png');

    await page.goto(`${BASE}/hosts`, { waitUntil: 'networkidle' });
    await shot(page, '06-servers.png');

    await page.goto(`${BASE}/incidents`, { waitUntil: 'networkidle' });
    await shot(page, '08-incidents.png');

    if (wallboardToken) {
        await page.goto(`${BASE}/wallboard/${wallboardToken}`, {
            waitUntil: 'domcontentloaded',
        });
        await page
            .waitForFunction(
                () =>
                    document.body.innerText.includes('checks OK') ||
                    document.body.innerText.includes('ALL GOOD'),
                { timeout: 15_000 },
            )
            .catch(() => {});
        await page.waitForTimeout(500);
        await shot(page, '07-wallboard.png');
    }
} finally {
    await browser.close();
}
