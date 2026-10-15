<?php
declare(strict_types=1);
require __DIR__.'/../cms/lib.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$lock=null;
try{
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);throw new RuntimeException('Use the contact form to send a message.');}
    if(!empty($_SERVER['HTTP_ORIGIN'])&&parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST)!==preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST'])){http_response_code(403);throw new RuntimeException('Invalid request origin.');}
    $data=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    if(!empty($data['company'])){echo '{"ok":true}';exit;}
    $name=trim((string)($data['name']??''));$email=trim((string)($data['email']??''));$subject=trim((string)($data['subject']??''));$message=trim((string)($data['message']??''));
    if(mb_strlen($name)<1||mb_strlen($name)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||mb_strlen($subject)>300||mb_strlen($message)<3||mb_strlen($message)>10000)throw new RuntimeException('Enter your name, a valid email, and a message (3–10,000 characters).');
    $lock=fopen(CMS_DATA.'/messages.lock','c');flock($lock,LOCK_EX);$messages=cms_json('messages');$ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'');$recent=0;
    foreach($messages['items'] as $item)if(($item['sender']??'')===$ip&&strtotime($item['created'])>time()-900)$recent++;
    if($recent>=5){http_response_code(429);throw new RuntimeException('Please wait 15 minutes before sending another message.');}
    if(count($messages['items'])>=1000){http_response_code(503);throw new RuntimeException('Inbox is full. Please use the email link instead.');}
    array_unshift($messages['items'],['id'=>bin2hex(random_bytes(12)),'name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message,'created'=>gmdate('c'),'read'=>false,'sender'=>$ip]);
    cms_write('messages',$messages);flock($lock,LOCK_UN);fclose($lock);$lock=null;echo '{"ok":true}';
}catch(Throwable $error){if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if(http_response_code()<400)http_response_code(400);echo json_encode(['error'=>$error->getMessage()]);}
