# Generated from Selenium IDE
# Test name: t05 - User supplied component
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_05_User_supplied_component:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_05_User_supplied_component(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.execute_script("$('.ui-sortable-handle[data-value] input:checked').prop('checked',false);$('[name=\"scheme-name-separator[]\"]').val('')")
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"U\"] input")).is_selected() else element.click()
    self.driver.find_element(By.NAME, "scheme-name-prefix[]").send_keys("p")
    self.driver.find_element(By.NAME, "scheme-name-suffix[]").send_keys("s")
    self.driver.find_element(By.NAME, "scheme-prompt-user-supplied[]").send_keys("Enter numeric value")
    self.driver.find_element(By.NAME, "scheme-user-supplied-format[]").send_keys("^[0-9]*$")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog']//p[contains(.,'Enter numeric value')]")) > 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//input[@type='text']").send_keys("abc")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//div[contains(@class,'ui-dialog-buttonset')]//button").click()
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog']//p[contains(.,'value you entered was not valid')]")) > 0
    self.driver.execute_script("//SETDESC:Assert value rejected")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//p[contains(.,'value you entered was not valid')]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//input[@type='text']").send_keys("123")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//div[contains(@class,'ui-dialog-buttonset')]//button").click()
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p123s"
    self.driver.execute_script("//SETDESC:Assert value accepted")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"Surveys/invite_participants.php\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//table[contains(.,'Public Survey URL')][contains(.,'01 DAG1')]")))
    self.vars["sdt"] = self.driver.execute_script("return window.location.href")
    self.vars["survey"] = self.driver.find_element(By.XPATH, "//table[contains(.,'Public Survey URL')]//tr[contains(.,'01 DAG1')]//td[2]").text
    self.driver.execute_script("window.location = arguments[0]", self.vars["survey"])
    self.driver.find_element(By.NAME, "submit-btn-saverecord").click()
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog']//p[contains(.,'Enter numeric value')]")) > 0
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//input[@type='text']").send_keys("abc")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//div[contains(@class,'ui-dialog-buttonset')]//button").click()
    assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog']//p[contains(.,'value you entered was not valid')]")) > 0
    self.driver.execute_script("//SETDESC:Assert value rejected")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//p[contains(.,'value you entered was not valid')]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//input[@type='text']").send_keys("456")
    self.driver.find_element(By.XPATH, "//div[@role='dialog']//div[contains(@class,'ui-dialog-buttonset')]//button").click()
    self.driver.execute_script("//SAVEDESC:Assert value accepted")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//button[contains(.,'Close survey')]")))
    self.driver.execute_script("window.location = arguments[0]", self.vars["sdt"])
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    assert len(self.driver.find_elements(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'p456s')]")) > 0
    self.driver.find_element(By.XPATH, "//table[@id='record_status_table']//a[contains(.,'p456s')]").click()
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p456s"
    self.driver.execute_script("//SETDESC:Assert correct record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.ID, "recordActionDropdownTrigger").click()
    self.driver.find_element(By.CSS_SELECTOR, "#recordActionDropdown a[onclick*=\"delete_record_dialog\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "[aria-describedby=\"delete_record_dialog\"] .ok-button").click()
    self.driver.find_element(By.XPATH, "//div[contains(@class,'ui-dialog-buttonpane')]//button[contains(.,'Close')]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"U\"] input")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
