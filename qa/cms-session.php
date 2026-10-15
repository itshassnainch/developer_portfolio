<?php
require __DIR__.'/../cms/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$input=json_decode(stream_get_contents(STDIN),true)??[];
if(($input['action']??'')==='restore'){
    $lock=fopen(CMS_DATA.'/content.lock','c');flock($lock,LOCK_EX);$current=cms_json('content');
    if($current['revision']!==$input['revision']){fwrite(STDERR,"Another editor changed content; verification data was not restored.\n");exit(1);}
    cms_write('templates',$input['templates']);cms_write('content',$input['content']);flock($lock,LOCK_UN);fclose($lock);echo "Restored original content.\n";exit;
}
if(($input['action']??'')==='end'){session_name('portfolio_admin');session_id($input['id']);session_start();session_destroy();exit;}
cms_session();$_SESSION['admin']=true;$_SESSION['last_active']=time();echo json_encode(['id'=>session_id(),'csrf'=>$_SESSION['csrf']]);session_write_close();