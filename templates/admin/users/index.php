<section class="hero"><h1>Përdoruesit e drejtorisë</h1><p>Krijoni llogari për zyrtarët dhe caktoni qasjen në aplikacion.</p></section>
<section class="panel">
    <h2><?=$editing === null ? 'Shto përdorues' : 'Ndrysho përdoruesin'?></h2>
    <form method="post" action="<?=e(app_url('/admin/users/save.php'))?>">
        <input type="hidden" name="_csrf" value="<?=e($csrf->token())?>">
        <input type="hidden" name="id" value="<?=(int)$form['id']?>">
        <input type="hidden" name="version" value="<?=e($form['version'])?>">
        <div class="detail-grid">
            <label class="field">Emri dhe mbiemri<input name="full_name" maxlength="200" required autocomplete="name" value="<?=e($form['full_name'])?>"></label>
            <label class="field">Emaili i hyrjes<input type="email" name="email" maxlength="254" required autocomplete="off" value="<?=e($form['email'])?>"></label>
            <label class="field">Roli<select name="role_code" required><?php foreach($roles as $code=>$label):?><option value="<?=e($code)?>" <?=$form['role_code']===$code?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
            <label class="field">Statusi<select name="is_active"><option value="1" <?=(string)$form['is_active']==='1'?'selected':''?>>Aktiv</option><option value="0" <?=(string)$form['is_active']==='0'?'selected':''?>>Joaktiv</option></select></label>
            <label class="field"><?=$editing === null ? 'Fjalëkalimi' : 'Fjalëkalim i ri (opsional)'?><input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" <?=$editing===null?'required':''?> aria-describedby="password-help"></label>
            <label class="field">Përsërit fjalëkalimin<input type="password" name="password_confirmation" minlength="12" maxlength="72" autocomplete="new-password" <?=$editing===null?'required':''?>></label>
        </div>
        <p id="password-help"><small>Të paktën 12 karaktere. Gjatë editimit, lërini bosh të dy fjalëkalimet për ta ruajtur atë ekzistues.</small></p>
        <p><small>Zyrtari mund të lexojë, eksportojë, editojë dhe klasifikojë biznese. Roli “Vetëm lexim” lejon shikimin dhe eksportin. Administratori menaxhon edhe përdoruesit dhe importet.</small></p>
        <div class="actions"><button type="submit"><?=$editing===null?'Krijo përdoruesin':'Ruaj ndryshimet'?></button><?php if($editing!==null):?><a class="button secondary" href="<?=e(app_url('/admin/users/index.php'))?>">Anulo / Shto tjetër</a><?php endif;?></div>
    </form>
</section>
<section class="panel"><h2>Llogaritë (<?=count($accounts)?>)</h2>
    <div class="table-wrap"><table><thead><tr><th>Emri</th><th>Emaili</th><th>Roli</th><th>Statusi</th><th>Hyrja e fundit</th><th>Veprime</th></tr></thead><tbody>
    <?php foreach($accounts as $account):?><tr>
        <td data-label="Emri"><?=e($account['full_name'])?><?=(int)$account['id']===$user['id']?' (ju)':''?></td>
        <td data-label="Emaili"><?=e($account['email'])?></td><td data-label="Roli"><?=e($roles[$account['role_code']] ?? $account['role_code'])?></td>
        <td data-label="Statusi"><span class="badge <?=(int)$account['is_active']===1?'ACTIVE':'DEACTIVATED'?>"><?=(int)$account['is_active']===1?'Aktiv':'Joaktiv'?></span></td>
        <td data-label="Hyrja e fundit"><?=e($account['last_login_at'] ?? 'Ende pa hyrje')?></td>
        <td data-label="Veprimi"><a class="button secondary" href="<?=e(app_url('/admin/users/index.php?id='.(int)$account['id']))?>">Ndrysho</a></td>
    </tr><?php endforeach;?></tbody></table></div>
</section>
