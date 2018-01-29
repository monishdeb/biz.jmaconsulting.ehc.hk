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
    elseif ($this->activityTypeName == 'Healthy Kids Outreach Event') {
      if ($this->importEntity == 'Event') {
        $sql = "
        SELECT p.id as source_id,
          e.title as subject,
          p.register_date as created_date,
          e.start_date as activity_date,
          p.contact_id as target_contact_id
          FROM civicrm_event e
           INNER JOIN civicrm_participant p on e.id = p.event_id
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
           INNER JOIN civicrm_contact cc ON cc.id = et.entity_id
          WHERE t.name LIKE '%HK%'
        ";
      }
    }
    elseif ($this->activityTypeName == 'Organising Event') {
      if ($this->importEntity == 'Event') {
        $sql = "
        SELECT p.id as source_id,
            e.title as subject,
            p.register_date as created_date,
            e.start_date as activity_date,
            p.contact_id as target_contact_id
            FROM civicrm_event e
             INNER JOIN civicrm_participant p on e.id=p.event_id
            WHERE e.title NOT LIKE '%hk%' AND e.title NOT LIKE '%fake%' AND e.title NOT LIKE '%test%'
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
           INNER JOIN civicrm_contact cc ON cc.id = et.entity_id
          WHERE t.name NOT LIKE '%HK%' AND t.name NOT LIKE '%Petition%' AND t.name NOT LIKE '%SALTA%'
        ";
      }
    }
    elseif (in_array($this->activityTypeName, array('SALTA', 'Sign Card', 'Petition')) {
      $searchString = ($this->activityTypeName == 'Sign Card') ? 'Card' : $this->activityTypeName;
      $sql = "
      SELECT t.id as source_id,
        t.name as subject,
        t.created_date as created_date,
        t.created_date as activity_date,
        et.entity_id as target_contact_id
        FROM civicrm_tag t
         INNER JOIN civicrm_entity_tag et ON t.id = et.tag_id AND et.entity_table = 'civicrm_contact'
         INNER JOIN civicrm_contact cc ON cc.id = et.entity_id
        WHERE t.name LIKE '%{$searchString}%'
      ";
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
        elseif (in_array($this->activityTypeName, array('Healthy Kids Outreach Event', 'Organising Event'))) {
          if ($this->importEntity == 'Tag' || $this->activityTypeName == 'SALTA') {
            if ($this->activityTypeName == 'SALTA') {
              $stringReplaceMap = array(
                '2009 ' => '20090101' . date('His'),
                '2007' => '20070101' . date('His'),
                '2012' => '20120101' . date('His'),
                '10-20-11' => '20101020' . date('His'),
                'VE 2006' => '20060101' . date('His'),
                '11-19-07' => '20060101' . date('His'),
                'graduates 06' => '20060101' . date('His'),
                '4.1.17' => '20170104' . date('His'),
              );
              foreach ($stringReplaceMap as $needle => $replaceDate) {
                if (strstr($dao->subject, $needle)) {
                  $activityParams['activity_date_time'] = $replaceDate;
                  break;
                }
              }
            }
            else {
              $stringReplaceMap = array(
                'July 2014' => '20140701' . date('His'),
                '11.16.17' => '20171116' . date('His'),
                '10/26/2015' => '20151026' . date('His'),
                '10/12/2015' => '20151012' . date('His'),
              );
              foreach ($stringReplaceMap as $needle => $replaceDate) {
                if (strstr($dao->subject, $needle)) {
                  $activityParams['activity_date_time'] = $replaceDate;
                  break;
                }
              }
            }
            $dao->subject = str_replace(array_keys($stringReplaceMap), '', $dao->subject);
            $activityParams['subject'] = str_replace(array('HS', '.'), array('', ' '), $dao->subject);
          }
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
