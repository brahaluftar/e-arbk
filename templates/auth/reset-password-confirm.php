<section class="panel login">
    <h1>Vendos fjalëkalim të ri</h1>
    <?php if ($validToken): ?>
        <form method="post" action="<?=e(app_url('/auth/reset-password-confirm.php'))?>">
            <input type="hidden" name="_csrf" value="<?=e($csrf->token())?>">
            <input type="hidden" name="token" value="<?=e($token)?>">
            <label class="field">Fjalëkalimi i ri<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
            <label class="field">Përsëriteni fjalëkalimin<input type="password" name="password_confirmation" minlength="12" maxlength="72" required autocomplete="new-password"></label>
            <button type="submit">Ndrysho fjalëkalimin</button>
        </form>
    <?php else: ?>
        <p>Lidhja është e pavlefshme ose ka skaduar. Kërkoni një lidhje të re.</p>
        <p><a href="<?=e(app_url('/auth/reset-password.php'))?>">Kërko lidhje të re</a></p>
    <?php endif; ?>
</section>