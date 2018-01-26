<?php
Class CRM_HK_Activities_Import {

  public $civicrmPath = '';
  public $sourceContactId = '';
  public $activityTypeName = '';
  public $repairAmountCustomFieldId = '';

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
    $activityParams = [
      'activity_type_id' => $this->activityTypeName,
      'subject' => $this->activityTypeName . ' activity',
      'status_id' => 'Completed',
      'source_contact_id' => $this->sourceContactId,
    ];
    $sql = NULL;
    if ($this->activityTypeName == 'Lead Assessment') {
      $activityParams['activity_type_id'] = 'HK Service';
      $sql = "
      SELECT healthy_homes_id as source_id,
        civicrm_contact_id as target_contact_id,
        Case_create_date as created_date,
        Lead_Visual_Inspection_Date as activity_date
      FROM `TABLE 339`
      WHERE Lead_Inspected_By_SDHC > 0 AND Overall_Services_Lead = 'Y'
      ";
    }
    elseif ($this->activityTypeName == 'Eligibility Review') {
      $activityParams['activity_type_id'] = 'HK Service';
      $sql = "
      SELECT healthy_homes_id as source_id,
        civicrm_contact_id as target_contact_id,
        Case_create_date as created_date,
        Income_Verification_Date as activity_date
      FROM `TABLE 339`
      WHERE Income_Qualifies = 'Y' AND civicrm_contact_id IS NOT NULL AND Income_Verification_Date <> ''
      ";
    }
    elseif ($this->activityTypeName == 'Lead Remediation') {
      $activityParams['activity_type_id'] = 'Lead Hazard Mitigated';
      $sql = "
      SELECT healthy_homes_id as source_id,
        civicrm_contact_id as target_contact_id,
        Case_create_date as created_date,
        Lead_Visual_Inspection_Date as activity_date,
        Housing_Investment_Lead_repairs as repair_amount
      FROM `TABLE 339`
      WHERE Housing_Investment_Lead_repairs > 0 AND civicrm_contact_id IS NOT NULL AND Lead_Visual_Inspection_Date <> ''
      ";
    }

    if ($sql) {
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $activityParams = array_merge($activityParams, array(
          'source_record_id' => $dao->source_id,
          'target_contact_id' => $dao->target_contact_id,
          'created_date' => $this->formatDate($dao->created_date),
          'activity_date_time' => $this->formatDate($dao->activity_date),
        ));
        if ($activityParams['activity_type_id'] == 'Lead Hazard Mitigated') {
          'custom_' . $repairAmountCustomFieldId => $dao->repair_amount,
        }
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
