<?php
require __DIR__ . '/lib.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (is_file(CMS_DATA . '/content.json')) { echo "Content already initialized.\n"; exit; }
$templates = ['blocks'=>[], 'items'=>[], 'pages'=>[]];
$content = ['revision'=>1,'blocks'=>[],'collections'=>[], 'profile'=>[
    'name'=>'Muhammad Imran Ali','shortName'=>'Imran','role'=>'Full Stack JavaScript Developer',
    'email'=>'imrangujjar3198@gmail.com','phone'=>'+92 302 6009873','whatsapp'=>'923026009873',
    'linkedin'=>'https://www.linkedin.com/in/muhammad-imran-ali-383110228','github'=>'https://github.com/MuhammadImranali98',
    'facebook'=>'https://www.facebook.com/muhammad.imran.ali.gujjar.503063','photo'=>'homeimg.png','resume'=>'resume.pdf'
]];
function model(string $html, string $label): array {
    $doc = cms_fragment($html); $xp = new DOMXPath($doc); $fields=[]; $counter=0;
    $skip=[];
    foreach ($xp->query('//ul[not(ancestor::nav)] | //div[contains(@class,"flex-wrap") and (span[contains(@class,"tag")])] | //pre') as $node) {
        if ($node->tagName === 'pre') { $type='code';$value=$node->textContent;$item=''; }
        else {
            $type='lines';$lines=[];$item='';
            foreach($node->childNodes as $child) if($child instanceof DOMElement){$lines[]=trim($child->textContent); if(!$item)$item=$doc->saveHTML($child);}
            $value=implode("\n",$lines); if(!$item)continue;
        }
        $key='f'.$counter++; $fields[$key]=['label'=>$type==='code'?'Developer card / code':($node->tagName==='ul'?'List items (one per line)':'Technologies (one per line)'), 'type'=>$type,'path'=>$node->getNodePath(),'default'=>$value,'item'=>$item];
        $skip[]=$node;
    }
    foreach($xp->query('//*[@id="cms-root"]//text()[not(ancestor::svg) and not(ancestor::script) and not(ancestor::style)]') as $text) {
        if(!cms_meaningful($text))continue;
        $excluded=false;for($p=$text->parentNode;$p;$p=$p->parentNode)if(in_array($p,$skip,true)||($p instanceof DOMElement&&$p->hasAttribute('data-cms-collection'))){$excluded=true;break;}
        if($excluded)continue;
        $tag=$text->parentNode->nodeName;
        $labelMap=['h1'=>'Main heading','h2'=>'Section heading','h3'=>'Title','p'=>'Text','a'=>'Link label','span'=>'Label','code'=>'Code'];
        $value=trim($text->textContent);$key='f'.$counter++;
        $fields[$key]=['label'=>($labelMap[$tag]??'Text').' — '.mb_substr($value,0,48),'type'=>mb_strlen($value)>85?'textarea':'text','path'=>$text->getNodePath(),'default'=>$text->nodeValue];
    }
    foreach($xp->query('//*[@id="cms-root"]//a/@href | //*[@id="cms-root"]//input/@placeholder | //*[@id="cms-root"]//textarea/@placeholder | //*[@id="cms-root"]//img/@alt') as $attr) {
        if($attr->ownerElement->hasAttribute('data-cms-collection'))continue;
        $key='f'.$counter++;$fields[$key]=['label'=>$attr->nodeName==='href'?'Link destination — '.mb_substr($attr->ownerElement->textContent?:$attr->ownerElement->getAttribute('aria-label'),0,40):ucfirst($attr->nodeName),'type'=>$attr->nodeName==='href'?'url':'text','path'=>$attr->getNodePath(),'default'=>$attr->nodeValue];
    }
    return ['label'=>$label,'html'=>cms_inner($doc->getElementById('cms-root')),'fields'=>$fields];
}
function defaults(array $model): array { return array_map(fn($f)=>$f['default'],$model['fields']); }
function block(string $id, DOMNode $node, string $label, ?string $collectionXPath = null, ?string $collectionKey = null): void {
    global $templates,$content;
    if(!isset($templates['blocks'][$id])) {
        $doc=cms_fragment($node->ownerDocument->saveHTML($node));$xp=new DOMXPath($doc);
        if($collectionXPath) {
            $collectionKey ??= $id;
            $nodes=iterator_to_array($xp->query($collectionXPath));
            if(!isset($content['collections'][$collectionKey]))foreach($nodes as $i=>$item){
                $templateId=$id.'-'.$i;
                $itemModel=model($doc->saveHTML($item),ucfirst($id).' item');
                $templates['items'][$templateId]=$itemModel;
                $content['collections'][$collectionKey][]=['id'=>$templateId,'template'=>$templateId,'visible'=>true,'values'=>defaults($itemModel)];
            }
            if($nodes){$marker=$doc->createElement('div');$marker->setAttribute('data-cms-collection',$collectionKey);$nodes[0]->parentNode->insertBefore($marker,$nodes[0]);foreach($nodes as $item)$item->parentNode->removeChild($item);}
        }
        $m=model(cms_inner($doc->getElementById('cms-root')),$label);$templates['blocks'][$id]=$m;$content['blocks'][$id]=defaults($m);
    }
    $marker=$node->ownerDocument->createElement('div');$marker->setAttribute('data-cms-block',$id);$node->parentNode->replaceChild($marker,$node);
}
$routes=['projects'=>'Projects',''=>'Home','about'=>'About','skills'=>'Skills','services'=>'Services','experience'=>'Experience','contact'=>'Contact'];
foreach(glob(CMS_ROOT.'/dist/projects/*/index.html') as $file) {$slug=basename(dirname($file));$routes['projects/'.$slug]='Case study: '.ucwords(str_replace('-',' ',$slug));}
foreach($routes as $route=>$label) {
    $file=CMS_ROOT.'/dist/'.($route?$route.'/':'').'index.html';$doc=cms_doc(file_get_contents($file));$xp=new DOMXPath($doc);
    foreach(['header'=>'Navigation','footer'=>'Footer'] as $tag=>$title)if($n=$xp->query('//'.$tag)->item(0))block($tag,$n,$title);
    $social=$xp->query('//body/div[contains(@class,"fixed") and contains(@class,"left-0")]')->item(0);if($social)block('social',$social,'Social sidebar');
    foreach(iterator_to_array($xp->query('//main//section')) as $section) {
        $text=trim($section->textContent);$id=null;$collection=null;
        if(str_starts_with($text,'About'))$id='about';
        elseif(str_starts_with($text,'Skills')){$id='skills';$collection='//*[@id="cms-root"]//div[contains(@class,"mt-12")]/div';}
        elseif(str_starts_with($text,'Projects')){$id=$route===''?'projects-home':'projects';$collection='//*[@id="cms-root"]//article';}
        elseif(str_starts_with($text,'Services')){$id='services';$collection='//*[@id="cms-root"]//div[contains(@class,"mt-12")]/div';}
        elseif(str_starts_with($text,'Experience')){$id='experience';$collection='//*[@id="cms-root"]//div[contains(@class,"mt-12")]/div';}
        elseif(str_starts_with($text,'Have a product'))$id='cta';
        elseif($route==='')$id='hero';
        if($id)block($id,$section,ucfirst($id),$collection,$id==='projects-home'?'projects':null);
    }
    if($route==='contact')if($node=$xp->query('//main/div')->item(0))block('contact',$node,'Contact');
    if(str_starts_with($route,'projects/'))if($node=$xp->query('//main/article')->item(0))block($route,$node,$label);
    $pageHtml=$doc->saveHTML();
    $templates['pages'][$route]=['label'=>$label,'html'=>$pageHtml,'title'=>$xp->query('//title')->item(0)?->textContent??$label];
    $content['pages'][$route]=['title'=>$templates['pages'][$route]['title'],'description'=>$xp->query('//meta[@name="description"]/@content')->item(0)?->nodeValue??''];
}
// Keep the homepage project heading separate from the projects page heading.
$templates['pages']['projects']['html']=str_replace('data-cms-block="projects"','data-cms-block="projects" data-cms-project-page="1"',$templates['pages']['projects']['html']);
cms_write('templates',$templates);cms_write('content',$content);cms_write('messages',['items'=>[]]);
foreach($routes as $route=>$label){$depth=$route?count(explode('/',$route)):0;$relative=str_repeat('../',$depth+1).'cms/front.php';file_put_contents(CMS_ROOT.'/dist/'.($route?$route.'/':'').'index.php',"<?php\n\$cmsRoute = ".var_export($route,true).";\nrequire __DIR__ . '/".$relative."';\n");}
echo 'Initialized '.count($routes).' pages, '.count($templates['blocks']).' blocks.' . PHP_EOL;
