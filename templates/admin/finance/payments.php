<div class="page-head"><div><h1>Pagesat</h1><p>Regjistrimi automatik nga raportet ditore dhe regjistrimi manual i pagesave.</p></div></div>
<section class="card" aria-labelledby="import-title">
  <div class="card-head"><div><h2 id="import-title">Raporti ditor i pagesave</h2><p>Ngarkoni raportin e pranuar nga Drejtoria e Financave. Pagesat lidhen me faturat sipas UNIREF-it dhe aplikohen automatikisht.</p></div><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/payment-template.php'))?>">Shkarko shabllonin</a></div>
  <form method="post" action="<?=e(app_url('/admin/finance/import-payments.php'))?>" enctype="multipart/form-data" class="form-grid"><?=csrf_field()?>
    <label class="field">Raporti (XLSX ose CSV)<input type="file" name="report" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required></label>
    <div class="form-actions"><button>Përpuno raportin</button></div>
  </form>
  <details class="disclosure"><summary>Formati i raportit</summary>
    <ul class="small">
      <li><strong>UNIREF</strong> (ose <em>Nr. i faturës</em>, ose <em>Përshkrimi</em> që përmban UNIREF-in) — identifikon faturën.</li>
      <li><strong>Shuma</strong> — p.sh. <code>125,50</code> ose <code>125.50</code>.</li>
      <li><strong>Data e pagesës</strong> — <code>22.10.2026</code>, <code>22/10/2026</code> ose <code>2026-10-22</code>.</li>
      <li><em>Referenca</em> dhe <em>Paguesi</em> — opsionale; referenca përdoret për të njohur dublikatat.</li>
    </ul>
    <p class="help">Pagesat që tejkalojnë mbetjen, faturat e anuluara, UNIREF-ët e panjohur dhe dublikatat nuk aplikohen; ato mbeten për shqyrtim. I njëjti skedar nuk mund të importohet dy herë.</p>
  </details>
</section>
<section class="card">
  <h2>Raportet e importuara</h2>
  <?php if($imports===[]):?><p class="empty">Ende nuk është importuar asnjë raport.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Raporti</th><th>Statusi</th><th class="num">Rreshta</th><th class="num">Aplikuar</th><th class="num">Për shqyrtim</th><th class="num">Shuma e aplikuar</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($imports as $batch):?>
      <tr>
        <td><a class="cell-title" href="<?=e(app_url('/admin/finance/payment-import.php?id='.(int)$batch['id']))?>"><?=e($batch['original_filename'])?></a><span class="cell-sub"><?=e(($batch['uploaded_by']??'').' · '.datetime_sq($batch['created_at']))?></span></td>
        <td data-label="Statusi"><?=badge('import',$batch['status_code'])?><?php if($batch['error_message']):?><span class="cell-sub"><?=e($batch['error_message'])?></span><?php endif;?></td>
        <td data-label="Rreshta" class="num"><?=(int)$batch['total_rows']?></td>
        <td data-label="Aplikuar" class="num"><?=(int)$batch['applied_rows']?></td>
        <td data-label="Për shqyrtim" class="num"><?=(int)$batch['review_rows']+(int)$batch['duplicate_rows']?></td>
        <td data-label="Shuma e aplikuar" class="num"><?=e(money($batch['applied_amount']))?></td>
        <td class="actions"><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/payment-import.php?id='.(int)$batch['id']))?>">Detajet</a></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
<section class="card" id="manuale">
  <h2>Pagesë manuale</h2>
  <form method="get" class="cluster"><label class="field">Gjej faturën<input name="q" value="<?=e($query)?>" maxlength="120" required placeholder="UNIREF, numri i faturës, biznesi ose NRB"></label><button class="btn--secondary">Kërko</button></form>
  <?php if($query!==''&&$payable===[]):?><p class="empty">Nuk u gjet faturë me bilanc për “<?=e($query)?>”.</p><?php elseif($payable!==[]):?>
  <div class="table-scroll disclosure"><table class="table">
    <thead><tr><th>Fatura</th><th>Biznesi</th><th>Viti</th><th class="num">Mbetja</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($payable as $invoice):?>
      <tr>
        <td><span class="cell-title"><?=e($invoice['invoice_number'])?></span><span class="cell-sub">Afati <?=e(date_sq($invoice['due_on']))?></span></td>
        <td data-label="Biznesi"><?=e($invoice['business_name_snapshot']??$invoice['legal_name'])?><span class="cell-sub">NRB <?=e($invoice['registration_number'])?></span></td>
        <td data-label="Viti"><?=(int)$invoice['fiscal_year']?></td>
        <td data-label="Mbetja" class="num"><?=e(money($invoice['outstanding']))?></td>
        <td class="actions"><a class="btn btn--small" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$invoice['id'].'#pagesat'))?>">Regjistro pagesë</a></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
<section class="card">
  <h2>Pagesat e fundit</h2>
  <?php if($recent===[]):?><p class="empty">Nuk ka pagesa.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Fatura</th><th>Data</th><th class="num">Shuma</th><th>Burimi</th><th>Statusi</th></tr></thead>
    <tbody><?php foreach($recent as $payment):?>
      <tr>
        <td><a class="cell-title" href="<?=e(app_url('/admin/finance/invoice.php?id='.(int)$payment['invoice_id'].'#pagesat'))?>"><?=e($payment['invoice_number'])?></a><span class="cell-sub"><?=e($payment['business_name_snapshot']??'')?></span></td>
        <td data-label="Data"><?=e(date_sq($payment['paid_on']))?></td>
        <td data-label="Shuma" class="num"><?=e(money($payment['amount']))?></td>
        <td data-label="Burimi"><?=$payment['import_row_id']?'Raport':'Manuale'?><span class="cell-sub"><?=e($payment['recorded_by']??'')?></span></td>
        <td data-label="Statusi"><?=$payment['reversed_at']?'<span class="badge badge--danger">Stornuar</span>':'<span class="badge badge--success">Valide</span>'?></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
