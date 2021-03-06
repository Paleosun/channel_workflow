<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Channel Workflow Module Control Panel File
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Module
 * @author		RBC
 * @link		http://paleosun.com
 */

class Channel_workflow_mcp {
	
	public $return_data;
	private $channel_field_map;
	private $_base_url;

	public function __construct()
	{
		$this->EE =& get_instance();

		$this->_base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=channel_workflow';

		$this->channel_field_map = $this->getChannelFieldMap();
	}
	
	//main index/default module page
	public function index()
	{
		$this->EE->cp->set_variable('cp_page_title', lang('channel_workflow_module_name'));
		$this->EE->load->library('table');

		$vars = array();
		$vars['_base_url'] = $this->_base_url;
		$vars['channel_fields_url'] = BASE.AMP.'C=admin_content'.AMP.'M=field_group_management';

		//get channels like this $channel_id=>('url_name,' 'Friendly Name', 'has_status_field')  
		$channels = $this->getChannelArray();
		$vars['channels'] = $channels;

		return $this->EE->load->view('index', $vars, TRUE);

	}

	//updates EE tables with necessary fields for adding the_channel_name_status
	public function addStatus()
	{

		$this->EE->cp->set_breadcrumb($this->_base_url, "Channel Worfklow");
		$this->EE->view->cp_page_title = "Adding Status";
		$this->EE->load->library('table');
		$this->EE->load->dbforge();

		$vars = array();
		$channel_id = $_GET['channel_id'];
		$channel_title= $_GET['channel_title'];
		$vars['channel_title'] = $channel_title;

		$status_field_name = $this->getStatusFieldName($channel_id);

		if (!isset($channel_id)) {
			die("channel id not set");
		}

		//there is a built-in function for this, refactor when you have time
		if ($this->fieldExists($status_field_name)) {
			//die($status_field_name." already exists!"); 
			$vars['success'] = FALSE; 
			return $this->EE->load->view('added_status', $vars, TRUE);
		}

		$site_id = $this->EE->config->item('site_id');

		$field_settings = "YTo2OntzOjE4OiJmaWVsZF9zaG93X3NtaWxleXMiO3M6MToibiI7czoxOToiZmllbGRfc2hvd19nbG9zc2FyeSI7czoxOiJuIjtzOjIxOiJmaWVsZF9zaG93X3NwZWxsY2hlY2siO3M6MToibiI7czoyNjoiZmllbGRfc2hvd19mb3JtYXR0aW5nX2J0bnMiO3M6MToibiI7czoyNDoiZmllbGRfc2hvd19maWxlX3NlbGVjdG9yIjtzOjE6Im4iO3M6MjA6ImZpZWxkX3Nob3dfd3JpdGVtb2RlIjtzOjE6Im4iO30=";

		//insert new field into exp_channel_fields
		$select_string = "INSERT INTO `exp_channel_fields` (`field_id`, `site_id`, `group_id`, `field_name`, `field_label`, `field_instructions`, `field_type`, `field_list_items`, `field_pre_populate`, `field_pre_channel_id`, `field_pre_field_id`, `field_ta_rows`, `field_maxl`, `field_required`, `field_text_direction`, `field_search`, `field_is_hidden`, `field_fmt`, `field_show_fmt`, `field_order`, `field_content_type`, `field_settings`) VALUES (NULL, '".$site_id."', '".$channel_id."', '".$status_field_name."', 'Status', '', 'select', 'Work in Progress\nCompleted\nApproved', 'n', '0', '0', '6', '128', 'n', 'ltr', 'n', 'n', 'none', 'n', '99', 'any','".$field_settings."')";
		//die($select_string);
		$query = $this->EE->db->query($select_string);

		$last_id = $this->EE->db->insert_id();
		$vars['id'] = $last_id;

		//add columns to exp_channel_data
		$field_ft = "field_ft_".$last_id;
		$field_id = "field_id_".$last_id;
		$fields = array(
				$field_ft => array(
					'type' => 'TEXT',
				),
				$field_id => array(
					'type' => 'TEXT',
				),
		);

		$this->EE->dbforge->add_column('channel_data', $fields);

		//add field formatting options to exp_field_formatting
		$data = array(
			 array(
					'field_id' => $last_id ,
					'field_fmt' => 'xhtml'
			 ),
			 array(
					'field_id' => $last_id ,
					'field_fmt' => 'markdown'
			 ),
			 array(
					'field_id' => $last_id ,
					'field_fmt' => 'br'
			 ),
			 array(
					'field_id' => $last_id ,
					'field_fmt' => 'none'
			 ),
		 );

		$this->EE->db->insert_batch('exp_field_formatting', $data); 
		//die($this->EE->db->last_query());

		//load view with feedback
		return $this->EE->load->view('added_status', $vars, TRUE);
		
	}

	//render statuses for a given channel
	public function viewStatus() 
	{

		$this->EE->load->library('table');
		
		//add show/hide buttons for each status
		$this->EE->cp->set_right_nav(array(
				'hide_wip' => '#',
				'hide_completed' => '#',
				'hide_approved' => '#',
			));

		$this->EE->load->library('javascript');
		$this->EE->javascript->output(array('
		
			var wipBtn = {
				class: ".workinprogress",
				selector: $(".button>:contains(Hide Works in Progress)"),
				name: "Work in Progress"
			};
	
			var completedBtn = {
				class: ".completed",			
				selector: $(".button>:contains(Hide Completed)"),
				name: "Completed"	
			};
			
			var approvedBtn = {
				class: ".approved",		
				selector: $(".button>:contains(Hide Approved)"),
				name: "Approved"	
			};
			
			var buttons = [wipBtn, completedBtn, approvedBtn];
			
			//initialize buttons
			buttons.forEach(function(button) {
				
				button.hidden = false;
				
				button.toggle = function() {
					console.log(\'clicky\');
					if (button.hidden === false) {
						$(button.class).parents("tr").slideUp(function() {
							button.hidden = true;
							button.selector.html("Show " + button.name);							
						});
					} else {
						$(button.class).parents("tr").slideDown(function() {
							button.hidden = false;
							button.selector.html("Hide " + button.name);
						});					
					}		
				},
				
				button.selector.click(function() {
					button.toggle();
				})
			})

		'));

		$this->EE->javascript->compile();

		$vars = array();
		$vars['_base_url'] = BASE.AMP.'C=content_publish'.AMP.'M=entry_form';
		$channel_id = $_GET['channel_id'];
		$channel_title= $_GET['channel_title'];

		if (!isset($channel_id)) {
			die("channel id not set");
		}

		$this->EE->view->cp_page_title = $channel_title;

		$this->EE->cp->set_breadcrumb($this->_base_url, "Channel Worfklow");

		$channel_map = $this->getChannelMap();

		//get array of all entries with this channel_id - name, url, status
		$entries = $this->getEntries($channel_id);

		$vars['entries'] = $entries;

		return $this->EE->load->view('status', $vars, TRUE);

	}
	
	//returns array of channels like this $channel_id=>('url_name,' 'Friendly Name', 'has_status_field')  
	function getChannelArray() 
	{

		$return_array = array();

		$this->EE->db->select('*');
		$this->EE->db->from('exp_channels');
		$query = $this->EE->db->get();
		$results = $query->result_array();

		foreach($results as $result) {
			$return_array[$result['channel_id']] = array(
				'url_title' => $result['channel_name'],
				'channel_title' => $result['channel_title'],
			); 
		}

		foreach ($return_array as $id=>$arr) {
			
			$field_string = $arr['url_title']."_status";
			if (array_key_exists($field_string, $this->channel_field_map)) {
				$return_array[$id]['has_status'] = true;
			}
			else {
				$return_array[$id]['has_status'] = false;
			}

		}

		return $return_array;
	}
	
	//helpers...

	function fieldExists($status_field_name)
	{
		//get all the field_names
		$this->EE->db->select('field_name');
		$this->EE->db->from('exp_channel_fields');
		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		//is status_field_name in there?
		foreach ($results as $result) {
			if ($result['field_name'] == $status_field_name) return true;
			
		}

		//status_field_name does not already exist in exp_channel_fields table
		return false;
	}


	//get the field_name_like_this
	function getStatusFieldName($id)
	{
		$this->EE->db->select('channel_name');
		$this->EE->db->from('exp_channels');
		$this->EE->db->where('channel_id', $id);
		$query = $this->EE->db->get();
		$results = $query->result_array();
		$channel_name = $results[0]['channel_name'];
		$status_field_name = $channel_name."_status";
		return $status_field_name;
	}

	//returns something like 'field_id_19'
	function getStatusField($id) 
	{

		$status_field_name = $this->getStatusFieldName($id); 

		//getStatusField.  that's what ya came here to do, right?
		$this->EE->db->select('field_id');
		$this->EE->db->from('exp_channel_fields');
		$this->EE->db->where('field_name', $status_field_name);
		$query = $this->EE->db->get();
		$results = $query->result_array();
		$status_field_num = $results[0]['field_id'];

		return "field_id_".$status_field_num;

	} 
	
	//takes channel_id and returns array of channel entries with statuses
	function getEntries($id) 
	{
		//what's status field for this channel
		$status_field = $this->getStatusField($id);

		$select_string = 'SELECT ct.entry_id, ct.url_title, ct.title,'.$status_field.'  FROM `exp_channel_data` cd, `exp_channel_titles` ct
			WHERE cd.entry_id= ct.entry_id AND cd.channel_id = '.$id;
		$query = $this->EE->db->query($select_string);
		$return_array = $query->result_array();

		//re-key on status string
		foreach ($return_array as $key=>$result) {
			$return_array[$key]['status'] = $result[$status_field];

		} 

		return $return_array;

	}

	//returns array of channel_name to channel_id
	function getChannelMap() 
	{

		$return_array = array();

		$this->EE->db->select('channel_id, channel_name');
		$this->EE->db->from('exp_channels');
		$query = $this->EE->db->get();
		$results = $query->result_array();

		foreach($results as $result) {
			$return_array[$result['channel_name']] = $result['channel_id'];
		}

		return $return_array;
	}
	
	//returns array of field_name to field_id
	function getChannelFieldMap() 
	{
		
		$return_array = array();

		$this->EE->db->select('field_id, field_name');
		$this->EE->db->from('exp_channel_fields');
		$query = $this->EE->db->get();
		$results = $query->result_array();

		foreach($results as $result) {
			$return_array[$result['field_name']] = $result['field_id'];
		}
		//die("<pre>".print_r($return_array,true)."</pre>");
		return $return_array;
	}
	
}
/* End of file mcp.channel_workflow.php */
/* Location: /system/expressionengine/third_party/channel_workflow/mcp.channel_workflow.php */
