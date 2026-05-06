# Generated from Selenium IDE
# Test name: t13 - Arm access logic
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_13_Arm_access_logic:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_13_Arm_access_logic(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-3").click()
    self.driver.find_element(By.CSS_SELECTOR, "[aria-labelledby=\"ui-id-3\"] [name=\"scheme-allow-access-logic[]\"]").send_keys("1=0")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"1\"]").click()
    time.sleep(2)
    self.vars["shownArm1"] = self.driver.execute_script("return window.location.search.indexOf('arm=1') != -1 ? '1' : '0'")
    self.driver.execute_script("//SETDESC:Assert arm 1 shown")
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"1\"]").send_keys("SAVESCREENSHOT")
    assert(self.vars["shownArm1"] == "1")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"2\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.vars["blockedArm2"] = self.driver.execute_script("return window.location.search.indexOf('arm=2') == -1 ? '1' : '0'")
    self.driver.execute_script("//SETDESC:Assert arm 2 not loaded")
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"updateParameterInURL\"][onclick*=\"1\"]").send_keys("SAVESCREENSHOT")
    assert(self.vars["blockedArm2"] == "1")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_home.php\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "arm_name").find_element(By.CSS_SELECTOR, "*[value='2']").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.vars["blockedArm2"] = self.driver.execute_script("return window.location.search.indexOf('arm=2') == -1 ? '1' : '0'")
    self.driver.execute_script("//SETDESC:Assert arm 2 not loaded")
    self.driver.find_element(By.ID, "arm_name").send_keys("SAVESCREENSHOT")
    assert(self.vars["blockedArm2"] == "1")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-3").click()
    self.vars["empty"] = self.driver.execute_script("return ''")
    self.driver.find_element(By.CSS_SELECTOR, "[aria-labelledby=\"ui-id-3\"] [name=\"scheme-allow-access-logic[]\"]").send_keys(self.vars["empty"])
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
