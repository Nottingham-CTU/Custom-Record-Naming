# Generated from Selenium IDE
# Test name: t04 - DAG used in name correctly
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait
from fn_switchuser import Test_fn_switchuser as Sub1

class Test_04_DAG_used_in_name_correctly:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_04_DAG_used_in_name_correctly(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.execute_script("$('.ui-sortable-handle[data-value] input:checked').prop('checked',false);$('[name=\"scheme-name-separator[]\"]').val('')")
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"G\"] input")).is_selected() else element.click()
    self.driver.find_element(By.NAME, "scheme-name-prefix[]").send_keys("p")
    self.driver.find_element(By.NAME, "scheme-name-suffix[]").send_keys("s")
    self.driver.find_element(By.CSS_SELECTOR, ".choose-dag-format").find_element(By.CSS_SELECTOR, "*[value='^([^ ]+)[ ]']").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select DAG')][contains(.,'Add new record')]")) > 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select DAG')][contains(.,'Add new record')]//select").find_element(By.XPATH, "(descendant::option)[. = '01 DAG1']").click()
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select DAG')]//button[contains(.,'Add new record')]").click()
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p01s"
    self.vars["username"] = "user1"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p01s"
    self.vars["username"] = "admin"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"Surveys/invite_participants.php\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//table[contains(.,'Public Survey URL')][contains(.,'01 DAG1')]")))
    self.vars["sdt"] = self.driver.execute_script("return window.location.href")
    self.vars["survey"] = self.driver.find_element(By.XPATH, "//table[contains(.,'Public Survey URL')]//tr[contains(.,'01 DAG1')]//td[2]").text
    self.driver.execute_script("window.location = arguments[0]", self.vars["survey"])
    self.driver.find_element(By.NAME, "submit-btn-saverecord").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//button[contains(.,'Close survey')]")))
    self.driver.execute_script("window.location = arguments[0]", self.vars["sdt"])
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    assert len(self.driver.find_elements(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'p01s')]")) > 0
    self.driver.find_element(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'p01s')]").click()
    self.driver.find_element(By.ID, "recordActionDropdownTrigger").click()
    self.driver.find_element(By.CSS_SELECTOR, "#recordActionDropdown a[onclick*=\"delete_record_dialog\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "[aria-describedby=\"delete_record_dialog\"] .ok-button").click()
    self.driver.find_element(By.XPATH, "//div[contains(@class,'ui-dialog-buttonpane')]//button[contains(.,'Close')]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.find_element(By.CSS_SELECTOR, ".choose-dag-format").find_element(By.CSS_SELECTOR, "*[value=':']").click()
    self.driver.find_element(By.NAME, "scheme-dag-format[]").send_keys("^(z)")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.execute_script("//SAVEDESC:Open survey link for DAG 1, confirm survey not loaded.")
    self.driver.execute_script("window.location = arguments[0]", self.vars["survey"])
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "footer")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".surveysubmit")) == 0
    self.driver.execute_script("//SAVEDESC:Return to survey distribution tools page.")
    self.driver.execute_script("window.location = arguments[0]", self.vars["sdt"])
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog'][contains(.,'Select DAG')][contains(.,'Add new record')]")) > 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select DAG')][contains(.,'Add new record')]//select").find_element(By.XPATH, "(descendant::option)[. = '01 DAG1']").click()
    self.driver.find_element(By.XPATH, "//div[@role='dialog'][contains(.,'Select DAG')]//button[contains(.,'Add new record')]").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "#record-home-link b")) == 0
    self.vars["username"] = "user1"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    WebDriverWait(self.driver, 4).until(expected_conditions.invisibility_of_element_located((By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]")))
    assert len(self.driver.find_elements(By.XPATH, "//body[contains(.,'(New records cannot be added to this arm from this Data Access Group)')]")) > 0
    self.driver.execute_script("$('#south').remove();$('button.btn-rcgreen[onclick*=\"record_home.php\"]').trigger('click')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "#record-home-link b")) == 0
    self.vars["username"] = "admin"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.find_element(By.CSS_SELECTOR, ".choose-dag-format").find_element(By.CSS_SELECTOR, "*[value='^([^ ]+)[ ]']").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"G\"] input")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
