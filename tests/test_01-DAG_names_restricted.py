# Generated from Selenium IDE
# Test name: t01 - DAG names restricted
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_01_DAG_names_restricted:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_01_DAG_names_restricted(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, ".choose-general-dag-format").find_element(By.CSS_SELECTOR, "*[value='^[0-9]{3}[ ]']").click()
    self.driver.find_element(By.NAME, "dag-format-notice").send_keys("Example DAG format message.")
    self.driver.find_element(By.ID, "ui-id-2").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "div[aria-labelledby=\"ui-id-2\"] .ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    self.driver.find_element(By.ID, "ui-id-3").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "div[aria-labelledby=\"ui-id-3\"] .ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"route=DataAccessGroupsController:index\"]").click()
    assert len(self.driver.find_elements(By.XPATH, "//div[@class='yellow'][contains(.,'Example DAG format message.')]")) > 0
    self.driver.find_element(By.ID, "new_group").send_keys("02 DAG2")
    self.driver.find_element(By.ID, "new_group_button").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[contains(@class,'simpleDialog')]//p[contains(.,'The DAG name you entered does not conform to the allowed DAG name format.')]")) > 0
    self.driver.find_element(By.XPATH, "//*[contains(@class,'ui-dialog')][contains(.,'The DAG name you entered does not conform to the allowed DAG name format.')]//button[contains(@class,'close-button')]").click()
    self.driver.find_element(By.CSS_SELECTOR, "span[id^=\"gid_\"][title*=\"rename\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "[id^=\"gid_\"][id$=\"_field\"]").send_keys(Keys.CONTROL, "a")
    self.driver.find_element(By.CSS_SELECTOR, "[id^=\"gid_\"][id$=\"_field\"]").send_keys("01 DAG1", Keys.ENTER)
    assert len(self.driver.find_elements(By.XPATH, "//*[contains(@class,'simpleDialog')]//p[contains(.,'The DAG name you entered does not conform to the allowed DAG name format.')]")) > 0
    self.driver.find_element(By.XPATH, "//*[contains(@class,'ui-dialog')][contains(.,'The DAG name you entered does not conform to the allowed DAG name format.')]//button[contains(@class,'close-button')]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, ".choose-general-dag-format").find_element(By.CSS_SELECTOR, "*[value='^[0-9]{2}[ ]']").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"route=DataAccessGroupsController:index\"]").click()
    self.driver.find_element(By.ID, "new_group").send_keys("02 DAG2")
    self.driver.find_element(By.ID, "new_group_button").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[contains(@class,'simpleDialog')]//p[contains(.,'The DAG name you entered does not conform to the allowed DAG name format.')]")) == 0
    assert len(self.driver.find_elements(By.XPATH, "//table[@id='table-dags_table']//tr[contains(.,'02 DAG2')]")) > 0
    self.driver.find_element(By.XPATH, "//table[@id='table-dags_table']//tr[contains(.,'02 DAG2')]//img[contains(@onclick,'del_msg')]").click()
    self.driver.find_element(By.XPATH, "//*[contains(@class,'ui-dialog')]//button[contains(.,'Delete')]").click()
    None if len(elements := self.driver.find_elements(By.XPATH, "//table[@id='table-dags_table']//tr[contains(.,'02 DAG2')]")) == 0 else WebDriverWait(self.driver, 30).until(expected_conditions.staleness_of(elements[0]))
    self.driver.find_element(By.CSS_SELECTOR, "span[id^=\"gid_\"][title*=\"rename\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "[id^=\"gid_\"][id$=\"_field\"]").send_keys(Keys.CONTROL, "a")
    self.driver.find_element(By.CSS_SELECTOR, "[id^=\"gid_\"][id$=\"_field\"]").send_keys("02 DAG2", Keys.ENTER)
    assert len(self.driver.find_elements(By.XPATH, "//*[contains(@class,'simpleDialog')]//p[contains(.,'The DAG name you entered does not conform to the allowed DAG name format.')]")) == 0
    self.driver.find_element(By.CSS_SELECTOR, "span[id^=\"gid_\"][title*=\"rename\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "[id^=\"gid_\"][id$=\"_field\"]").send_keys(Keys.CONTROL, "a")
    self.driver.find_element(By.CSS_SELECTOR, "[id^=\"gid_\"][id$=\"_field\"]").send_keys("01 DAG1", Keys.ENTER)
    assert len(self.driver.find_elements(By.XPATH, "//*[contains(@class,'simpleDialog')]//p[contains(.,'The DAG name you entered does not conform to the allowed DAG name format.')]")) == 0
