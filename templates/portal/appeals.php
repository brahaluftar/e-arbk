<div class="page-head"><div><h1>Ankesat mbi faturat</h1><p>Ankesa shqyrtohet nga koordinatori dhe komisioni; vendimi shfaqet këtu.</p></div></div>
<section class="card"><h2>Paraqit ankesë</h2>
  <?php if($invoices===[]):?><p class="empty">Nuk keni fatura për të cilat mund të ankoheni.</p><?php else:?>
  <form method="post" class="form-grid"><?=csrf_field()?>
    <label class="field">Fatura<select name="invoice_id" required><option value="">Zgjidhni</option><?php foreach($invoices as $invoice):?><option value="<?=(int)$invoice['id']?>"><?=e($invoice['invoice_number'].' — '.$invoice['business_name'])?></option><?php endforeach;?></select></label>
    <label class="field">Titulli<input name="subject" maxlength="200" required></label>
    <label class="field span-all">Përshkrimi<textarea name="appeal_text" maxlength="10000" rows="6" required></textarea></label>
    <div class="form-actions"><button>Dërgo ankesën</button></div>
  </form>
  <?php endif;?>
</section>
<section class="card"><h2>Ankesat e mia</h2>
  <?php if($appeals===[]):?><p class="empty">Nuk ka ankesa.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Ankesa</th><th>Fatura</th><th>Statusi</th><th>Vendimi</th></tr></thead>
    <tbody><?php foreach($appeals as $appeal):?><tr>
      <td><span class="cell-title"><?=e($appeal['subject'])?></span><span class="cell-sub"><?=e(datetime_sq($appeal['submitted_at']))?></span></td>
      <td data-label="Fatura"><?=e($appeal['invoice_number'])?><span class="cell-sub"><?=e($appeal['business_name'])?></span></td>
      <td data-label="Statusi"><?=badge('appeal',$appeal['status_code'])?></td>
      <td data-label="Vendimi"><?=$appeal['decision_code']?badge('decision',$appeal['decision_code']).' ':''?><?=e($appeal['decision_text']??'—')?></td>
    </tr><?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
