<?php
require __DIR__ . '/lib.php';
$templates=cms_json('templates');$content=cms_json('content');
$route=$cmsRoute??'';
if(isset($_GET['project'])) {
    $id=(string)$_GET['project'];$project=null;
    foreach($content['collections']['projects'] as $item)if($item['id']===$id&&$item['visible']){$project=$item;break;}
    if(!$project||empty($project['case'])){http_response_code(404);exit('Project not found.');}
    $route=$project['case']['route'];
}
if(!isset($templates['pages'][$route])){http_response_code(404);exit('Page not found.');}
if(str_starts_with($route,'projects/')){
    $published=false;foreach($content['collections']['projects'] as $item)if(($item['case']['route']??'')===$route&&($item['visible']??true)){$published=true;break;}
    if(!$published){http_response_code(404);exit('Project not found.');}
}
$page=$templates['pages'][$route];$doc=cms_doc($page['html']);$xp=new DOMXPath($doc);
// Resolve assets and internal destinations from one base, regardless of page depth.
$scriptPath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$position=strpos($scriptPath,'/dist/');
$base=$position!==false?substr($scriptPath,0,$position).'/dist/':rtrim(dirname($scriptPath),'/').'/dist/';
foreach(iterator_to_array($xp->query('//*[@data-cms-block]')) as $marker) {
    $id=$marker->getAttribute('data-cms-block');
    if(($content['visibility'][$id]??true)===false){$marker->parentNode->removeChild($marker);continue;}
    $collections=$content['collections'];
    if($route==='')$collections['projects']=array_slice(array_values(array_filter($collections['projects'],fn($item)=>$item['visible']??true)),0,3);
    // Block fragments originated at different depths; normalize their original links.
    $html=cms_render_model($templates['blocks'][$id],$content['blocks'][$id]??[],$collections);
    $fragment=cms_fragment($html);$fxp=new DOMXPath($fragment);
    foreach($fxp->query('//@href | //@src') as $attr) {
        $value=$attr->nodeValue;
        if($value!==''&&!preg_match('~^(https?:|mailto:|tel:|data:|#|/)~i',$value))$attr->nodeValue=$base.preg_replace('~^(?:\./|\.\./)+~','',$value);
    }
    cms_replace($marker,cms_inner($fragment->getElementById('cms-root')));
}
// Normalize the shell's relative assets too.
foreach($xp->query('//link/@href | //script/@src') as $attr){$value=$attr->nodeValue;if(!preg_match('~^(https?:|/|#)~i',$value))$attr->nodeValue=$base.preg_replace('~^(?:\./|\.\./)+~','',$value);}
$original=cms_json('templates')['profile']??[
    'name'=>'Muhammad Imran Ali','shortName'=>'Imran','role'=>'Full Stack JavaScript Developer',
    'email'=>'imrangujjar3198@gmail.com','phone'=>'+92 302 6009873','whatsapp'=>'923026009873',
    'linkedin'=>'https://www.linkedin.com/in/muhammad-imran-ali-383110228','github'=>'https://github.com/MuhammadImranali98',
    'facebook'=>'https://www.facebook.com/muhammad.imran.ali.gujjar.503063'
];
$replace=[];foreach($original as $key=>$value)if(isset($content['profile'][$key])&&$value!=='')$replace[$value]=$content['profile'][$key];
$replace['Imran Ali']=$content['profile']['name'];
foreach($xp->query('//body//text()[not(ancestor::script) and not(ancestor::style)] | //title/text() | //@alt | //meta/@content | //a/@href') as $node)$node->nodeValue=strtr($node->nodeValue,$replace);
foreach($xp->query('//a[starts-with(@href,"tel:")]') as $phone)$phone->setAttribute('href','tel:'.preg_replace('/[^+0-9]/','',$content['profile']['phone']));
foreach(iterator_to_array($xp->query('//a[@href="" or @href="mailto:" or @href="tel:"]')) as $link)$link->parentNode->removeChild($link);
foreach($xp->query('//img[contains(@src,"homeimg.png")]') as $img)$img->setAttribute('src',$base.$content['profile']['photo']);
foreach($xp->query('//a[contains(@href,"resume.pdf")]') as $link)$link->setAttribute('href',$base.$content['profile']['resume']);
if($title=$xp->query('//title')->item(0))$title->textContent=strtr($content['pages'][$route]['title']??$page['title'],$replace);
foreach($xp->query('//meta[@property="og:title"] | //meta[@name="twitter:title"]') as $meta)$meta->setAttribute('content',$title?->textContent??$content['profile']['name']);
foreach($xp->query('//meta[@property="og:site_name"]') as $meta)$meta->setAttribute('content',$content['profile']['name'].' | Portfolio');
$origin=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https://':'http://').$_SERVER['HTTP_HOST'];
foreach($xp->query('//meta[@property="og:image"] | //meta[@name="twitter:image"]') as $meta)$meta->setAttribute('content',$origin.$base.$content['profile']['photo']);
foreach($xp->query('//meta[@name="description"] | //meta[@property="og:description"] | //meta[@name="twitter:description"]') as $meta)$meta->setAttribute('content',strtr($content['pages'][$route]['description']??'',$replace));
foreach($xp->query('//link[@rel="canonical"] | //meta[@property="og:url"] | //link[@rel="author"]') as $old)$old->parentNode->removeChild($old);
$config=$doc->createElement('script');$config->textContent='window.portfolioConfig='.json_encode(['email'=>$content['profile']['email'],'contactEndpoint'=>$base.'contact.php'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';';
$themeBootstrap=$doc->createElement('script');$themeBootstrap->textContent='(()=>{let theme="dark";try{const saved=localStorage.getItem("portfolio-theme");if(saved==="light"||saved==="dark")theme=saved;}catch{}document.documentElement.dataset.theme=theme;})();';
$doc->getElementsByTagName('head')->item(0)->insertBefore($themeBootstrap,$doc->getElementsByTagName('head')->item(0)->firstChild);
$doc->getElementsByTagName('head')->item(0)->appendChild($config);
$palette=$_GET['palette']??'electric';
if(is_string($palette)&&in_array($palette,['teal','burgundy','electric'],true)){
    $doc->documentElement->setAttribute('data-palette',$palette);
    $paletteCss=$doc->createElement('link');$paletteCss->setAttribute('rel','stylesheet');$paletteCss->setAttribute('href',$base.'theme-preview.css');$doc->getElementsByTagName('head')->item(0)->appendChild($paletteCss);
    foreach($xp->query('//a/@href') as $link){$href=$link->nodeValue;if(str_starts_with($href,$base)&&!preg_match('~\.(pdf|png|jpg|webp)(?:\?|$)~i',$href))$link->nodeValue=$href.(str_contains($href,'?')?'&':'?').'palette='.$palette;}
}
// A changed local asset gets a fresh URL so previous colors cannot remain cached.
foreach($xp->query('//link[@rel="stylesheet"]/@href | //script[@src]/@src') as $attr){
    $assetUrl=$attr->nodeValue;
    if(str_starts_with($assetUrl,$base)){
        $assetRelative=explode('?',substr($assetUrl,strlen($base)))[0];
        $assetPath=CMS_ROOT.'/dist/'.$assetRelative;
        if(is_file($assetPath))$attr->nodeValue=$base.$assetRelative.'?v='.filemtime($assetPath);
    }
}
header('Cache-Control: no-store');header('Content-Type: text/html; charset=utf-8');
echo $doc->saveHTML();
