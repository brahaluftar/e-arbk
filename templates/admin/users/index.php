<div class="page-head"><div><h1>Përdoruesit</h1><p>Llogaritë e zyrtarëve komunalë. Llogaritë e bizneseve krijohen me ftesë nga “Qasjet e bizneseve”.</p></div>
  <?php if($editing!==null):?><div class="page-actions"><a class="btn btn--secondary" href="<?=e(app_url('/admin/users/index.php'))?>">+ Përdorues i ri</a></div><?php endif;?></div>
<section class="card">
  <h2><?=$editing === null ? 'Shto përdorues' : 'Ndrysho: '.e($editing['full_name'])?></h2>
  <form method="post" action="<?=e(app_url('/admin/users/save.php'))?>"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$form['id']?>"><input type="hidden" name="version" value="<?=e($form['version'])?>">
    <div class="form-grid">
      <label class="field">Emri dhe mbiemri<input name="full_name" maxlength="200" required autocomplete="name" value="<?=e($form['full_name'])?>"></label>
      <label class="field">Emaili i hyrjes<input type="email" name="email" maxlength="254" required autocomplete="off" value="<?=e($form['email'])?>"></label>
      <label class="field">Roli<select name="role_code" required><?php foreach($roles as $code=>$label):?><option value="<?=e($code)?>" <?=$form['role_code']===$code?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
      <label class="field">Statusi<select name="is_active"><option value="1" <?=(string)$form['is_active']==='1'?'selected':''?>>Aktiv</option><option value="0" <?=(string)$form['is_active']==='0'?'selected':''?>>Joaktiv</option></select></label>
      <label class="field"><?=$editing === null ? 'Fjalëkalimi' : 'Fjalëkalim i ri (opsional)'?><small>12–72 karaktere</small><input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" <?=$editing===null?'required':''?>></label>
      <label class="field">Përsërit fjalëkalimin<input type="password" name="password_confirmation" minlength="12" maxlength="72" autocomplete="new-password" <?=$editing===null?'required':''?>></label>
    </div>
    <p class="help">Zyrtari lexon, eksporton, editon dhe klasifikon biznese. “Vetëm lexim” lejon shikimin dhe eksportin. Administratori menaxhon edhe financat, importet dhe përdoruesit. Ndryshimi i fjalëkalimit, i rolit ose çaktivizimi e mbyll sesionin aktiv të përdoruesit.</p>
    <div class="form-actions"><button type="submit"><?=$editing===null?'Krijo përdoruesin':'Ruaj ndryshimet'?></button></div>
  </form>
</section>
<section class="card"><h2>Llogaritë (<?=count($accounts)?>)</h2>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Emri</th><th>Emaili</th><th>Roli</th><th>Statusi</th><th>Hyrja e fundit</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($accounts as $account):?><tr>
      <td><span class="cell-title"><?=e($account['full_name'])?><?=(int)$account['id']===$user['id']?' (ju)':''?></span></td>
      <td data-label="Emaili"><?=e($account['email'])?></td>
      <td data-label="Roli"><?=e(label('role',$account['role_code']))?></td>
      <td data-label="Statusi"><?=badge('active',(string)(int)$account['is_active'])?></td>
      <td data-label="Hyrja e fundit"><?=$account['last_login_at']?e(datetime_sq($account['last_login_at'])):'Ende pa hyrje'?></td>
      <td class="actions"><?php if($account['role_code']!=='BUSINESS'):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/users/index.php?id='.(int)$account['id']))?>">Ndrysho</a><?php endif;?></td>
    </tr><?php endforeach;?></tbody>
  </table></div>
</section>
