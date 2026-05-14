# Generated from Selenium IDE
# Test name: t07 - Field value lookup
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_07_Field_value_lookup:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_07_Field_value_lookup(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.execute_script("$('.ui-sortable-handle[data-value] input:checked').prop('checked',false);$('[name=\"scheme-name-separator[]\"]').val('')")
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"F\"] input")).is_selected() else element.click()
    self.driver.find_element(By.NAME, "scheme-name-prefix[]").send_keys("p")
    self.driver.find_element(By.NAME, "scheme-name-suffix[]").send_keys("s")
    self.driver.find_element(By.NAME, "scheme-prompt-field-lookup[]").send_keys("Select item below:")
    self.driver.find_element(By.NAME, "scheme-field-lookup-value[]").send_keys("first_name")
    self.driver.find_element(By.NAME, "scheme-field-lookup-desc[]").send_keys("last_name")
    self.driver.find_element(By.NAME, "scheme-field-lookup-filter[]").send_keys("[first_name] <> 'xyz'")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"2\"]").click()
    self.vars["lookups"] = self.driver.execute_script("return [['abc','a'],['def','b'],['xyz','z']]")
    for self.vars["lookup"] in self.vars["lookups"]:
      self.vars["lookupVal"] = self.driver.execute_script("return arguments[0][0]", self.vars["lookup"])
      self.vars["lookupDesc"] = self.driver.execute_script("return arguments[0][1]", self.vars["lookup"])
      self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
      self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=demographics\"]").click()
      self.driver.find_element(By.NAME, "first_name").send_keys(self.vars["lookupVal"])
      self.driver.find_element(By.NAME, "last_name").send_keys(self.vars["lookupDesc"])
      self.driver.find_element(By.ID, "submit-btn-saverecord").click()
      self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"1\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Assert prompt is shown")
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Add new record')]")) > 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Add new record')]").send_keys("SAVESCREENSHOT")
    self.driver.execute_script("//SAVEDESC:Assert that only the expected items are present in the dropdown.")
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Add new record')]//option[@value='abc' and .='a']")) > 0
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Add new record')]//option[@value='def' and .='b']")) > 0
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Add new record')]//option[@value='xyz']")) == 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Add new record')]//select").find_element(By.XPATH, "(descendant::option)[. = 'a']").click()
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')]//button[contains(.,'Add new record')]").click()
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "pabcs"
    self.driver.execute_script("//SETDESC:Assert correct value used")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"Surveys/invite_participants.php\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//table[contains(.,'Public Survey URL')][contains(.,'01 DAG1')]")))
    self.vars["sdt"] = self.driver.execute_script("return window.location.href")
    self.vars["survey"] = self.driver.find_element(By.XPATH, "//table[contains(.,'Public Survey URL')]//tr[contains(.,'01 DAG1')]//td[2]").text
    self.driver.execute_script("window.location = arguments[0]", self.vars["survey"])
    self.driver.find_element(By.NAME, "submit-btn-saverecord").click()
    self.driver.execute_script("//SETDESC:Assert prompt is shown")
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Submit')]")) > 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Submit')]").send_keys("SAVESCREENSHOT")
    self.driver.execute_script("//SAVEDESC:Assert that only the expected items are present in the dropdown.")
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Submit')]//option[@value='abc' and .='a']")) > 0
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Submit')]//option[@value='def' and .='b']")) > 0
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Submit')]//option[@value='xyz']")) == 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')][contains(.,'Submit')]//select").find_element(By.XPATH, "(descendant::option)[. = 'a']").click()
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select item below:')]//button[contains(.,'Submit')]").click()
    self.driver.execute_script("//SAVEDESC:Assert accepted")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//button[contains(.,'Close survey')]")))
    self.driver.execute_script("window.location = arguments[0]", self.vars["sdt"])
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    assert len(self.driver.find_elements(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'pabcs')]")) > 0
    self.driver.find_element(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'pabcs')]").click()
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "pabcs"
    self.driver.execute_script("//SETDESC:Assert correct record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"ProjectSetup/index.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"ProjectSetup/other_functionality.php\"]").click()
    self.driver.find_element(By.XPATH, "//button[contains(.,'Erase all data')]").click()
    self.driver.find_element(By.XPATH, "//div[@aria-describedby='erase_dialog']//button[contains(.,'Erase all data')]").click()
    self.driver.find_element(By.XPATH, "//div[@role='dialog' and contains(.,'data has now been deleted')]//button[contains(.,'Close')]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"U\"] input")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
