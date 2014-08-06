<?php 
/*Это точка входа в сам компонент */

defined('_JEXEC') or die;

//Load styles and javascripts
//$document->addScript(JURI::base().'components/com_stalrams/assets/js/genpass.js');

$controller = JControllerLegacy::getInstance('Stalrams');
//Выполнить задачу запроса
$controller->execute(JRequest::getCmd('task'));
//Переадресация
$controller->redirect();