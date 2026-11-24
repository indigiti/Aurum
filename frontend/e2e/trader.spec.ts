import {test,expect,type Route,type WebSocketRoute} from '@playwright/test';

async function mockPublicMarket(page:any){
  await page.routeWebSocket('wss://ws.okx.com/ws/v5/public',(socket:WebSocketRoute)=>socket.close());
  await page.route('**/api/market.php**',async (route:Route)=>{
    const action=new URL(route.request().url()).searchParams.get('action');
    if(action==='instrument'){
      await route.fulfill({json:{symbol:'XAUUSD',instrument:'XAU-USDT-SWAP',quoteCurrency:'USDT',proxy:true,data:{provider:'OKX',instrument:'XAU-USDT-SWAP',state:'live',contractType:'linear',contractValue:0.001,contractValueCurrency:'XAU',lotSize:1,minimumContracts:1,tickSize:0.1,settlementCurrency:'USDT',contractsPerOunce:1000,simulatedLotOunces:100,contractsPerSimulatedLot:100000}}});
    }else if(action==='depth'){
      await route.fulfill({json:{data:{timestamp:Date.now(),bids:[{price:4000,contracts:10}],asks:[{price:4000.25,contracts:12}]}}});
    }else if(action==='candles'){
      const now=Math.floor(Date.now()/1000/60)*60;
      const data=Array.from({length:60},(_,i)=>{const p=4000+i*.2;return {time:now-(59-i)*60,open:p,high:p+1,low:p-1,close:p+.2,volume:10,confirmed:true}});
      await route.fulfill({json:{symbol:'XAUUSD',instrument:'XAU-USDT-SWAP',quoteCurrency:'USDT',proxy:true,data}});
    }else await route.fulfill({status:400,json:{error:'Unsupported test action'}});
  });
}
async function register(page:any,email:string){
  await page.goto('./');
  await page.getByRole('button',{name:'Start challenge',exact:true}).click();
  await page.getByLabel('Email',{exact:true}).fill(email);
  await page.getByLabel('Password (10+ characters)').fill('Aurum-E2E-Password-2026');
  await page.getByLabel('Virtual account').selectOption('5000');
  await page.getByRole('button',{name:'Create account'}).click();
  await expect(page.locator('main > .stats strong').first()).toHaveText('$5,000.00');
}
test('trader can register, inspect challenge, analytics and navigate on mobile',async({page})=>{
  await mockPublicMarket(page);
  await register(page,'trader-e2e@example.test');
  await expect(page.getByText(/OKX contract: 0.001 XAU/)).toBeVisible();
  await page.getByRole('button',{name:'Challenges'}).click();
  await expect(page.getByRole('heading',{name:/Challenge · Phase 1/})).toBeVisible();
  await page.getByRole('button',{name:'Analytics'}).click();
  await expect(page.getByRole('heading',{name:'Performance analytics'})).toBeVisible();
  await expect(page.getByText('No server-observed equity snapshots yet.')).toBeVisible();
  await page.getByRole('button',{name:'Replay',exact:true}).click();
  await page.getByRole('button',{name:'Load history'}).click();
  await expect(page.getByText(/20 \/ 60 bars revealed/)).toBeVisible();
  await page.getByRole('button',{name:'Next bar'}).click();
  await expect(page.getByText(/21 \/ 60 bars revealed/)).toBeVisible();
  await page.getByRole('button',{name:'Account',exact:true}).click();
  await page.getByLabel('New virtual account').selectOption('10000');
  await page.getByRole('button',{name:'Create another simulated account'}).click();
  await expect(page.locator('main > .stats strong').first()).toHaveText('$10,000.00');
  await page.getByLabel('Active trading account').selectOption('primary');
  await expect(page.locator('main > .stats strong').first()).toHaveText('$5,000.00');
  await page.setViewportSize({width:390,height:844});
  await page.getByRole('button',{name:'Menu'}).click();
  await page.getByRole('button',{name:'Journal'}).click();
  await expect(page.getByRole('heading',{name:'Journal',exact:true})).toBeVisible();
});
test('allowlisted administrator can inspect health and update challenge rules',async({page})=>{
  await mockPublicMarket(page);
  await register(page,'admin@example.test');
  await page.getByRole('button',{name:'Admin'}).click();
  await expect(page.getByRole('heading',{name:'System health'})).toBeVisible();
  await expect(page.getByText('test',{exact:true})).toBeVisible();
  await page.getByLabel('Daily loss %').fill('4');
  await page.getByLabel('Maximum loss %').fill('8');
  await page.getByRole('button',{name:'Save rules for new accounts'}).click();
  await expect(page.locator('header .headerMessage summary')).toHaveAttribute('title','Admin action completed');
  await page.locator('header .headerMessage summary').click();
  await expect(page.locator('.headerMessagePanel')).toContainText('Admin action completed');
});

test('guest navigation never repeats the authentication form',async({page})=>{
  await mockPublicMarket(page);
  await page.goto('./');
  await expect(page.getByLabel('Email',{exact:true})).toHaveCount(0);
  for(const tab of ['Replay','Calendar','Journal','Analytics','Account']){
    await page.getByRole('button',{name:tab,exact:true}).click();
    await expect(page.getByLabel('Email',{exact:true})).toHaveCount(0);
  }
  await page.getByRole('button',{name:'Sign in to continue'}).click();
  await expect(page.getByRole('heading',{name:'Welcome back'})).toBeVisible();
  await expect(page.getByLabel('Email',{exact:true})).toBeVisible();
  await expect(page.getByLabel('Virtual account',{exact:true})).toHaveCount(0);
  await expect(page.getByLabel('Email verification code')).toHaveCount(0);
  await page.getByRole('button',{name:'Register',exact:true}).click();
  await expect(page.getByLabel('Virtual account',{exact:true})).toBeVisible();
  await page.getByRole('button',{name:'Back to workspace'}).click();
  await expect(page.getByLabel('Email',{exact:true})).toHaveCount(0);
  await expect(page.getByRole('heading',{name:'Account',exact:true})).toBeVisible();
  await page.setViewportSize({width:390,height:844});
  await page.getByRole('button',{name:'Menu'}).click();
  await page.getByRole('button',{name:'Terminal',exact:true}).click();
  await expect(page.getByLabel('Email',{exact:true})).toHaveCount(0);
});

test('one-click demo can trade, reload, reset and exit without credentials',async({page})=>{
  await mockPublicMarket(page);
  await page.goto('./');
  await page.getByRole('button',{name:'Try demo',exact:true}).click();
  await expect(page.locator('header .headerDemoBalance')).toHaveText('$100K');
  await expect(page.locator('main > .stats strong').first()).toHaveText('$100,000.00');
  await expect(page.getByLabel('Email',{exact:true})).toHaveCount(0);
  await expect(page.getByRole('button',{name:'Admin',exact:true})).toHaveCount(0);
  await expect(page.getByRole('button',{name:'Virtual Payouts',exact:true})).toHaveCount(0);
  await page.getByRole('button',{name:'Buy market',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Open positions · 1'})).toBeVisible();
  await page.reload();
  await expect(page.getByRole('heading',{name:'Open positions · 1'})).toBeVisible();
  await page.getByRole('button',{name:'Analytics',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Performance analytics'})).toBeVisible();
  page.once('dialog',d=>d.accept());
  await page.getByRole('button',{name:'Reset demo',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Open positions · 0'})).toBeVisible();
  await page.getByRole('button',{name:'Exit demo',exact:true}).click();
  await expect(page.getByRole('button',{name:'Try demo',exact:true})).toBeVisible();
});

test('V3 chart controls, theme, panel and mobile layout remain usable',async({page})=>{
  await mockPublicMarket(page);
  await page.setViewportSize({width:1440,height:900});
  await page.goto('./');
  await expect(page.getByRole('region',{name:'Trading chart'})).toBeVisible();
  await expect(page.locator('.chartHost canvas').first()).toBeVisible();
  await page.getByRole('button',{name:'Light theme',exact:true}).click();
  await expect(page.locator('html')).toHaveAttribute('data-theme','light');
  await page.getByRole('button',{name:'EMA',exact:true}).click();
  await expect(page.getByRole('button',{name:'EMA',exact:true})).toHaveAttribute('aria-pressed','true');
  await page.getByRole('button',{name:'Hide order ticket',exact:true}).click();
  await expect(page.locator('#order-ticket')).toBeHidden();
  await page.getByRole('button',{name:'Show order ticket',exact:true}).click();
  await expect(page.locator('#order-ticket')).toBeVisible();
  await page.getByRole('button',{name:'Collapse sidebar',exact:true}).click();
  await expect(page.locator('.shell')).toHaveClass(/navCompact/);
  await page.getByRole('button',{name:'Expand chart',exact:true}).click();
  await expect(page.locator('.shell')).toHaveClass(/chartExpanded/);
  await page.keyboard.press('Escape');
  await expect(page.locator('.shell')).not.toHaveClass(/chartExpanded/);
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme','light');
  await page.setViewportSize({width:390,height:844});
  await expect(page.getByRole('navigation',{name:'Mobile shortcuts'})).toBeVisible();
  await page.getByRole('button',{name:'Open order ticket',exact:true}).click();
  await expect(page.locator('#order-ticket')).toBeVisible();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1)).toBe(true);
  await page.getByRole('button',{name:'Open risk',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Challenges',exact:true})).toBeVisible();
});

test('demo exposure book matches buys and sells and preserves net exposure',async({page})=>{
  await mockPublicMarket(page);await page.goto('./');
  await page.getByRole('button',{name:'Try demo',exact:true}).click();
  const size=page.getByLabel('Size (troy ounces · 100 oz = 1 simulated lot)');
  await size.fill('3');await page.getByRole('button',{name:'Buy market',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Open positions · 1'})).toBeVisible();
  await size.fill('2');await page.getByRole('button',{name:'Sell market',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Open positions · 2'})).toBeVisible();
  await expect(page.locator('.exposureGrid')).toContainText('0.01 BUY');
  await page.getByRole('button',{name:'Close matched (0.02 / side)',exact:true}).click();
  await expect(page.getByRole('dialog',{name:'Review position closure'})).toBeVisible();
  await page.getByRole('button',{name:'Confirm close',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Open positions · 1'})).toBeVisible();
  await expect(page.locator('.exposureGrid')).toContainText('0.01 BUY');
  await page.getByRole('button',{name:'Close all positions',exact:true}).click();
  await page.getByRole('button',{name:'Confirm close',exact:true}).click();
  await expect(page.getByRole('heading',{name:'Open positions · 0'})).toBeVisible();
  await expect(page.locator('.exposureGrid')).toContainText('0.00 FLAT');
});

test('workspace status is compact and appears before live controls',async({page})=>{
 await mockPublicMarket(page);await page.setViewportSize({width:1440,height:900});await page.goto('./');
 await page.getByRole('button',{name:'Try demo',exact:true}).click();
 await expect(page.locator('header .headerContext')).toBeVisible();
 await expect(page.locator('header .headerDemoBalance')).toHaveText('$100K');
 await expect(page.locator('header').getByRole('button',{name:'Reset demo',exact:true})).toBeVisible();
 await expect(page.locator('.demoBanner,.marketDisclosure,main > .notice')).toHaveCount(0);
 expect(await page.locator('header .headerReset').evaluate(el=>!!(el.compareDocumentPosition(document.querySelector('header .headerConnection')!)&Node.DOCUMENT_POSITION_FOLLOWING))).toBe(true);
 await page.locator('header .headerMarket summary').click();
 await expect(page.getByText('Market and simulation information',{exact:true})).toBeVisible();
 await expect(page.locator('header .headerMarket .headerMarketDetails')).toContainText('not spot XAU/USD');
 await page.locator('header .headerMarket summary').click();
 await page.setViewportSize({width:390,height:844});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1)).toBe(true);
});

test('icon header keeps accessible names and touch targets on narrow mobile screens',async({page})=>{
 await mockPublicMarket(page);await page.setViewportSize({width:320,height:720});await page.goto('./');
 await page.getByRole('button',{name:'Try demo',exact:true}).click();
 const header=page.locator('header');
 await expect(header.locator('.headerDemoBalance')).toHaveText('$100K');
 for(const name of ['Reset demo','Light theme','Exit demo']){
  const b=header.getByRole('button',{name,exact:true});await expect(b).toBeVisible();
  await expect(b).toHaveAttribute('title',name);await expect(b).toHaveText('');
  const bounds=await b.boundingBox();expect(bounds?.width).toBeGreaterThanOrEqual(44);expect(bounds?.height).toBeGreaterThanOrEqual(44);
 }
 await header.locator('.headerConnection summary').click();
 await expect(header.locator('.headerConnection .headerMarketDetails')).toBeVisible();
 await expect(header.locator('.headerConnection .headerMarketDetails')).toContainText('Server execution feed:');
 await header.locator('.headerConnection summary').click();
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1)).toBe(true);
});

test('V4 mobile order sheet, risk sizing and price alerts',async({page})=>{
 await mockPublicMarket(page);await page.setViewportSize({width:390,height:844});await page.goto('./');
 await page.getByRole('button',{name:'Try demo',exact:true}).click();
 await page.getByRole('button',{name:'Open order ticket',exact:true}).click();
 await expect(page.locator('#order-ticket')).toBeVisible();
 await page.getByLabel('Stop loss (optional)').fill('3990');
 await page.getByText('Position-size calculator',{exact:true}).click();
 await expect(page.getByRole('button',{name:'Use calculated size'})).toBeEnabled();
 await page.getByRole('button',{name:'Use calculated size'}).click();
 await page.getByText('Depth of market',{exact:true}).click();
 await expect(page.locator('.depthGrid')).toContainText('4000.25');
 await page.getByRole('button',{name:'Close order ticket',exact:true}).click();
 await expect(page.locator('#order-ticket')).toBeHidden();
 await page.getByRole('button',{name:'Menu',exact:true}).click();
 await page.getByRole('button',{name:'Notifications',exact:true}).click();
 await page.getByLabel('Alert price').fill('3999');
 await page.getByRole('button',{name:'Create price alert'}).click();
 await expect(page.locator('.alertRow')).toContainText('triggered');
 await expect(page.getByRole('button',{name:'Triggered price alerts: 1'})).toBeVisible();
});

test('V4 chart indicators, secondary timeframe and workspace preferences',async({page})=>{
 await mockPublicMarket(page);await page.goto('./');
 await page.getByRole('button',{name:'BB',exact:true}).click();
 await page.getByRole('button',{name:'VWAP',exact:true}).click();
 await page.getByLabel('Oscillator indicator').selectOption('RSI');
 await expect(page.locator('.oscillatorPanel')).toContainText('RSI 14');
 await page.getByRole('button',{name:'Toggle multi-chart'}).click();
 await page.getByLabel('Secondary timeframe').selectOption('1H');
 await expect(page.locator('.secondaryHost canvas').first()).toBeVisible();
 await page.reload();
 await expect(page.getByRole('button',{name:'BB',exact:true})).toHaveAttribute('aria-pressed','true');
 await expect(page.getByLabel('Secondary timeframe')).toHaveValue('1H');
 await expect(page.getByLabel('Oscillator indicator')).toHaveValue('RSI');
});

test('V4 basket persists after reload and PWA manifest is scoped to Aurum',async({page,request})=>{
 await mockPublicMarket(page);await page.goto('./');
 const manifest=await request.get('./manifest.webmanifest');expect(manifest.ok()).toBe(true);
 const data=await manifest.json();expect(data.scope).toBe('./');expect(data.icons.some((i:any)=>i.sizes==='192x192')).toBe(true);
 await page.getByRole('button',{name:'Try demo',exact:true}).click();
 await page.getByRole('button',{name:'Buy market',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Open positions · 1'})).toBeVisible();
 await page.getByLabel('Select visible positions').check();
 page.once('dialog',d=>d.accept('My basket'));
 await page.getByRole('button',{name:'Save basket',exact:true}).click();
 await expect(page.getByLabel('Saved position basket').locator('option')).toHaveCount(2);
 await page.reload();
 await expect(page.getByLabel('Saved position basket').locator('option')).toHaveCount(2);
 await page.getByLabel('Saved position basket').selectOption({label:'My basket · 1 open'});
 await expect(page.getByRole('button',{name:'Close selected (1)',exact:true})).toBeEnabled();
});
