<?php

//Защита от прямого обращения к скрипту
defined('_JEXEC') or die;

$document = &JFactory::getDocument();
$baseurl = JURI::base();
//$document->addScript($baseurl . "components/com_equipment/js/genpass.js");
$document->addScript(JURI::base(true).'/components/com_stalrams/assets/js/genpass.js');

class StalramsViewGenpass extends JViewLegacy
{
//Функция которая выводит данные из модели. function getStalram() это функция модели, а тут мы извлекаем её $rows = $model->getStalram();
public $lists = array();

function display ($tpl = null)
	{
	$model = $this->getModel();//Получение модели
		
		$this->lists['numbers'] = JHTML::_('select.integerlist', 1, 20, 1, 'numbers', 'id = "appar"', $selected = null, $format = "%d");
		
		parent::display($tpl);;
	}

}

?>