import {test,expect,type Route,type WebSocketRoute} from '@playwright/test';

async function mockPublicMarket(page:any){
  await page.routeWebSocket('wss://ws.okx.com/ws/v5/public',(socket:WebSocketRoute)=>socket.close());
  await page.route('**/api/market.php**',async (route:Route)=>{
    const action=new URL(route.request().url()).searchParams.get('action');
    if(action==='instrument'){
      await route.fulfill({json:{symbol:'XAUUSD',instrument:'XAU-USDT-SWAP',quoteCurrency:'USDT',proxy:true,data:{provider:'OKX',instrument:'XAU-USDT-SWAP',state:'live',contractType:'linear',contractValue:0.001,contractValueCurrency:'XAU',lotSize:1,minimumContracts:1,tickSize:0.1,settlementCurrency:'USDT',contractsPerOunce:1000,simulatedLotOunces:100,contractsPerSimulatedLot:100000}}});
    }else if(action==='candles'){
      const now=Math.floor(Date.now()/1000/60)*60;
      const data=Array.from({length:60},(_,i)=>{const p=4000+i*.2;return {time:now-(59-i)*60,open:p,high:p+1,low:p-1,close:p+.2,confirmed:true}});
      await route.fulfill({json:{symbol:'XAUUSD',instrument:'XAU-USDT-SWAP',quoteCurrency:'USDT',proxy:true,data}});
    }else await route.fulfill({status:400,json:{error:'Unsupported test action'}});
  });
}
async function register(page:any,email:string){
  await page.goto('./');
  await page.getByLabel('Email',{exact:true}).fill(email);
  await page.getByLabel('Password (10+ characters)').fill('Aurum-E2E-Password-2026');
  await page.getByLabel('Virtual account').selectOption('5000');
  await page.getByRole('button',{name:'Create account'}).click();
  await expect(page.getByText('$5,000.00').first()).toBeVisible();
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
  await expect(page.getByText('Admin action completed')).toBeVisible();
});
