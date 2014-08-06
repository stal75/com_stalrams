<?php // no direct access

defined( '_JEXEC' ) or die( 'Restricted access' ); 
 
class StalramsControllersGenpass extends LendrControllersDefault
{
  function execute()
  {
    $app = JFactory::getApplication();
    $viewName = $app->input->get('task');
    //$app->input->set('layout','edit');
    $app->input->set('view', $viewName);
     echo 'contr-pass';
    //display view
    return parent::execute();
  }
}