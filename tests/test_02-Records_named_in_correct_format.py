# Generated from Selenium IDE
# Test name: t02 - Records named in correct format
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

class Test_02_Records_named_in_correct_format:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_02_Records_named_in_correct_format(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.execute_script("$('.ui-sortable-handle[data-value] input:checked').prop('checked',false);$('[name=\"scheme-name-separator[]\"]').val('')")
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"G\"] input")).is_selected() else element.click()
    assert self.driver.find_element(By.NAME, "scheme-name-type[]").get_attribute("value") == "RG"
    self.driver.find_element(By.NAME, "scheme-name-prefix[]").send_keys("p")
    self.driver.find_element(By.NAME, "scheme-name-separator[]").send_keys("-")
    self.driver.find_element(By.NAME, "scheme-name-suffix[]").send_keys("s")
    self.driver.find_element(By.CSS_SELECTOR, ".choose-dag-format").find_element(By.CSS_SELECTOR, "*[value=':']").click()
    self.driver.find_element(By.NAME, "scheme-dag-format[]").send_keys("^([0-9]{2})[ ]")
    self.driver.find_element(By.NAME, "scheme-dag-section[]").send_keys("1")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.vars["username"] = "user1"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p1-01s"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"record_home.php\"]:not([href*=\"&id=\"])").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"webroot\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p1-01s"
    self.vars["username"] = "admin"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"Surveys/invite_participants.php\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//table[contains(.,'Public Survey URL')][contains(.,'01 DAG1')]")))
    self.vars["sdt"] = self.driver.execute_script("return window.location.href")
    self.vars["survey"] = self.driver.find_element(By.XPATH, "//table[contains(.,'Public Survey URL')]//tr[contains(.,'none')]//td[2]").text
    self.driver.execute_script("//SAVEDESC:Open survey link for no DAG, confirm survey not loaded.")
    self.driver.execute_script("window.location = arguments[0]", self.vars["survey"])
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "footer")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".surveysubmit")) == 0
    self.driver.execute_script("//SAVEDESC:Return to survey distribution tools page.")
    self.driver.execute_script("window.location = arguments[0]", self.vars["sdt"])
    self.vars["survey"] = self.driver.find_element(By.XPATH, "//table[contains(.,'Public Survey URL')]//tr[contains(.,'01 DAG1')]//td[2]").text
    self.driver.execute_script("//SAVEDESC:Open survey link for DAG 01, confirm survey loaded.")
    self.driver.execute_script("window.location = arguments[0]", self.vars["survey"])
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "footer")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, ".surveysubmit")) > 0
    self.driver.find_element(By.CSS_SELECTOR, ".surveysubmit button").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "surveyacknowledgment")))
    self.driver.execute_script("//SAVEDESC:Return to survey distribution tools page.")
    self.driver.execute_script("window.location = arguments[0]", self.vars["sdt"])
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"record_status_dashboard.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'p1-01s')]").send_keys("SAVESCREENSHOT")
    assert len(self.driver.find_elements(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'p1-01s')]")) > 0
    self.driver.find_element(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'p1-01s')]").click()
    self.driver.find_element(By.ID, "recordActionDropdownTrigger").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "#recordActionDropdown a[onclick*=\"deleteRecord\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "div[aria-describedby=\"delete_record_dialog\"] .ui-dialog-buttonset .ok-button").click()
    self.driver.find_element(By.CSS_SELECTOR, "div[aria-describedby^=\"popup\"] .ui-dialog-buttonset .close-button").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    ActionChains(self.driver).drag_and_drop(self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"R\"]"),self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"U\"]")).perform()
    assert self.driver.find_element(By.NAME, "scheme-name-type[]").get_attribute("value") == "GR"
    self.driver.execute_script("//SETDESC:Swap order of naming components")
    self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"G\"]").send_keys("SAVESCREENSHOT")
    self.driver.execute_script("$('#south').remove()")
    time.sleep(0.5)
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.vars["username"] = "user1"
    sub=Sub1();sub.driver=self.driver;sub.vars=self.vars;sub.test_fn_switchuser() # Run fn switchuser
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p01-1s"
