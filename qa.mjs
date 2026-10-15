import {spawn} from 'node:child_process';
import {writeFileSync} from 'node:fs';
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=19223','--user-data-dir=C:/xampp/htdocs/developer_portfolio/.qa-cdp','about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;
try {
  let tabs;
  for(let i=0;i<50;i++) {try {tabs=await (await fetch('http://127.0.0.1:19223/json')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
  if(!tabs) throw new Error('Browser did not start');
  socket=new WebSocket(tabs.find(x=>x.type==='page').webSocketDebuggerUrl);
  await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
  let id=0; const pending=new Map();
  socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(data.error):p.resolve(data.result);}};
  const call=(method,params={})=>new Promise((resolve,reject)=>{const request=++id;pending.set(request,{resolve,reject});socket.send(JSON.stringify({id:request,method,params}));});
  await call('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  await call('Page.navigate',{url:'http://localhost/developer_portfolio/dist/'});
  await new Promise(r=>setTimeout(r,1800));
  const result=await call('Runtime.evaluate',{expression:`JSON.stringify({viewport:innerWidth,scrollWidth:document.documentElement.scrollWidth,heroWidth:document.querySelector('main .container-page').getBoundingClientRect().width,menuButton:!!document.querySelector('[aria-label="Menu"]')})`,returnByValue:true});
  console.log(result.result.value);
  const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
  writeFileSync('preview-mobile-verified.png',Buffer.from(shot.data,'base64'));
  const menu=await call('Runtime.evaluate',{expression:`document.querySelector('[aria-label="Menu"]').click();JSON.stringify({expanded:document.querySelector('[aria-label="Menu"]').getAttribute('aria-expanded'),links:document.querySelectorAll('.mobile-navigation a').length})`,returnByValue:true});
  console.log(menu.result.value);
} finally {socket?.close();browser.kill();}
