<?php
require __DIR__.'/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$t=cms_json('templates');
$labels=[
    'skills'=>['f0'=>'Skills (one per line)','f1'=>'Skill group name'],
    'projects'=>['f0'=>'Technologies (one per line)','f1'=>'Project date / status','f2'=>'Title — Project name','f3'=>'Category','f4'=>'Project description','f5'=>'Case-study button label','f6'=>'Live URL (leave blank if unavailable)','f7'=>'Case-study destination'],
    'experience'=>['f0'=>'Technologies (one per line)','f1'=>'Employment dates','f2'=>'Title — Job role','f3'=>'Company','f4'=>'Responsibilities'],
    'services'=>['f0'=>'Tasks (one per line)','f1'=>'Title — Contribution area','f2'=>'Description']
];
foreach($t['items'] as $id=>&$model)foreach($labels as $prefix=>$fields)if(str_starts_with($id,$prefix.'-'))foreach($fields as $key=>$label)if(isset($model['fields'][$key]))$model['fields'][$key]['label']=$label;unset($model);
cms_write('templates',$t);echo "Admin field labels updated.\n";
