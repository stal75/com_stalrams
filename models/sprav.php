<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

class StalramsModelSprav extends JModelLegacy
{
	function getLDAPs(){
		$db = JFactory::getDBO();// Подключаемся к базе.
		$query = "SELECT id, name FROM #__sprav_org";//Определяем запрос
		$db->setQuery($query);//Выполняем запрос
		
		if ($rList = $db->loadObjectList()) {
			return $rList;
		}
		else {
			return $db->stderr();
		}
	}
	function getLDAP($id){
		$db = JFactory::getDBO();// Подключаемся к базе.
		$query = "SELECT * FROM #__sprav_org WHERE id =$id";//Определяем запрос
		$db->setQuery($query);//Выполняем запрос
		if ($rList = $db->loadObjectList()) {
			return $rList;
		}
		else {
			return $db->stderr();
		}
	}
}