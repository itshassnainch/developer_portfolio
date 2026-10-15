<?php
require __DIR__.'/lib.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
foreach(['content','templates'] as $name)if(!is_file(CMS_DATA.'/'.$name.'.json'))throw new RuntimeException('Restore the tracked cms/data/'.$name.'.json file from this repository.');
if(!is_file(CMS_DATA.'/messages.json'))cms_write('messages',['items'=>[]]);
echo "Hassnain portfolio content is ready. Set up your password at /admin/ on localhost.\n";
