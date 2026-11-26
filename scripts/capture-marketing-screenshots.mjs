/**
 * Marketing screenshots (Playwright).
 *
 * Prereqs:
 *   scripts/e2e-serve.sh 8099
 *   php artisan nocline:demo:seed && php artisan nocline:demo:simulate --once --chaos (e2e env)
 *   npm run build
 *
 * Usage:
 *   npx playwright@latest install chromium
 *   node scripts/capture-marketing-screenshots.mjs
 *
 * Output: docs/public/media/screenshots/ (served by VitePress at /media/screenshots/).
 */
import { cpSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from 'playwright';

const BASE = process.env.NOCLINE_SCREENSHOT_URL ?? 'http://127.0.0.1:8099';
const OUT = join(import.meta.dirname, '../docs/public/media/screenshots');
const VIEWPORT = {
    width: Number(process.env.NOCLINE_SCREENSHOT_WIDTH ?? 1920),
    height: Number(process.env.NOCLINE_SCREENSHOT_HEIGHT ?? 1080),
};

function loadDemoAuth() {
    const manifestPath = join(
        import.meta.dirname,
        '../storage/app/nocline-demo.json',
    );
    try {
        const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
        return {
            email: manifest.login?.demo_email ?? 'demo@nocline.test',
            password: manifest.login?.demo_password ?? 'password',
            wallboardToken: manifest.wallboard,
            screenshot: manifest.screenshot ?? {},
        };
    } catch {
        return {
            email: 'demo@nocline.test',
            password: 'password',
            wallboardToken: null,
            screenshot: {},
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

async function shot(page, name, { fullPage = false, delay = 400 } = {}) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(delay);
    await page.screenshot({
        path: join(OUT, name),
        fullPage,
    });
    console.log(`wrote ${name} (${VIEWPORT.width}x${VIEWPORT.height})`);
}

async function gotoShot(page, path, name, opts) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    await shot(page, name, opts);
}

const { email, password, wallboardToken, screenshot } = loadDemoAuth();

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 1,
    colorScheme: 'dark',
});
const page = await context.newPage();

try {
    await login(page, email, password);

    // --- Console
    await gotoShot(page, '/dashboard', '02-dashboard.png');
    const objectStoragePanel = page.getByRole('region', {
        name: /object storage/i,
    });
    if (await objectStoragePanel.count()) {
        await objectStoragePanel.scrollIntoViewIfNeeded();
        await shot(page, '02b-dashboard-object-storage.png');
    }

    // --- Monitors
    await gotoShot(page, '/checks', '03-monitors.png', { delay: 600 });
    if (screenshot.check_flaky_id) {
        await page
            .getByRole('link', { name: /flaky\.acme/i })
            .first()
            .click();
        await page.waitForURL(/\/checks\/\d+/, { timeout: 15_000 });
        await shot(page, '03b-monitor-flaky-detail.png', { delay: 500 });
    }
    if (screenshot.check_blog_id) {
        await gotoShot(
            page,
            `/checks/${screenshot.check_blog_id}`,
            '03c-monitor-wordpress.png',
        );
    }
    if (screenshot.check_shop_id) {
        await gotoShot(
            page,
            `/checks/${screenshot.check_shop_id}/history`,
            '03d-monitor-history.png',
            { delay: 600 },
        );
    }

    // --- Wizard
    await gotoShot(page, '/monitor/new', '04-add-monitor-wizard.png');
    await page.getByRole('button', { name: /A website/i }).click();
    await page.getByLabel('Website address').fill('laravel.com');
    await page
        .waitForFunction(
            () =>
                document.body.innerText.match(/Laravel|WordPress|health/i) !==
                null,
            { timeout: 20_000 },
        )
        .catch(() => {});
    await shot(page, '05-website-detect-laravel.png', { delay: 800 });

    await page.goto(`${BASE}/monitor/new?kind=server`, {
        waitUntil: 'networkidle',
    });
    await shot(page, '04b-add-monitor-server.png');

    await page.goto(`${BASE}/monitor/new`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Something else/i }).click();
    await shot(page, '04c-add-monitor-something-else.png', { delay: 300 });
    await page.getByRole('button', { name: /Object storage/i }).click();
    await shot(page, '17-object-storage-wizard.png', { delay: 400 });

    // --- Servers
    await gotoShot(page, '/hosts', '06-servers.png');
    if (screenshot.host_db_id) {
        await gotoShot(
            page,
            `/hosts/${screenshot.host_db_id}`,
            '09-host-detail-eol.png',
            { delay: 600 },
        );
    }
    if (screenshot.host_web_id) {
        await gotoShot(
            page,
            `/monitor/servers/${screenshot.host_web_id}/install`,
            '10-server-install-command.png',
            { delay: 500 },
        );
    }
    if (screenshot.host_offline_id) {
        await gotoShot(
            page,
            `/hosts/${screenshot.host_offline_id}`,
            '06b-server-offline-enroll.png',
            { delay: 500 },
        );
    }
    if (screenshot.heartbeat_backup_id) {
        await gotoShot(
            page,
            `/monitor/heartbeats/${screenshot.heartbeat_backup_id}`,
            '18-heartbeat-install.png',
            { delay: 500 },
        );
    }

    // --- Renewals, groups, probes, maintenance
    await gotoShot(page, '/renewals', '13-renewals.png');
    await gotoShot(page, '/groups', '14-groups.png');
    await gotoShot(page, '/probes', '15-probes.png');
    await gotoShot(page, '/maintenance', '16-maintenance.png');

    // --- Alerts, status, wallboards, vault, API
    await gotoShot(page, '/alertes/channels', '21-alerts-channels.png');
    await gotoShot(page, '/status-pages', '20-status-pages-admin.png');
    if (screenshot.status_slug) {
        await gotoShot(
            page,
            `/status/${screenshot.status_slug}`,
            '19-status-page-public.png',
        );
    }
    await gotoShot(page, '/wallboards', '22-wallboards-manage.png');
    await gotoShot(page, '/settings/api-tokens', '24-api-tokens.png');
    await gotoShot(page, '/audit', '25-audit-log.png');
    await gotoShot(page, '/users', '26-users-roles.png');

    const vault = await page.goto(`${BASE}/coffre`, {
        waitUntil: 'networkidle',
    });
    if (vault?.ok()) {
        await shot(page, '23-vault.png');
    }

    // --- Incidents
    await gotoShot(page, '/incidents', '08-incidents.png');

    // --- Wallboard (public)
    if (wallboardToken) {
        await page.goto(`${BASE}/wallboard/${wallboardToken}`, {
            waitUntil: 'domcontentloaded',
        });
        await page
            .waitForFunction(
                () =>
                    document.body.innerText.includes('checks OK') ||
                    document.body.innerText.includes('ALL GOOD') ||
                    document.body.innerText.includes('/'),
                { timeout: 15_000 },
            )
            .catch(() => {});
        await shot(page, '07-wallboard.png', { delay: 500 });
    }
} finally {
    await browser.close();
}

// Legacy path still linked from some external refs (GitHub raw trees).
const legacyOut = join(import.meta.dirname, '../docs/media/screenshots');
cpSync(OUT, legacyOut, { recursive: true, force: true });