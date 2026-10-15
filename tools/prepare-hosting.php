<?php
declare(strict_types=1);
require __DIR__.'/../cms/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$buildRoot=CMS_ROOT.'/.deployment';
if(!is_dir($buildRoot))mkdir($buildRoot,0777,true);
file_put_contents($buildRoot.'/.htaccess',"Require all denied\n");
$destination=$buildRoot.'/hosting-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($destination,0777,true);
function deploy_copy(string $source,string $target):void {
    if(!is_file($source))throw new RuntimeException('Missing deployment file: '.basename($source));
    if(!is_dir(dirname($target)))mkdir(dirname($target),0777,true);
    if(!copy($source,$target))throw new RuntimeException('Unable to copy deployment file.');
}
function deploy_tree(string $source,string $target):void {
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));
    foreach($files as $file)if($file->isFile())deploy_copy($file->getPathname(),$target.'/'.str_replace('\\','/',substr($file->getPathname(),strlen($source)+1)));
}
$lock=fopen(CMS_DATA.'/content.lock','c');flock($lock,LOCK_SH);
try{
    $content=cms_json('content');$templates=cms_json('templates');
    if(!is_file(CMS_DATA.'/auth.json'))throw new RuntimeException('Create your local admin password before packaging for public hosting.');
    foreach(['content','templates','auth'] as $name)deploy_copy(CMS_DATA.'/'.$name.'.json',$destination.'/cms/data/'.$name.'.json');
}finally{flock($lock,LOCK_UN);fclose($lock);}
foreach(['admin.css','admin.js','index.php','api.php'] as $file)deploy_copy(CMS_ROOT.'/admin/'.$file,$destination.'/admin/'.$file);
foreach(['lib.php','front.php'] as $file)deploy_copy(CMS_ROOT.'/cms/'.$file,$destination.'/cms/'.$file);
deploy_copy(CMS_DATA.'/.htaccess',$destination.'/cms/data/.htaccess');
deploy_copy(CMS_DATA.'/index.html',$destination.'/cms/data/index.html');
file_put_contents($destination.'/cms/data/messages.json',"{\"items\":[]}\n");
foreach(['.htaccess','site.css','site.js','theme-preview.css','contact.php','project.php','favicon.ico','icon.svg','apple-icon.png'] as $file)deploy_copy(CMS_ROOT.'/dist/'.$file,$destination.'/dist/'.$file);
deploy_tree(CMS_ROOT.'/dist/_next',$destination.'/dist/_next');
deploy_copy(CMS_ROOT.'/dist/uploads/.htaccess',$destination.'/dist/uploads/.htaccess');
foreach(['photo','resume'] as $key){
    $asset=$content['profile'][$key];
    if(str_contains($asset,'..')||str_starts_with($asset,'/')||!preg_match('~^[a-zA-Z0-9_./-]+$~',$asset))throw new RuntimeException('Invalid active asset path.');
    deploy_copy(CMS_ROOT.'/dist/'.$asset,$destination.'/dist/'.$asset);
}
foreach($templates['pages'] as $route=>$page){
    $folder=$route===''?'':$route.'/';
    $source=CMS_ROOT.'/dist/'.$folder.'index.php';
    if(is_file($source))deploy_copy($source,$destination.'/dist/'.$folder.'index.php');
    // Projects created in Admin use dist/project.php instead of physical folders.
    elseif(!str_starts_with($route,'projects/'))throw new RuntimeException('Missing page entry point: '.$route);
}
file_put_contents($destination.'/index.php',"<?php\nheader('Location: ./dist/', true, 302);\nexit;\n");
file_put_contents($destination.'/.htaccess',"Options -Indexes\nDirectoryIndex index.php index.html\n");
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination,FilesystemIterator::SKIP_DOTS));$count=0;
foreach($files as $file)if($file->isFile()){
    if($file->getSize()>10*1024*1024)throw new RuntimeException('A deployment file exceeds 10 MB.');
    $count++;
}
echo json_encode(['directory'=>realpath($destination),'files'=>$count,'pages'=>count($templates['pages']),'revision'=>$content['revision'],'admin'=>'Existing password retained; messages excluded.'],JSON_UNESCAPED_SLASHES).PHP_EOL;
