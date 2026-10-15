<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$cmsRoute=$argv[1]??'';
$_SERVER['SCRIPT_NAME']='/dist/'.($cmsRoute?$cmsRoute.'/':'').'index.php';$_SERVER['HTTP_HOST']='hassnainali.fwh.is';$_SERVER['REQUEST_METHOD']='GET';$_GET=[];
require __DIR__.'/../cms/front.php';
