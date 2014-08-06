 <?php 
defined('_JEXEC') or die('Restricted access');?>
 
<h2>Генератор логина и имени ПК</h2>
<form name=trans_form>
<p>Введите строку (Фамилия пробел инициалы)</p>
 <p><input name="inputrus" size="30" type="text" value=""></p>
<p><input type="button" value="Сгенерировать" onClick=generate()></p>
<table>
<tr><td>Логин: </td><td><input name="getbacklog" size="30" type="text" value=""></td></tr>
<tr><td>Имя ПК: </tb><td><input name="getbackpk" size="30" type="text" value=""></td></tr>
</table>
<?php echo JHTML::_('form.token'); ?>
<input type="hidden" name="option" value="com_equipment" />
<input type="hidden" name="task" value="" />
</form>
