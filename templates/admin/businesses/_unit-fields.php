<?php /** @var array<string,mixed> $values */ $v=static fn(string $key): string => e((string)($values[$key]??''));?>
<label class="field">Emri i njësisë<input name="unit_name" maxlength="255" required value="<?=$v('unit_name')?>"></label>
<label class="field">Adresa<input name="address" maxlength="500" required value="<?=$v('address')?>"></label>
<label class="field">Telefoni<input name="phone_number" maxlength="50" value="<?=$v('phone_number')?>" inputmode="tel"></label>
<label class="field">Emaili<input type="email" name="email" maxlength="254" value="<?=$v('email')?>"></label>
<label class="field">Menaxheri<input name="manager_name" maxlength="255" value="<?=$v('manager_name')?>"></label>
<label class="field">Kodi NACE<small>4 karaktere, p.sh. 4711</small><input name="nace_code" minlength="4" maxlength="4" pattern="[0-9A-Za-z]{4}" required value="<?=$v('nace_code')?>"></label>
<label class="field">Veprimtaria NACE<input name="nace_description" maxlength="510" value="<?=$v('nace_description')?>"></label>
<label class="field">Tarifa vjetore (€)<input type="number" name="annual_tariff" min="0.01" step="0.01" value="<?=$v('annual_tariff')?>"></label>
<label class="check"><input type="checkbox" name="auto_invoice" value="1" <?=!empty($values['auto_invoice'])?'checked':''?>> Faturim automatik në gjenerimin vjetor</label>
