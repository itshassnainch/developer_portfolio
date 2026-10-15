<?php
require __DIR__.'/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$lock=fopen(CMS_DATA.'/content.lock','c');flock($lock,LOCK_EX);
try{
    $content=cms_json('content');$templates=cms_json('templates');
    copy(CMS_DATA.'/content.json',CMS_DATA.'/backup-before-remove-education-'.date('Ymd-His').'.json');
    copy(CMS_DATA.'/templates.json',CMS_DATA.'/templates-before-remove-education-'.date('Ymd-His').'.json');
    foreach($templates['pages'] as &$page){
        $doc=cms_doc($page['html']);$xp=new DOMXPath($doc);
        foreach(iterator_to_array($xp->query('//*[@data-cms-block="education"]')) as $node)$node->parentNode->removeChild($node);
        $page['html']=$doc->saveHTML();
    }unset($page);
    $doc=cms_fragment($templates['blocks']['header']['html']);$xp=new DOMXPath($doc);
    foreach(iterator_to_array($xp->query('//li[a[@data-cv-nav="education"]]')) as $node)$node->parentNode->removeChild($node);
    $templates['blocks']['header']['html']=cms_inner($doc->getElementById('cms-root'));
    unset($templates['blocks']['header']['fields']['education-label'],$templates['blocks']['header']['fields']['education-link'],$content['blocks']['header']['education-label'],$content['blocks']['header']['education-link']);
    foreach(iterator_to_array($xp->query('//nav/ul/li/a')) as $i=>$anchor){
        $templates['blocks']['header']['fields']['f'.($i+1)]['path']=$anchor->firstChild->getNodePath();
        $templates['blocks']['header']['fields']['f'.($i+9)]['path']=$anchor->getAttributeNode('href')->getNodePath();
    }
    unset($templates['pages']['education'],$templates['blocks']['education'],$content['pages']['education'],$content['blocks']['education'],$content['collections']['education'],$content['visibility']['education']);
    foreach(array_keys($templates['items']) as $id)if(str_starts_with($id,'education-'))unset($templates['items'][$id]);
    $content['revision']++;cms_write('templates',$templates);cms_write('content',$content);
    echo "Education section and navigation removed.\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
