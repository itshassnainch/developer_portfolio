<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$directory=realpath($argv[1]??'');$allowed=realpath(__DIR__.'/../.deployment');
if(!$directory||!$allowed||!str_starts_with($directory,$allowed.DIRECTORY_SEPARATOR))throw new RuntimeException('Expected a prepared hosting directory.');
foreach(['cms/data/auth.json','cms/data/content.json','cms/data/templates.json','cms/data/messages.json','cms/data/.htaccess','dist/uploads/.htaccess','index.php','admin/index.php'] as $file)if(!is_file($directory.'/'.$file))throw new RuntimeException('Missing file: '.$file);
$auth=json_decode(file_get_contents($directory.'/cms/data/auth.json'),true,512,JSON_THROW_ON_ERROR);
if(empty($auth['password'])||password_get_info($auth['password'])['algo']===null)throw new RuntimeException('Admin password hash is missing.');
$messages=json_decode(file_get_contents($directory.'/cms/data/messages.json'),true,512,JSON_THROW_ON_ERROR);
if($messages['items']!==[])throw new RuntimeException('Private inbox must not be copied.');
$_SERVER['SCRIPT_NAME']='/dist/index.php';$_SERVER['HTTP_HOST']='portfolio.example';$_SERVER['REQUEST_METHOD']='GET';
ob_start();require $directory.'/dist/index.php';$html=ob_get_clean();
if(!str_contains($html,'data-palette="electric"')||!str_contains($html,'/dist/theme-preview.css?v='))throw new RuntimeException('Cyan palette missing from hosted output.');
if(!str_contains($html,'src="/dist/uploads/'))throw new RuntimeException('Hosted photo path is not portable.');
if(str_contains($html,'localhost/developer_portfolio'))throw new RuntimeException('Hosted page still references localhost.');
if(!str_contains($html,'href="/dist/contact/?palette=electric"'))throw new RuntimeException('Hosted navigation is not portable.');
if(!str_contains(file_get_contents($directory.'/cms/data/.htaccess'),'Require all denied'))throw new RuntimeException('Private data protection missing.');
echo "PASS: portable hosted paths, cyan theme, active portrait, protected admin hash, empty inbox and required runtime files.\n";
