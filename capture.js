const puppeteer = require('puppeteer');

(async () => {
  console.log('Launching browser...');
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 1200, height: 630 });
  console.log('Navigating to http://127.0.0.1:3000...');
  // Wait a bit to ensure the token data loads if any
  await page.goto('http://127.0.0.1:3000', { waitUntil: 'networkidle0' });
  await new Promise(r => setTimeout(r, 2000)); 
  console.log('Capturing screenshot...');
  await page.screenshot({ path: 'site_preview.png' });
  await browser.close();
  console.log('Screenshot saved to site_preview.png');
})();
