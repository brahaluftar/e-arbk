<div class="page-head"><div><h1>Email raportet</h1><p>Të dërguara: <?=number_format((int)($summary['sent']??0),0,',','.')?> · Dështuara: <?=number_format((int)($summary['failed']??0),0,',','.')?> · Gjithsej: <?=number_format((int)($summary['total']??0),0,',','.')?></p></div></div>
<?php if($messages===[]):?><section class="card empty">Nuk ka emaila të regjistruar.</section><?php else:?>
<div class="table-scroll"><table class="table">
  <thead><tr><th>Marrësi</th><th>Lloji</th><th>Statusi</th><th>Hapur</th><th class="num">Klikime</th></tr></thead>
  <tbody><?php foreach($messages as $m):?>
    <tr>
      <td><span class="cell-title"><?=e($m['recipient_email'])?></span><span class="cell-sub"><?=e($m['subject'])?> · <?=e(datetime_sq($m['queued_at']))?></span></td>
      <td data-label="Lloji"><?=e(label('message_type',$m['message_type']))?></td>
      <td data-label="Statusi"><?=badge('message',$m['status_code'])?><?php if($m['failure_message']):?><span class="cell-sub"><?=e($m['failure_message'])?></span><?php endif;?></td>
      <td data-label="Hapur"><?=e(datetime_sq($m['first_opened_at']))?></td>
      <td data-label="Klikime" class="num"><?=(int)($m['open_count']??0)?></td>
    </tr>
  <?php endforeach;?></tbody>
</table></div>
<?php endif;?>
