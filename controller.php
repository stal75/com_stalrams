
<?php
//Запрет к прямому обращению 
defined('_JEXEC') or die;

class StalramsController extends JControllerLegacy
{
//Возвращение способа отображения, кешируемый или нет
  public function display($cachable = false, $urlparams = false)
  {
 
 
    $view   = $this->input->get('view', 'genpass');
    $layout = $this->input->get('layout', 'default');
 
    // Проверка формы редактирования
    
     
    // $this->setRedirect(JRoute::_('index.php?option=com_stalrams&view=genpass', false));
 
// Отображаем представление
//echo 'contr-deff';
    parent::display();
//Вернуть значение
    return $this;
  }
public function genpass($cachable = false, $urlparams = false)
  {
	$this->input->set('view', 'genpass');
	parent::display();
  }
  
public function translit($cachable = false, $urlparams = false)
  {
	$this->input->set('view', 'translit');
	parent::display();
  }
  
  public function zabbix($cachable = false, $urlparams = false)
  {
  	$this->input->set('view', 'zabbix');
  	parent::display();
  }
  
  public function sprav($cachable = false, $urlparams = false)
  {
  	$this->input->set('view', 'sprav');
  	parent::display();
  }
  
  
}

?>