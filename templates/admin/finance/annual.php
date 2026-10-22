<div class="page-head"><div><h1>Faturimi vjetor</h1><p>Krijon faturat vjetore për të gjitha bizneset aktive me tarifë dhe për njësitë me faturim automatik. Përpunimi kryhet në sfond.</p></div></div>
<section class="card">
  <h2>Gjenero faturat</h2>
  <form method="post" action="<?=e(app_url('/admin/finance/generate-annual.php'))?>" class="form-grid"><?=csrf_field()?>
    <label class="field">Data e fillimit të periudhës<small>Periudha zgjat 12 muaj; viti fiskal është viti i kësaj date.</small><input type="date" name="from_date" required value="<?=e(date('Y').'-01-01')?>"></label>
    <div class="form-actions"><button>Vendos në radhë</button></div>
  </form>
  <p class="help">Burimet e tarifës: <?=e(implode(', ',array_map(static fn(string $s): string => ['DISCOUNT'=>'tarifa me lirim','REGISTERED'=>'tarifa e regjistruar','CLASSIFICATION'=>'tarifa e klasifikimit'][$s]??$s,$sources)))?>. Bizneset që kanë faturë për vitin kalohen.</p>
</section>
<section class="card">
  <h2>Job-et dhe eksportet</h2>
  <?php if($jobs===[]):?><p class="empty">Ende nuk ka gjenerime.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Job</th><th>Periudha</th><th>Statusi</th><th>Progresi</th><th class="num">Krijuar</th><th class="num">Kaluar</th><th class="num">Gabime</th><th class="num">Totali</th><th>Dokumentet</th></tr></thead>
    <tbody><?php foreach($jobs as $job): $percent=(int)$job['total_items']>0?round((int)$job['processed_items']*100/(int)$job['total_items'],1):0;?>
      <tr>
        <td><a class="cell-title" href="<?=e(app_url('/admin/finance/invoices.php?job='.(int)$job['id'].'&year='.(int)$job['fiscal_year']))?>">#<?=(int)$job['id']?></a><span class="cell-sub"><?=e($job['requested_by']??'')?> · <?=e(datetime_sq($job['created_at']))?></span></td>
        <td data-label="Periudha"><?=e(date_sq($job['period_start']).' – '.date_sq($job['period_end']))?></td>
        <td data-label="Statusi"><?=badge('job',$job['status_code'])?></td>
        <td data-label="Progresi"><progress class="progress" max="100" value="<?=$percent?>"></progress><span class="cell-sub"><?=number_format($percent,1,',','.')?>% (<?=(int)$job['processed_items']?>/<?=(int)$job['total_items']?>)</span></td>
        <td data-label="Krijuar" class="num"><?=(int)$job['created_items']?></td>
        <td data-label="Kaluar" class="num"><?=(int)$job['skipped_items']?></td>
        <td data-label="Gabime" class="num"><?=(int)$job['failed_items']?></td>
        <td data-label="Totali" class="num"><?=e(money($job['total_amount']))?></td>
        <td data-label="Dokumentet"><div class="actions">
          <?php if(($job['artifact_status']??'')==='READY'):?><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/job-artifact.php?job='.(int)$job['id'].'&type=pdf'))?>">PDF</a><a class="btn btn--secondary btn--small" href="<?=e(app_url('/admin/finance/job-artifact.php?job='.(int)$job['id'].'&type=xlsx'))?>">Excel</a>
          <?php elseif(($job['artifact_status']??'')==='FAILED'):?><span class="small"><?=e($job['artifact_error']??'Eksporti dështoi')?></span><form method="post" action="<?=e(app_url('/admin/finance/retry-job-artifacts.php'))?>"><?=csrf_field()?><input type="hidden" name="job_id" value="<?=(int)$job['id']?>"><button class="btn--small btn--secondary">Riprovo eksportin</button></form>
          <?php else:?><?=badge('artifact',$job['artifact_status']??'PENDING')?><?php endif;?>
          <?php if((int)$job['failed_items']>0):?><form method="post" action="<?=e(app_url('/admin/finance/retry-annual-job.php'))?>"><?=csrf_field()?><input type="hidden" name="job_id" value="<?=(int)$job['id']?>"><button class="btn--small">Riprovo gabimet</button></form><?php endif;?>
        </div></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
