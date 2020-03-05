<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
 * Developped by Lolokai Conseil (https://www.lolokaiconseil.com)
 *
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0  
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Glpi91Provider extends AbstractProvider {
    protected $_glpi_connected = 0;
    protected $_glpi_session = null;
    
    const GPLI_ENTITIES_TYPE = 10;
    const GPLI_GROUPS_TYPE = 11;
    const GLPI_ITIL_CATEGORIES_TYPE = 12;
    
    const ARG_CONTENT = 1;
    const ARG_ENTITY = 2;
    const ARG_URGENCY = 3;
    const ARG_IMPACT = 4;
    const ARG_CATEGORY = 5;
    const ARG_USER = 6;
    const ARG_USER_EMAIL = 7;
    const ARG_GROUP = 8;
    const ARG_GROUP_ASSIGN = 9;
    const ARG_TITLE = 10;
    
    protected $_internal_arg_name = array(
        self::ARG_CONTENT => 'content',
        self::ARG_ENTITY => 'entity',
        self::ARG_URGENCY => 'urgency',
        self::ARG_IMPACT => 'impact',
        self::ARG_CATEGORY => 'category',
        self::ARG_USER => 'user',
        self::ARG_USER_EMAIL => 'user_email',
        self::ARG_GROUP => 'group',
        self::ARG_GROUP_ASSIGN => 'groupassign',
        self::ARG_TITLE => 'title',
    );

    function __destruct() {
        
    }
    
    /**
     * Set default extra value 
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['path'] = '/glpi/apirest.php/';
        $this->default_data['https'] = 0;
	$this->default_data['ignorecertificate'] = 0;
        $this->default_data['timeout'] = 60;
		$this->default_data['range'] = 100;
        
        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_TITLE, 'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_title.ihtml"}'),
            array('Arg' => self::ARG_CONTENT, 'Value' => '{$body}'),
            array('Arg' => self::ARG_ENTITY, 'Value' => '{$select.glpi_entity.id}'),
            array('Arg' => self::ARG_CATEGORY, 'Value' => '{$select.glpi_itil_category.id}'),
            array('Arg' => self::ARG_GROUP_ASSIGN, 'Value' => '{$select.glpi_group.id}'),
            array('Arg' => self::ARG_USER_EMAIL, 'Value' => '{$user.email}'),
            array('Arg' => self::ARG_URGENCY, 'Value' => '{$select.urgency.value}'),
            array('Arg' => self::ARG_IMPACT, 'Value' => '{$select.impact.value}'),
        );
    }
    
    protected function _setDefaultValueMain($body_html = 0) {
        parent::_setDefaultValueMain($body_html); 
        $this->default_data['url'] = 'http://{$address}/glpi/front/ticket.form.php?id={$ticket_id}';
        
        $this->default_data['clones']['groupList'] = array(
            array('Id' => 'glpi_entity', 'Label' => _('Entity'), 'Type' => self::GPLI_ENTITIES_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'glpi_group', 'Label' => _('Glpi group'), 'Type' => self::GPLI_GROUPS_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'glpi_itil_category', 'Label' => _('Itil category'), 'Type' => self::GLPI_ITIL_CATEGORIES_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'urgency', 'Label' => _('Urgency'), 'Type' => self::CUSTOM_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'impact', 'Label' => _('Impact'), 'Type' => self::CUSTOM_TYPE, 'Filter' => '', 'Mandatory' => ''),
        );
        $this->default_data['clones']['customList'] = array(
            array('Id' => 'urgency', 'Value' => '1', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '2', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '3', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '4', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '5', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '1', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '2', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '3', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '4', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '5', 'Default' => ''),
        );
    }
    
    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';
        
        $this->_checkFormValue('address', "Please set 'Address' value");
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
		$this->_checkFormValue('range', "Please set 'Range' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormInteger('timeout', "'Timeout' must be a number");
		$this->_checkFormInteger('range', "'Range' must be a number");
        $this->_checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");
        
        $this->_checkLists();
        
        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }
    
    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Glpi91/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("glpi" => _("Glpi")));
        
		/*curl_setopt($ch, CURLOPT_PROXYPORT, $this->rule_data['proxy_port']);
		curl_setopt($ch, CURLOPT_PROXYTYPE, $this->rule_data['proxy_type']);
		curl_setopt($ch, CURLOPT_PROXY, $this->rule_data['proxy_address']);
		curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->rule_data['proxy_loginpassword']);*/
		
		if (isset($this->rule_data['ignorecertificate']) && $this->rule_data['ignorecertificate'] == 'yes') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    		}

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' . $this->_getFormValue('address') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' . $this->_getFormValue('path') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' . $this->_getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' . $this->_getFormValue('password') . '" autocomplete="off" />';
		$usertoken_html = '<input size="50" name="user_token" type="text" value="' . $this->_getFormValue('user_token') . '" autocomplete="off" />';
		$apptoken_html = '<input size="50" name="app_token" type="text" value="' . $this->_getFormValue('app_token') . '" autocomplete="off" />';
        $https_html = '<input type="checkbox" name="https" value="yes" ' . ($this->_getFormValue('https') == 'yes' ? 'checked' : '') . '/>';
        $ignorecertificate_html = '<input type="checkbox" name="ignorecertificate" value="yes" ' . ($this->_getFormValue('ignorecertificate') == 'yes' ? 'checked' : '') . '/>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' . $this->_getFormValue('timeout') . '" />';
		$range_html = '<input size="2" name="range" type="text" value="' . $this->_getFormValue('range') . '" />';
		$proxyport_html = '<input size="50" name="proxy_port" type="text" value="' . $this->_getFormValue('proxy_port') . '" autocomplete="off" />';
		$proxytype_html = '<input size="50" name="proxy_type" type="text" value="' . $this->_getFormValue('proxy_type') . '" autocomplete="off" />';
		$proxyaddress_html = '<input size="50" name="proxy_address" type="text" value="' . $this->_getFormValue('proxy_address') . '" autocomplete="off" />';
		$proxyloginpassword_html = '<input size="50" name="proxy_loginpassword" type="text" value="' . $this->_getFormValue('proxy_loginpassword') . '" autocomplete="off" />';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->_required_field, 'html' => $address_html),
            'path' => array('label' => _("Path"), 'html' => $path_html),
            'username' => array('label' => _("Username"), 'html' => $username_html),
            'password' => array('label' => _("Password"), 'html' => $password_html),
			'user_token' => array('label' => _("User_Token"), 'html' => $usertoken_html),
			'app_token' => array('label' => _("App_Token"), 'html' => $apptoken_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
	    'ignorecertificate' => array('label' => _("Ignore SSL Certificate Error"), 'html' => $ignorecertificate_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
			'range' => array('label' => _("Maximum result"), 'html' => $range_html),
			'proxy_port' => array('label' => _("Proxy Port"), 'html' => $proxyport_html),
			'proxy_type' => array('label' => _("Proxy type (HTTP or HTTPS)"), 'html' => $proxytype_html),
			'proxy_address' => array('label' => _("Proxy Address"), 'html' => $proxyaddress_html),
			'proxy_loginpassword' => array('label' => _("Proxy Login/Password (login:password)"), 'html' => $proxyloginpassword_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );
        
        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" type="select-one">' .
        '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</options>' .
        '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</options>' .
        '<option value="' . self::ARG_ENTITY . '">' . _('Entity') . '</options>' .
        '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</options>' .
        '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</options>' .
        '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</options>' .
        '<option value="' . self::ARG_USER . '">' . _('User') . '</options>' .
        '<option value="' . self::ARG_USER_EMAIL . '">' . _('User email') . '</options>' .
        '<option value="' . self::ARG_GROUP . '">' . _('Group') . '</options>' .
        '<option value="' . self::ARG_GROUP_ASSIGN . '">' . _('Group assign') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );
        
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        
        $this->_config['clones']['mappingTicket'] = $this->_getCloneValue('mappingTicket');
    }
    
    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {
        
    }
    
    protected function saveConfigExtra() {
        $this->_save_config['simple']['address'] = $this->_submitted_config['address'];
        $this->_save_config['simple']['path'] = $this->_submitted_config['path'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
		$this->_save_config['simple']['user_token'] = $this->_submitted_config['user_token'];
		$this->_save_config['simple']['app_token'] = $this->_submitted_config['app_token'];
        $this->_save_config['simple']['https'] = (isset($this->_submitted_config['https']) && $this->_submitted_config['https'] == 'yes') ? 
            $this->_submitted_config['https'] : '';
	$this->_save_config['simple']['ignorecertificate'] = (isset($this->_submitted_config['ignorecertificate']) && $this->_submitted_config['ignorecertificate'] == 'yes') ?
            $this->_submitted_config['ignorecertificate'] : '';
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];
		$this->_save_config['simple']['range'] = $this->_submitted_config['range'];
		$this->_save_config['simple']['proxy_port'] = $this->_submitted_config['proxy_port'];
		$this->_save_config['simple']['proxy_type'] = $this->_submitted_config['proxy_type'];
		$this->_save_config['simple']['proxy_address'] = $this->_submitted_config['proxy_address'];
		$this->_save_config['simple']['proxy_loginpassword'] = $this->_submitted_config['proxy_loginpassword'];
        
        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
    }
    
    protected function getGroupListOptions() {        
        $str = '<option value="' . self::GPLI_ENTITIES_TYPE . '">Glpi entities</options>' .
        '<option value="' . self::GPLI_GROUPS_TYPE . '">Glpi groups</options>' .
        '<option value="' . self::GLPI_ITIL_CATEGORIES_TYPE . '">Glpi itil categories</options>';
        return $str;
    }
    
    protected function assignGlpiEntities($entry, &$groups_order, &$groups) {
		$filter = null;
        if (isset($entry['Filter']) && !is_null($entry['Filter']) && $entry['Filter'] != '') {
            $filter = $entry['Filter'];
        }
		$code = $this->callGLPI('listEntitiesGlpi');
		
		$groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
		
		$result = array();
		foreach ($code as $row) {
			$entityname = $this->callGLPI('getentityname',$row);
			if ($filter != null) {
				if (preg_match($filter,$entityname)) {
					$result[$row]=$entityname;
				}
			}
			else
			{
				$result[$row]=$entityname;
			}
        }
        asort($result);
        $this->saveSession('glpi_entities', $code);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignGlpiGroups($entry, &$groups_order, &$groups) {
		$filter = null;
        if (isset($entry['Filter']) && !is_null($entry['Filter']) && $entry['Filter'] != '') {
            $filter = $entry['Filter'];
        }
		$code = $this->callGLPI('listGroupsGlpi',$filter);
		
		$groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
		
		$result = array();
		foreach ($code as $row) {
			$result[$row['id']]=$row['completename'];
        }
        asort($result);
        $this->saveSession('glpi_groups', $code);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignItilCategories($entry, &$groups_order, &$groups) {
		//listItilCategoriesGlpi
		$filter = null;
        if (isset($entry['Filter']) && !is_null($entry['Filter']) && $entry['Filter'] != '') {
            $filter = $entry['Filter'];
        }
		$code = $this->callGLPI('listItilCategoriesGlpi',$filter);
		
		$groups[$entry['Id']] = array('label' => _($entry['Label']) . 
                                                        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
		
		$result = array();
		foreach ($code as $row) {
			$result[$row['id']]=$row['name'];
        }
        asort($result);
        $this->saveSession('glpi_itil_categories', $code);
        $groups[$entry['Id']]['values'] = $result;
		
    }
        
    protected function assignOthers($entry, &$groups_order, &$groups) {
        if ($entry['Type'] == self::GPLI_ENTITIES_TYPE) {
            $this->assignGlpiEntities($entry, $groups_order, $groups);
        } else if ($entry['Type'] == self::GPLI_GROUPS_TYPE) {
            $this->assignGlpiGroups($entry, $groups_order, $groups);
        } else if ($entry['Type'] == self::GLPI_ITIL_CATEGORIES_TYPE) {
            $this->assignItilCategories($entry, $groups_order, $groups);
        }
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        
        return $result;
    }
    
    protected function assignSubmittedValuesSelectMore($select_input_id, $selected_id) {
        $session_name = null;
        foreach ($this->rule_data['clones']['groupList'] as $value) {
            if ($value['Id'] == $select_input_id) {                    
                if ($value['Type'] == self::GPLI_ENTITIES_TYPE) {
                    $session_name = 'glpi_entities';
                } else if ($value['Type'] == self::GPLI_GROUPS_TYPE) {
                    $session_name = 'glpi_groups';
                } else if ($value['Type'] == self::GLPI_ITIL_CATEGORIES_TYPE) {
                    $session_name = 'glpi_itil_categories';
                }
            }
        }
        
        if (is_null($session_name) && $selected_id == -1) {
            return array();
        }
        if ($selected_id == -1) {
            return array('id' => null, 'value' => null);
        }
        
        $result = $this->getSession($session_name);
        
        if (is_null($result)) {
            return array();
        }

        foreach ($result as $value)  {
            if ($value['id'] == $selected_id) {                
                return $value;
            }
        }
        
        return array();
    }
    
	/**
	* Ticket creation
	* @return array result
	*/
    protected function doSubmit($dbStorage, $contact, $hostProblems, $serviceProblems, $extra_ticket_arguments=array()) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());
        
		/* Build the short description */
		$title = '';
		for ($i = 0; $i < count($hostProblems); $i++) {
		  if ($title !== '') {
			$title .= ' | ';
		  }
		  $title .= $hostProblems[$i]['name'];
		}
		for ($i = 0; $i < count($serviceProblems); $i++) {
		  if ($title !== '') {
			$title .= ' | ';
		  }
		  $title .= $serviceProblems[$i]['host_name'] . ' - ' . $serviceProblems[$i]['description'];
		}
		
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $hostProblems);
        $tpl->assign('service_selected', $serviceProblems);
        $this->assignSubmittedValues($tpl);
		$tpl->assign('string', '{$body}');
		$body = $tpl->fetch('eval.ihtml');
        
        /* Create ticket */
		try {
		  $data = $this->_submitted_config;
		  $data['title'] = 'Incident on ' . $title;
		  $data['body'] = $body;
		  $resultInfo = $this->callGLPI('createticketGLPI', $data);
		  $result['ticket_id'] = $resultInfo['id'];
		} catch (\Exception $e) {
		  $result['ticket_error_message'] = 'Error during create GLPI ticket';
		}
		$this->saveHistory(
		  $dbStorage,
		  $result,
		  array(
			'contact' => $contact,
			'host_problems' => $hostProblems,
			'service_problems' => $serviceProblems,
			'ticket_value' => $resultInfo['id'],
			'subject' => $title,
			'data_type' => self::DATA_TYPE_JSON,
			'data' => json_encode($data)
		  )
		);
		return $result;
    }
    
	/**
	* Get every EntityName
	* @param string $sessiontoken The token session
	* @param string $id Entity to search
	* @return string $entity Entity Name
	*/
	protected function getentityname($sessiontoken,$id = "") {
		$uri = 'Entity/'.$id;
		$result = $this->runGETHttpRequest($uri, $sessiontoken);
		if ($id == "") {
			$entity = "Root Entity";
		}
		else
		{
			$entity = $result['completename'];
		}
		
		return $entity;
    }
	
	/**
	* Get every Entities
	* @param string $sessiontoken The token session
	* @param string $filter Filter to categories
	* @return array All ID entities
	*/
    protected function listEntitiesGlpi($sessiontoken,$filter=null) {
		$uri = 'getActiveEntities';
		$result = $this->runGETHttpRequest($uri, $sessiontoken, "&sort=2");
		
		$entities = array();
		for ($i = 0; $i < count($result['active_entity']['active_entities']); $i++) {
			$entities[$i] = $result['active_entity']['active_entities'][$i]['id'];
		}

		return $entities;
    }
	
	/**
	* Get id of user-token
	* @param string $sessiontoken The token session
	* @return ID of user-token
	*/
	protected function getCurrentID($sessiontoken) {
		$uri = 'getFullSession';
		$result = $this->runGETHttpRequest($uri, $sessiontoken);
		
		$currentid = $result['session']['glpiID'];

		return $currentid;
    }
    
	/**
	* Get every GLPI groups
	* @param string $sessiontoken The token session
	* @param string $filter Filter to categories
	* @return array All groups
	*/
    protected function listGroupsGlpi($sessiontoken,$filter=null) {
        $uri = 'Group';
		$result = $this->runGETHttpRequest($uri, $sessiontoken);
		
		$groups = array();
		for ($i = 0; $i < count($result); $i++) {
			if ($filter != null) {
				if (preg_match($filter,$result[$i]['completename'])) {
					$groups[$i]['id'] = $result[$i]['id'];
					$groups[$i]['completename'] = $result[$i]['completename'];
				}
			}
			else
			{
				$groups[$i]['id'] = $result[$i]['id'];
				$groups[$i]['completename'] = $result[$i]['completename'];
			}
		}
		sort($groups);
		return $groups;
    }

	/**
	* Get every ITIL Categories
	* @param string $sessiontoken The token session
	* @param string $filter Filter to categories
	* @return array All Categories
	*/
    protected function listItilCategoriesGlpi($sessiontoken,$filter=null) {
        $uri = 'ITILCategory';
		$result = $this->runGETHttpRequest($uri, $sessiontoken);
		
		$itilcategory = array();
		for ($i = 0; $i < count($result); $i++) {
			$entityname = $this->callGLPI('getentityname',$result[$i]['entities_id']);
			if ($filter != null) {
				if (preg_match($filter,$result[$i]['name'])) {
					$itilcategory[$i]['id'] = $result[$i]['id'];
					$itilcategory[$i]['name'] = $entityname." - ".$result[$i]['completename'];
				}
			}
			else
			{
				$itilcategory[$i]['id'] = $result[$i]['id'];
				$itilcategory[$i]['name'] = $entityname." - ".$result[$i]['completename'];
			}
		}
		sort($itilcategory);
		return $itilcategory;
    }
	
	/**
	* Recover full URL
	* @return the full URL
	*/
	protected function getCompleteURL() {
		$proto = 'http';
		if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
			$proto = 'https';
		}
		$host = $this->rule_data['address'];
		
		$url = '/';
		if (!is_null($this->rule_data['path']) || $this->rule_data['path'] != '') {
			$url = $this->rule_data['path'];
		}
		if ($this->_glpi_connected == 1) {
			$url .= '?session=' . $this->_glpi_session;
		}
		return "$proto://$host/$url";
	}
	
	/**
	* Get a a access token
	*
	* @return array The token
	*/
	protected function getAccessToken() {
    $array_result = array('code' => -1);
	
	$completeurl = $this->getCompleteURL();
	
	if (!(isset($this->rule_data['user_token'])))
	{
		$loginAuthorization = "Authorization: Basic ".base64_encode($this->rule_data['username'].":".$this->rule_data['password']);
	}
	else
	{
		$loginAuthorization = "Authorization: user_token ".$this->rule_data['user_token'];
	}

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $completeurl.'initSession/');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json',$loginAuthorization,"App-Token: ".$this->rule_data['app_token']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	if (isset($this->rule_data['proxy_address'])) {
		curl_setopt($ch, CURLOPT_PROXYPORT, $this->rule_data['proxy_port']);
		curl_setopt($ch, CURLOPT_PROXYTYPE, $this->rule_data['proxy_type']);
		curl_setopt($ch, CURLOPT_PROXY, $this->rule_data['proxy_address']);
		curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->rule_data['proxy_loginpassword']);
	}

    if (isset($this->rule_data['ignorecertificate']) && $this->rule_data['ignorecertificate'] == 'yes') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

    $returnJson = curl_exec($ch);
    if ($returnJson === false) {
      throw new \Exception(curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    #if ($status !== 200) {
    #  throw new \Exception(curl_error($ch));
    #}
    curl_close($ch);

    $return = json_decode($returnJson, true);
	
    if (strpos($returnJson,'ERROR_WRONG_APP_TOKEN_PARAMETER')) {
	echo "<b>Your App Token is incorrect</b><br/>";
    }
    else if (strpos($returnJson,'ERROR_GLPI_LOGIN_USER_TOKEN')) {
	echo "<b>Your User Token is incorrect</b><br/>";
    }
    else {
    	return array(
      		'sessiontoken' => $return['session_token']
    	);
    }
  }

  /**
   * Call a GLPI Rest webservices
   * @param string $methodname The method to call
   * @param string $params Parameter to send to the method
   */
  protected function callGLPI($methodName, $params = "") {
    $sessiontoken = $this->getCache('sessiontoken');

    if (is_null($sessiontoken)) {
      $tokens = $this->getAccessToken();
      $sessiontoken = $tokens['sessiontoken'];
      $this->setCache('sessiontoken', $tokens['sessiontoken'], 1600);
    } elseif (is_null($sessiontoken)) {
      $tokens = $this->sessiontoken($sessiontoken);
      $sessiontoken = $tokens['sessiontoken'];
      $this->setCache('sessiontoken', $tokens['sessiontoken'], 1600);
    }
	if (!empty($params)) {
		return $this->$methodName($sessiontoken,$params);
	}
	else {
		return $this->$methodName($sessiontoken);
	}
  }

  /**
   * Execute the http request using POST method
   *
   * @param string $uri The URI to call
   * @param string $accessToken The OAuth access token
   * @param string $method The http method
   * @param string $data The data to send, used in method POST, PUT, PATCH
   */
  protected function runPOSTHttpRequest($uri, $sessiontoken, $arguments = "") {
    $instance = self::getCompleteURL();
    $url = $instance . $uri;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Session-Token: ' . $sessiontoken,
	  'App-Token: ' . $this->rule_data['app_token']
    ));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$arguments);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	if (isset($this->rule_data['proxy_address'])) {
		curl_setopt($ch, CURLOPT_PROXYPORT, $this->rule_data['proxy_port']);
		curl_setopt($ch, CURLOPT_PROXYTYPE, $this->rule_data['proxy_type']);
		curl_setopt($ch, CURLOPT_PROXY, $this->rule_data['proxy_address']);
		curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->rule_data['proxy_loginpassword']);
	}
	
	if (isset($this->rule_data['ignorecertificate']) && $this->rule_data['ignorecertificate'] == 'yes') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    	}

    $returnJson = curl_exec($ch);
    if ($returnJson === false) {
      throw new \Exception(curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status < 200 && $status >= 300) {
      throw new \Exception(curl_error($ch));
    }
    curl_close($ch);

    return json_decode($returnJson, true);
  }
  
  
  
  /**
   * Execute the http request using GET method
   *
   * @param string $uri The URI to call
   * @param string $accessToken The OAuth access token
   * @param string $method The http method
   * @param string $data The data to send, used in method POST, PUT, PATCH
   */
  protected function runGETHttpRequest($uri, $sessiontoken, $urlparameter = "") {
    $instance = self::getCompleteURL();
    $url = $instance . $uri . "/?range=0-" . $this->rule_data['range'] . $urlparameter;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Session-Token: ' . $sessiontoken,
	  'App-Token: ' . $this->rule_data['app_token']
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	if (isset($this->rule_data['proxy_address'])) {
		curl_setopt($ch, CURLOPT_PROXYPORT, $this->rule_data['proxy_port']);
		curl_setopt($ch, CURLOPT_PROXYTYPE, $this->rule_data['proxy_type']);
		curl_setopt($ch, CURLOPT_PROXY, $this->rule_data['proxy_address']);
		curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->rule_data['proxy_loginpassword']);
	}

	if (isset($this->rule_data['ignorecertificate']) && $this->rule_data['ignorecertificate'] == 'yes') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    	}
	
    $returnJson = curl_exec($ch);
    if ($returnJson === false) {
      throw new \Exception(curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status < 200 && $status >= 300) {
      throw new \Exception(curl_error($ch));
    }
    curl_close($ch);

    return json_decode($returnJson, true);
  }
  
  /**
   * Add a value to the cache
   *
   * @param string $key The cache key name
   * @param mixed $value The value to cache
   * @param int|null $ttl The ttl of expire this cache, if it's null no expire
   */
  protected function setCache($key, $value, $ttl = null) {
    $cacheFile = $this->getCacheFilename($key);
    file_put_contents($cacheFile, json_encode(array(
      'value' => $value,
      'ttl' => $ttl,
      'created' => time()
    )));
  }

  /**
   * Get a cache value
   *
   * @param string $key The cache key name
   * @return mixed The cache value or null if not found or expired
   */
  protected function getCache($key) {
    $cacheFile = $this->getCacheFilename($key);
    if (!file_exists($cacheFile)) {
      return null;
    }
    $cacheJson = file_get_contents($cacheFile);
    $cache = json_decode($cacheJson, true);
    if (!is_null($cache['ttl'])) {
      $timeTtl = $cache['ttl'] + $cache['created'];
      if ($timeTtl < time()) {
        unlink($cacheFile);
        return null;
      }
    }
    return $cache['value'];
  }

  /**
   * Get the cache file name
   *
   * @param string $key The cache key name
   * @return string The full path to the cache file
   */
  protected function getCacheFilename($key) {
    $tmpDir = sys_get_temp_dir();
    return $tmpDir . '/' . $this->_getFormValue('address') . '_' . $key;
  }

  /**
   * Create the ticket
   *
   * @param string $sessiontoken is the token of the session
   * @param string $args is the arguments used in order to create the ticket
   * @return : result
   */
  protected function createticketGLPI($sessiontoken,$args) {
    $titre = $args["title"];
	$iddemandeur = $this->callGLPI('getCurrentID');
	$tabentitie = explode("_",$args["select_glpi_entity"]);
	$entity = $tabentitie[0];
	$tabgroupassign = explode("_",$args["select_glpi_group"]);
	$groupassign = $tabgroupassign[0];
	$body = $args["body"];
	$tabidcategorie = explode("_",$args["select_glpi_itil_category"]);
	$idcategorie = $tabidcategorie[0];
	$taburgency = explode("_",$args["select_urgency"]);
	$urgency = $taburgency[1];
	$tabimpact = explode("_",$args["select_impact"]);
	$impact = $tabimpact[1];

	$tableargs = array("input" => array ("name" => $titre, "entities_id" => $entity, "_users_id_requester" => $iddemandeur, "_users_id_assign" => 0, "_groups_id_assign" => $groupassign, "content" => $body, "itilcategories_id" => $idcategorie, "impact" => $impact, "urgency" => $urgency, "type" => "1"));
	
	$tableargs_string = json_encode($tableargs);
	return $this->runPOSTHttpRequest("Ticket", $sessiontoken, $tableargs_string);
	
  }
  
  
}
