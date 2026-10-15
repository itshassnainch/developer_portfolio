<?php
require __DIR__ . '/../cms/lib.php';
cms_session();
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');
$configured=is_file(CMS_DATA.'/auth.json');$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        cms_csrf();$action=$_POST['action']??'';
        if($action==='logout'){$_SESSION=[];session_destroy();header('Location: ./');exit;}
        $password=(string)($_POST['password']??'');
        if(!$configured&&$action==='setup') {
            if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true))throw new RuntimeException('First setup must be completed on this computer.');
            if(strlen($password)<8||strlen($password)>200)throw new RuntimeException('Choose a password with 8–200 characters.');
            if($password!==($_POST['confirm']??''))throw new RuntimeException('Passwords do not match.');
            $lock=fopen(CMS_DATA.'/auth.lock','c');flock($lock,LOCK_EX);
            if(is_file(CMS_DATA.'/auth.json'))throw new RuntimeException('Admin is already configured. Refresh and sign in.');
            cms_write('auth',['password'=>password_hash($password,PASSWORD_DEFAULT)]);flock($lock,LOCK_UN);fclose($lock);
            session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['last_active']=time();header('Location: ./');exit;
        }
        if($configured&&$action==='login') {
            $auth=cms_json('auth');
            if(time()<($_SESSION['login_after']??0))throw new RuntimeException('Please wait a moment before trying again.');
            if(!password_verify($password,$auth['password'])){$_SESSION['login_after']=time()+2;throw new RuntimeException('Password is incorrect.');}
            session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['last_active']=time();header('Location: ./');exit;
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$signedIn=cms_authenticated();
function h(string $value):string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Portfolio Admin</title><link rel="stylesheet" href="admin.css?v=<?=filemtime(__DIR__.'/admin.css')?>"></head>
<body>
<?php if(!$signedIn): ?>
<main class="login-wrap"><div class="login-card"><span class="brand-mark">P<span>.</span></span><p class="eyebrow">YOUR WEBSITE, YOUR CONTENT</p><h1><?= $configured?'Welcome back.':'Set up your admin.' ?></h1><p class="muted"><?= $configured?'Sign in to manage your portfolio.':'Apna password banayein. Phir poori website yahan se manage karein.' ?></p>
<?php if($error):?><p class="error" role="alert"><?=h($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="<?=$configured?'login':'setup'?>"><label>Password<input type="password" name="password" minlength="<?=$configured?1:8?>" maxlength="200" required autocomplete="<?=$configured?'current-password':'new-password'?>" autofocus></label>
<?php if(!$configured):?><label>Confirm password<input type="password" name="confirm" required minlength="8" maxlength="200" autocomplete="new-password"></label><?php endif;?>
<button class="primary" type="submit"><?=$configured?'Sign in':'Create admin & continue'?></button></form><a class="back-link" href="../dist/">← View website</a></div></main>
<?php else: ?>
<aside class="sidebar"><a href="./" class="brand">Portfolio<span>.</span><small>ADMIN STUDIO</small></a><nav id="navigation" aria-label="Admin sections"></nav><div class="sidebar-bottom"><a href="../dist/" target="_blank" rel="noopener">↗ Open website</a><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button class="text-button">Sign out</button></form></div></aside>
<main class="workspace"><header class="topbar"><div><p class="eyebrow">CONTENT MANAGER</p><h1 id="page-title">Dashboard</h1></div><div class="top-actions"><span id="save-state" class="muted" aria-live="polite">All changes saved</span><button id="save-button" class="primary" disabled>Save changes</button></div></header><div id="notice" role="status" hidden></div><section id="editor" aria-label="Content editor"><p class="muted">Loading your content…</p></section></main>
<script>window.adminConfig=<?=json_encode(['csrf'=>$_SESSION['csrf']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="admin.js" defer></script>
<?php endif; ?>
</body></html>
