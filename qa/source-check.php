<?php
require __DIR__.'/../cms/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$templates=cms_json('templates');$content=cms_json('content');$count=0;
foreach(['blocks','items'] as $kind)foreach($templates[$kind] as $id=>$model){$doc=cms_fragment($model['html']);$xp=new DOMXPath($doc);foreach($model['fields'] as $key=>$field){if(!$xp->query($field['path'])->length)throw new RuntimeException('Missing editable node: '.$id.' '.$key);$count++;}}
if($templates['profile']['name']!==$content['profile']['name'])throw new RuntimeException('Profile baseline does not match personalized source.');
foreach(['photo','resume'] as $key)if(!is_file(CMS_ROOT.'/dist/'.$content['profile'][$key]))throw new RuntimeException('Active profile asset is missing: '.$key);
foreach($templates['pages'] as $route=>$page){$file=CMS_ROOT.'/dist/'.($route?$route.'/':'').'index.html';if(is_file($file)&&!str_contains(file_get_contents($file),$content['profile']['name']))throw new RuntimeException('Generated page lacks profile name: '.$route);}
echo 'PASS: '.$count.' editable nodes, personalized baseline, active photo/CV, and generated page identities.'.PHP_EOL;
