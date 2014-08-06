<?php

//Защита от прямого обращения к скрипту
defined('_JEXEC') or die;

class StalramsViewStalrams extends JViewLegacy
{
//Функция которая выводит данные из модели. function getStalram() это функция модели, а тут мы извлекаем её $rows = $model->getStalram();
function display ($tpl = null)
	{
	$model = $this->getModel();
	$rows = $model->getStalram();
	$this->assignRef('rows',$rows);
	
parent::display($tp1);
	}


}

?>