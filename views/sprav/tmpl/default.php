<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
$url_img= '/components/com_sprav/img/';
?>
<table width="100%"   class="text1" height="100%" cellspacing="10" cellpadding="0">
<tr><td valign=top width=250><br>
<form action="<?php echo JRoute::_('index.php')?>" method=get>
<?php echo $this->names;?>
<?php echo JHTML::_('form.token'); ?>
	<input type="hidden" name="option" value="com_sprav"/>
	<input type="hidden" name="task" value="123"/>
</form>
<?php //<p><a href=print_spr.php?id=$id class=men3 target=print>Версия для печати</a>?>
<p align=center><table   bgcolor=White  class=tb width=70%><tr><td colspan=2 align=center><b> Коды ПЭС:</b></td></tr>
<tr><td>Тула </td><td><b>61</b></td></tr>
<tr><td>Новомосковск </td><td><b>62</b></td></tr>
<tr><td>Ефремов</td><td> <b>57</b></td></tr>
<tr><td>Суворов </td><td><b>64</b></td></tr></table>
<p><br><table bgcolor="White" class=tb border="1" cellpadding="2" cellspacing="0"><tr><td colspan="2" align="center">
  <b> Для выхода на АТС филиалов МРСК необходимо набрать:</b><br>
  <i> 58-(гудок)-(код МРСК)-внутренний номер абонента</i> (для абонентов с внутренними номерами 2ХХХ)<br>
  <i> 58-(гудок)-58-(код МРСК)-внутренний номер абонента</i> (для абонентов с внутренними номерами 7ХХХ)<br>
  <b> Коды МРСК: </b></tr></td>
  <tr><td>ИА МРСК</td><td>641</td>
  <tr><td>Владимир</td><td>800</td></tr>
  <tr><td>Тула</td><td>802</td></tr>
  <tr><td>Н.Новгород</td><td>803</td></tr>
  <tr><td>Иваново</td><td>804</td></tr>
  <tr><td>Рязань</td><td>818</td></tr>
  <tr><td>Калуга</td><td>821</td></tr>
  <tr><td>Йошкар-Ола</td><td>860</td></tr>
  <tr><td>Ижевск</td><td>888</td></tr>
  <tr><td>Киров</td><td>889</td></tr></table>
  <p><br><table bgcolor="White" class=tb border="1" cellpadding="2" cellspacing="0"><tr><td colspan="3" align="center">
  <b> Для изменения данных обращайтесь:</b></td></tr>
   <tr><td>Тулэнерго</td> <td>Ситников Сергей Михайлович</td><td  width="60">(77-61)</td></tr>
   <tr><td>ТЭС</td><td>Шарапов Евгений Александрович</td> <td>(9-45)</td></tr>
   <tr> <td>НЭС</td> <td>Сапожников Михаил Николаевич</td><td>(4-69)</td></tr>
   <tr><td>ЕЭС</td><td>Боев Евгений Сергеевич</td><td>57-4-18</td></tr>
   <tr> <td>СЭС</td> <td>Сериков Алексей Сергеевич</td><td>(2-27)</td></tr> </table>
   </td><td valign=top>
    <h3 align=center>СПРАВОЧНИК ТЕЛЕФОНОВ И ЭЛЕКТРОННЫХ АДРЕСОВ</h3>
    <h3 align=center><?php echo $this->name?></h3>
   	<p><br><img src="<?php echo $url_img.$this->img;?>" border="0" align="right" alt="<?php echo $this->name?>">
	<form  action="<?php echo JRoute::_('index.php')?>" method=get id="form-login">
	<b>Введите фамилию для поиска:</b> <br><input name=person type=Text > 
	<input type="submit" font-size:16px value="Поиск>>" >
	<?php //<a class="a_demo_four" href="#" onclick="document.getElementById('form-login').submit();">Поиск</a>*/?>
	<font class=text3> - Если ничего не введено будут выведены все записи</font>	          
	<?php echo JHTML::_('form.token'); ?>
	<input type="hidden" name="option" value="com_sprav">
	<input type="hidden" name="task" value="123">
	<input type="hidden" name="id" value= "<?php echo JRequest::getVar('id')?>">
	</form>
	<form action="<?php echo JRoute::_('index.php')?>"  method=get>
	<b>Выберите службу/управление:</b><br>
	<?php echo $this->lists['Dep'];?>
	<input type="submit" value="Поиск>>" >
	<?php echo JHTML::_('form.token'); ?>
	<input type="hidden" name="option" value="com_sprav">
	<input type="hidden" name="task" value="">
	<input type="hidden" name="id" value="<?php echo JRequest::getVar('id');?>">
    </form>
    <?php if  ($this->metka<>0): ?>
    	<form action="<?php echo JRoute::_('index.php')?>"  method=get>
   		<b>Выберите отдел:</b><br>
    	<?php echo $this->lists['Divis'];?>
		<?php echo JHTML::_('form.token'); ?>   
		<input type="submit" value="Поиск>>" >
   		<input type=Hidden name=depart value="<?php echo JRequest::getVar('depart')?>">
   		<input type="hidden" name="option" value="com_sprav" />
		<input type="hidden" name="task" value="">
		<input type="hidden" name="id" value="<?php echo JRequest::getVar('id');?>">
   		</form>
    <?php endif;?>
	<?php echo $this->rezSearch;?>
	
	<br><br></td></tr></table></body>