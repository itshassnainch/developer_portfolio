<?php
declare(strict_types=1);
require __DIR__.'/../cms/lib.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function answer(array $data):never {echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function validate_values(array $values,array $schema):array {
    $out=[];foreach($schema['fields'] as $key=>$field){$value=$values[$key]??$field['default'];if(!is_string($value)||mb_strlen($value)>30000)throw new RuntimeException('A content field is invalid or too long.');if($field['type']==='url'&&!cms_url($value))throw new RuntimeException('Use an http, https, email, telephone or local link.');$out[$key]=$value;}return $out;
}
function clean_schema(array $templates):array {
    $result=['blocks'=>[],'items'=>[]];foreach(['blocks','items'] as $kind)foreach($templates[$kind] as $id=>$model){$fields=[];foreach($model['fields'] as $key=>$field)$fields[$key]=array_intersect_key($field,array_flip(['label','type','default']));$result[$kind][$id]=['label'=>$model['label'],'fields'=>$fields];}return $result;
}
$lock=null;
try {
    cms_require_auth();$action=$_GET['action']??'load';
    if($_SERVER['REQUEST_METHOD']==='GET'){
        if($action==='messages')answer(['messages'=>cms_json('messages')['items']]);
        if($action!=='load'){http_response_code(404);throw new RuntimeException('Unknown action.');}
        $templates=cms_json('templates');answer(['content'=>cms_json('content'),'schema'=>clean_schema($templates),'pages'=>array_map(fn($p)=>['label'=>$p['label']],$templates['pages'])]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);throw new RuntimeException('Method not allowed.');}
    cms_csrf();
    if($action==='password'){
        $input=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
        $auth=cms_json('auth');if(!password_verify((string)($input['current']??''),$auth['password']))throw new RuntimeException('Current password is incorrect.');
        $password=(string)($input['password']??'');if(strlen($password)<8||strlen($password)>200)throw new RuntimeException('Choose a password with 8–200 characters.');
        cms_write('auth',['password'=>password_hash($password,PASSWORD_DEFAULT)]);session_regenerate_id(true);answer(['ok'=>true]);
    }
    if($action==='message'){
        $input=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);if(!in_array($input['operation']??'', ['read','delete'],true))throw new RuntimeException('Invalid message action.');$lock=fopen(CMS_DATA.'/messages.lock','c');flock($lock,LOCK_EX);$messages=cms_json('messages');$found=false;
        foreach($messages['items'] as $i=>&$message)if($message['id']===($input['id']??'')){$found=true;if(($input['operation']??'')==='delete')unset($messages['items'][$i]);else $message['read']=true;break;}unset($message);
        if(!$found)throw new RuntimeException('Message not found.');$messages['items']=array_values($messages['items']);cms_write('messages',$messages);flock($lock,LOCK_UN);fclose($lock);$lock=null;answer(['ok'=>true,'messages'=>$messages['items']]);
    }
    $lock=fopen(CMS_DATA.'/content.lock','c');flock($lock,LOCK_EX);$current=cms_json('content');$templates=cms_json('templates');
    $input=$action==='upload'?$_POST:json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    if((int)($input['revision']??0)!==$current['revision']){http_response_code(409);throw new RuntimeException('Content changed in another tab. Reload this panel before saving.');}
    if($action==='upload'){
        $type=(string)($_POST['type']??'');if(!in_array($type,['photo','resume'],true))throw new RuntimeException('Invalid upload type.');
        $file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Upload failed. Try a smaller file.');
        if($file['size']>($type==='photo'?4:10)*1024*1024)throw new RuntimeException('Photo limit is 4 MB; resume limit is 10 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if($type==='photo'){if(!isset($types[$mime])||!getimagesize($file['tmp_name']))throw new RuntimeException('Choose a JPG, PNG or WebP image.');$ext=$types[$mime];}
        else{if($mime!=='application/pdf'||file_get_contents($file['tmp_name'],false,null,0,5)!=='%PDF-')throw new RuntimeException('Choose a PDF resume.');$ext='pdf';}
        $path='uploads/'.$type.'-'.bin2hex(random_bytes(12)).'.'.$ext;
        if(!move_uploaded_file($file['tmp_name'],CMS_ROOT.'/dist/'.$path))throw new RuntimeException('Unable to store the upload.');
        $current['profile'][$type]=$path;$current['revision']++;cms_write('content',$current);flock($lock,LOCK_UN);fclose($lock);$lock=null;answer(['ok'=>true,'path'=>$path,'revision'=>$current['revision']]);
    }
    if($action!=='save')throw new RuntimeException('Unknown action.');
    $submitted=$input['content']??null;if(!is_array($submitted))throw new RuntimeException('Invalid content.');
    $next=$current;
    foreach($current['profile'] as $key=>$old){$value=$submitted['profile'][$key]??$old;if(!is_string($value)||mb_strlen($value)>1000)throw new RuntimeException('Invalid profile field.');$value=trim($value);
        if(in_array($key,['name','shortName','email'],true)&&$value==='')throw new RuntimeException('Name, short name and email are required.');
        if($key==='email'&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
        if(in_array($key,['linkedin','github','facebook'],true)&&$value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$value)))throw new RuntimeException('Social links must start with http:// or https://.');
        if($key==='whatsapp'&&$value!==''&&!preg_match('/^\d{7,16}$/',$value))throw new RuntimeException('WhatsApp number must contain digits only, including country code.');
        if(in_array($key,['photo','resume'],true)&&$value!==$old)throw new RuntimeException('Use the upload buttons to replace photo or resume.');
        $next['profile'][$key]=$value;
    }
    foreach($templates['blocks'] as $id=>$model){$next['blocks'][$id]=validate_values($submitted['blocks'][$id]??$current['blocks'][$id]??[],$model);$next['visibility'][$id]=(bool)($submitted['visibility'][$id]??true);}
    foreach($current['collections'] as $id=>$items){
        $list=$submitted['collections'][$id]??[];if(!is_array($list)||count($list)>100)throw new RuntimeException('A section can contain up to 100 items.');$clean=[];$seen=[];
        foreach($list as $item){$itemId=$item['id']??'';$template=$item['template']??'';if(!is_string($itemId)||!preg_match('/^[a-z0-9-]{1,80}$/',$itemId)||isset($seen[$itemId]))throw new RuntimeException('Invalid or duplicate item.');$seen[$itemId]=true;
            if(!isset($templates['items'][$template])||!str_starts_with($template,$id.'-'))throw new RuntimeException('Invalid section template.');
            $entry=['id'=>$itemId,'template'=>$template,'visible'=>(bool)($item['visible']??true),'values'=>validate_values($item['values']??[],$templates['items'][$template])];
            if($id==='projects'){
                $oldItem=null;foreach($items as $existing)if($existing['id']===$itemId){$oldItem=$existing;break;}
                if($oldItem&&isset($oldItem['case']))$entry['case']=$oldItem['case'];
                elseif(!$oldItem){
                    $sourceRoute=array_key_first(array_filter($templates['pages'],fn($p,$r)=>str_starts_with($r,'projects/'),ARRAY_FILTER_USE_BOTH));
                    $route='projects/'.$itemId;
                    $templates['blocks'][$route]=$templates['blocks'][$sourceRoute];$templates['blocks'][$route]['label']='Case study: new project';
                    $templates['pages'][$route]=$templates['pages'][$sourceRoute];$templates['pages'][$route]['html']=str_replace('data-cms-block="'.$sourceRoute.'"','data-cms-block="'.$route.'"',$templates['pages'][$sourceRoute]['html']);$templates['pages'][$route]['label']='Case study: '.$itemId;
                    $values=[];foreach($templates['blocks'][$route]['fields'] as $key=>$field)$values[$key]=$field['default'];
                    $title='New project';$description='';foreach($templates['items'][$template]['fields'] as $key=>$field){if(str_starts_with($field['label'],'Title —'))$title=$entry['values'][$key];if($field['type']==='textarea')$description=$entry['values'][$key];}
                    $category='';$year='';$tech='';$live='';
                    foreach($templates['items'][$template]['fields'] as $key=>$field){if(str_starts_with($field['label'],'Label —')||$field['label']==='Project date / status')$year=$entry['values'][$key];if((str_starts_with($field['label'],'Text —')&&$field['type']==='text')||$field['label']==='Category')$category=$entry['values'][$key];if($field['type']==='lines')$tech=$entry['values'][$key];if($field['type']==='url'&&($key==='f6'||str_starts_with($field['default'],'http')))$live=$entry['values'][$key];}
                    foreach($templates['blocks'][$route]['fields'] as $key=>$field){
                        if(str_starts_with($field['label'],'Main heading'))$values[$key]=$title;
                        if(str_starts_with($field['label'],'Text —')&&$field['type']==='textarea')$values[$key]=$description;
                        if(str_starts_with($field['label'],'Text —')&&$field['type']==='text'){
                            if($key==='f3')$values[$key]=$category;
                            elseif($key==='f4')$values[$key]=$year;
                            elseif($key==='f6')$values[$key]='Role: ';
                            elseif($key==='f7')$values[$key]=$next['profile']['role'];
                        }
                        if($field['type']==='lines')$values[$key]=str_starts_with($field['label'],'Technologies')?$tech:'';
                        if($field['type']==='url'&&($key==='f13'||str_starts_with($field['default'],'http')))$values[$key]=$live;
                    }
                    $next['blocks'][$route]=$values;$next['pages'][$route]=['title'=>$title.' | '.$next['profile']['name'],'description'=>$description];$entry['case']=['route'=>$route];
                    foreach($templates['items'][$template]['fields'] as $key=>$field)if($field['type']==='url'&&str_contains($field['default'],'projects/'))$entry['values'][$key]='project.php?project='.$itemId;
                }
            }
            $clean[]=$entry;
        }$next['collections'][$id]=$clean;
    }
    foreach($current['pages'] as $route=>$page){$values=$submitted['pages'][$route]??$page;foreach(['title','description'] as $key){if(!is_string($values[$key]??null)||mb_strlen($values[$key])>2000)throw new RuntimeException('Invalid page metadata.');$next['pages'][$route][$key]=$values[$key];}}
    $next['revision']=$current['revision']+1;
    // Keep a recoverable copy of the previous content for each save.
    $backup=CMS_DATA.'/backup-'.date('Ymd-His').'-'.$current['revision'].'.json';copy(CMS_DATA.'/content.json',$backup);
    cms_write('templates',$templates);cms_write('content',$next);flock($lock,LOCK_UN);fclose($lock);$lock=null;
    answer(['ok'=>true,'content'=>$next,'schema'=>clean_schema($templates),'pages'=>array_map(fn($p)=>['label'=>$p['label']],$templates['pages'])]);
}catch(Throwable $error){if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if(http_response_code()<400)http_response_code(400);answer(['error'=>$error->getMessage()]);}
