<?php
declare(strict_types=1);
require __DIR__.'/../cms/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$lock=fopen(CMS_DATA.'/content.lock','c');flock($lock,LOCK_EX);
try{
    $t=cms_json('templates');$c=cms_json('content');
    $archive=CMS_ROOT.'/.legacy-source/source-cleanup-'.date('Ymd-His');
    mkdir($archive,0777,true);file_put_contents(dirname($archive).'/.htaccess',"Require all denied\n");
    copy(CMS_DATA.'/templates.json',$archive.'/templates.json');copy(CMS_DATA.'/content.json',$archive.'/content.json');
    foreach(['about','experience'] as $page)copy(CMS_ROOT.'/dist/'.$page.'/index.html',$archive.'/'.$page.'-edited.html');
    foreach($t['blocks'] as $id=>&$model){
        $values=$c['blocks'][$id]??[];$model['html']=cms_render_model($model,$values,[],true);
        foreach($model['fields'] as $key=>&$field)$field['default']=$values[$key]??$field['default'];unset($field);
        $doc=cms_fragment($model['html']);$xp=new DOMXPath($doc);
        foreach($xp->query('//img') as $image){$image->setAttribute('src','./'.$c['profile']['photo']);$image->setAttribute('data-cms-photo','true');$image->setAttribute('alt',$c['profile']['name']);}
        $model['html']=cms_inner($doc->getElementById('cms-root'));
    }unset($model);
    $used=[];foreach($c['collections'] as $items)foreach($items as $item)$used[$item['template']]=$item['values'];
    foreach(array_keys($t['items']) as $id){
        if(!isset($used[$id])){unset($t['items'][$id]);continue;}
        $model=&$t['items'][$id];$model['html']=cms_render_model($model,$used[$id],[],true);
        foreach($model['fields'] as $key=>&$field)$field['default']=$used[$id][$key]??$field['default'];unset($field,$model);
    }
    $activeCases=[];foreach($c['collections']['projects'] as $project)if(isset($project['case']['route']))$activeCases[$project['case']['route']]=true;
    foreach(array_keys($t['pages']) as $route)if(str_starts_with($route,'projects/')&&!isset($activeCases[$route])){unset($t['pages'][$route],$t['blocks'][$route],$c['pages'][$route],$c['blocks'][$route],$c['visibility'][$route]);}
    foreach($t['pages'] as $route=>&$page){
        $doc=cms_doc($page['html']);$xp=new DOMXPath($doc);$title=$c['pages'][$route]['title'];$description=$c['pages'][$route]['description'];
        $doc->getElementsByTagName('title')->item(0)->textContent=$title;
        foreach($xp->query('//meta') as $meta){
            $name=$meta->getAttribute('name')?:$meta->getAttribute('property');
            if(in_array($name,['author','creator','publisher'],true))$meta->setAttribute('content',$c['profile']['name']);
            elseif(in_array($name,['application-name','og:site_name'],true))$meta->setAttribute('content',$c['profile']['name'].' | Portfolio');
            elseif(in_array($name,['og:title','twitter:title'],true))$meta->setAttribute('content',$title);
            elseif(in_array($name,['description','og:description','twitter:description'],true))$meta->setAttribute('content',$description);
            elseif($name==='og:image:alt')$meta->setAttribute('content',$c['profile']['name'].' | '.$c['profile']['role']);
            elseif(in_array($name,['og:image','twitter:image'],true))$meta->setAttribute('content','/dist/'.$c['profile']['photo']);
            elseif($name==='og:url')$meta->parentNode->removeChild($meta);
        }
        foreach(iterator_to_array($xp->query('//link[@rel="author" or @rel="canonical"] | //comment()')) as $node)$node->parentNode->removeChild($node);
        $page['html']=$doc->saveHTML();
    }unset($page);
    $t['profile']=$c['profile'];$c['revision']++;
    cms_write('templates',$t);cms_write('content',$c);
    copy(CMS_ROOT.'/dist/homeimg.png',$archive.'/previous-homeimg.png');copy(CMS_ROOT.'/dist/resume.pdf',$archive.'/previous-resume.pdf');
    copy(CMS_ROOT.'/dist/'.$c['profile']['photo'],CMS_ROOT.'/dist/homeimg.png');copy(CMS_ROOT.'/dist/'.$c['profile']['resume'],CMS_ROOT.'/dist/resume.pdf');
    echo "Personalized template HTML, defaults, metadata and fallback assets. Backups: ".$archive.PHP_EOL;
}finally{flock($lock,LOCK_UN);fclose($lock);}
