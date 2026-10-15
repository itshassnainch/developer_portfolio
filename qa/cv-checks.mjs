import {spawn,spawnSync} from 'node:child_process';
import {writeFileSync,readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const root='http://localhost/developer_portfolio',php='C:/xampp/php/php.exe';
const original=JSON.parse(readFileSync('cms/data/content.json','utf8')),templates=JSON.parse(readFileSync('cms/data/templates.json','utf8'));
const session=JSON.parse(spawnSync(php,['qa/cms-session.php'],{input:'{}',encoding:'utf8'}).stdout);
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--remote-debugging-port=19229','--user-data-dir=C:/xampp/htdocs/developer_portfolio/.qa-cv','about:blank'],{windowsHide:true,stdio:'ignore'});
let socket,revision=original.revision;
try {
 let tabs;for(let i=0;i<50;i++){try{tabs=await(await fetch('http://127.0.0.1:19229/json')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
 if(!tabs)throw new Error('Browser unavailable');socket=new WebSocket(tabs.find(x=>x.type==='page').webSocketDebuggerUrl);await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
 let id=0;const pending=new Map();socket.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(data.error):p.resolve(data.result);}};
 const call=(method,params={})=>new Promise((resolve,reject)=>{const next=++id;pending.set(next,{resolve,reject});socket.send(JSON.stringify({id:next,method,params}));});
 const evaluate=async(expression)=>{const result=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.text);return result.result.value;};
 const wait=condition=>evaluate(`new Promise((resolve,reject)=>{let n=0;const t=setInterval(()=>{if(${condition}){clearInterval(t);resolve(true);}else if(n++>100){clearInterval(t);reject(new Error('Timed out'));}},100);})`);
 await call('Page.navigate',{url:root+'/dist/uploads/hassnain-ali-cv.html'});await wait(`document.querySelector('.footer')`);
 const pdf=await call('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,displayHeaderFooter:false});writeFileSync('dist/uploads/hassnain-ali-cv.pdf',Buffer.from(pdf.data,'base64'));
 assert.ok(readFileSync('dist/uploads/hassnain-ali-cv.pdf').subarray(0,5).toString()==='%PDF-');
 assert.equal(original.collections.projects.length,1);assert.equal(original.collections.experience.length,1);assert.equal(original.collections.education.length,3);assert.equal(original.profile.photo,'uploads/professional-portrait-01a1395d.png');
 for(const route of ['','about/','skills/','projects/','services/','experience/','education/','contact/','projects/rest-api-task-manager/']){
   const response=await fetch(root+'/dist/'+route);assert.equal(response.status,200,route);const html=await response.text();assert.ok(!/Fatal error:|Warning:|data-cms-block=/.test(html),route);
   const body=html.split('<body')[1].replace(/<script[\s\S]*?<\/script>/g,'').replace(/<[^>]+>/g,' ');
   assert.ok(!/Operator Labs|Raheem Ud Din|Full Stack JavaScript|React\.js|Next\.js|3\+ years/.test(body),'Old content on '+route);
 }
 assert.equal((await fetch(root+'/dist/projects/operator-labs-website/')).status,404);
 const home=await(await fetch(root+'/dist/')).text();for(const value of ['Hassnain Ali','Junior PHP Developer','REST API Task Manager','PMAS Arid Agricultural University','ha4216845@gmail.com'])assert.ok(home.includes(value),value);
 const education=await(await fetch(root+'/dist/education/')).text();for(const value of ['CGPA: 2.8','Marks: 79%','Marks: 74%','Front-End Developer Internship Certificate','Snooker'])assert.ok(education.includes(value),value);
 await call('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});await call('Page.navigate',{url:root+'/dist/'});await wait(`document.querySelector('img')?.complete`);await evaluate(`new Promise(r=>setTimeout(r,800))`);
 let shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});writeFileSync('preview-cv-home.png',Buffer.from(shot.data,'base64'));
 await call('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});assert.equal(await evaluate('document.documentElement.scrollWidth'),390);
 shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});writeFileSync('preview-cv-mobile.png',Buffer.from(shot.data,'base64'));
 await call('Network.setCookie',{name:'portfolio_admin',value:session.id,url:root+'/admin/',path:'/developer_portfolio/admin/',httpOnly:true,sameSite:'Strict'});
 await call('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});await call('Page.navigate',{url:root+'/admin/'});await wait(`document.querySelector('.welcome')`);
 await evaluate(`[...document.querySelectorAll('#navigation button')].find(x=>x.textContent==='Education & credentials').click()`);assert.equal(await evaluate(`document.querySelectorAll('.collection-item').length`),3);
 await evaluate(`(()=>{const label=[...document.querySelectorAll('.collection-item label')].find(x=>x.firstChild.textContent==='Result');const input=label.querySelector('input');input.value='CGPA: 2.8 (verification)';input.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('#save-button').click();})()`);
 await wait(`document.querySelector('#notice').textContent.includes('Saved. Your local website is updated.')`);revision++;
 assert.ok((await(await fetch(root+'/dist/education/')).text()).includes('CGPA: 2.8 (verification)'));
 const authHeaders={Cookie:`portfolio_admin=${session.id}`,'X-CSRF-Token':session.csrf,'Content-Type':'application/json'};
 const current=await(await fetch(root+'/admin/api.php',{headers:authHeaders})).json();const draft=current.content;
 const newProject=structuredClone(draft.collections.projects[0]);newProject.id='projects-cv-qa';delete newProject.case;newProject.values.f2='Temporary CV Admin Project';newProject.values.f3='REST API learning';newProject.values.f1='Learning project';draft.collections.projects.push(newProject);
 const saveResponse=await fetch(root+'/admin/api.php?action=save',{method:'POST',headers:authHeaders,body:JSON.stringify({revision,content:draft})});const saveData=await saveResponse.json();assert.equal(saveResponse.status,200,JSON.stringify(saveData));revision=saveData.content.revision;
 const newCase=await(await fetch(root+'/dist/project.php?project=projects-cv-qa')).text();assert.ok(newCase.includes('Temporary CV Admin Project'));assert.ok(newCase.includes('Junior PHP Developer'));assert.ok(!newCase.includes('href="/developer_portfolio/dist/" class="btn-primary btn-block-mobile text-sm"'));
 console.log('PASS: CV content on all nine pages, old projects removed, education credentials and marks, preserved glasses portrait, generated PDF, mobile layout, and education save through admin.');
}finally {
 socket?.close();browser.kill();
 const restored=spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'restore',content:original,templates,revision}),encoding:'utf8'});if(restored.status!==0)throw new Error(restored.stderr);console.log('Restored final CV content after verification.');
 spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'end',id:session.id}),encoding:'utf8'});
}
