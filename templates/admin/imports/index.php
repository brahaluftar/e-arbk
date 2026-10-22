<div class="page-head"><div><h1>Importet Excel</h1><p>Ngarkoni eksportet e ARBK-së dhe listat e kontakteve ose njësive. Përpunimi kryhet nga worker-i në sfond.</p></div></div>
<section class="card">
  <h2>Import i ri</h2>
  <form method="post" action="<?=e(app_url('/admin/imports/upload.php'))?>" enctype="multipart/form-data" class="form-grid"><?=csrf_field()?>
    <label class="field">Lloji<select name="source_type" required><?php foreach(App\Support\Labels::options('import_type') as $code=>$text):?><option value="<?=e($code)?>"><?=e($text)?></option><?php endforeach;?></select></label>
    <label class="field">Skedari XLSX<input type="file" name="xlsx" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
    <div class="form-actions"><button>Vendos në radhë</button></div>
  </form>
  <details class="disclosure"><summary>Kolonat e kërkuara</summary>
    <ul class="small">
      <li><strong>Regjistri, Grua/Vajzë, të shuara:</strong> Nr. i biznesit, Emri i biznesit, Aktiv. Nace2 - Përshkrimi, Sektori, Qyteti (importohen vetëm rreshtat me Qyteti = Prishtinë). Qelizat bosh nuk fshijnë vlerat ekzistuese.</li>
      <li><strong>Kontaktet:</strong> NRB, MENAXHERI, NUMRI_TELEFONIT, NUMRI_PUNETOREVE, Madhesia.</li>
      <li><strong>Njësitë / degët:</strong> NRB, EMRI_NJESISE, ADRESA, NUMRI_TELEFONIT, EMAILI, MENAXHERI, NACE (4 karaktere), VEPRIMTARIA_NACE, TARIFA_VJETORE, FATURIM_AUTOMATIK (Po/Jo).</li>
    </ul>
  </details>
</section>
<section class="card">
  <h2>Historiku</h2>
  <?php if($runs===[]):?><p class="empty">Nuk ka importe.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Skedari</th><th>Lloji</th><th>Statusi</th><th class="num">Rreshta</th><th class="num">Shtuar</th><th class="num">Përditësuar</th><th class="num">Gabime</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($runs as $run):?>
      <tr>
        <td><span class="cell-title"><?=e($run['original_filename'])?></span><span class="cell-sub">#<?=(int)$run['id']?> · <?=e(($run['uploaded_by']??'').' · '.datetime_sq($run['completed_at']??$run['created_at']))?></span></td>
        <td data-label="Lloji"><?=e(label('import_type',$run['source_type']))?></td>
        <td data-label="Statusi"><?=badge('import',$run['status'])?><?php if($run['error_message']):?><span class="cell-sub"><?=e($run['error_message'])?></span><?php endif;?></td>
        <td data-label="Rreshta" class="num"><?=number_format((int)$run['total_rows'],0,',','.')?></td>
        <td data-label="Shtuar" class="num"><?=number_format((int)$run['inserted_rows'],0,',','.')?></td>
        <td data-label="Përditësuar" class="num"><?=number_format((int)$run['updated_rows'],0,',','.')?></td>
        <td data-label="Gabime" class="num"><?=number_format((int)$run['error_rows'],0,',','.')?></td>
        <td class="actions"><?php if($run['status']==='FAILED'):?><form method="post" action="<?=e(app_url('/admin/imports/retry.php'))?>"><?=csrf_field()?><input type="hidden" name="run_id" value="<?=(int)$run['id']?>"><button class="btn--small btn--secondary">Riprovo</button></form><?php endif;?></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
