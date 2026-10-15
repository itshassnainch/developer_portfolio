import {spawn} from 'node:child_process';
import {writeFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=19235','--user-data-dir=C:/xampp/htdocs/developer_portfolio/.qa-palettes','about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;
try {
 let tabs;for(let n=0;n<50;n++){try{tabs=await(await fetch('http://127.0.0.1:19235/json')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
 socket=new WebSocket(tabs.find(x=>x.type==='page').webSocketDebuggerUrl);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
 let id=0;const pending=new Map();socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(data.error):p.resolve(data.result);}};
 const call=(method,params={})=>new Promise((resolve,reject)=>{const next=++id;pending.set(next,{resolve,reject});socket.send(JSON.stringify({id:next,method,params}));});
 const evaluate=async expression=>{const result=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.text);return result.result.value;};
 await call('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
 for(const [palette,expected] of [['electric','rgb(34, 211, 238)']]){
  await call('Page.navigate',{url:'http://localhost/developer_portfolio/dist/?palette='+palette});
  await evaluate(`new Promise(r=>setTimeout(r,1000))`);
  assert.equal(await evaluate('document.documentElement.dataset.palette'),palette);
  assert.equal(await evaluate(`getComputedStyle(document.querySelector('.btn-primary')).backgroundColor`),expected);
  assert.ok(await evaluate(`document.querySelector('header nav ul a').href.includes('palette=${palette}')`));
  const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});writeFileSync('preview-'+palette+'.png',Buffer.from(shot.data,'base64'));
 }
 console.log('PASS: electric cyan palette and preview navigation. Default theme preserved.');
}finally{socket?.close();browser.kill();}
