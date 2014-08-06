<?php
//защита от прямого доступа
defined('_JEXEC') or die('Restricted access');
?>
<link rel="stylesheet" type="text/css" href="components/com_stalrams/css/stalram.css">
<div id="v1"> 
<div class="b1"><b></b></div><div class="b2"><b><i><q></q></i></b></div> 
<div class="b3"><b><i></i></b></div><div class="b4"><b></b></div><div class="b5"><b></b></div> 
<div class="text"> 
<!--Это таблица где выводятся данные из базы-->
<table class="tablica">

<!--Первый цикл который выведет нам данные-->
<?php foreach ($this->rows as $row ) { ?>
<!--Проверка опубликован раздел или нет-->
<?php 
if($row->state==1)
{
echo '<tr class="cvet-razdel">
<td class="cvet-razdela">'.$row->name.'</td>
<td class="cvet-razdela">'.$row->opisanie.'</td>
<td class="cvet-razdela">'.$row->adres.'</td>

</tr>';
}}
?> 

</table>
</div> 
<div class="b5"><b></b></div><div class="b4"><b></b></div><div class="b3"><b><i></i></b></div> 
<div class="b2"><b><i><q></q></i></b></div><div class="b1"><b></b></div> 
</div>