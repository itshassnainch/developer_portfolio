<?php
require __DIR__.'/../cms/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$templates=cms_json('templates');$content=cms_json('content');$count=0;
foreach(['blocks','items'] as $kind)foreach($templates[$kind] as $id=>$model){$doc=cms_fragment($model['html']);$xp=new DOMXPath($doc);foreach($model['fields'] as $key=>$field){if(!$xp->query($field['path'])->length)throw new RuntimeException('Missing editable node: '.$id.' '.$key);$count++;}}
if($templates['profile']['name']!==$content['profile']['name'])throw new RuntimeException('Profile baseline does not match personalized source.');
if(hash_file('sha256',CMS_ROOT.'/dist/homeimg.png')!==hash_file('sha256',CMS_ROOT.'/dist/'.$content['profile']['photo']))throw new RuntimeException('Fallback portrait is not current.');
if(hash_file('sha256',CMS_ROOT.'/dist/resume.pdf')!==hash_file('sha256',CMS_ROOT.'/dist/'.$content['profile']['resume']))throw new RuntimeException('Fallback CV is not current.');
foreach($templates['pages'] as $route=>$page){$file=CMS_ROOT.'/dist/'.($route?$route.'/':'').'index.html';if(is_file($file)&&!str_contains(file_get_contents($file),$content['profile']['name']))throw new RuntimeException('Generated page lacks profile name: '.$route);}
echo 'PASS: '.$count.' editable nodes, personalized baseline, active fallback photo/CV, and generated page identities.'.PHP_EOL;
