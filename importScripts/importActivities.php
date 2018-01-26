<?php
Class CRM_HK_Activities_Import {

  public $civicrmPath = '';
  public $sourceContactId = '';
  public $activityTypeName = '';

  function __construct() {
    // you can run this program either from an apache command, or from the cli
    $this->initialize();
  }

  function initialize() {
    $civicrmPath = $this->civicrmPath;
    require_once $civicrmPath .'civicrm.config.php';
    require_once $civicrmPath .'CRM/Core/Config.php';
    $config = CRM_Core_Config::singleton();
  }

  function createActivitiesFromHKData() {
    if ($this->activityTypeName == 'Lead Assessment') {
      $activityParams = [
        'activity_type_id' => 'Lead Assessment',
        'subject' => 'Lead Assesment activity',
        'status_id' => 'Completed',
        'source_contact_id' => $this->sourceContactId,
      ];
      $dao = CRM_Core_DAO::executeQuery("
      SELECT healthy_homes_id as source_id,
        civicrm_contact_id as target_contact_id,
        Case_create_date as created_date,
        Lead_Visual_Inspection_Date as activity_date
      FROM `TABLE 339`
      WHERE Lead_Inspected_By_SDHC > 0 AND Overall_Services_Lead = 'Y'
      ");
      while ($dao->fetch()) {
        $activityParams = array_merge($activityParams, array(
          'source_record_id' => $dao->source_id,
          'target_contact_id' => $dao->target_contact_id,
          'created_date' => $this->formatDate($dao->created_date),
          'activity_date_time' => $this->formatDate($dao->activity_date),
        ));
        civicrm_api3('Activity', 'create', $activityParams);
      }
    }
  }

  /*
  * Build Date using string.
  */
  protected function formatDate($dateString) {
    $dateString = str_replace('/', '-', $dateString);
    $date = date('Ymd', strtotime($dateString));
    return $date;
  }

}

$import = new CRM_HK_Activities_Import();
$import->createActivitiesFromHKData();
