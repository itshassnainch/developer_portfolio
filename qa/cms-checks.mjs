import {spawnSync} from 'node:child_process';
import {readFileSync,unlinkSync} from 'node:fs';
import assert from 'node:assert/strict';
const origin='http://localhost/developer_portfolio';
const php='C:/xampp/php/php.exe';
const originalContent=JSON.parse(readFileSync('cms/data/content.json','utf8'));
const originalTemplates=JSON.parse(readFileSync('cms/data/templates.json','utf8'));
const sessionResult=spawnSync(php,['qa/cms-session.php'],{input:'{}',encoding:'utf8'});
if(sessionResult.status!==0)throw new Error(sessionResult.stderr);
const session=JSON.parse(sessionResult.stdout);
const headers={Cookie:`portfolio_admin=${session.id}`,'X-CSRF-Token':session.csrf};
let revision=originalContent.revision,uploaded;
const request=async(action,body,extra={})=>{
  const response=await fetch(`${origin}/admin/api.php?action=${action}`,{method:body===undefined?'GET':'POST',headers:{...headers,...(body instanceof FormData?{}:{'Content-Type':'application/json'}),...extra},body:body===undefined?undefined:body instanceof FormData?body:JSON.stringify(body)});
  const text=await response.text();let data;try{data=JSON.parse(text);}catch{throw new Error(`Unexpected API response (${response.status}): ${text.slice(0,300)}`);}return{status:response.status,data};
};
try{
  assert.equal((await fetch(`${origin}/admin/api.php`)).status,401);
  assert.equal((await fetch(`${origin}/cms/data/content.json`)).status,403);
  const loaded=await request('load');assert.equal(loaded.status,200);const state=structuredClone(loaded.data.content);
  assert.equal(state.collections.projects.length,6);assert.equal(state.collections.skills.length,3);
  assert.equal((await request('save',{revision,content:state},{'X-CSRF-Token':'invalid'})).status,403);
  const routes=['','about/','skills/','projects/','services/','experience/','contact/',...Object.keys(state.pages).filter(x=>x.startsWith('projects/')).map(x=>x+'/')];
  for(const route of routes){const response=await fetch(`${origin}/dist/${route}`);assert.equal(response.status,200,route);const text=await response.text();assert.ok(!/Warning:|Fatal error:|data-cms-block=/.test(text),route);}
  state.profile.name='CMS Verification <safe> & Name';state.profile.shortName='CMS';state.profile.email='cms@example.com';state.profile.phone='+92 333 1234567';state.profile.whatsapp='923331234567';
  const skill=structuredClone(state.collections.skills[0]);skill.id='item-cms-skill-test';
  const skillSchema=loaded.data.schema.items[skill.template].fields;
  for(const [key,field]of Object.entries(skillSchema))skill.values[key]=field.type==='lines'?'CMS integration skill\nAnother skill':'CMS skill category';state.collections.skills.push(skill);
  state.visibility={...(state.visibility||{}),services:false};
  const project=structuredClone(state.collections.projects[0]);project.id='item-cms-project-test';delete project.case;
  const projectSchema=loaded.data.schema.items[project.template].fields;
  for(const [key,field]of Object.entries(projectSchema)){
    if(field.label.startsWith('Title —'))project.values[key]='CMS Temporary Project';
    else if(field.type==='textarea')project.values[key]='Temporary CMS project description <img src=x onerror=alert(1)>.';
    else if(field.type==='lines')project.values[key]='PHP\nJavaScript';
    else if(field.type==='url'&&field.default.startsWith('http'))project.values[key]='https://example.com/';
  }
  state.collections.projects.unshift(project);
  state.collections.projects[1].visible=false;
  const saved=await request('save',{revision,content:state});assert.equal(saved.status,200,JSON.stringify(saved.data));revision=saved.data.content.revision;
  assert.equal((await request('save',{revision:revision-1,content:state})).status,409);
  const home=await(await fetch(`${origin}/dist/`)).text();const skills=await(await fetch(`${origin}/dist/skills/`)).text();
  assert.ok(home.includes('CMS integration skill')&&skills.includes('CMS integration skill'));
  assert.ok(home.includes('CMS Verification &lt;safe&gt; &amp; Name'));
  assert.ok(!home.includes('How I can help your team.'));
  assert.ok(!home.includes('<img src=x onerror=alert(1)>'));
  assert.ok(home.includes('CMS Temporary Project'));
  assert.ok(home.includes('project.php?project=item-cms-project-test'));
  const caseStudy=await(await fetch(`${origin}/dist/project.php?project=item-cms-project-test`)).text();assert.ok(caseStudy.includes('CMS Temporary Project'));
  assert.equal((await fetch(`${origin}/dist/${state.collections.projects[1].case.route}/`)).status,404);
  const bad=new FormData();bad.append('type','photo');bad.append('revision',revision);bad.append('file',new Blob(['<?php echo 1; ?>'],{type:'image/png'}),'invalid.png');assert.equal((await request('upload',bad)).status,400);
  const upload=new FormData();upload.append('type','photo');upload.append('revision',revision);upload.append('file',new Blob([readFileSync('dist/homeimg.png')],{type:'image/png'}),'portrait.png');
  const photo=await request('upload',upload);assert.equal(photo.status,200,JSON.stringify(photo.data));revision=photo.data.revision;uploaded=photo.data.path;
  assert.ok((await(await fetch(`${origin}/dist/`)).text()).includes(uploaded));
  assert.equal((await fetch(`${origin}/dist/contact.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:'CMS test',email:'invalid',message:'Test message'})})).status,400);
  const sent=await fetch(`${origin}/dist/contact.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:'CMS integration verification',email:'test@example.com',subject:'Temporary verification message',message:'CMS inbox integration test.'})});assert.equal(sent.status,200);
  const inbox=await request('messages');const message=inbox.data.messages.find(x=>x.name==='CMS integration verification');assert.ok(message);
  assert.equal((await request('message',{id:message.id,operation:'read'})).status,200);
  assert.equal((await request('message',{id:message.id,operation:'delete'})).status,200);
  console.log('PASS: authentication, CSRF, protected storage, all 13 pages, shared edits, item creation, section visibility, safe text rendering, conflict detection, project case study, image upload, upload rejection and contact inbox.');
}finally{
  const restored=spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'restore',content:originalContent,templates:originalTemplates,revision}),encoding:'utf8'});
  if(restored.status!==0)throw new Error(restored.stderr);console.log(restored.stdout.trim());
  if(uploaded&&/^uploads\/photo-[a-f0-9]+\.(png|jpg|webp)$/.test(uploaded))unlinkSync('dist/'+uploaded);
  spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'end',id:session.id}),encoding:'utf8'});
}
