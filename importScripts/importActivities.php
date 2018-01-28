<?php
Class CRM_HK_Activities_Import {

  public $civicrmPath = '';
  public $sourceContactId = '';
  public $activityTypeName = '';
  // used for 'Lead Remediation' activity type
  public $repairAmountCustomFieldId = '';
  // used for 'Healthy Kids Outreach Event' activity type
  public $importEntity = 'Event';

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
      $sql = "
      SELECT
          import.entity_id as target_contact_id,
          case_create_date_16 as created_date,
          income_verification_date_55 as activity_date
        FROM civicrm_value_healthy_kids_import_information_2 import
        INNER JOIN civicrm_value_income_information_6 income ON import.entity_id = income.entity_id
        WHERE income_qualifies__58 = 1 AND import.entity_id IS NOT NULL AND income_verification_date_55 <> ''
      ";
    }
    elseif ($this->activityTypeName == 'Lead Remediation') {
      $activityParams['activity_type_id'] = 'Lead Hazard Mitigated';
      $sql = "
      SELECT
          import.entity_id as target_contact_id,
          case_create_date_16 as created_date,
          lead_visual_inspection_date_260 as activity_date,
          annual_income_56 as repair_amount
        FROM civicrm_value_healthy_kids_import_information_2 import
        INNER JOIN civicrm_value_healthy_kids_information_1 hki ON hki.entity_id = import.entity_id
        INNER JOIN civicrm_value_income_information_6 income ON import.entity_id = income.entity_id
        WHERE annual_income_56 > 0 AND import.entity_id IS NOT NULL AND lead_visual_inspection_date_260 <> ''
      ";
    }
    elseif ($this->activityTypeName == 'Healthy Kids Outreach Event') {
      if ($this->importEntity == 'Event') {
        $sql = "
        SELECT e.id as source_id,
          e.title as subject,
          p.register_date as created_date,
          e.start_date as activity_date,
          p.contact_id as target_contact_id
          FROM civicrm_event e
           INNER JOIN civicrm_participant p on e.id=p.event_id
          WHERE e.title LIKE '%hk%'
        ";
      }
      else {
        $sql = "
        SELECT t.id as source_id,
          t.name as subject,
          t.created_date as created_date,
          t.created_date as activity_date,
          et.entity_id as target_contact_id
          FROM civicrm_tag t
           INNER JOIN civicrm_entity_tag et ON t.id = et.tag_id AND et.entity_table = 'civicrm_contact'
          WHERE t.name LIKE '%HK%'
        ";
      }

    }

    if ($sql) {
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $activityParams = array_merge($activityParams, array(
          'target_contact_id' => $dao->target_contact_id,
          'created_date' => $dao->created_date,
          'activity_date_time' => $dao->activity_date,
        ));
        if ($activityParams['activity_type_id'] == 'Lead Hazard Mitigated') {
          $activityParams['custom_' . $this->repairAmountCustomFieldId] = $dao->repair_amount;
        }
        elseif ($this->activityTypeName == 'Healthy Kids Outreach Event') {
          if ($this->importEntity == 'Tag') {
            if (strstr($dao->subject, 'July 2014')) {
              $activityParams['activity_date_time'] = '20140701' . date('His');
            }
            elseif (strstr($dao->subject, '11.16.17')) {
              $activityParams['activity_date_time'] = '20171116' . date('His');
            }
            elseif (strstr($dao->subject, '10/26/2015')) {
              $activityParams['activity_date_time'] = '20151026' . date('His');
            }
            elseif (strstr($dao->subject, '10/12/2015')) {
              $activityParams['activity_date_time'] = '20151012' . date('His');
            }
          }
          $activityParams['subject'] = $dao->subject;
          $activityParams['source_record_id'] = $dao->source_id;
        }
        civicrm_api3('Activity', 'create', $activityParams);
      }
    }
  }

  /*
  * Build Date using string.
  */
  protected function formatDate($dateString) {
    $dateString = explode('/', $dateString);
    if (empty($dateString[0]) || empty($dateString[1])) {
      $dateString = '01-01-1900';
    }
    else {
      $dateString = implode('-', array($dateString[1], $dateString[0], $dateString[2]));
    }
    $date = date('Ymd', strtotime($dateString));
    return $date;
  }

}

$import = new CRM_HK_Activities_Import();
$import->createActivitiesFromHKData();
