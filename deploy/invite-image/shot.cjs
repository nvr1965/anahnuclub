const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext({ viewport: { width: 1200, height: 630 } })).newPage();
  for (const c of ['ru','en','de','he','ar','tg']) {
    await p.goto('file:///root/scripts/og/' + c + '.html'); await p.waitForTimeout(1500);
    await p.screenshot({ path: '/root/work/anahnuclub/public/images/invite/' + c + '.jpg', type: 'jpeg', quality: 88 });
  }
  await b.close();
})();
