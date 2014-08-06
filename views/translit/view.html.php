<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
 
// import Joomla view library
jimport('joomla.application.component.view');

$document = &JFactory::getDocument();
$baseurl = JURI::base();
$document->addScript(JURI::base(true).'/components/com_stalrams/assets/js/translit.js');


class StalramsViewTranslit extends JViewLegacy
{
	public $lists = array();
	
	// Overwriting JView display method
	function display($tpl = null) 
	{
		
		$model = $this->getModel();//Получение модели
		
		$this->lists['numbers'] = JHTML::_('select.integerlist', 1, 20, 1, 'numbers', 'id = "appar"', $selected = null, $format = "%d");
		

		parent::display($tpl);
	}
}