<p class="crumbs"><a href="<?=e(app_url('/admin/finance/payments.php'))?>">← Pagesat</a></p>
<div class="page-head"><div><h1><?=e($batch['original_filename'])?></h1><p>Importuar nga <?=e($batch['uploaded_by']??'—')?> · <?=e(datetime_sq($batch['created_at']))?> · <?=badge('import',$batch['status_code'])?></p></div></div>
<dl class="totals card">
  <div><dt>Rreshta</dt><dd><?=(int)$batch['total_rows']?></dd></div>
  <div><dt>Aplikuar</dt><dd><?=(int)$batch['applied_rows']?></dd></div>
  <div><dt>Dublikata</dt><dd><?=(int)$batch['duplicate_rows']?></dd></div>
  <div><dt>Për shqyrtim</dt><dd><?=(int)$batch['review_rows']?></dd></div>
  <div class="is-strong"><dt>Shuma e aplikuar</dt><dd><?=e(money($batch['applied_amount']))?></dd></div>
</dl>
<?php if($batch['restored_at']):?><p class="notice">Të hyrat u rindërtuan vetëm nga ky raport më <?=e(datetime_sq($batch['restored_at']))?> nga <?=e($batch['restored_by']??'—')?>.</p><?php endif;?>
<?php if($batch['error_message']):?><p class="notice notice--danger"><?=e($batch['error_message'])?></p><?php endif;?>
<nav class="tabs"><a href="?id=<?=(int)$batch['id']?>"<?=$onlyReview?' aria-current="page"':''?>>Për shqyrtim</a><a href="?id=<?=(int)$batch['id']?>&amp;all=1"<?=!$onlyReview?' aria-current="page"':''?>>Të gjitha rreshtat</a></nav>
<?php if($rows===[]):?><section class="card empty"><?=$onlyReview?'Të gjitha pagesat u aplikuan. Nuk ka asgjë për shqyrtim.':'Raporti nuk ka rreshta.'?></section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Rreshti</th><th>UNIREF / Fatura</th><th class="num">Shuma</th><th>Data</th><th>Paguesi / Referenca</th><th>Rezultati</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
  <tbody><?php foreach($rows as $row):?>
    <tr>
      <td><span class="cell-title">#<?=(int)$row['source_row_number']?></span></td>
      <td data-label="UNIREF / Fatura"><?=e($row['uniref']??$row['invoice_number']??'—')?><?php if($row['business_name_snapshot']):?><span class="cell-sub"><?=e($row['business_name_snapshot'])?></span><?php endif;?></td>
      <td data-label="Shuma" class="num"><?=$row['amount']!==null?e(money($row['amount'])):'—'?></td>
      <td data-label="Data"><?=e(date_sq($row['paid_on']))?></td>
      <td data-label="Paguesi / Referenca"><?=e($row['payer_name']??'—')?><span class="cell-sub"><?=e($row['reference']??'')?></span></td>
      <td data-label="Rezultati"><?=badge('payment_row',$row['status_code'])?><?php if($row['message']):?><span class="cell-sub"><?=e($row['message'])?></span><?php endif;?></td>
      <td class="actions"><?php if($row['invoice_id']):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$row['invoice_id'].'#pagesat'))?>">Fatura</a><?php endif;?></td>
    </tr>
  <?php endforeach;?></tbody>
</table></div>
<?php endif;?>
<?php if($batch['status_code']==='COMPLETED'):?>
<section class="card card--danger" id="rindertimi" aria-labelledby="restore-title">
  <h2 id="restore-title">Rindërto të hyrat vetëm nga ky raport</h2>
  <details class="disclosure"><summary>Fshi të gjitha pagesat dhe apliko vetëm këtë raport…</summary>
    <ul class="small">
      <li>Fshihen <strong>të gjitha</strong> pagesat aktuale: nga raportet e tjera, manuale dhe të stornuara.</li>
      <li>Pastaj rreshtat e këtij raporti aplikohen përsëri ndaj faturave aktuale. Rreshtat që nuk gjejnë faturë, tejkalojnë mbetjen ose përsëriten mbeten për shqyrtim.</li>
      <li>Gjithçka kryhet në një hap: nëse ndodh një gabim, nuk ndryshon asgjë.</li>
    </ul>
    <form method="post" action="<?=e(app_url('/admin/finance/restore-payments.php'))?>" class="form-grid"><?=csrf_field()?><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>">
      <label class="field">Komenti (i detyrueshëm)<textarea name="comment" maxlength="1000" required placeholder="P.sh. Rindërtim nga raporti kumulativ i Drejtorisë së Financave."></textarea></label>
      <label class="field"><span>Shkruani <strong>FSHI</strong> për të konfirmuar</span><input name="confirm" required autocomplete="off" pattern="[Ff][Ss][Hh][Ii]"></label>
      <div class="form-actions"><button class="btn--danger">Rindërto të hyrat nga ky raport</button></div>
    </form>
  </details>
</section>
<?php endif;?>
