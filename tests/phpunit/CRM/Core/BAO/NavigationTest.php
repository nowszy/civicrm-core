<?php
require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Class CRM_Core_BAO_NavigationTest.
 */
class CRM_Core_BAO_NavigationTest extends CiviUnitTestCase {

  /**
   * Set up data for the test run.
   *
   * Here we ensure we are starting from a default report navigation.
   */
  public function setUp() {
    parent::setUp();
    CRM_Core_BAO_Navigation::rebuildReportsNavigation(CRM_Core_Config::domainID());
  }

  /**
   * Test that a missing report menu link is added by rebuildReportsNavigation.
   */
  public function testCreateMissingReportMenuItemLink() {
    $reportCount = $this->getCountReportInstances();
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_navigation WHERE url = 'civicrm/report/instance/1?reset=1'");
    $this->assertEquals($reportCount - 1, $this->getCountReportInstances());
    CRM_Core_BAO_Navigation::rebuildReportsNavigation(CRM_Core_Config::domainID());

    $this->assertEquals($reportCount, $this->getCountReportInstances());
    $url = 'civicrm/report/instance/1';
    $url_params = 'reset=1';
    $new_nav = CRM_Core_BAO_Navigation::getNavItemByUrl($url, $url_params);
    $this->assertObjectHasAttribute('id', $new_nav);
    $this->assertNotNull($new_nav->id);
  }

  /**
   * Test that an existing report link is rebuilt under it's parent.
   *
   * Function tests CRM_Core_BAO_Navigation::rebuildReportsNavigation.
   */
  public function testUpdateExistingReportMenuLink() {
    $url = 'civicrm/report/instance/1';
    $url_params = 'reset=1';
    $existing_nav = CRM_Core_BAO_Navigation::getNavItemByUrl($url, $url_params);
    $this->assertNotEquals(FALSE, $existing_nav);
    $existing_nav->parent_id = 1;
    $existing_nav->save();
    CRM_Core_BAO_Navigation::rebuildReportsNavigation(CRM_Core_Config::domainID());
    $parent_url = 'civicrm/report/list';
    $parent_url_params = 'compid=99&reset=1';
    $reportsMenu = CRM_Core_BAO_Navigation::createOrUpdateTopLevelReportsNavItem(CRM_Core_Config::domainID());
    $parent_nav = CRM_Core_BAO_Navigation::getNavItemByUrl($parent_url, $parent_url_params, $reportsMenu->id);
    $this->assertNotEquals($parent_nav->id, 1);
    $changed_existing_nav = new CRM_Core_BAO_Navigation();
    $changed_existing_nav->id = $existing_nav->id;
    $changed_existing_nav->find(TRUE);
    $this->assertEquals($changed_existing_nav->parent_id, $parent_nav->id);
  }


  /**
   * Test that a navigation item can be retrieved by it's url.
   */
  public function testGetNavItemByUrl() {
    $random_string = substr(sha1(rand()), 0, 7);
    $name = "Test Menu Link {$random_string}";
    $url = "civicrm/test/{$random_string}";
    $url_params = "reset=1";
    $params = array(
      'name' => $name,
      'label' => ts($name),
      'url' => "{$url}?{$url_params}",
      'parent_id' => NULL,
      'is_active' => TRUE,
      'permission' => array(
        'access CiviCRM',
      ),
    );
    CRM_Core_BAO_Navigation::add($params);
    $new_nav = CRM_Core_BAO_Navigation::getNavItemByUrl($url, $url_params);
    $this->assertObjectHasAttribute('id', $new_nav);
    $this->assertNotNull($new_nav->id);
    $new_nav->delete();
  }

  /**
   * Get a count of report instances.
   *
   * @return int
   */
  protected function getCountReportInstances() {
    return CRM_Core_DAO::singleValueQuery(
      "SELECT count(*) FROM civicrm_navigation WHERE url LIKE 'civicrm/report/instance/%'");
  }

  /**
   * Run fixNavigationMenu() on a menu which already has navIDs
   * everywhere. They should be unchanged.
   */
  public function testFixNavigationMenu_preserveIDs() {
    $input[10] = array(
      'attributes' => array(
        'label' => 'Custom Menu Entry',
        'parentID' => NULL,
        'navID' => 10,
        'active' => 1,
      ),
      'child' => array(
        '11' => array(
          'attributes' => array(
            'label' => 'Custom Child Menu',
            'parentID' => 10,
            'navID' => 11,
          ),
          'child' => NULL,
        ),
      ),
    );

    $output = $input;
    CRM_Core_BAO_Navigation::fixNavigationMenu($output);

    $this->assertEquals(NULL, $output[10]['attributes']['parentID']);
    $this->assertEquals(10, $output[10]['attributes']['navID']);
    $this->assertEquals(10, $output[10]['child'][11]['attributes']['parentID']);
    $this->assertEquals(11, $output[10]['child'][11]['attributes']['navID']);
  }

  /**
   * Run fixNavigationMenu() on a menu which is missing some navIDs. They
   * should be filled in, and others should be preserved.
   */
  public function testFixNavigationMenu_inferIDs() {
    $input[10] = array(
      'attributes' => array(
        'label' => 'Custom Menu Entry',
        'parentID' => NULL,
        'navID' => 10,
        'active' => 1,
      ),
      'child' => array(
        '0' => array(
          'attributes' => array(
            'label' => 'Custom Child Menu',
          ),
          'child' => NULL,
        ),
        '100' => array(
          'attributes' => array(
            'label' => 'Custom Child Menu 2',
            'navID' => 100,
          ),
          'child' => NULL,
        ),
      ),
    );

    $output = $input;
    CRM_Core_BAO_Navigation::fixNavigationMenu($output);

    $this->assertEquals('Custom Menu Entry', $output[10]['attributes']['label']);
    $this->assertEquals(NULL, $output[10]['attributes']['parentID']);
    $this->assertEquals(10, $output[10]['attributes']['navID']);

    $this->assertEquals('Custom Child Menu', $output[10]['child'][101]['attributes']['label']);
    $this->assertEquals(10, $output[10]['child'][101]['attributes']['parentID']);
    $this->assertEquals(101, $output[10]['child'][101]['attributes']['navID']);

    $this->assertEquals('Custom Child Menu 2', $output[10]['child'][100]['attributes']['label']);
    $this->assertEquals(10, $output[10]['child'][100]['attributes']['parentID']);
    $this->assertEquals(100, $output[10]['child'][100]['attributes']['navID']);
  }

  public function testFixNavigationMenu_inferIDs_deep() {
    $input[10] = array(
      'attributes' => array(
        'label' => 'Custom Menu Entry',
        'parentID' => NULL,
        'navID' => 10,
        'active' => 1,
      ),
      'child' => array(
        '0' => array(
          'attributes' => array(
            'label' => 'Custom Child Menu',
          ),
          'child' => array(
            '100' => array(
              'attributes' => array(
                'label' => 'Custom Child Menu 2',
                'navID' => 100,
              ),
              'child' => NULL,
            ),
          ),
        ),
      ),
    );

    $output = $input;
    CRM_Core_BAO_Navigation::fixNavigationMenu($output);

    $this->assertEquals('Custom Menu Entry', $output[10]['attributes']['label']);
    $this->assertEquals(NULL, $output[10]['attributes']['parentID']);
    $this->assertEquals(10, $output[10]['attributes']['navID']);

    $this->assertEquals('Custom Child Menu', $output[10]['child'][101]['attributes']['label']);
    $this->assertEquals(10, $output[10]['child'][101]['attributes']['parentID']);
    $this->assertEquals(101, $output[10]['child'][101]['attributes']['navID']);

    $this->assertEquals('Custom Child Menu 2', $output[10]['child'][101]['child'][100]['attributes']['label']);
    $this->assertEquals(101, $output[10]['child'][101]['child'][100]['attributes']['parentID']);
    $this->assertEquals(100, $output[10]['child'][101]['child'][100]['attributes']['navID']);
  }

}