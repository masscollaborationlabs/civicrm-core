<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * Upgrade logic for the 5.67.x series.
 *
 * Each minor version in the series is handled by either a `5.67.x.mysql.tpl` file,
 * or a function in this class named `upgrade_5_67_x`.
 * If only a .tpl file exists for a version, it will be run automatically.
 * If the function exists, it must explicitly add the 'runSql' task if there is a corresponding .mysql.tpl.
 *
 * This class may also implement `setPreUpgradeMessage()` and `setPostUpgradeMessage()` functions.
 */
class CRM_Upgrade_Incremental_php_FiveSixtySeven extends CRM_Upgrade_Incremental_Base {

  public function setPreUpgradeMessage(&$preUpgradeMessage, $rev, $currentVer = NULL) {
    parent::setPreUpgradeMessage($preUpgradeMessage, $rev, $currentVer);
    if ($rev === '5.67.alpha1') {
      $customPrivacy = CRM_Core_DAO::executeQuery('
        SELECT value, label
        FROM civicrm_option_value
        WHERE is_active = 1 AND value NOT IN ("0", "1")
          AND option_group_id = (SELECT id FROM civicrm_option_group WHERE name = "note_privacy")')
        ->fetchMap('value', 'label');
      if ($customPrivacy) {
        $preUpgradeMessage .= '<p>'
          . ts('This site has custom note privacy options (%1) which may not work correctly after the upgrade, due to the deprecation of hook_civicrm_notePrivacy. If you are using this hook, see <a %2>developer documentation on updating your code</a>.', [1 => '"' . implode('", "', $customPrivacy) . '"', 2 => 'href="https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_notePrivacy/" target="_blank"']) .
          '</p>';
      }
    }
  }

  /**
   * Upgrade step; adds tasks including 'runSql'.
   *
   * @param string $rev
   *   The version number matching this function name
   */
  public function upgrade_5_67_alpha1($rev): void {
    $this->addTask(ts('Upgrade DB to %1: SQL', [1 => $rev]), 'runSql', $rev);
    $this->addTask('Make Note.privacy required', 'alterColumn', 'civicrm_note', 'privacy', "varchar(255) NOT NULL DEFAULT 0 COMMENT 'Foreign Key to Note Privacy Level (which is an option value pair and hence an implicit FK)'");
    $this->addTask('Make EntityFile.entity_table required', 'alterColumn', 'civicrm_entity_file', 'entity_table', "varchar(64) NOT NULL COMMENT 'physical tablename for entity being joined to file, e.g. civicrm_contact'");
    $this->addExtensionTask('Enable Authx extension', ['authx'], 1101);
    $this->addExtensionTask('Enable Afform extension', ['org.civicrm.afform'], 1102);
    $this->addTask('Add "civicrm_note" to "note_used_for" option group', 'addNoteNote');
    $this->addTask('Add cache_fill_took column to Group table', 'addColumn', 'civicrm_group', 'cache_fill_took',
      'DOUBLE DEFAULT NULL COMMENT "Seconds taken to fill smart group cache, not always related to cache_date"',
      FALSE);
  }

  public static function addNoteNote(CRM_Queue_TaskContext $ctx): bool {
    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => 'note_used_for',
      'label' => ts('Notes'),
      'name' => 'Note',
      'value' => 'civicrm_note',
    ]);
    return TRUE;
  }

}