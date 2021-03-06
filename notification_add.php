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

require_once('initialize.php');
require_once(LIBRARY_DIR.'/tdcron/class.tdcron.php');
require_once(LIBRARY_DIR.'/tdcron/class.tdcron.entry.php');
import('form.Form');
import('ttFavReportHelper');
import('ttNotificationHelper');

// Access checks.
if (!ttAccessAllowed('manage_advanced_settings')) {
  header('Location: access_denied.php');
  exit();
}
if (!$user->isPluginEnabled('no')) {
  header('Location: feature_disabled.php');
  exit();
}
if (!$user->exists()) {
  header('Location: access_denied.php'); // No users in subgroup.
  exit();
}
if ($request->isPost()) {
  $cl_fav_report_id = (int) $request->getParameter('fav_report');
  if (!ttFavReportHelper::get($cl_fav_report_id)) {
    header('Location: access_denied.php'); // Invalid fav report id in post.
    exit();
  }
}
// End of access checks.

$fav_reports = ttFavReportHelper::getReports();

if ($request->isPost()) {
  $cl_cron_spec = trim($request->getParameter('cron_spec'));
  $cl_email = trim($request->getParameter('email'));
  $cl_cc = trim($request->getParameter('cc'));
  $cl_subject = trim($request->getParameter('subject'));
  $cl_report_condition = trim($request->getParameter('report_condition'));
} else {
  $cl_cron_spec = '0 4 * * 1'; // Default schedule - weekly on Mondays at 04:00 (server time).
}

$form = new Form('notificationForm');
$form->addInput(array('type'=>'combobox',
  'name'=>'fav_report',
  'style'=>'width: 250px;',
  'value'=>$cl_fav_report_id,
  'data'=>$fav_reports,
  'datakeys'=>array('id','name'),
  'empty'=>array(''=>$i18n->get('dropdown.select'))
));
$form->addInput(array('type'=>'text','maxlength'=>'100','name'=>'cron_spec','style'=>'width: 250px;','value'=>$cl_cron_spec));
$form->addInput(array('type'=>'text','maxlength'=>'100','name'=>'email','style'=>'width: 250px;','value'=>$cl_email));
$form->addInput(array('type'=>'text','name'=>'cc','style'=>'width: 300px;','value'=>$cl_cc));
$form->addInput(array('type'=>'text','name'=>'subject','style'=>'width: 300px;','value'=>$cl_subject));
$form->addInput(array('type'=>'text','maxlength'=>'100','name'=>'report_condition','style'=>'width: 250px;','value'=>$cl_report_condition));
$form->addInput(array('type'=>'submit','name'=>'btn_add','value'=>$i18n->get('button.add')));

if ($request->isPost()) {
  // Validate user input.
  if (!$cl_fav_report_id) $err->add($i18n->get('error.report'));
  if (!ttValidCronSpec($cl_cron_spec)) $err->add($i18n->get('error.field'), $i18n->get('label.schedule'));
  if (!ttValidEmailList($cl_email)) $err->add($i18n->get('error.field'), $i18n->get('form.email'));
  if (!ttValidEmailList($cl_cc, true)) $err->add($i18n->get('error.field'), $i18n->get('label.cc'));
  if (!ttValidString($cl_subject, true)) $err->add($i18n->get('error.field'), $i18n->get('label.subject'));
  if (!ttValidCondition($cl_report_condition)) $err->add($i18n->get('error.field'), $i18n->get('label.condition'));

  if ($err->no()) {
    // Calculate next execution time.
    $next = tdCron::getNextOccurrence($cl_cron_spec, mktime()); 

    if (ttNotificationHelper::insert(array(
        'cron_spec' => $cl_cron_spec,
        'next' => $next,
        'report_id' => $cl_fav_report_id,
        'email' => $cl_email,
        'cc' => $cl_cc,
        'subject' => $cl_subject,
        'report_condition' => $cl_report_condition,
        'status' => ACTIVE))) {
        header('Location: notifications.php');
        exit();
      } else
        $err->add($i18n->get('error.db'));
  }
} // isPost

$smarty->assign('forms', array($form->getName()=>$form->toArray()));
$smarty->assign('title', $i18n->get('title.add_notification'));
$smarty->assign('content_page_name', 'notification_add.tpl');
$smarty->display('index.tpl');
