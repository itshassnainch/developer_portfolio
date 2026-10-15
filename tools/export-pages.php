<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../cms/lib.php';
$templates=cms_json('templates');
foreach($templates['pages'] as $route=>$page){
    $script=CMS_ROOT.'/dist/'.($route?$route.'/':'').'index.php';if(!is_file($script))continue;
    $command=[PHP_BINARY,__DIR__.'/render-export.php',$route];$pipes=[];
    $process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,CMS_ROOT);
    if(!is_resource($process))throw new RuntimeException('Cannot render page.');
    $html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    if($status!==0||$errors!=='')throw new RuntimeException('Page export failed: '.$route.' '.$errors);
    $doc=cms_doc($html);$xp=new DOMXPath($doc);$depth=$route?count(explode('/',$route)):0;$prefix=$depth?str_repeat('../',$depth):'./';
    foreach($xp->query('//@href | //@src') as $attr)if(str_starts_with($attr->nodeValue,'/dist/'))$attr->nodeValue=$prefix.substr($attr->nodeValue,6);
    foreach($xp->query('//script[not(@src)]') as $inline)if(str_contains($inline->textContent,'window.portfolioConfig='))$inline->textContent='window.portfolioConfig='.json_encode(['email'=>cms_json('content')['profile']['email'],'contactEndpoint'=>$prefix.'contact.php'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';';
    file_put_contents(dirname($script).'/index.html',preg_replace('/[ \t]+$/m','',$doc->saveHTML()));echo 'Exported '.($route?:'home').PHP_EOL;
}
