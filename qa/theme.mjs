import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=19231','--user-data-dir=C:/xampp/htdocs/developer_portfolio/.qa-theme','about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;
try{
 let tabs;for(let n=0;n<50;n++){try{tabs=await(await fetch('http://127.0.0.1:19231/json')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
 socket=new WebSocket(tabs.find(x=>x.type==='page').webSocketDebuggerUrl);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
 let id=0;const pending=new Map();socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(data.error):p.resolve(data.result);}};
 const call=(method,params={})=>new Promise((resolve,reject)=>{const next=++id;pending.set(next,{resolve,reject});socket.send(JSON.stringify({id:next,method,params}));});
 const evaluate=async expression=>{const result=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.text);return result.result.value;};
 const ready=()=>evaluate(`new Promise((r,j)=>{let n=0;const t=setInterval(()=>{if(document.querySelector('.theme-toggle')){clearInterval(t);r(true);}else if(n++>100){clearInterval(t);j(new Error('Page timed out'));}},100);})`);
 await call('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await call('Page.navigate',{url:'http://localhost/developer_portfolio/dist/'});await ready();
 await evaluate(`localStorage.removeItem('portfolio-theme')`);await call('Page.reload');await new Promise(r=>setTimeout(r,400));await ready();
 assert.equal(await evaluate('document.documentElement.dataset.theme'),'dark');
 await evaluate(`document.querySelector('.theme-toggle').click()`);assert.equal(await evaluate('document.documentElement.dataset.theme'),'light');
 assert.equal(await evaluate(`localStorage.getItem('portfolio-theme')`),'light');
 assert.ok(await evaluate(`getComputedStyle(document.body).backgroundColor.match(/[0-9]+/g).slice(0,3).every(value=>Number(value)>230)`));
 await call('Page.navigate',{url:'http://localhost/developer_portfolio/dist/contact/'});await new Promise(r=>setTimeout(r,400));await ready();assert.equal(await evaluate('document.documentElement.dataset.theme'),'light');
 assert.equal(await evaluate(`getComputedStyle(document.querySelector('.field')).color`),'rgb(15, 23, 42)');
 await call('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});assert.equal(await evaluate('document.documentElement.scrollWidth'),390);
 await evaluate(`document.querySelector('[aria-label="Menu"]').click()`);assert.equal(await evaluate(`document.querySelector('[aria-label="Menu"]').getAttribute('aria-expanded')`),'true');
 await evaluate(`document.querySelector('.theme-toggle').click()`);assert.equal(await evaluate('document.documentElement.dataset.theme'),'dark');
 await call('Page.reload');await new Promise(r=>setTimeout(r,400));await ready();assert.equal(await evaluate('document.documentElement.dataset.theme'),'dark');
 console.log('PASS: default dark, light toggle, saved preference across pages/reload, readable contact form, mobile layout/menu, and switching back to dark.');
}finally{socket?.close();browser.kill();}
