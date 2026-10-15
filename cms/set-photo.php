<?php
require __DIR__.'/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$path=$argv[1]??'';
if(!preg_match('~^uploads/[a-zA-Z0-9-]+\.(png|jpg|webp)$~',$path)||!is_file(CMS_ROOT.'/dist/'.$path)||!getimagesize(CMS_ROOT.'/dist/'.$path))throw new RuntimeException('Invalid portrait file.');
$lock=fopen(CMS_DATA.'/content.lock','c');flock($lock,LOCK_EX);
try{
    $content=cms_json('content');
    copy(CMS_DATA.'/content.json',CMS_DATA.'/backup-'.date('Ymd-His').'-'.$content['revision'].'.json');
    $content['profile']['photo']=$path;$content['revision']++;
    cms_write('content',$content);
    echo "Homepage portrait updated.\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
