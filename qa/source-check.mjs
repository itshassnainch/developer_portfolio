import {spawnSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const php='C:/xampp/php/php.exe',base='http://localhost/developer_portfolio';
const content=JSON.parse(readFileSync('cms/data/content.json','utf8')),templates=JSON.parse(readFileSync('cms/data/templates.json','utf8'));
const session=JSON.parse(spawnSync(php,['qa/cms-session.php'],{input:'{}',encoding:'utf8'}).stdout);
let revision=content.revision;
const request=async(action,data)=>{const response=await fetch(base+'/admin/api.php?action='+action,{method:data?'POST':'GET',headers:{Cookie:`portfolio_admin=${session.id}`,'X-CSRF-Token':session.csrf,'Content-Type':'application/json'},body:data?JSON.stringify(data):undefined});const result=await response.json();assert.equal(response.status,200,JSON.stringify(result));return result;};
try{
 const loaded=await request('load');const draft=loaded.content;draft.profile.name='Profile Verification';draft.profile.shortName='Verified';draft.profile.role='PHP Verification Developer';
 const project=structuredClone(draft.collections.projects[0]);project.id='projects-source-test';delete project.case;project.values.f2='Source Verification Project';project.values.f6='https://example.com/';draft.collections.projects.push(project);
 const saved=await request('save',{revision,content:draft});revision=saved.content.revision;
 const home=await(await fetch(base+'/dist/')).text();assert.ok(home.includes('Profile Verification'));assert.ok(home.includes('PHP Verification Developer'));
 const page=await(await fetch(base+'/dist/project.php?project=projects-source-test')).text();assert.ok(page.includes('Source Verification Project'));assert.ok(page.includes('https://example.com/'));assert.ok(page.includes('PHP Verification Developer'));
 for(const route of Object.keys(content.pages)){
   const owner=content.collections.projects.find(item=>item.case?.route===route);
   const url=owner?base+'/dist/project.php?project='+encodeURIComponent(owner.id):base+'/dist/'+(route?route+'/':'');
   const response=await fetch(url);
   if(owner?.visible===false){assert.equal(response.status,404,route);continue;}
   assert.equal(response.status,200,route);const text=await response.text();assert.ok(text.includes('Profile Verification'),route);assert.ok(!/Warning:|Fatal error:/.test(text),route);
 }
 console.log('PASS: admin profile propagation, new case study with live URL, and all current PHP pages.');
}finally{
 const restore=spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'restore',content,templates,revision}),encoding:'utf8'});if(restore.status!==0)throw new Error(restore.stderr);
 spawnSync(php,['qa/cms-session.php'],{input:JSON.stringify({action:'end',id:session.id}),encoding:'utf8'});
}
