# Generated from Selenium IDE
# Test name: t08 - Check digits mod97
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_08_Check_digits_mod97:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_08_Check_digits_mod97(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.execute_script("$('.ui-sortable-handle[data-value] input:checked').prop('checked',false);$('[name=\"scheme-name-separator[]\"]').val('')")
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"U\"] input")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"C\"] input")).is_selected() else element.click()
    self.driver.find_element(By.NAME, "scheme-name-prefix[]").send_keys("1")
    self.driver.find_element(By.NAME, "scheme-name-suffix[]").send_keys("1")
    self.driver.find_element(By.NAME, "scheme-prompt-user-supplied[]").send_keys("Enter numeric value")
    self.driver.find_element(By.NAME, "scheme-user-supplied-format[]").send_keys("^[0-9]*$")
    self.driver.find_element(By.NAME, "scheme-check-digit-algorithm[]").find_element(By.CSS_SELECTOR, "*[value='mod97']").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.vars["listNames"] = self.driver.execute_script("return [['123','1123641'],['000','1000681'],['9876543','19876543531']]")
    for self.vars["infoName"] in self.vars["listNames"]:
      self.vars["enteredName"] = self.driver.execute_script("return arguments[0][0]", self.vars["infoName"])
      self.vars["resultName"] = self.driver.execute_script("return arguments[0][1]", self.vars["infoName"])
      self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
      self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
      assert len(self.driver.find_elements(By.XPATH, "//div[@role='dialog']//p[contains(.,'Enter numeric value')]")) > 0
      self.driver.find_element(By.XPATH, "//div[@role='dialog']//input[@type='text']").send_keys(self.vars["enteredName"])
      self.driver.find_element(By.XPATH, "//div[@role='dialog']//div[contains(@class,'ui-dialog-buttonset')]//button").click()
      assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == self.vars["resultName"]
      self.driver.execute_script("//SETDESC:Assert correct check digits")
      self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"U\"] input")).is_selected() else element.click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"C\"] input")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
