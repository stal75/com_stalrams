<?php
defined('_JEXEC') or die('Restricted access');
?>
<h2>Генератор паролей</h2>
<form name=pass_form>
<input name="upper" type="checkbox">Заглавные буквы<br>
<input name="lower" type="checkbox">Маленькие буквы<br>
<input name="digit" type="checkbox">Цифры<br>
<input name="spec" type="checkbox">Спецсимволы<br>
<p>Длина пароля: <?php echo $this->lists['numbers'];?></p> 
<br><input type="button" value="Сгенерировать" onClick=generate()>
<br><br>Пароль: <input name="getback" size="30" type="text" value="">
<?php echo JHTML::_('form.token'); ?>
<input type="hidden" name="option" value="com_equipment" />
<input type="hidden" name="task" value="" />
</form>