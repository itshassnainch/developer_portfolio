import {spawn,spawnSync} from 'node:child_process';
import {writeFileSync,readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const php='C:/xampp/php/php.exe';const session=JSON.parse(spawnSync(php,['qa/cms-session.php'],{input:'{}',encoding:'utf8'}).stdout);
const content=JSON.parse(readFileSync('cms/data/content.json','utf8')),templates=JSON.parse(readFileSync('cms/data/templates.json','utf8'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=19225','--user-data-dir=C:/xampp/htdocs/developer_portfolio/.qa-admin','about:blank'],{windowsHide:true,stdio:'ignore'});
let socket,revision=content.revision;
try{
 let tabs;for(let i=0;i<50;i++){try{tabs=await(await fetch('http://127.0.0.1:19225/json')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
 if(!tabs)throw new Error('Browser unavailable');socket=new WebSocket(tabs.find(x=>x.type==='page').webSocketDebuggerUrl);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
 let id=0;const requests=new Map();socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=requests.get(data.id);requests.delete(data.id);data.error?p.reject(data.error):p.resolve(data.result);}};
 const call=(method,params={})=>new Promise((resolve,reject)=>{const next=++id;requests.set(next,{resolve,reject});socket.send(JSON.stringify({id:next,method,params}));});
 const evaluate=async(expression)=>{const result=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.text);return result.result.value;};
 const wait=async(condition)=>evaluate(`new Promise((resolve,reject)=>{let n=0;const t=setInterval(()=>{if(${condition}){clearInterval(t);resolve(true);}else if(n++>100){clearInterval(t);reject(new Error('Browser condition timed out'));}},100);})`);
 await call('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await call('Network.setCookie',{name:'portfolio_admin',value:session.id,url:'http://localhost/developer_portfolio/admin/',path:'/developer_portfolio/admin/',httpOnly:true,sameSite:'Strict'});
 await call('Page.navigate',{url:'http://localhost/developer_portfolio/admin/'});await wait(`document.querySelector('#navigation button')&&document.querySelector('.welcome')`);
 let screenshot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});writeFileSync('preview-admin.png',Buffer.from(screenshot.data,'base64'));
 await evaluate(`[...document.querySelectorAll('#navigation button')].find(x=>x.textContent==='Profile & uploads').click()`);
 await evaluate(`(()=>{const label=[...document.querySelectorAll('#editor label')].find(x=>x.firstChild.textContent==='Full name');const input=label.querySelector('input');input.value='Browser Admin Verification';input.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('#save-button').click();})()`);
 await wait(`document.querySelector('#notice').textContent.includes('Saved. Your local website is updated.')`);revision++;
 const rendered=await(await fetch('http://localhost/developer_portfolio/dist/')).text();assert.ok(rendered.includes('Browser Admin Verification'));
 await evaluate(`[...document.querySelectorAll('#navigation button')].find(x=>x.textContent==='Projects & case studies').click()`);assert.equal(await evaluate(`document.querySelectorAll('.collection-item').length`),6);
 assert.equal(await evaluate(`document.querySelectorAll('.collection-item details').length`),6);
 await call('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
 await evaluate(`[...document.querySelectorAll('#navigation button')].find(x=>x.textContent==='Overview').click()`);
 assert.equal(await evaluate(`document.documentElement.scrollWidth`),390);
 screenshot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});writeFileSync('preview-admin-mobile.png',Buffer.from(screenshot.data,'base64'));
 console.log('PASS: admin dashboard, browser editing & saving, all six case-study editors, mobile admin without page overflow.');
}finally{
 socket?.close();browser.kill();
 const restore=spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'restore',content,templates,revision}),encoding:'utf8'});if(restore.status!==0)throw new Error(restore.stderr);console.log(restore.stdout.trim());
 spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'end',id:session.id}),encoding:'utf8'});
}
