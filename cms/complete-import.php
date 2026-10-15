<?php
require __DIR__.'/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$templates=cms_json('templates');$content=cms_json('content');
if(!isset($templates['blocks']['marquee'])) {
    $doc=cms_doc(file_get_contents(CMS_ROOT.'/dist/index.html'));$xp=new DOMXPath($doc);
    $outer=$xp->query('//main//div[contains(@class,"border-y")]')->item(0);
    $fragment=cms_fragment($doc->saveHTML($outer));$fxp=new DOMXPath($fragment);$list=$fxp->query('//*[@id="cms-root"]//div[contains(@class,"animate-marquee")]')->item(0);
    $lines=[];foreach($list->childNodes as $child)if($child instanceof DOMElement)$lines[]=trim(str_replace('/','',$child->textContent));$lines=array_values(array_unique($lines));
    $templates['blocks']['marquee']=['label'=>'Scrolling technologies','html'=>cms_inner($fragment->getElementById('cms-root')),'fields'=>['f0'=>['label'=>'Technologies (one per line)','type'=>'lines','path'=>$list->getNodePath(),'default'=>implode("\n",$lines),'item'=>$fragment->saveHTML($list->firstChild),'repeat'=>3]]];
    $content['blocks']['marquee']=['f0'=>implode("\n",$lines)];
    $shell=cms_fragment($templates['blocks']['hero']['html']);$sxp=new DOMXPath($shell);$target=$sxp->query('//div[contains(@class,"border-y")]')->item(0);$marker=$shell->createElement('div');$marker->setAttribute('data-cms-block','marquee');$target->parentNode->replaceChild($marker,$target);$templates['blocks']['hero']['html']=cms_inner($shell->getElementById('cms-root'));
}
$heroDoc=cms_fragment($templates['blocks']['hero']['html']);$heroXp=new DOMXPath($heroDoc);$marqueeMarker=$heroXp->query('//*[@data-cms-block="marquee"]')->item(0);
if($marqueeMarker){$prefix=$marqueeMarker->getNodePath().'/';foreach($templates['blocks']['hero']['fields'] as $key=>$field)if(str_starts_with($field['path'],$prefix)){unset($templates['blocks']['hero']['fields'][$key],$content['blocks']['hero'][$key]);}}
foreach($content['collections']['projects'] as &$item){
    foreach($templates['items'][$item['template']]['fields'] as $key=>$field)if($field['type']==='url'&&str_contains($field['default'],'projects/')){$route=preg_replace('~^(?:\./|\.\./)+~','',rtrim($field['default'],'/'));if(isset($templates['pages'][$route]))$item['case']=['route'=>$route];}
}unset($item);
cms_write('templates',$templates);cms_write('content',$content);echo "Completed shared content import.\n";
