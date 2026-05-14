# Generated from Selenium IDE
# Test name: init
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_init:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_init(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "New Project").click()
    self.driver.find_element(By.ID, "app_title").send_keys("Custom Record Naming Test")
    self.driver.find_element(By.ID, "purpose").find_element(By.XPATH, "(descendant::option)[. = 'Practice / Just for fun']").click()
    self.driver.find_element(By.ID, "project_template_radio1").click()
    self.driver.find_element(By.XPATH, "//*[@id='table-template_projects_list']//tr[contains(.,'Longitudinal Database (2 arms)')]//input").click()
    self.driver.find_element(By.CSS_SELECTOR, ".btn-primaryrc").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"ExternalModules/manager/project.php\"]").click()
    self.driver.find_element(By.ID, "external-modules-enable-modules-button").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "tr[data-module=\"custom_record_naming\"] .enable-button")))
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "tr[data-module=\"custom_record_naming\"] .enable-button").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"route=DataAccessGroupsController\"]").click()
    self.driver.find_element(By.ID, "new_group").send_keys("01 DAG1")
    self.driver.find_element(By.ID, "new_group_button").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//table[@id='table-dags_table'][contains(.,'01 DAG1')]")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"UserRights/index.php\"]").click()
    self.driver.find_element(By.ID, "new_username").send_keys("user1")
    self.driver.find_element(By.ID, "addUserBtn").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "[aria-describedby=\"editUserPopup\"]")))
    None if (element := self.driver.find_element(By.NAME, "record_create")).is_selected() else element.click()
    self.driver.find_element(By.ID, "group_role").find_element(By.XPATH, "(descendant::option)[2]").click()
    None if not (element := self.driver.find_element(By.CSS_SELECTOR, "#editUserPopup [name=\"notify_email\"]")).is_selected() else element.click()
    self.driver.find_element(By.XPATH, "//div[@aria-describedby='editUserPopup']//button[contains(@style,'bold')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//table[@id='table-user_rights_roles_table']//tr[contains(.,'user1')]")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"ProjectSetup/index.php\"]").click()
    self.driver.find_element(By.ID, "setupEnableSurveysBtn").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.XPATH, "//*[@id='setupEnableSurveysBtn'][contains(.,'Disable')]")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"Design/online_designer.php\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "button[onclick*=\"Surveys/create_survey.php\"][onclick*=\"page=demographics\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "surveySettingsSubmit").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
