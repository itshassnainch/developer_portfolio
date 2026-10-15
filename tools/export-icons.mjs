import {spawn} from 'node:child_process';
import {writeFileSync} from 'node:fs';
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=19239','--user-data-dir=C:/xampp/htdocs/developer_portfolio/.qa-icons','about:blank'],{windowsHide:true,stdio:'ignore'});
let socket;
try{
 let tabs;for(let n=0;n<50;n++){try{tabs=await(await fetch('http://127.0.0.1:19239/json')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
 socket=new WebSocket(tabs.find(x=>x.type==='page').webSocketDebuggerUrl);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
 let id=0;const pending=new Map();socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(data.error):p.resolve(data.result);}};
 const call=(method,params={})=>new Promise((resolve,reject)=>{const next=++id;pending.set(next,{resolve,reject});socket.send(JSON.stringify({id:next,method,params}));});
 await call('Page.navigate',{url:'http://localhost/developer_portfolio/dist/'});await new Promise(r=>setTimeout(r,400));
 const result=await call('Runtime.evaluate',{expression:`(async()=>{const image=new Image();image.src='/developer_portfolio/dist/icon.svg?fresh='+Date.now();await image.decode();return [256,180].map(size=>{const canvas=document.createElement('canvas');canvas.width=canvas.height=size;canvas.getContext('2d').drawImage(image,0,0,size,size);return canvas.toDataURL('image/png').split(',')[1];});})()`,awaitPromise:true,returnByValue:true});
 if(result.exceptionDetails)throw new Error(result.exceptionDetails.text);
 const [favicon,apple]=result.result.value.map(value=>Buffer.from(value,'base64'));writeFileSync('dist/apple-icon.png',apple);
 const directory=Buffer.alloc(22);directory.writeUInt16LE(1,2);directory.writeUInt16LE(1,4);directory.writeUInt16LE(1,10);directory.writeUInt16LE(32,12);directory.writeUInt32LE(favicon.length,14);directory.writeUInt32LE(22,18);writeFileSync('dist/favicon.ico',Buffer.concat([directory,favicon]));
 console.log('Exported cyan HA favicon and Apple icon from SVG.');
}finally{socket?.close();browser.kill();}
