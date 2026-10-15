(() => {
  'use strict';
  const editor=document.querySelector('#editor'), title=document.querySelector('#page-title'), saveButton=document.querySelector('#save-button'), saveState=document.querySelector('#save-state'), notice=document.querySelector('#notice');
  const tabs=[['dashboard','Overview'],['profile','Profile & uploads'],['home','Home page'],['about','About'],['skills','Skills'],['projects','Projects & case studies'],['services','Services'],['experience','Experience'],['contact','Contact page'],['header','Navigation'],['footer','Footer & social links'],['seo','Page titles & SEO'],['messages','Messages'],['account','Password']];
  let state, schema, pages, active='dashboard', dirty=false, busy=false;
  function element(tag,className,text){const node=document.createElement(tag);if(className)node.className=className;if(text!==undefined)node.textContent=text;return node;}
  function button(text,className,onClick){const node=element('button',className,text);node.type='button';node.addEventListener('click',onClick);return node;}
  function report(message,error=false){notice.hidden=false;notice.className=error?'error':'';notice.textContent=message;}
  function changed(){dirty=true;saveButton.disabled=busy;saveState.textContent='Unsaved changes';saveState.classList.add('save-indicator');}
  function setSaved(){dirty=false;saveButton.disabled=true;saveState.textContent='All changes saved';saveState.classList.remove('save-indicator');}
  async function api(action,body,method='POST'){
    const options={method,headers:{'X-CSRF-Token':window.adminConfig.csrf},credentials:'same-origin'};
    if(body instanceof FormData)options.body=body;else if(body!==undefined){options.headers['Content-Type']='application/json';options.body=JSON.stringify(body);}
    const response=await fetch(`api.php?action=${encodeURIComponent(action)}`,options);
    let data;try{data=await response.json();}catch{throw new Error('The server could not respond. Check that Apache and PHP are running.');}
    if(!response.ok)throw new Error(data.error||'Request failed.');return data;
  }
  function adopt(data){state=data.content;schema=data.schema;pages=data.pages;}
  async function save(){
    if(busy||!dirty)return true;busy=true;document.querySelector('.workspace').inert=true;document.querySelector('.sidebar').inert=true;saveState.textContent='Saving…';
    try{adopt(await api('save',{revision:state.revision,content:state}));setSaved();report('Saved. Your local website is updated.');render();return true;}
    catch(error){report(error.message,true);saveState.textContent='Changes not saved';return false;}
    finally{busy=false;document.querySelector('.workspace').inert=false;document.querySelector('.sidebar').inert=false;saveButton.disabled=!dirty;}
  }
  saveButton.addEventListener('click',save);
  addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
  const nav=document.querySelector('#navigation');
  tabs.forEach(([id,label])=>nav.append(button(label,'',()=>select(id))));
  function select(id){active=id;notice.hidden=true;render();window.scrollTo({top:0,behavior:'smooth'});}
  function panel(name,description){const node=element('div','panel');const heading=element('div','panel-heading');const copy=element('div');copy.append(element('h2','',name));if(description)copy.append(element('p','muted',description));heading.append(copy);node.append(heading);return node;}
  function inputField(label,value,setter,type='text',help){const wrapper=element('label',type==='textarea'||type==='lines'||type==='code'?'wide':'',label);const isArea=['textarea','lines','code'].includes(type);const input=element(isArea?'textarea':'input');if(!isArea)input.type=type==='url'?'text':type;input.value=value??'';input.maxLength=30000;if(isArea)input.rows=type==='lines'?5:4;input.addEventListener('input',()=>{setter(input.value);changed();});wrapper.append(input);if(help)wrapper.append(element('small','field-help',help));return wrapper;}
  function modelFields(model,values){const grid=element('div','fields');Object.entries(model.fields).forEach(([key,field])=>grid.append(inputField(field.label,values[key],v=>values[key]=v,field.type,field.type==='lines'?'Har line par ek item. Lines add ya remove kar sakte hain.':field.type==='url'?'Website URL, email link, or local page path.':undefined)));return grid;}
  function block(id,name){if(!schema.blocks[id])return;const node=panel(name||schema.blocks[id].label,'Text change karein, phir Save changes dabayein.');const toggle=element('label','toggle-label');const checkbox=element('input');checkbox.type='checkbox';checkbox.checked=state.visibility?.[id]!==false;checkbox.addEventListener('change',()=>{state.visibility??={};state.visibility[id]=checkbox.checked;changed();});toggle.append(checkbox,document.createTextNode('Show section'));node.querySelector('.panel-heading').append(toggle);node.append(modelFields(schema.blocks[id],state.blocks[id]));editor.append(node);}
  function itemTitle(item){const fields=schema.items[item.template].fields;let key=Object.keys(fields).find(k=>fields[k].label.startsWith('Title —'));if(!key)key=Object.keys(fields).find(k=>fields[k].type==='text');return (item.values[key]||'Untitled item').trim();}
  function collection(id,name){
    const list=state.collections[id];const outer=panel(name,'Items add, edit, reorder, hide ya delete kar sakte hain.');
    const add=button('+ Add item','primary small',()=>{
      const template=Object.keys(schema.items).find(key=>key.startsWith(id+'-'));if(!template)return;
      const values={};Object.entries(schema.items[template].fields).forEach(([key,field])=>{values[key]=field.label.startsWith('Title —')?'New '+id.slice(0,-1):'';});
      list.push({id:'item-'+crypto.randomUUID().replaceAll('-',''),template,visible:true,values});changed();render();
    });outer.querySelector('.panel-heading').append(add);
    if(!list.length)outer.append(element('div','empty','No items yet. Add your first item.'));
    list.forEach((item,index)=>{
      const card=element('div','collection-item');const header=element('div','item-header');const itemHeading=element('div');itemHeading.append(element('h3','',itemTitle(item)),element('span','',`Item ${index+1}`));
      const actions=element('div','item-actions');const toggle=element('label','toggle-label');const checkbox=element('input');checkbox.type='checkbox';checkbox.checked=item.visible;checkbox.addEventListener('change',()=>{item.visible=checkbox.checked;changed();});toggle.append(checkbox,document.createTextNode('Visible'));actions.append(toggle);
      const up=button('↑','secondary small',()=>{[list[index-1],list[index]]=[list[index],list[index-1]];changed();render();});up.disabled=index===0;up.setAttribute('aria-label','Move item up');
      const down=button('↓','secondary small',()=>{[list[index+1],list[index]]=[list[index],list[index+1]];changed();render();});down.disabled=index===list.length-1;down.setAttribute('aria-label','Move item down');
      actions.append(up,down,button('Delete','danger small',()=>{if(confirm('Delete this item? It will be removed from the website when you save.')){list.splice(index,1);changed();render();}}));
      header.append(itemHeading,actions);card.append(header);const body=element('div','item-body');body.append(modelFields(schema.items[item.template],item.values));
      if(id==='projects'){
        let route=item.case?.route;
        if(!route){const fields=schema.items[item.template].fields;for(const [key,field] of Object.entries(fields))if(field.type==='url'&&field.default.includes('projects/'))route=item.values[key]?.replace(/^(?:\.\/|\.\.\/)+/,'').replace(/\/$/,'');}
        if(route&&schema.blocks[route]){const details=element('details');details.append(element('summary','','Edit complete case study'));details.append(modelFields(schema.blocks[route],state.blocks[route]));body.append(details);}
        else body.append(element('p','field-help','Save this new project first. Then its complete case-study editor will appear here.'));
      }
      card.append(body);outer.append(card);
    });editor.append(outer);
  }
  function profile(){
    const node=panel('Your details','Yahan ki details poori website par update hongi.');const fields=element('div','fields');
    const names={name:'Full name',shortName:'Brand / short name',role:'Professional title',email:'Contact email',phone:'Phone number',whatsapp:'WhatsApp number (country code + digits)',linkedin:'LinkedIn URL',github:'GitHub URL',facebook:'Facebook URL'};
    Object.entries(names).forEach(([key,label])=>fields.append(inputField(label,state.profile[key],v=>state.profile[key]=v,key==='email'?'email':'text')));node.append(fields);editor.append(node);
    const uploads=panel('Photo & resume','Photo: JPG, PNG or WebP, up to 4 MB. Resume: PDF, up to 10 MB. Uploads update immediately.');
    ['photo','resume'].forEach(type=>{
      const row=element('div','upload-grid');if(type==='photo'){const img=element('img','portrait-preview');img.src='../dist/'+state.profile.photo;img.alt='Current profile photo';row.append(img);}else{const link=element('a','secondary','↗ View current resume');link.href='../dist/'+state.profile.resume;link.target='_blank';link.rel='noopener';row.append(link);}
      const form=element('div','upload-form');const input=element('input');input.type='file';input.accept=type==='photo'?'image/jpeg,image/png,image/webp':'application/pdf';input.setAttribute('aria-label',type==='photo'?'Choose profile photo':'Choose PDF resume');
      const upload=button(type==='photo'?'Upload photo':'Upload resume','secondary',async()=>{if(!input.files[0]){report('Choose a file first.',true);return;}upload.disabled=true;const data=new FormData();data.append('file',input.files[0]);data.append('type',type);data.append('revision',state.revision);try{const response=await api('upload',data);state.profile[type]=response.path;state.revision=response.revision;report('Uploaded. Your local website is updated.');render();}catch(error){report(error.message,true);}finally{upload.disabled=false;}});form.append(input,upload);row.append(form);uploads.append(row);
    });editor.append(uploads);
  }
  function overview(){
    const node=panel('Make it yours.','');node.classList.add('welcome');node.append(element('p','','Naam, content, projects aur photo sab yahan se change karein. Code edit karne ki zarurat nahi.'));const links=element('div','quick-links');links.append(button('Edit my profile','primary',()=>select('profile')),button('Manage projects','secondary',()=>select('projects')));const view=element('a','secondary','↗ View website');view.href='../dist/';view.target='_blank';view.rel='noopener';links.append(view);node.append(links);editor.append(node);
    const stats=element('div','stats');[['projects','Projects'],['skills','Skill groups'],['services','Services'],['experience','Experience entries']].forEach(([id,label])=>{const stat=element('div','stat');stat.append(element('strong','',String(state.collections[id]?.length||0)),element('span','',label));stats.append(stat);});editor.append(stats);
    const guide=panel('How to update your website','Changes are saved on this XAMPP computer.');const list=element('ol','instruction-list');['Profile mein apna naam, email, social links, photo aur resume update karein.','Sections se apna text edit karein. List mein har line par ek item likhein.','Skills, projects, services aur experience mein Add item se nayi entries banayein.','Save changes dabayein, phir website refresh karein. Home aur related pages dono update honge.','Contact form ke messages yahan Messages tab mein aayenge.'].forEach(text=>list.append(element('li','',text)));guide.append(list);editor.append(guide);
  }
  async function messages(){
    editor.append(element('p','muted','Loading messages…'));
    try{const data=await api('messages',undefined,'GET');if(active!=='messages')return;editor.replaceChildren();const top=panel('Contact inbox','Visitors ke messages yahan save hote hain. Reply ke liye sender ka email link kholein.');top.querySelector('.panel-heading').append(button('Refresh','secondary small',()=>messages()));editor.append(top);
      if(!data.messages.length){editor.append(element('div','empty','No messages yet. Messages from your contact page will appear here.'));return;}
      data.messages.forEach(message=>{const card=element('article','message'+(message.read?'':' unread'));const heading=element('div','panel-heading');const who=element('div');who.append(element('h3','',message.subject||'Project enquiry'));const meta=element('div','message-meta',`${message.name} · ${new Date(message.created).toLocaleString()}`);who.append(meta);const email=element('a','',message.email);email.href='mailto:'+message.email;who.append(email);const actions=element('div','item-actions');
        const update=async(operation)=>{if(operation==='delete'&&!confirm('Delete this message permanently?'))return;try{await api('message',{id:message.id,operation});messages();}catch(error){report(error.message,true);}};
        if(!message.read)actions.append(button('Mark read','secondary small',()=>update('read')));actions.append(button('Delete','danger small',()=>update('delete')));heading.append(who,actions);card.append(heading,element('p','',message.message));editor.append(card);});
    }catch(error){if(active==='messages'){editor.replaceChildren();report(error.message,true);}}
  }
  function seo(){Object.entries(state.pages).forEach(([route,value])=>{const node=panel(pages[route]?.label||route||'Home');const fields=element('div','fields');fields.append(inputField('Browser tab / search title',value.title,v=>value.title=v),inputField('Search description',value.description,v=>value.description=v,'textarea'));node.append(fields);editor.append(node);});}
  function account(){const node=panel('Change admin password','Use at least 8 characters.');const form=element('form','fields');const current=element('input');current.type='password';current.required=true;current.autocomplete='current-password';const fresh=element('input');fresh.type='password';fresh.required=true;fresh.minLength=8;fresh.autocomplete='new-password';const confirmInput=element('input');confirmInput.type='password';confirmInput.required=true;confirmInput.minLength=8;confirmInput.autocomplete='new-password';[['Current password',current],['New password',fresh],['Confirm new password',confirmInput]].forEach(([label,input])=>{const field=element('label','',label);field.append(input);form.append(field);});const submit=element('button','primary','Update password');submit.type='submit';form.append(submit);form.addEventListener('submit',async event=>{event.preventDefault();if(fresh.value!==confirmInput.value){report('Passwords do not match.',true);return;}submit.disabled=true;try{await api('password',{current:current.value,password:fresh.value});form.reset();report('Password updated.');}catch(error){report(error.message,true);}finally{submit.disabled=false;}});node.append(form);editor.append(node);}
  function render(){
    if(!state)return;editor.replaceChildren();title.textContent=tabs.find(([id])=>id===active)[1];[...nav.children].forEach((node,index)=>node.classList.toggle('active',tabs[index][0]===active));
    if(active==='dashboard')overview();else if(active==='profile')profile();else if(active==='home'){block('hero','Hero / introduction');block('marquee','Scrolling technologies');block('projects-home','Home projects heading');block('cta','Call to action');}
    else if(active==='about')block('about','About you');else if(['skills','projects','services','experience'].includes(active)){block(active,'Section heading');collection(active,tabs.find(([id])=>id===active)[1]);}
    else if(active==='contact')block('contact','Contact information & form labels');else if(active==='header')block('header','Navigation & buttons');else if(active==='footer'){block('footer','Footer');block('social','Social sidebar');}
    else if(active==='seo')seo();else if(active==='messages')messages();else if(active==='account')account();
  }
  api('load',undefined,'GET').then(data=>{adopt(data);setSaved();render();}).catch(error=>{editor.replaceChildren();report(error.message,true);});
})();
