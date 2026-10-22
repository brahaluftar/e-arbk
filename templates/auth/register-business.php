<section class="card login">
  <img class="login-logo" src="<?=e(app_url('/logo_prishtina.png'))?>" alt="Logoja e Komunës së Prishtinës">
  <h1>Regjistrimi i biznesit</h1>
  <?php if($invite===null):?>
    <p>Linku i regjistrimit ka skaduar ose është përdorur. Kërkoni një ftesë të re nga komuna.</p>
  <?php else:?>
    <p>Llogaria krijohet për <strong><?=e($invite['email'])?></strong>. Lidhja me biznesin aktivizohet pasi ta verifikojë zyrtari komunal.</p>
    <form method="post"><?=csrf_field()?><input type="hidden" name="token" value="<?=e($token)?>">
      <label class="field">Emri dhe mbiemri<input name="full_name" maxlength="200" required autocomplete="name"></label>
      <label class="field">Numri unik i biznesit (NRBIZ)<input name="registration_number" maxlength="50" required autocomplete="off" inputmode="numeric"></label>
      <label class="field">Fjalëkalimi<small>12–72 karaktere</small><input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
      <label class="field">Përsëriteni fjalëkalimin<input type="password" name="password_confirmation" minlength="12" maxlength="72" required autocomplete="new-password"></label>
      <button>Regjistrohu dhe dërgo kërkesën</button>
    </form>
  <?php endif;?>
</section>
