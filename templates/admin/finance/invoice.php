<?php
$id=(int)$invoice['id'];$status=(string)$invoice['balance_status'];$cancelled=$status==='CANCELLED';
$open=$cancelled?0.0:(float)$invoice['outstanding'];$obligations=$open+(float)$invoice['previous_debt'];
$fieldLabels=['amount'=>'Shuma','due_on'=>'Afati i pagesës','notes'=>'Shënimi','payment_id'=>'Pagesa','paid_on'=>'Data e pagesës','reference'=>'Referenca','uniref'=>'UNIREF','email'=>'Emaili','language'=>'Gjuha'];
$show=static fn(mixed $v): string => $v===null||$v===''?'—':(is_scalar($v)?(string)$v:json_encode($v,JSON_UNESCAPED_UNICODE));
?>
<p class="crumbs"><a href="<?=e(app_url('/admin/finance/invoices.php?year='.(int)$invoice['fiscal_year']))?>">← Faturat <?=(int)$invoice['fiscal_year']?></a> · <a href="<?=e(app_url('/admin/businesses/view.php?id='.(int)$invoice['business_id'].'#faturat'))?>">Biznesi</a></p>
<div class="page-head">
  <div><h1>Fatura <?=e($invoice['invoice_number'])?></h1><p><?=e($invoice['business_name_snapshot']??$invoice['legal_name'])?> · <?=badge('invoice',$status)?></p></div>
  <?php if($invoice['uniref']):?><div class="page-actions"><?php foreach(App\Billing\InvoiceTranslations::LANGUAGES as $code=>$name):?><a class="btn <?=$code==='sq'?'':'btn--secondary'?>" href="<?=e(app_url('/admin/finance/invoice-pdf.php?id='.$id.'&lang='.$code))?>">PDF <?=e(strtoupper($code))?></a><?php endforeach;?></div><?php endif;?>
</div>
<dl class="totals card">
  <div><dt>Totali i faturës</dt><dd><?=e(money($invoice['amount']))?></dd></div>
  <div><dt>Paguar</dt><dd><?=e(money($invoice['paid_amount']))?></dd></div>
  <div><dt>Mbetja e kësaj fature</dt><dd><?=e(money($open))?></dd></div>
  <div><dt>Borxhi paraprak</dt><dd><?=e(money($invoice['previous_debt']))?></dd></div>
  <div class="is-strong"><dt>Total detyrime</dt><dd><?=e(money($obligations))?></dd></div>
</dl>
<section class="card">
  <h2>Të dhënat</h2>
  <dl class="dl-grid">
    <div><dt>UNIREF</dt><dd><?=e($invoice['uniref']??'—')?></dd></div>
    <div><dt>Biznesi</dt><dd><?=e($invoice['legal_name'])?> (NRB <?=e($invoice['registration_number'])?>)</dd></div>
    <div><dt>Njësia</dt><dd><?=e($invoice['unit_name']??'Biznesi kryesor')?></dd></div>
    <div><dt>Viti fiskal</dt><dd><?=(int)$invoice['fiscal_year']?></dd></div>
    <div><dt>Data e faturës</dt><dd><?=e(date_sq($invoice['issued_on']))?></dd></div>
    <div><dt>Afati i pagesës</dt><dd><?=e(date_sq($invoice['due_on']))?></dd></div>
    <div><dt>Periudha</dt><dd><?=e(date_sq($invoice['period_start']).' – '.date_sq($invoice['period_end']))?></dd></div>
    <div><dt>NACE</dt><dd><?=e(($invoice['registered_nace_code_snapshot']??'—').' '.($invoice['nace_description_snapshot']??''))?></dd></div>
    <div><dt>Krijuar nga</dt><dd><?=e($invoice['created_by_name']??'—')?> · <?=e(datetime_sq($invoice['created_at']))?></dd></div>
    <div><dt>Dërguar me email</dt><dd><?=e(datetime_sq($invoice['emailed_at']))?></dd></div>
    <div><dt>Shënimi</dt><dd><?=e($invoice['notes']??'—')?></dd></div>
  </dl>
  <?php if(!$cancelled):?>
  <details class="disclosure"><summary>Edito faturën</summary>
    <form method="post" action="<?=e(app_url('/admin/finance/update-invoice.php'))?>" class="form-grid"><?=csrf_field()?><input type="hidden" name="invoice_id" value="<?=$id?>"><input type="hidden" name="action" value="edit">
      <label class="field">Shuma (€)<input type="number" name="amount" min="0.01" step="0.01" required value="<?=e(number_format((float)$invoice['amount'],2,'.',''))?>"></label>
      <label class="field">Afati i pagesës<input type="date" name="due_on" value="<?=e(substr((string)($invoice['due_on']??''),0,10))?>"></label>
      <label class="field span-all">Shënimi në faturë<input name="notes" maxlength="500" value="<?=e($invoice['notes']??'')?>"></label>
      <label class="field span-all">Koment (i detyrueshëm)<small>Çfarë u ndryshua dhe pse. Ruhet në historik me emrin tuaj dhe kohën.</small><textarea name="comment" maxlength="1000" required></textarea></label>
      <div class="form-actions"><button>Ruaj ndryshimet</button></div>
    </form>
  </details>
  <?php endif;?>
  <details class="disclosure"><summary><?=$cancelled?'Rikthe faturën':'Anulo faturën'?></summary>
    <form method="post" action="<?=e(app_url('/admin/finance/update-invoice.php'))?>" class="stack"><?=csrf_field()?><input type="hidden" name="invoice_id" value="<?=$id?>"><input type="hidden" name="action" value="<?=$cancelled?'restore':'cancel'?>">
      <?php if(!$cancelled):?><p class="notice">Fatura e anuluar nuk llogaritet si borxh. Faturat me pagesa nuk mund të anulohen pa i stornuar pagesat.</p><?php endif;?>
      <label class="field">Koment (i detyrueshëm)<textarea name="comment" maxlength="1000" required></textarea></label>
      <div class="form-actions"><button class="<?=$cancelled?'':'btn--danger'?>"><?=$cancelled?'Rikthe faturën':'Anulo faturën'?></button></div>
    </form>
  </details>
</section>
<section class="card" id="pagesat">
  <h2>Pagesat</h2>
  <?php if($payments===[]):?><p class="empty">Nuk ka pagesa.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Data</th><th class="num">Shuma</th><th>Referenca</th><th>Regjistruar nga</th><th>Statusi</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($payments as $payment): $reversed=$payment['reversed_at']!==null;?>
      <tr>
        <td><span class="cell-title"><?=e(date_sq($payment['paid_on']))?></span><span class="cell-sub"><?=$payment['import_row_id']?'Nga raporti i Drejtorisë së Financave':'Manuale'?></span></td>
        <td data-label="Shuma" class="num"><?=e(money($payment['amount']))?></td>
        <td data-label="Referenca"><?=e($payment['reference']??'—')?><?php if($payment['notes']):?><span class="cell-sub"><?=e($payment['notes'])?></span><?php endif;?></td>
        <td data-label="Regjistruar nga"><?=e($payment['recorded_by']??'—')?><span class="cell-sub"><?=e(datetime_sq($payment['created_at']))?></span></td>
        <td data-label="Statusi"><?php if($reversed):?><span class="badge badge--danger">Stornuar</span><span class="cell-sub"><?=e(($payment['reversed_by']??'').' · '.datetime_sq($payment['reversed_at']).': '.$payment['reversal_reason'])?></span><?php else:?><span class="badge badge--success">Valide</span><?php endif;?></td>
        <td class="actions"><?php if(!$reversed):?><details class="row-menu"><summary>Storno</summary><div class="row-menu-body"><form method="post" action="<?=e(app_url('/admin/finance/reverse-payment.php'))?>" class="stack"><?=csrf_field()?><input type="hidden" name="payment_id" value="<?=(int)$payment['id']?>"><input type="hidden" name="invoice_id" value="<?=$id?>"><label class="field">Arsyeja e stornimit<textarea name="reason" maxlength="500" required></textarea></label><button class="btn--danger btn--small">Storno pagesën</button></form></div></details><?php endif;?></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
  <?php if(!$cancelled&&$open>0):?>
  <details class="disclosure" <?=$payments===[]?'open':''?>><summary>+ Regjistro pagesë</summary>
    <form method="post" action="<?=e(app_url('/admin/finance/save-payment.php'))?>" class="form-grid"><?=csrf_field()?><input type="hidden" name="invoice_id" value="<?=$id?>">
      <label class="field">Shuma (€)<small>Mbetja: <?=e(money($open))?></small><input name="amount" type="number" min="0.01" max="<?=e(number_format($open,2,'.',''))?>" step="0.01" required value="<?=e(number_format($open,2,'.',''))?>"></label>
      <label class="field">Data e pagesës<input name="paid_on" type="date" required value="<?=e(date('Y-m-d'))?>"></label>
      <label class="field">Referenca<input name="reference" maxlength="100"></label>
      <label class="field">Shënim<input name="notes" maxlength="500"></label>
      <div class="form-actions"><button>Regjistro pagesën</button></div>
    </form>
  </details>
  <?php endif;?>
</section>
<?php if($invoice['uniref']&&!$cancelled):?>
<section class="card">
  <h2>Dërgo me email</h2>
  <form method="post" action="<?=e(app_url('/admin/finance/email-invoice.php'))?>" class="form-grid"><?=csrf_field()?><input type="hidden" name="invoice_id" value="<?=$id?>">
    <label class="field">Emaili<small>Bosh = përdoruesi i portalit ose emaili i biznesit (<?=e($invoice['business_email']??'—')?>)</small><input type="email" name="email" maxlength="254"></label>
    <label class="field">Gjuha e faturës<select name="lang"><?php foreach(App\Billing\InvoiceTranslations::LANGUAGES as $code=>$name):?><option value="<?=e($code)?>"><?=e($name)?></option><?php endforeach;?></select></label>
    <div class="form-actions"><button class="btn--secondary">Dërgo</button></div>
  </form>
</section>
<?php endif;?>
<section class="card">
  <h2>Historiku</h2>
  <?php if($history===[]):?><p class="empty">Nuk ka ngjarje.</p><?php else:?>
  <ol class="timeline">
    <?php foreach($history as $event):?>
    <li><strong><?=e(label('invoice_event',$event['event_code']))?></strong> <span class="meta">· <?=e($event['actor_name']??'Sistemi')?> · <?=e(datetime_sq($event['created_at']))?></span>
      <?php if($event['changes']!==[]):?><ul><?php foreach($event['changes'] as $field=>$change):?><li><?=e($fieldLabels[$field]??$field)?>: <?php if(is_array($change)&&array_key_exists('from',$change)):?><?=e($show($change['from']))?> → <strong><?=e($show($change['to']))?></strong><?php else:?><?=e($show($change))?><?php endif;?></li><?php endforeach;?></ul><?php endif;?>
      <?php if($event['comment']):?><blockquote><?=nl2br(e($event['comment']))?></blockquote><?php endif;?>
    </li>
    <?php endforeach;?>
  </ol>
  <?php endif;?>
</section>
