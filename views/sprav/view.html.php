<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
 
// import Joomla view library
jimport('joomla.application.component.view');
 
/**
 * HTML View class for the HelloWorld Component
 */
class StalramsViewSprav extends JViewLegacy
{
	function Clear_array_empty($array)
	{
		$ret_arr = array();
		foreach($array as $val)
		{
			if (!empty($val))
			{
				$ret_arr[] = trim($val);
			}
		}
		return $ret_arr;
	}
	
	// Overwriting JView display method
	function display($tpl = null) 
	{
		
		$model = $this->getModel();
		$document =& JFactory::getDocument();
		$document->addStyleSheet('components/com_stalrams/assets/css/sprav.css');
		
		$Uv = 'СѓРІ';
		
		$id= JRequest::getVar('id');
		if ($id == '') $id=20;

		$names=$this->get('LDAPs');
		
		foreach($names as $row) {
		    $this->names .= "<p><a href=".JRoute::_('index.php')."?option=com_sprav&id=$row->id class=men3>".$row->name.'</a>';
		}
		echo 'sdfsdfsf';
		$result=$model->getLDAP($id); //РџРѕР»СѓС‡Р°РµРј С‚РµРєСѓС‰РёР№ LDAP
 		
		if (count($result) > 0) {
		 	$this->id=$result[0]->id;
		 	$this->name=$result[0]->name;
		 	$this->domen=$result[0]->domen;
		 	$this->ldap=$result[0]->ldap;
		 	$this->searh=$result[0]->searh;
		 	$this->userPName=$result[0]->login;
		 	$this->passw=$result[0]->passw;
		 	$this->img=$result[0]->img;
		}
		
		$ad=ldap_connect($this->ldap);  // РѕР±СЏР·Р°РЅ Р±С‹С‚СЊ РїСЂР°РІРёР»СЊРЅС‹Р№ LDAP-СЃРµСЂРІРµСЂ!
		if ($ad){
			ldap_set_option ($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option ($ad, LDAP_OPT_REFERRALS, 0);
			if ($r = @ldap_bind ($ad, $this->userPName, $this->passw)){
			   $justthese = array("department");
               $sr=@ldap_search($ad, $this->searh, "(|(sn=*))", $justthese);
               $zap=@ldap_get_entries($ad, $sr);
  			   for ($i=0; $i<$zap["count"]; $i++){
		   		  $arrdep[$i]=$zap[$i]['department'][0];
			   }
		$arrdep=array_unique($arrdep);
		sort($arrdep);

		$arrdep = $this->Clear_array_empty($arrdep);//Р§РёСЃС‚РёРј РјР°СЃСЃРёРІ РѕС‚ РїСѓСЃС‚С‹С… РґРµРїР°СЂС‚Р°РјРµРЅС‚РѕРІ
		$this->lists['Dep'] = JHTML::_('select.genericlist', //Р¤РѕСЂРјРёСЂСѓРµРј СЃРїРёСЃРѕРє РґРµРїР°СЂС‚Р°РјРµРЅС‚РѕРІ
				$arrdep,
				'depart',
				'id = "depart" width=100 size="1"',
				'text',
				'text',
				JRequest::getVar('depart'),
				'reg',
				true);

		unset($zap);
    	@ldap_free_result($sr);
    	$this->rezSearch = '';
  		if (JRequest::getVar('depart')!=''){
  			$depart=$arrdep[JRequest::getVar('depart')];
  			$this->rezSearch .= "Р�СЃРєР°Р»Рё: <b>".$depart."</b>";
  			$depart=str_replace('\"', '"',  $depart);
        	$filter="(&(department=$depart)(!(telephoneNumber=$Uv*)))";
			$justthese = array( "ou", "sn", "cn", "company", "department", "division","telephoneNumber", "pager", "mobile", "mail", "title" );
			$sr=@ldap_search($ad,  $this->searh, $filter, $justthese);
			$q=@ldap_count_entries($ad, $sr);
		
			////////////////////////////////////////////// РїРѕРґРѕС‚РґРµР»С‹
			$zapd=@ldap_get_entries($ad, $sr);
			$this->metka=0;
			for ($i=0; $i<$zapd["count"]; $i++){
				$arrdepd[$i]=$zapd[$i]['division'][0];
				if ($arrdepd[$i]<>'') $this->metka=1;
			}
		
			if  ($this->metka<>0){
				$arrdepd=array_unique($arrdepd);
				sort($arrdepd);
		
				$this->lists['Divis'] = JHTML::_('select.genericlist',
					$arrdepd,
					'divis',
					'name = "divis" id = "divis" width=100 size="1"',
					'value',
					'text',
					JRequest::getVar('divis'),
					'reg');
			
				unset($zapd);
      		} else 
				if (JRequest::getVar('divis')=='') {
					$this->rezSearch .="<br>РќР°Р№РґРµРЅРѕ ".$q." Р·Р°РїРёСЃРё";
				}
		}
		
		
		if (JRequest::getVar('divis')!=''){
			$divis=$arrdepd[JRequest::getVar('divis')];
			$divis=str_replace('\"', '"',  $divis);
			$this->rezSearch .="/<b>".$divis."</b>";
			$filter="(&(division=$divis)(!(telephoneNumber=$Uv*)))";
			$justthese = array( "ou", "sn", "cn", "company", "department", "division","telephoneNumber", "pager", "mobile", "mail", "title" );
			$sr=@ldap_search($ad,  $this->searh, $filter, $justthese);
			$q=@ldap_count_entries($ad, $sr);
 	    	$this->rezSearch .= "<br>РќР°Р№РґРµРЅРѕ ".$q." Р·Р°РїРёСЃРё";
		}
		
		if (JRequest::getVar('person')!=''){////////////////////////// РІРІРµРґРµРЅРѕ С„РёРѕ
			$person=trim(JRequest::getVar('person'));
			$this->rezSearch .="Р�СЃРєР°Р»Рё: ".$person;
			$filter="(&(sn=$person*)(!(telephoneNumber=$Uv*)))";
			$justthese = array("cn", "company", "department", "division","telephoneNumber", "pager", "mobile", "mail", "title" );
			$sr=@ldap_search($ad,  $this->searh, $filter, $justthese);
			$q=@ldap_count_entries($ad, $sr);
			$this->rezSearch .="<br>РќР°Р№РґРµРЅРѕ ".$q." Р·Р°РїРёСЃРё";
		}
    	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		
		if ($q<>0){
			$entry = ldap_first_entry($ad, $sr);
			//   РІС‹РІРѕРґ РІСЃРµС… РґР°РЅРЅС‹С… РёР· Р±Р°Р·С‹
			Do {
				$ar = ldap_get_attributes ($ad,  $entry);
		    	$this->rezSearch .= "<hr>";
			    $this->rezSearch .= "<br> <b>". $ar['cn'][0]."</b>";
			    $this->rezSearch .= "<br> ". $ar['company'][0];
			    if (isset($ar['department'][0])) $this->rezSearch .= "<br> ".$ar['department'][0];
		    	if (isset($ar['division'][0]))  $this->rezSearch .= "<br>". $ar['division'][0];
			    $this->rezSearch .= "<br> ". $ar['title'][0];
			    $this->rezSearch .= "<table>";
			    if ($ar['telephoneNumber'][0]!='')$this->rezSearch .= "<tr><td align=center width='10'><img src='/components/com_sprav/assets/images/tel-rab.png'></td><td>  РўРµР»РµС„РѕРЅ РІРЅСѓС‚СЂРµРЅРЅРёР№: </td><td><b>".$ar['telephoneNumber'][0]."</b></td></tr>";
			    if ($ar['pager'][0]!='')$this->rezSearch .= "<tr><td align=center width='10'><img src='/components/com_sprav/assets/images/tel-rab.png'></td><td>   РўРµР»РµС„РѕРЅ РіРѕСЂРѕРґСЃРєРѕР№: </td><td><b>".$ar['pager'][0]."<b></td></tr>";
			    if ($ar['mobile'][0]!='') $this->rezSearch .= "<tr><td align=center width='10'><img src='/components/com_sprav/assets/images/tel-sot.png'></td><td>  РўРµР»РµС„РѕРЅ РјРѕР±РёР»СЊРЅС‹Р№: </td><td><b>".$ar['mobile'][0]."<b></td></tr>";
			    
			    if ($ar['mail'][0]!='') $this->rezSearch .= "<tr><td align=center width='10'><img src='/components/com_sprav/assets/images/icon-mail2.png'></td><td colspan='2'>  E-mail: <a href=mailto:". $ar['mail'][0]." >". $ar['mail'][0]."</a></td></tr>";
			    //print_r($ar);
			    $this->rezSearch .= "</table>";
    	        for ($j=1; $j<$ar['mail']["count"]; $j++){
			        if (isset($ar['mail'][$j])) {
		    	        $this->rezSearch .= ",  <a href=mailto:". $ar['mail'][$j]." class=men4u>".$ar['mail'][$j].'</a>';
		    		}
		    	}//// for j
				$entry=ldap_next_entry($ad, $entry);
			} while ($entry);
		}/////// if ($q<>0){
		ldap_unbind ($ad);
		}///////if ($r
		
		}///////if ($ad){
		parent::display($tpl);
	}
}