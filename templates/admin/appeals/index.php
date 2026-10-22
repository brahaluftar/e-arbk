<div class="page-head"><div><h1>Ankesat e faturave</h1><p>Rrjedha: pranim → koordinator → komision → shqyrtim → vendim → përgjigje.</p></div></div>
<?php if($appeals===[]):?><section class="card empty">Nuk ka ankesa.</section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Ankesa</th><th>Fatura</th><th>Ankuesi</th><th>Statusi</th><th><span class="visually-hidden">Hapi tjetër</span></th></tr></thead>
  <tbody><?php foreach($appeals as $a): $next=App\Service\InvoiceAppealService::TRANSITIONS[$a['status_code']]??[];?>
    <tr>
      <td><span class="cell-title"><?=e($a['subject'])?></span><details class="disclosure"><summary>Teksti i ankesës</summary><p><?=nl2br(e($a['appeal_text']))?></p><?php if($a['decision_text']):?><p><strong>Vendimi (<?=e(label('decision',$a['decision_code']))?>):</strong> <?=nl2br(e($a['decision_text']))?></p><?php endif;?></details></td>
      <td data-label="Fatura"><?=e($a['invoice_number'])?><span class="cell-sub"><?=e($a['business_name_snapshot'])?></span></td>
      <td data-label="Ankuesi"><?=e($a['submitter_name'])?><span class="cell-sub"><?=e(datetime_sq($a['submitted_at']))?></span></td>
      <td data-label="Statusi"><?=badge('appeal',$a['status_code'])?><?php if($a['commission_reference']):?><span class="cell-sub">Komisioni: <?=e($a['commission_reference'])?></span><?php endif;?></td>
      <td class="actions"><?php if($next!==[]):?><details class="row-menu"><summary>Hapi tjetër</summary><div class="row-menu-body">
        <?php foreach($next as $status):?>
        <form method="post" class="stack"><?=csrf_field()?><input type="hidden" name="appeal_id" value="<?=(int)$a['id']?>"><input type="hidden" name="to_status" value="<?=e($status)?>">
          <?php if($status==='COMMISSION'):?><label class="field">Referenca e komisionit<input name="commission_reference" maxlength="100" required></label><?php endif;?>
          <?php if($status==='UNDER_REVIEW'):?><label class="field">Shënimet e shqyrtimit<textarea name="review_notes"></textarea></label><?php endif;?>
          <?php if($status==='DECIDED'):?><label class="field">Vendimi<select name="decision_code" required><option value="">Zgjidhni</option><?php foreach(App\Support\Labels::options('decision') as $code=>$text):?><option value="<?=e($code)?>"><?=e($text)?></option><?php endforeach;?></select></label><label class="field">Arsyetimi<textarea name="decision_text" required></textarea></label><?php endif;?>
          <label class="field">Koment<input name="comment" maxlength="1000"></label>
          <button class="btn--small <?=$status==='REJECTED'?'btn--danger':''?>"><?=e(label('appeal_action',$status))?></button>
        </form>
        <?php endforeach;?>
      </div></details><?php endif;?></td>
    </tr>
  <?php endforeach;?></tbody>
</table></div>
<?php endif;?>
