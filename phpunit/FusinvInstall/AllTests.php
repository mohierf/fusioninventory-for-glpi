<?php

/*
   ------------------------------------------------------------------------
   FusionInventory
   Copyright (C) 2010-2013 by the FusionInventory Development Team.

   http://www.fusioninventory.org/   http://forge.fusioninventory.org/
   ------------------------------------------------------------------------

   LICENSE

   This file is part of FusionInventory project.

   FusionInventory is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   FusionInventory is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with FusionInventory. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   FusionInventory
   @author    David Durieux
   @co-author
   @copyright Copyright (c) 2010-2013 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010

   ------------------------------------------------------------------------
 */

class FusinvInstall extends PHPUnit_Framework_TestCase {

   public function testDB($pluginname='', $when='') {
      global $DB;

      if ($pluginname == '') {
         return;
      }

      $comparaisonSQLFile = "plugin_".$pluginname."-empty.sql";
      // See http://joefreeman.co.uk/blog/2009/07/php-script-to-compare-mysql-database-schemas/

      $file_content = file_get_contents("../../".$pluginname."/install/mysql/".$comparaisonSQLFile);
      $a_lines = explode("\n", $file_content);

      $a_tables_ref = array();
      $current_table = '';
      foreach ($a_lines as $line) {
         if (strstr($line, "CREATE TABLE ")
                 OR strstr($line, "CREATE VIEW")) {
            $matches = array();
            preg_match("/`(.*)`/", $line, $matches);
            $current_table = $matches[1];
         } else {
            if (preg_match("/^`/", trim($line))) {
               $s_line = explode("`", $line);
               $s_type = explode("COMMENT", $s_line[2]);
               $s_type[0] = trim($s_type[0]);
               $s_type[0] = str_replace(" COLLATE utf8_unicode_ci", "", $s_type[0]);
               $s_type[0] = str_replace(" CHARACTER SET utf8", "", $s_type[0]);
               $a_tables_ref[$current_table][$s_line[1]] = str_replace(",", "", $s_type[0]);
            }
         }
      }

     // * Get tables from MySQL
     $a_tables_db = array();
     $a_tables = array();
     // SHOW TABLES;
     $query = "SHOW TABLES";
     $result = $DB->query($query);
     while ($data=$DB->fetch_array($result)) {
        if ((strstr($data[0], "tracker")
                OR strstr($data[0], $pluginname))
            AND(!strstr($data[0], "glpi_plugin_fusioninventory_pcidevices"))
            AND(!strstr($data[0], "glpi_plugin_fusioninventory_pcivendors"))
            AND(!strstr($data[0], "glpi_plugin_fusioninventory_usbdevices"))
            AND(!strstr($data[0], "glpi_plugin_fusioninventory_usbvendors"))){
           
            $data[0] = str_replace(" COLLATE utf8_unicode_ci", "", $data[0]);
            $data[0] = str_replace("( ", "(", $data[0]);
            $data[0] = str_replace(" )", ")", $data[0]);
            $a_tables[] = $data[0];
         }
      }

      foreach($a_tables as $table) {
         $query = "SHOW CREATE TABLE ".$table;
         $result = $DB->query($query);
         while ($data=$DB->fetch_array($result)) {
            $a_lines = explode("\n", $data['Create Table']);
            
            foreach ($a_lines as $line) {
               if (strstr($line, "CREATE TABLE ")
                       OR strstr($line, "CREATE VIEW")) {
                  $matches = array();
                  preg_match("/`(.*)`/", $line, $matches);
                  $current_table = $matches[1];
               } else {
                  if (preg_match("/^`/", trim($line))) {
                     $s_line = explode("`", $line);
                     $s_type = explode("COMMENT", $s_line[2]);
                     $s_type[0] = trim($s_type[0]);
                     $s_type[0] = str_replace(" COLLATE utf8_unicode_ci", "", $s_type[0]);
                     $s_type[0] = str_replace(" CHARACTER SET utf8", "", $s_type[0]);
                     $s_type[0] = str_replace(",", "", $s_type[0]);
                     if (trim($s_type[0]) == 'text') {
                        $s_type[0] .= ' DEFAULT NULL';
                     }
                     $a_tables_db[$current_table][$s_line[1]] = $s_type[0];
                  }
               }
            }
         }
      }

      $a_tables_ref_tableonly = array();
      foreach ($a_tables_ref as $table=>$data) {
         $a_tables_ref_tableonly[] = $table;
      }
      $a_tables_db_tableonly = array();
      foreach ($a_tables_db as $table=>$data) {
         $a_tables_db_tableonly[] = $table;
      }
      
       // Compare
      $tables_toremove = array_diff($a_tables_db_tableonly, $a_tables_ref_tableonly);
      $tables_toadd = array_diff($a_tables_ref_tableonly, $a_tables_db_tableonly);

      // See tables missing or to delete
      $this->assertEquals(count($tables_toadd), 0, 'Tables missing '.$when.' '.print_r($tables_toadd, TRUE));
      $this->assertEquals(count($tables_toremove), 0, 'Tables to delete '.$when.' '.print_r($tables_toremove, TRUE));

      // See if fields are same
      foreach ($a_tables_db as $table=>$data) {
         if (isset($a_tables_ref[$table])) {
            $fields_toremove = array_diff_assoc($data, $a_tables_ref[$table]);
            $fields_toadd = array_diff_assoc($a_tables_ref[$table], $data);
            $diff = "======= DB ============== Ref =======> ".$table."\n";
            $diff .= print_r($data, TRUE);
            $diff .= print_r($a_tables_ref[$table], TRUE);

            // See tables missing or to delete
            $this->assertEquals(count($fields_toadd), 0, 'Fields missing/not good in '.$when.' '.$table.' '.print_r($fields_toadd, TRUE)." into ".$diff);
            $this->assertEquals(count($fields_toremove), 0, 'Fields to delete in '.$when.' '.$table.' '.print_r($fields_toremove, TRUE)." into ".$diff);

         }
      }

      /*
       * Check if all modules registered
       */
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules`
         WHERE `modulename`='WAKEONLAN'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'WAKEONLAN module not registered');

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules`
         WHERE `modulename`='INVENTORY'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'INVENTORY module not registered');

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules`
         WHERE `modulename`='ESX'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'ESX module not registered');

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules`
         WHERE `modulename`='NETWORKINVENTORY'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'NETWORKINVENTORY module not registered');

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules`
         WHERE `modulename`='NETWORKDISCOVERY'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, 'NETWORKDISCOVERY module not registered');

//      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_agentmodules`
//         WHERE `modulename`='DEPLOY'";
//      $result = $DB->query($query);
//      $this->assertEquals($DB->numrows($result), 1, 'DEPLOY module not registered');


      /*
       * Verify in taskjob definition PluginFusinvsnmpIPRange not exist
       */
      $query = "SELECT * FROM `glpi_plugin_fusioninventory_taskjobs`";
      $result = $DB->query($query);
      while ($data=$DB->fetch_array($result)) {
         $snmprangeip = 0;
         if (strstr($data['definition'], "PluginFusinvsnmpIPRange")) {
            $snmprangeip = 1;
         }
         $this->assertEquals($snmprangeip, 0, 'Have some "PluginFusinvsnmpIPRange" items in taskjob definition');
      }

      /*
       * Verify cron created
       */
      $crontask = new CronTask();
      $this->assertTrue($crontask->getFromDBbyName('PluginFusioninventoryTaskjob', 'taskscheduler'),
              'Cron taskscheduler not created');
      $this->assertTrue($crontask->getFromDBbyName('PluginFusioninventoryTaskjobstate', 'cleantaskjob'),
              'Cron cleantaskjob not created');
      $this->assertTrue($crontask->getFromDBbyName('PluginFusioninventoryNetworkPortLog', 'cleannetworkportlogs'),
              'Cron cleannetworkportlogs not created');


      /*
       * Verify config fields added
       */
      $plugin = new Plugin();
      $data = $plugin->find("directory='fusioninventory'");
      $plugins_id = 0;
      if (count($data)) {
         $fields = current($data);
         $plugins_id = $fields['id'];
      }
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_configs`
         WHERE `type`='ssl_only'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, "type 'ssl_only' not added in config for plugins ".$plugins_id);

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_configs`
         WHERE `type`='delete_task'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, "type 'delete_task' not added in config");

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_configs`
         WHERE `type`='inventory_frequence'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, "type 'inventory_frequence' not added in config");

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_configs`
         WHERE `type`='agent_port'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, "type 'agent_port' not added in config");

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_configs`
         WHERE `type`='extradebug'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, "type 'extradebug' not added in config");

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_configs`
         WHERE `type`='users_id'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, "type 'users_id' not added in config");

      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_configs`
         WHERE `type`='version'";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 1, "type 'version' not added in config");



      // TODO : test glpi_displaypreferences, rules, bookmark...


      /*
       * Verify table glpi_plugin_fusioninventory_inventorycomputercriterias 
       * have right 10 lines
       */
      $query = "SELECT `id` FROM `glpi_plugin_fusioninventory_inventorycomputercriterias`";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 10, "Number of criteria not right in table".
              " glpi_plugin_fusioninventory_inventorycomputercriterias ".$when);

      
      
      /*
       * Verify SNMP models have a right itemtype
       */
      $query = "SELECT * FROM `glpi_plugin_fusioninventory_snmpmodels`
         WHERE `itemtype` NOT IN('Computer','NetworkEquipment', 'Printer')";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 0, "SNMP models have invalid itemtype");


      /*
       * Verify SNMP models not in double
       */
      $query = "SELECT count(*) as cnt, `name` FROM `glpi_plugin_fusioninventory_snmpmodels`
         GROUP BY `name`
         HAVING cnt >1";
      $result = $DB->query($query);
      $this->assertEquals($DB->numrows($result), 0, "SNMP models are in double (name of models)");



   }
}

require_once 'Install/AllTests.php';
require_once 'Update/AllTests.php';

class FusinvInstall_AllTests  {

   public static function suite() {

      $suite = new PHPUnit_Framework_TestSuite('FusinvInstall');
      $suite->addTest(Install_AllTests::suite());
      $suite->addTest(Update_AllTests::suite());
      return $suite;
   }
}

?>