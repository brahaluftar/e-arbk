<?php
$id=(int)$business['REGULATION_ID'];
$flag=static fn(mixed $v): string => $v===null?'—':((int)$v===1?'Po':'Jo');
$state=$business['assignment_method']??null;
?>
<p class="crumbs"><a href="<?=e(app_url($returnPath))?>">← Regjistri i bizneseve</a></p>
<div class="page-head">
  <div><h1><?=e($business['Emri'])?></h1><p>NRB <?=e($business['NRBIZ'])?> · ID <?=$id?> · <?=badge('status',$business['normalized_atk_status'])?></p></div>
</div>
<nav class="section-nav" aria-label="Seksionet"><a href="#te-dhenat">Të dhënat</a><a href="#njesite">Njësitë (<?=count($units)?>)</a><a href="#klasifikimi">Klasifikimi</a><a href="#faturat">Faturat (<?=count($invoices)?>)</a><a href="#lejet">Lejet (<?=count($permits)?>)</a></nav>

<section class="card" id="te-dhenat">
  <div class="card-head"><h2>Të dhënat e biznesit</h2><?php if($debt>0):?><span class="badge badge--danger">Borxh: <?=e(money($debt))?></span><?php else:?><span class="badge badge--success">Pa borxh</span><?php endif;?></div>
  <dl class="dl-grid">
    <?php foreach(['NUMRI_FISKAL'=>'Numri fiskal','ADRESA'=>'Adresa','EMAIL'=>'Emaili','NUMRI_TELEFONIT'=>'Telefoni','MENAXHERI'=>'Menaxheri','NACE_CODE_REG'=>'Kodi NACE','NACEPERSHKRIMI'=>'Veprimtaria NACE','Statusi'=>'Statusi ARBK','NR_PUNETOREVE'=>'Numri i punëtorëve','TOTAL_M'=>'Gjithsej meshkuj','TOTAL_F'=>'Gjithsej femra','pronesia_grua'=>'Pronësia e gruas (%)','perqindja_veteran'=>'Pronësia e veteranit (%)'] as $key=>$label):?>
      <div><dt><?=e($label)?></dt><dd><?=e(($business[$key]??'')===''||$business[$key]===null?'—':$business[$key])?></dd></div>
    <?php endforeach;?>
    <?php foreach(['Pasiv'=>'Pasiv','ATK_MBYLLUR'=>'ATK mbyllur','pronare_grua'=>'Pronare grua','pronar_veteran'=>'Pronar veteran'] as $key=>$label):?><div><dt><?=e($label)?></dt><dd><?=e($flag($business[$key]??null))?></dd></div><?php endforeach;?>
    <div><dt>Tarifa efektive</dt><dd><?=$business['effective_tariff']!==null?e(money($business['effective_tariff'])):'—'?></dd></div>
  </dl>
  <?php if($canEdit):?>
  <details class="disclosure"><summary>Edito të dhënat</summary>
    <form method="post" action="<?=e(app_url('/admin/businesses/update.php'))?>"><?=csrf_field()?><input type="hidden" name="business_id" value="<?=$id?>"><input type="hidden" name="return" value="<?=e($returnQuery)?>">
      <div class="form-grid">
        <?php foreach(['NUMRI_FISKAL'=>['Numri fiskal',30],'ADRESA'=>['Adresa',500],'EMAIL'=>['Emaili',254],'NUMRI_TELEFONIT'=>['Telefoni',50],'MENAXHERI'=>['Menaxheri',255]] as $field=>[$label,$max]):?><label class="field"><?=e($label)?><input name="<?=e($field)?>" maxlength="<?=$max?>" value="<?=e($business[$field]??'')?>"<?=$field==='EMAIL'?' type="email"':''?>></label><?php endforeach;?>
        <?php foreach(['NR_PUNETOREVE'=>'Numri i punëtorëve','TOTAL_M'=>'Gjithsej meshkuj','TOTAL_F'=>'Gjithsej femra'] as $field=>$label):?><label class="field"><?=e($label)?><input type="number" min="0" name="<?=e($field)?>" value="<?=e($business[$field]??'')?>"></label><?php endforeach;?>
        <?php foreach(['Pasiv'=>'Pasiv','ATK_MBYLLUR'=>'ATK mbyllur','pronare_grua'=>'Pronare grua','pronar_veteran'=>'Pronar veteran'] as $field=>$label):?><label class="field"><?=e($label)?><select name="<?=e($field)?>"><option value="">—</option><option value="1" <?=isset($business[$field])&&(int)$business[$field]===1?'selected':''?>>Po</option><option value="0" <?=isset($business[$field])&&(int)$business[$field]===0?'selected':''?>>Jo</option></select></label><?php endforeach;?>
        <?php foreach(['pronesia_grua'=>'Pronësia e gruas (%)','perqindja_veteran'=>'Pronësia e veteranit (%)'] as $field=>$label):?><label class="field"><?=e($label)?><input type="number" min="0" max="100" step="0.01" name="<?=e($field)?>" value="<?=e($business[$field]??'')?>"></label><?php endforeach;?>
      </div>
      <div class="form-actions"><button type="submit">Ruaj ndryshimet</button></div>
    </form>
  </details>
  <?php endif;?>
</section>

<section class="card" id="njesite">
  <div class="card-head"><div><h2>Njësitë / degët</h2><p>Njësitë aktive me “faturim automatik” faturohen në gjenerimin vjetor.</p></div></div>
  <?php if($units===[]):?><p class="empty">Nuk ka njësi të regjistruara.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Njësia</th><th>Kontakti</th><th>NACE</th><th class="num">Tarifa</th><th>Faturim</th><th>Statusi</th><?php if($canEdit):?><th><span class="visually-hidden">Veprime</span></th><?php endif;?></tr></thead>
    <tbody><?php foreach($units as $unit):?>
      <tr>
        <td><span class="cell-title"><?=e($unit['unit_name'])?></span><span class="cell-sub"><?=e($unit['address'])?></span></td>
        <td data-label="Kontakti"><?=e($unit['manager_name']??'—')?><span class="cell-sub"><?=e(trim(($unit['phone_number']??'').' '.($unit['email']??''))?:'—')?></span></td>
        <td data-label="NACE"><?=e($unit['nace_code'])?><span class="cell-sub"><?=e($unit['nace_description']??'')?></span></td>
        <td data-label="Tarifa" class="num"><?=$unit['annual_tariff']!==null?e(money($unit['annual_tariff'])):'—'?></td>
        <td data-label="Faturim"><?=(int)$unit['auto_invoice']===1?'Automatik':'Manual'?><?php if($unit['latest_invoice_year']):?><span class="cell-sub">Fatura e fundit: <?=(int)$unit['latest_invoice_year']?></span><?php endif;?></td>
        <td data-label="Statusi"><?=badge('active',(string)(int)$unit['is_active'])?></td>
        <?php if($canEdit):?><td class="actions"><details class="row-menu"><summary>Veprime</summary><div class="row-menu-body">
          <?php if($isAdmin&&(int)$unit['is_active']===1):?>
          <form method="post" action="<?=e(app_url('/admin/businesses/invoice-unit.php'))?>" class="cluster"><?=csrf_field()?><input type="hidden" name="business_id" value="<?=$id?>"><input type="hidden" name="unit_id" value="<?=(int)$unit['id']?>"><label class="field">Viti fiskal<input type="number" name="fiscal_year" min="2000" max="2100" value="<?=date('Y')?>" required></label><button class="btn--small">Gjenero faturën</button></form>
          <?php endif;?>
          <form method="post" action="<?=e(app_url('/admin/businesses/update-unit.php'))?>" class="stack"><?=csrf_field()?><input type="hidden" name="business_id" value="<?=$id?>"><input type="hidden" name="unit_id" value="<?=(int)$unit['id']?>">
            <?php $values=$unit; require __DIR__.'/_unit-fields.php';?>
            <label class="field">Statusi<select name="is_active"><option value="1" <?=(int)$unit['is_active']===1?'selected':''?>>Aktive</option><option value="0" <?=(int)$unit['is_active']===0?'selected':''?>>Joaktive</option></select></label>
            <button class="btn--small">Ruaj njësinë</button>
          </form>
        </div></details></td><?php endif;?>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
  <?php if($canEdit):?>
  <details class="disclosure"><summary>+ Regjistro njësi / degë</summary>
    <form method="post" action="<?=e(app_url('/admin/businesses/add-unit.php'))?>"><?=csrf_field()?><input type="hidden" name="business_id" value="<?=$id?>">
      <div class="form-grid"><?php $values=[]; require __DIR__.'/_unit-fields.php';?></div>
      <div class="form-actions"><button>Regjistro njësinë</button></div>
    </form>
  </details>
  <?php endif;?>
</section>

<section class="card" id="klasifikimi">
  <h2>Klasifikimi komunal</h2>
  <?php if($business['nace_list_id']):?><p><strong><?=e($business['nace_category'])?></strong> · <?=e(money($business['tariff_snapshot']))?> · <?=badge('classification',$state)?></p><?php else:?><p class="muted">Nuk ka klasifikim aktiv.</p><?php endif;?>
  <?php if($canEdit):?>
    <?php if($categories===[]):?><p class="notice">NACE_LIST nuk ka kategori për kodin <?=e($business['NACE_CODE_REG']??'—')?>.</p><?php else:?>
    <form method="post" action="<?=e(app_url('/admin/businesses/classify.php'))?>" class="disclosure"><?=csrf_field()?><input type="hidden" name="business_id" value="<?=$id?>"><input type="hidden" name="return" value="<?=e($returnQuery)?>">
      <label class="field">Kategoria e vlefshme për NACE <?=e($business['NACE_CODE_REG'])?><select name="mapping_key" required><option value="">Zgjidhni</option><?php foreach($categories as $category):?><option value="<?=e($category['mapping_key'])?>"><?=e($category['sector'].' — '.$category['activity'].' ('.money($category['tariff']).')')?></option><?php endforeach;?></select></label>
      <div class="form-actions"><button name="action" value="save">Ruaj</button><button class="btn--secondary" name="action" value="next">Ruaj dhe hap biznesin e radhës</button></div>
    </form>
    <?php endif;?>
  <?php endif;?>
</section>

<section class="card" id="faturat">
  <div class="card-head"><div><h2>Faturat</h2><p>Faturat e biznesit dhe të njësive të tij.</p></div><?php if($debt>0):?><span class="badge badge--danger">Borxh gjithsej: <?=e(money($debt))?></span><?php endif;?></div>
  <?php if($invoices===[]):?><p class="empty">Nuk ka fatura.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Fatura</th><th>Viti</th><th>Njësia</th><th class="num">Shuma</th><th class="num">Mbetja</th><th>Statusi</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($invoices as $invoice):?>
      <tr>
        <td><?php if($isAdmin):?><a class="cell-title" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$invoice['id']))?>"><?=e($invoice['invoice_number'])?></a><?php else:?><span class="cell-title"><?=e($invoice['invoice_number'])?></span><?php endif;?><span class="cell-sub">Afati <?=e(date_sq($invoice['due_on']))?></span></td>
        <td data-label="Viti"><?=(int)$invoice['fiscal_year']?></td>
        <td data-label="Njësia"><?=e($invoice['unit_name']??'Biznesi kryesor')?></td>
        <td data-label="Shuma" class="num"><?=e(money($invoice['amount']))?></td>
        <td data-label="Mbetja" class="num"><?=e(money($invoice['outstanding']))?></td>
        <td data-label="Statusi"><?=badge('invoice',$invoice['status_code'])?></td>
        <td class="actions"><?php if($isAdmin):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$invoice['id']))?>">Hap</a><?php elseif($invoice['uniref']):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/portal/invoice-pdf.php?id='.(int)$invoice['id']))?>">PDF</a><?php endif;?></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>

<section class="card" id="lejet">
  <h2>Lejet e ushtrimit të veprimtarisë</h2>
  <?php if($canEdit):?>
    <?php if($permitBlock!==null):?><p class="notice notice--danger"><?=e($permitBlock)?></p><?php else:?>
    <form method="post" action="<?=e(app_url('/admin/permits/issue.php'))?>" class="form-grid"><?=csrf_field()?><input type="hidden" name="business_id" value="<?=$id?>">
      <label class="field">Vlen nga<input type="date" name="valid_from" required value="<?=date('Y-m-d')?>"></label>
      <label class="field">Vlen deri<input type="date" name="valid_until" required value="<?=date('Y-m-d',strtotime('+1 year -1 day'))?>"></label>
      <div class="form-actions"><button>Gjenero lejen me QR</button></div>
    </form>
    <?php endif;?>
  <?php endif;?>
  <?php if($permits!==[]):?>
  <div class="table-scroll" id="permit-list"><table class="table">
    <thead><tr><th>Numri serik</th><th>Vlefshmëria</th><th>Statusi</th><th>Lëshuar nga</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($permits as $permit):?>
      <tr>
        <td><span class="cell-title"><?=e($permit['serial_number'])?></span><span class="cell-sub">Lëshuar <?=e(date_sq($permit['issued_on']))?></span></td>
        <td data-label="Vlefshmëria"><?=e(date_sq($permit['valid_from']).' – '.date_sq($permit['valid_until']))?></td>
        <td data-label="Statusi"><?=badge('permit',$permit['effective_status'])?></td>
        <td data-label="Lëshuar nga"><?=e($permit['issued_by']??'—')?></td>
        <td class="actions"><div class="actions"><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/permits/pdf.php?id='.(int)$permit['id']))?>">PDF</a>
          <?php if($canEdit&&$permit['status_code']==='ACTIVE'):?><details class="row-menu"><summary>Revoko</summary><div class="row-menu-body"><form method="post" action="<?=e(app_url('/admin/permits/revoke.php'))?>" class="stack"><?=csrf_field()?><input type="hidden" name="permit_id" value="<?=(int)$permit['id']?>"><input type="hidden" name="business_id" value="<?=$id?>"><label class="field">Arsyeja<textarea name="reason" maxlength="500" required></textarea></label><button class="btn--danger btn--small">Revoko lejen</button></form></div></details><?php endif;?>
        </div></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php elseif(!$canEdit):?><p class="empty">Nuk ka leje.</p><?php endif;?>
</section>
