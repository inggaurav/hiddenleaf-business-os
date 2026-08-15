import { chromium } from 'playwright';
import path from 'path';

const ARTIFACT_DIR = 'C:/Users/manag/.gemini/antigravity/brain/f78d71e4-1f7b-45eb-ad9b-2b96851ad66a';

async function auditAndCapture() {
  const browser = await chromium.launch({ headless: true });

  // 1. Desktop Audit & Full-Page Screenshot
  console.log('\n=== 1. AUDITING DESKTOP (1280x800) ===');
  const desktopCtx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
  });
  const desktopPage = await desktopCtx.newPage();

  // Login
  await desktopPage.goto('https://hiddenleafagency.com/login', { waitUntil: 'networkidle', timeout: 25000 });
  await desktopPage.fill('input[type="email"]', 'demo@hiddenleaf.local');
  await desktopPage.fill('input[type="password"]', 'Demo@HiddenLeaf2026!');
  await Promise.all([
    desktopPage.waitForNavigation({ waitUntil: 'networkidle', timeout: 25000 }),
    desktopPage.click('button[type="submit"]'),
  ]);

  console.log('Landed on URL:', desktopPage.url());
  await desktopPage.waitForTimeout(1000);

  // Extract all text and metrics
  const desktopText = await desktopPage.$eval('#app', el => el.innerText);
  console.log('Dashboard text length:', desktopText.length);

  // Extract sidebar links
  const sidebarLinks = await desktopPage.$$eval('aside nav a', els => els.map(e => ({ name: e.innerText.trim(), href: e.getAttribute('href') })));
  console.log('Sidebar Navigation Links count:', sidebarLinks.length);
  console.log('Sidebar Links:', sidebarLinks.map(l => l.name).join(', '));

  // Take Desktop Screenshot
  const desktopScreenshotPath = path.join(ARTIFACT_DIR, 'desktop_dashboard.png');
  await desktopPage.screenshot({ path: desktopScreenshotPath, fullPage: true });
  console.log('Saved desktop screenshot to:', desktopScreenshotPath);

  // 2. Mobile iPhone Audit & Full-Page Screenshot
  console.log('\n=== 2. AUDITING MOBILE iPHONE (390x844) ===');
  const mobileIphoneCtx = await browser.newContext({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
  });
  const mobileIphonePage = await mobileIphoneCtx.newPage();

  await mobileIphonePage.goto('https://hiddenleafagency.com/login', { waitUntil: 'networkidle', timeout: 25000 });
  await mobileIphonePage.fill('input[type="email"]', 'demo@hiddenleaf.local');
  await mobileIphonePage.fill('input[type="password"]', 'Demo@HiddenLeaf2026!');
  await Promise.all([
    mobileIphonePage.waitForNavigation({ waitUntil: 'networkidle', timeout: 25000 }),
    mobileIphonePage.click('button[type="submit"]'),
  ]);

  console.log('Mobile iPhone Landed on URL:', mobileIphonePage.url());
  await mobileIphonePage.waitForTimeout(1000);

  const iphoneScreenshotPath = path.join(ARTIFACT_DIR, 'mobile_iphone_dashboard.png');
  await mobileIphonePage.screenshot({ path: iphoneScreenshotPath, fullPage: true });
  console.log('Saved iPhone screenshot to:', iphoneScreenshotPath);

  // Open mobile sidebar
  const menuBtn = await mobileIphonePage.$('button[aria-label="Open Navigation Sidebar"]');
  if (menuBtn) {
    await menuBtn.click();
    await mobileIphonePage.waitForTimeout(500);
    const iphoneNavScreenshotPath = path.join(ARTIFACT_DIR, 'mobile_iphone_sidebar.png');
    await mobileIphonePage.screenshot({ path: iphoneNavScreenshotPath });
    console.log('Saved iPhone sidebar screenshot to:', iphoneNavScreenshotPath);
  }

  // 3. Mobile Pixel 8 Audit & Full-Page Screenshot
  console.log('\n=== 3. AUDITING MOBILE PIXEL 8 (412x915) ===');
  const mobilePixelCtx = await browser.newContext({
    viewport: { width: 412, height: 915 },
    isMobile: true,
    hasTouch: true,
    userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
  });
  const mobilePixelPage = await mobilePixelCtx.newPage();

  await mobilePixelPage.goto('https://hiddenleafagency.com/login', { waitUntil: 'networkidle', timeout: 25000 });
  await mobilePixelPage.fill('input[type="email"]', 'demo@hiddenleaf.local');
  await mobilePixelPage.fill('input[type="password"]', 'Demo@HiddenLeaf2026!');
  await Promise.all([
    mobilePixelPage.waitForNavigation({ waitUntil: 'networkidle', timeout: 25000 }),
    mobilePixelPage.click('button[type="submit"]'),
  ]);

  console.log('Mobile Pixel Landed on URL:', mobilePixelPage.url());
  await mobilePixelPage.waitForTimeout(1000);

  const pixelScreenshotPath = path.join(ARTIFACT_DIR, 'mobile_pixel_dashboard.png');
  await mobilePixelPage.screenshot({ path: pixelScreenshotPath, fullPage: true });
  console.log('Saved Pixel screenshot to:', pixelScreenshotPath);

  // Metric verification strings
  const checks = {
    sales18500: desktopText.includes('18,500') || desktopText.includes('$18,500'),
    purchases4200: desktopText.includes('4,200') || desktopText.includes('$4,200'),
    net14300: desktopText.includes('14,300') || desktopText.includes('$14,300'),
    margin77: desktopText.includes('77.3%') || desktopText.includes('77%'),
    crmDeal48000: desktopText.includes('48,000') || desktopText.includes('$48,000'),
    hasCRM: sidebarLinks.some(l => l.name.toLowerCase().includes('crm') || l.href.includes('crm')),
    hasAccounting: sidebarLinks.some(l => l.name.toLowerCase().includes('account') || l.href.includes('account')),
    hasSales: sidebarLinks.some(l => l.name.toLowerCase().includes('sale') || l.href.includes('sale')),
    hasProcurement: sidebarLinks.some(l => l.name.toLowerCase().includes('purchase') || l.name.toLowerCase().includes('procurement') || l.href.includes('purchase')),
    hasHRM: sidebarLinks.some(l => l.name.toLowerCase().includes('hrm') || l.name.toLowerCase().includes('hr') || l.href.includes('hrm')),
    hasTaskly: sidebarLinks.some(l => l.name.toLowerCase().includes('project') || l.name.toLowerCase().includes('task') || l.href.includes('taskly')),
    hasPOS: sidebarLinks.some(l => l.name.toLowerCase().includes('pos') || l.href.includes('pos')),
  };

  console.log('\n=== AUDIT CHECKS ===');
  console.log(JSON.stringify(checks, null, 2));

  await browser.close();
}

auditAndCapture().catch(console.error);
