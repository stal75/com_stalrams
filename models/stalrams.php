<?php

//Защита от прямого обращения к скрипту
defined('_JEXEC') or die;


//Название класса модели и название файла
class StalramsModelStalrams extends JModelLegacy
{
//Функция её мы будем выводит в виде.
function getStalram()
	{
	//Подключение к бд joomla
	$db = $this->getDbo();
	
	//Выбираем из какой таблицы будем вытаскивать данные ORDER BY ordering это порядок отображения данных этим займёмся в админ панеле.
	$query = 'SELECT * FROM #__stalrams ORDER BY ordering';
	$db->setQuery($query);
	$row = $db->loadObjectlist();
//вернуть row	
return $row;	
	
	}
	
}
?>