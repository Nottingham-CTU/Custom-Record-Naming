# Generated from Selenium IDE
# Test name: t03 - Record numbers assigned correctly
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_03_Record_numbers_assigned_correctly:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_03_Record_numbers_assigned_correctly(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    self.driver.find_element(By.LINK_TEXT, "Custom Record Naming Test").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.execute_script("$('.ui-sortable-handle[data-value] input:checked').prop('checked',false);$('[name=\"scheme-name-separator[]\"]').val('')")
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"R\"] input")).is_selected() else element.click()
    self.driver.find_element(By.NAME, "scheme-name-prefix[]").send_keys("p")
    self.driver.find_element(By.NAME, "scheme-name-suffix[]").send_keys("s")
    self.driver.find_element(By.NAME, "scheme-number-start[]").send_keys("")
    self.driver.find_element(By.NAME, "scheme-number-pad[]").find_element(By.XPATH, "(descendant::option)[1]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p1s"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.find_element(By.NAME, "scheme-number-start[]").send_keys("5")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p5s"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.find_element(By.NAME, "scheme-number-pad[]").find_element(By.CSS_SELECTOR, "*[value='3']").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p005s"
    self.driver.find_element(By.CSS_SELECTOR, "#event_grid_table a[href*=\"page=demographics\"]").click()
    self.driver.find_element(By.ID, "submit-btn-saverecord").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.find_element(By.NAME, "scheme-number-start[]").send_keys("")
    self.driver.find_element(By.NAME, "scheme-number-pad[]").find_element(By.CSS_SELECTOR, "*[value='']").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[data-field=\"scheme-numbering[]\"] [data-value=\"A\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p1s"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.find_element(By.NAME, "scheme-number-start[]").send_keys("")
    self.driver.find_element(By.NAME, "scheme-number-pad[]").find_element(By.CSS_SELECTOR, "*[value='']").click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, "[data-field=\"scheme-numbering[]\"] [data-value=\"A\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"T\"] input")).is_selected() else element.click()
    self.driver.find_element(By.NAME, "scheme-timestamp-format[]").send_keys("\\a")
    self.driver.find_element(By.NAME, "scheme-timestamp-tz[]").find_element(By.CSS_SELECTOR, "*[value='U']").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p6as"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[data-field=\"scheme-numbering[]\"] [data-value=\"T\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p1as"
    self.driver.find_element(By.CSS_SELECTOR, "#event_grid_table a[href*=\"page=demographics\"]").click()
    self.driver.find_element(By.ID, "submit-btn-saverecord").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p2as"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    self.driver.find_element(By.NAME, "scheme-timestamp-format[]").send_keys("\\b")
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p1bs"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, "[data-field=\"scheme-numbering[]\"] [data-value=\"T\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"DataEntry/record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button.btn-rcgreen[onclick*=\"record_home.php\"]").click()
    self.driver.execute_script("//SETDESC:Verify record name")
    self.driver.find_element(By.ID, "record_display_name").send_keys("SAVESCREENSHOT")
    assert self.driver.find_element(By.CSS_SELECTOR, "#record-home-link b").text == "p6bs"
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=custom_record_naming\"]").click()
    self.driver.find_element(By.ID, "ui-id-2").click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, ".ui-sortable-handle[data-value=\"T\"] input")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "p > button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"ProjectSetup/index.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"ProjectSetup/other_functionality.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "button[onclick*=\"eraseAllData\"]").click()
    self.driver.find_element(By.XPATH, "//div[@aria-describedby='erase_dialog']//button[contains(.,'Erase all data')]").click()
