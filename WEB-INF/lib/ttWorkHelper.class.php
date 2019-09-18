<?php
// +----------------------------------------------------------------------+
// | Anuko Time Tracker
// +----------------------------------------------------------------------+
// | Copyright (c) Anuko International Ltd. (https://www.anuko.com)
// +----------------------------------------------------------------------+
// | LIBERAL FREEWARE LICENSE: This source code document may be used
// | by anyone for any purpose, and freely redistributed alone or in
// | combination with other software, provided that the license is obeyed.
// |
// | There are only two ways to violate the license:
// |
// | 1. To redistribute this code in source form, with the copyright
// |    notice or license removed or altered. (Distributing in compiled
// |    forms without embedded copyright notices is permitted).
// |
// | 2. To redistribute modified versions of this code in *any* form
// |    that bears insufficient indications that the modifications are
// |    not the work of the original author(s).
// |
// | This license applies to this document only, not any other software
// | that it may be combined with.
// |
// +----------------------------------------------------------------------+
// | Contributors:
// | https://www.anuko.com/time_tracker/credits.htm
// +----------------------------------------------------------------------+

define('TT_CURL_SUCCESS', 1);

// Class ttWorkHelper is used to help with operations with the Remote work plugin.
class ttWorkHelper {
  var $errors = null;            // Errors go here. Set in constructor by reference.
  var $remote_work_uri = null;   // Location of remote work server.
  var $register_uri = null;      // URI to register with remote work server.
  var $put_work_uri = null;      // URI to publish offer.
  var $get_work_uri = null;      // URI to get offer details.
  var $get_active_work_uri = null;    // URI to get active work for group.
  var $get_available_work_uri = null; // URI to get available work for group.
  var $delete_work_uri = null;   // URI to delete eotk.
  // TODO: design how (and what) to delete when a group is deleted.
  var $update_work_uri = null;  // URI to update work.
  var $site_id = null;           // Site id for remote work server.
  var $site_key = null;          // Site key for remote work server.

  // Constructor.
  function __construct(&$errors) {
    $this->errors = &$errors;

    if (defined('REMOTE_WORK_URI')) {
      $this->remote_work_uri = REMOTE_WORK_URI;
      $this->register_uri = $this->remote_work_uri.'register';
      $this->put_work_uri = $this->remote_work_uri.'putwork';
      $this->get_work_uri = $this->remote_work_uri.'getwork';
      $this->get_active_work_uri = $this->remote_work_uri.'getactivework';
      $this->get_avaialable_work_uri = $this->remote_work_uri.'getavailablework';

      $this->delete_work_uri = $this->remote_work_uri.'deletework';
      $this->update_work_uri = $this->remote_work_uri.'updatework';
      $this->checkSiteRegistration();
    }
  }

  // checkSiteRegistration - obtains site id and key from local database.
  // If not found, it tries to register with remote work server.
  function checkSiteRegistration() {
    global $i18n;
    global $user;
    $mdb2 = getConnection();

    // Obtain site id.
    $sql = "select param_value as id from tt_site_config where param_name = 'worksite_id'";
    $res = $mdb2->query($sql);
    $val = $res->fetchRow();
    if (!$val) {
      // No site id found, need to register.
      $fields = array('lang' => urlencode($user->lang),
        'name' => urlencode('time tracker'),
        'origin' => urlencode('time tracker source'));

      // Urlify the data for the POST.
      foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
      $fields_string = rtrim($fields_string, '&');

      // Open connection.
      $ch = curl_init();

      // Set the url, number of POST vars, POST data.
      curl_setopt($ch, CURLOPT_URL, $this->register_uri);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      // Execute a post request.
      $result = curl_exec($ch);

      // Close connection.
      curl_close($ch);

      if (!$result) {
        $this->errors->add($i18n->get('error.remote_work'));
        return false;
      }

      $result_array = json_decode($result, true);

      // Check for errors.
      $call_status = $result_array['call_status'];
      if (!$call_status) {
        $this->errors->add($i18n->get('error.remote_work'));
        return false;
      }
      if ($call_status['code'] != TT_CURL_SUCCESS) {
        $this->errors->add($call_status['error']);
        return false;
      }

      $reg_status = $result_array['reg_status']; // Registration status.
      if ($reg_status['site_id'] && $reg_status['site_key']) {
        $this->site_id = $reg_status['site_id'];
        $this->site_key = $reg_status['site_key'];

        // Registration successful. Store id and key locally for future use.
        $sql = "insert into tt_site_config values('worksite_id', $this->site_id, now(), null)";
        $mdb2->exec($sql);
        $sql = "insert into tt_site_config values('worksite_key', ".$mdb2->quote($this->site_key).", now(), null)";
        $mdb2->exec($sql);
      } else {
        $this->errors->add($i18n->get('error.remote_work'));
      }
    } else {
      // Site id found.
      $this->site_id = $val['id'];

      // Obtain site key.
      $sql = "select param_value as site_key from tt_site_config where param_name = 'worksite_key'";
      $res = $mdb2->query($sql);
      $val = $res->fetchRow();
      $this->site_key = $val['site_key'];
    }
  }

  // putWork - publishes a work item in remote work server.
  function putWork($fields) {
    global $i18n;
    global $user;
    $mdb2 = getConnection();

    $group_id = $user->getGroup();
    $org_id = $user->org_id;

    $curl_fields = array('lang' => urlencode($user->lang),
      'site_id' => urlencode($this->site_id),
      'site_key' => urlencode($this->site_key),
      'org_id' => urlencode($org_id),
      'org_key' => urlencode($user->getOrgKey()),
      'group_id' => urlencode($group_id),
      'group_key' => urlencode($user->getGroupKey()),
      'subject' => urlencode($fields['subject']),
      'descr_short' => urlencode($fields['descr_short']),
      'descr_long' => urlencode($fields['descr_long']),
      'currency' => urlencode($fields['currency']),
      'amount' => urlencode($fields['amount'])
    );

    // url-ify the data for the POST.
    foreach($curl_fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    $fields_string = rtrim($fields_string, '&');

    // Open connection.
    $ch = curl_init();

    // Set the url, number of POST vars, POST data.
    curl_setopt($ch, CURLOPT_URL, $this->put_work_uri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute a post request.
    $result = curl_exec($ch);

    // Close connection.
    curl_close($ch);

    if (!$result) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }

    $result_array = json_decode($result, true);

    // Check for errors.
    $call_status = $result_array['call_status'];
    if (!$call_status) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }
    if ($call_status['code'] != TT_CURL_SUCCESS) {
      $this->errors->add($call_status['error']);
      return false;
    }

    return true;
  }

  // getActiveWork - obtains a list of work items this group is currently oputsourcing.
  function getActiveWork() {
    global $i18n;
    global $user;
    $mdb2 = getConnection();

    $group_id = $user->getGroup();
    $org_id = $user->org_id;

    $curl_fields = array('lang' => urlencode($user->lang),
      'site_id' => urlencode($this->site_id),
      'site_key' => urlencode($this->site_key),
      'org_id' => urlencode($org_id),
      'org_key' => urlencode($user->getOrgKey()),
      'group_id' => urlencode($group_id),
      'group_key' => urlencode($user->getGroupKey())
    );

    // url-ify the data for the POST.
    foreach($curl_fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    $fields_string = rtrim($fields_string, '&');

    // Open connection.
    $ch = curl_init();

    // Set the url, number of POST vars, POST data.
    curl_setopt($ch, CURLOPT_URL, $this->get_active_work_uri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute a post request.
    $result = curl_exec($ch);

    // Close connection.
    curl_close($ch);

    if (!$result) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }

    $result_array = json_decode($result, true);

    // Check for errors.
    $call_status = $result_array['call_status'];
    if (!$call_status) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }
    if ($call_status['code'] != TT_CURL_SUCCESS) {
      $this->errors->add($call_status['error']);
      return false;
    }

    $active_work = $result_array['active_work'];
    return $active_work;
  }

  // getWork - gets work item details from remote work server.
  function getWork($work_id) {
    global $i18n;
    global $user;
    $mdb2 = getConnection();

    $group_id = $user->getGroup();
    $org_id = $user->org_id;

    $curl_fields = array('lang' => urlencode($user->lang),
      'site_id' => urlencode($this->site_id),
      'site_key' => urlencode($this->site_key),
      'org_id' => urlencode($org_id),
      'org_key' => urlencode($user->getOrgKey()),
      'group_id' => urlencode($group_id),
      'group_key' => urlencode($user->getGroupKey()),
      'work_id' => urlencode($work_id));

    // url-ify the data for the POST.
    foreach($curl_fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    $fields_string = rtrim($fields_string, '&');

    // Open connection.
    $ch = curl_init();

    // Set the url, number of POST vars, POST data.
    curl_setopt($ch, CURLOPT_URL, $this->get_work_uri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute a post request.
    $result = curl_exec($ch);

    // Close connection.
    curl_close($ch);

    if (!$result) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }

    $result_array = json_decode($result, true);

    // Check for errors.
    $call_status = $result_array['call_status'];
    if (!$call_status) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }
    if ($call_status['code'] != TT_CURL_SUCCESS) {
      $this->errors->add($call_status['error']);
      return false;
    }

    $work_item = $result_array['work_item'];
    return $work_item;
  }

  // deleteWork - deletes work item from remote work server.
  function deleteWork($work_id) {
    global $i18n;
    global $user;
    $mdb2 = getConnection();

    $group_id = $user->getGroup();
    $org_id = $user->org_id;

    $curl_fields = array('lang' => urlencode($user->lang),
      'site_id' => urlencode($this->site_id),
      'site_key' => urlencode($this->site_key),
      'org_id' => urlencode($org_id),
      'org_key' => urlencode($user->getOrgKey()),
      'group_id' => urlencode($group_id),
      'group_key' => urlencode($user->getGroupKey()),
      'work_id' => urlencode($work_id));

    // url-ify the data for the POST.
    foreach($curl_fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    $fields_string = rtrim($fields_string, '&');

    // Open connection.
    $ch = curl_init();

    // Set the url, number of POST vars, POST data.
    curl_setopt($ch, CURLOPT_URL, $this->delete_work_uri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute a post request.
    $result = curl_exec($ch);

    // Close connection.
    curl_close($ch);

    if (!$result) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }

    $result_array = json_decode($result, true);

    // Check for errors.
    $call_status = $result_array['call_status'];
    if (!$call_status) {
      $this->errors->add($i18n->get('error.remote_work'));
      return false;
    }
    if ($call_status['code'] != TT_CURL_SUCCESS) {
      $this->errors->add($call_status['error']);
      return false;
    }

    return true;
  }

  // getCurrencies - obtains a list of supported currencies.
  static function getCurrencies() {
    $mdb2 = getConnection();

    $sql = "select id, name from tt_work_currencies order by id";
    $res = $mdb2->query($sql);
    if (is_a($res, 'PEAR_Error')) return false;

    while ($val = $res->fetchRow()) {
      $result[] = $val;
    }
    return $result;
  }
}
