<?php declare(strict_types=1);
use App\Security\Auth;
$assetVersion=(string)filemtime(dirname(__DIR__).'/public/assets/app.css');
$role=$user['role_code']??'';
$isAdmin=$role==='ADMIN';$isEditor=in_array($role,Auth::EDITOR_ROLES,true);
?>
<!doctype html><html lang="sq"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · Tarifat e bizneseve</title><link rel="stylesheet" href="<?=e(app_url('/assets/app.css?v='.$assetVersion))?>"></head>
<body><a class="skip" href="#main">Kalo te përmbajtja</a>
<header class="topbar"><div class="topbar-inner">
<a class="brand" href="<?=e(app_url($user?Auth::landingPath($user):'/auth/login.php'))?>"><img src="<?=e(app_url('/logo_prishtina.png'))?>" alt=""><span><strong>Komuna e Prishtinës</strong><small>Tarifat e bizneseve</small></span></a>
<?php if($user):?>
<input type="checkbox" id="nav-toggle" class="nav-toggle-input"><label for="nav-toggle" class="nav-toggle" aria-label="Hap ose mbyll menynë">☰ Menyja</label>
<nav class="nav" aria-label="Navigimi kryesor">
<?php if($role==='BUSINESS'):?>
  <a class="nav-link" href="<?=e(app_url('/portal/index.php'))?>"<?=nav_current('/portal/index','/portal/business-request')?>>Bizneset e mia</a>
  <a class="nav-link" href="<?=e(app_url('/portal/invoices.php'))?>"<?=nav_current('/portal/invoice')?>>Faturat</a>
  <a class="nav-link" href="<?=e(app_url('/portal/permits.php'))?>"<?=nav_current('/portal/permit')?>>Lejet e punës</a>
  <a class="nav-link" href="<?=e(app_url('/portal/appeals.php'))?>"<?=nav_current('/portal/appeals')?>>Ankesat</a>
<?php else:?>
  <a class="nav-link" href="<?=e(app_url('/admin/dashboard.php'))?>"<?=nav_current('/admin/dashboard')?>>Paneli</a>
  <div class="nav-group"><button type="button" class="nav-group-title"<?=nav_current('/admin/businesses','/admin/imports')?>>Bizneset</button><div class="nav-menu">
    <a class="nav-link" href="<?=e(app_url('/admin/businesses/index.php'))?>"<?=nav_current('/admin/businesses')?>>Regjistri</a>
    <a class="nav-link" href="<?=e(app_url('/admin/businesses/index.php?classification=UNCLASSIFIED'))?>">Klasifikimi NACE</a>
    <?php if($isAdmin):?><a class="nav-link" href="<?=e(app_url('/admin/imports/index.php'))?>"<?=nav_current('/admin/imports')?>>Importet Excel</a><?php endif;?>
  </div></div>
  <?php if($isAdmin):?>
  <div class="nav-group"><button type="button" class="nav-group-title"<?=nav_current('/admin/finance')?>>Financat</button><div class="nav-menu">
    <a class="nav-link" href="<?=e(app_url('/admin/finance/index.php'))?>"<?=nav_current('/admin/finance/index')?>>Përmbledhje</a>
    <a class="nav-link" href="<?=e(app_url('/admin/finance/annual.php'))?>"<?=nav_current('/admin/finance/annual')?>>Faturimi vjetor</a>
    <a class="nav-link" href="<?=e(app_url('/admin/finance/invoices.php'))?>"<?=nav_current('/admin/finance/invoice')?>>Faturat</a>
    <a class="nav-link" href="<?=e(app_url('/admin/finance/payments.php'))?>"<?=nav_current('/admin/finance/payment')?>>Pagesat</a>
  </div></div>
  <?php endif;?>
  <?php if($isEditor):?>
  <div class="nav-group"><button type="button" class="nav-group-title"<?=nav_current('/admin/business-access','/admin/appeals')?>>Portali</button><div class="nav-menu">
    <a class="nav-link" href="<?=e(app_url('/admin/business-access/index.php'))?>"<?=nav_current('/admin/business-access')?>>Qasjet e bizneseve</a>
    <a class="nav-link" href="<?=e(app_url('/admin/appeals/index.php'))?>"<?=nav_current('/admin/appeals')?>>Ankesat</a>
  </div></div>
  <?php endif;?>
  <?php if($isAdmin):?>
  <div class="nav-group"><button type="button" class="nav-group-title"<?=nav_current('/admin/users','/admin/messages')?>>Administrimi</button><div class="nav-menu">
    <a class="nav-link" href="<?=e(app_url('/admin/users/index.php'))?>"<?=nav_current('/admin/users')?>>Përdoruesit</a>
    <a class="nav-link" href="<?=e(app_url('/admin/messages/index.php'))?>"<?=nav_current('/admin/messages')?>>Email raportet</a>
  </div></div>
  <?php endif;?>
<?php endif;?>
  <div class="user-box"><span class="user-name"><?=e($user['full_name'])?><small><?=e(label('role',$role))?></small></span><form method="post" action="<?=e(app_url('/auth/logout.php'))?>"><?=csrf_field()?><button class="btn-logout">Dil</button></form></div>
</nav>
<?php endif;?>
</div></header>
<main id="main" class="shell">
<?php foreach($flashes as $flash): $type=in_array($flash['type']??'',['success','error'],true)?$flash['type']:'info';?><div class="flash flash--<?=e($type)?>" role="<?=$type==='error'?'alert':'status'?>"><?=e($flash['message']??'')?></div><?php endforeach;?>
<?php require $templateFile;?>
</main>
<footer>Komuna e Prishtinës · Tarifat e bizneseve</footer>
</body></html>
