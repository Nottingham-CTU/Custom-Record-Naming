# Generated from Selenium IDE
# Test name: t12 - New records logic
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_12_New_records_logic:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_12_New_records_logic(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-3").click()
    self.driver.find_element(By.CSS_SELECTOR, "[aria-labelledby=\"ui-id-3\"] [name=\"scheme-allow-new-logic[]\"]").send_keys("1=0")
    self.driver.execute_script("$('#south').remove()")
    time.sleep(0.5)
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"1\"]").click()
    time.sleep(2)
    WebDriverWait(self.driver, 3).until(expected_conditions.visibility_of_element_located((By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]")))
    self.driver.execute_script("//SETDESC:Assert add record button present")
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"2\"]").click()
    WebDriverWait(self.driver, 5).until(expected_conditions.invisibility_of_element_located((By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]")))
    self.driver.execute_script("//SAVEDESC:Assert add record button not present on record status dashboard page")
    self.driver.execute_script("$('#south').remove();$('button.btn-rcgreen[onclick*=\"record_home.php\"]').trigger('click')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.ID, "record_display_name")) == 0
    self.driver.execute_script("//SAVEDESC:Assert cannot bypass hidden button and create record")
    self.driver.execute_script("$.post('../ProjectGeneral/set_ui_state.php?'+window.location.search.replace(/.*(pid=[0-9]+).*/,'$1'),'object=aer_prefs&name=arm_last&state=1&global=0&redcap_csrf_token='+$('[name=\"redcap_csrf_token\"]').val())")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_home.php\"]:not([href*=\"logout=1\"])").click()
    self.driver.execute_script("$('#south').remove()")
    if self.driver.execute_script("return ($('#select2-arm_name-container').length > 0)"):
      self.driver.find_element(By.XPATH, "//*[contains(@class,'select2-container')][descendant::*[@id='select2-arm_name-container']]").click()
      self.driver.find_element(By.XPATH, "//ul[@id='select2-arm_name-results']/li[2]").click()
    else:
      self.driver.find_element(By.ID, "arm_name").find_element(By.CSS_SELECTOR, "*[value='2']").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    WebDriverWait(self.driver, 5).until(expected_conditions.invisibility_of_element_located((By.CSS_SELECTOR, "#center button.btn-rcgreen")))
    self.driver.execute_script("//SAVEDESC:Assert add record button not present on add/edit records page")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-3").click()
    self.vars["empty"] = self.driver.execute_script("return ''")
    self.driver.find_element(By.CSS_SELECTOR, "[aria-labelledby=\"ui-id-3\"] [name=\"scheme-allow-new-logic[]\"]").send_keys(self.vars["empty"])
    self.driver.execute_script("$('#south').remove()")
    time.sleep(0.5)
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
