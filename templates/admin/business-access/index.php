<div class="page-head"><div><h1>Qasjet e bizneseve</h1><p>Ftesat, kërkesat për qasje dhe lidhjet e përdoruesve të portalit me bizneset.</p></div></div>
<nav class="section-nav"><a href="#kerkesat">Kërkesat (<?=count($requests)?>)</a><a href="#ftesat">Ftesat</a><a href="#lidhjet">Lidhjet aktive</a></nav>
<section class="card" id="kerkesat">
  <h2>Kërkesat për aprovim</h2>
  <?php if($requests===[]):?><p class="empty">Nuk ka kërkesa në pritje.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Përdoruesi</th><th>Biznesi</th><th>Data</th><th>Vendimi</th></tr></thead>
    <tbody><?php foreach($requests as $request):?>
      <tr>
        <td><span class="cell-title"><?=e($request['full_name'])?></span><span class="cell-sub"><?=e($request['email'])?></span></td>
        <td data-label="Biznesi"><?=e($request['legal_name'])?><span class="cell-sub">NRB <?=e($request['registration_number'])?></span></td>
        <td data-label="Data"><?=e(datetime_sq($request['requested_at']))?></td>
        <td class="actions"><form method="post" action="<?=e(app_url('/admin/business-access/review.php'))?>" class="stack"><?=csrf_field()?><input type="hidden" name="request_id" value="<?=(int)$request['id']?>"><label class="field">Shënim (opsional)<input name="note" maxlength="300"></label><div class="actions"><button class="btn--small" name="decision" value="APPROVED">Aprovo</button><button class="btn--small btn--danger" name="decision" value="REJECTED">Refuzo</button></div></form></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
<section class="card" id="ftesat">
  <h2>Fto përdorues biznesi</h2>
  <form method="post" action="<?=e(app_url('/admin/business-access/invite.php'))?>" class="form-grid"><?=csrf_field()?><label class="field">Emaili<input type="email" name="email" maxlength="254" required></label><div class="form-actions"><button>Dërgo ftesën</button></div></form>
  <details class="disclosure"><summary>Ftesat masive</summary>
    <p class="help">Dërgon ftesë në çdo email unik të ARBK_LIST që ende nuk ka llogari ose ftesë të dorëzuar. Dërgohen <?=200?> ftesa për çdo klik; ftesat që kanë dështuar dërgohen sërish.</p>
    <form method="post" action="<?=e(app_url('/admin/business-access/invite-all.php'))?>"><?=csrf_field()?><button class="btn--secondary">Dërgo grupin e radhës</button></form>
  </details>
  <?php if($invites!==[]):?>
  <details class="disclosure"><summary>Ftesat e fundit (<?=count($invites)?>)</summary>
    <div class="table-scroll"><table class="table">
      <thead><tr><th>Emaili</th><th>Dërgimi</th><th>Gjendja</th><th>Skadon</th></tr></thead>
      <tbody><?php foreach($invites as $invite):?>
        <tr>
          <td><span class="cell-title"><?=e($invite['email'])?></span><span class="cell-sub"><?=e(datetime_sq($invite['created_at']))?></span></td>
          <td data-label="Dërgimi"><?=badge('message',$invite['message_status']??'QUEUED')?></td>
          <td data-label="Gjendja"><?=$invite['consumed_at']?'Regjistruar':($invite['opened_at']?'Hapur':'Pa hapur')?></td>
          <td data-label="Skadon"><?=e(datetime_sq($invite['expires_at']))?></td>
        </tr>
      <?php endforeach;?></tbody>
    </table></div>
  </details>
  <?php endif;?>
</section>
<section class="card" id="lidhjet">
  <h2>Lidhjet aktive</h2>
  <details class="disclosure"><summary>+ Lidh një përdorues me biznes</summary>
    <form method="post" action="<?=e(app_url('/admin/business-access/save.php'))?>" class="form-grid"><?=csrf_field()?>
      <label class="field">Emaili i përdoruesit të portalit<input type="email" name="email" maxlength="254" required></label>
      <label class="field">NRB e biznesit<input name="registration_number" maxlength="50" required inputmode="numeric"></label>
      <div class="form-actions"><button>Lidhe</button></div>
    </form>
  </details>
  <?php if($links===[]):?><p class="empty">Nuk ka lidhje aktive.</p><?php else:?>
  <div class="table-scroll"><table class="table">
    <thead><tr><th>Përdoruesi</th><th>Biznesi</th><th>Caktuar nga</th><th><span class="visually-hidden">Veprime</span></th></tr></thead>
    <tbody><?php foreach($links as $link):?>
      <tr>
        <td><span class="cell-title"><?=e($link['full_name'])?></span><span class="cell-sub"><?=e($link['email'])?></span></td>
        <td data-label="Biznesi"><?=e($link['legal_name'])?><span class="cell-sub">NRB <?=e($link['registration_number'])?></span></td>
        <td data-label="Caktuar nga"><?=e($link['assigned_by'])?><span class="cell-sub"><?=e(datetime_sq($link['assigned_at']))?></span></td>
        <td class="actions"><details class="row-menu"><summary>Ndërpre</summary><div class="row-menu-body"><form method="post" action="<?=e(app_url('/admin/business-access/save.php'))?>" class="stack"><?=csrf_field()?><input type="hidden" name="action" value="unlink"><input type="hidden" name="link_id" value="<?=(int)$link['id']?>"><label class="field">Arsyeja<input name="reason" maxlength="300" required></label><button class="btn--small btn--danger">Ndërpre lidhjen</button></form></div></details></td>
      </tr>
    <?php endforeach;?></tbody>
  </table></div>
  <?php endif;?>
</section>
