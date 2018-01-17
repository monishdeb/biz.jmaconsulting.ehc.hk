<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'CRM_Hk_Form_Report_ChildrenServed',
    'entity' => 'ReportTemplate',
    'params' =>
    array (
      'version' => 3,
      'label' => 'Families & Children’s Health Report',
      'description' => 'Families & Children’s Health Report (biz.jmaconsulting.ehc.hk)',
      'class_name' => 'CRM_Hk_Form_Report_FamiliesChildrensHealth',
      'report_url' => 'families-childrens-health',
      'component' => '',
    ),
  ),
);
