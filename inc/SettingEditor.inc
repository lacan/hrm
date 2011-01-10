<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Setting.inc");
require_once("Database.inc");
require_once("User.inc");

/*!
	\class    BaseSettingEditor
	\brief    Abstract class for a SettingEditor
*/
abstract class BaseSettingEditor {
	
	/*!
		\var	$user
		\brief	Current User
	*/
    protected $user;

	/*!
		\var	$message
		\brief	Error message from last operation
	*/
    protected $message;

	/*!
		\var	$selected
		\brief	Name of the currently selected Setting
	*/
    protected $selected;

    /*!
		\brief	Protected constructor: creates a new SettingEditor and
				selects the default Setting if a default 
				Setting exists.
		\todo	Check: how can a SettingEditor have a list of Setting even before creation?
		\param	$user	Current User
	*/
    protected function __construct( User $user) { 
        $this->user = $user;
        $this->message = '';
        $this->selected = NULL;
        foreach ($this->settings() as $setting) {
            if ($setting->isDefault()) { 
                $this->selected = $setting->name();
              }
        } 
    }

    /*!
		\brief	Abstract function: creates and returns a new Setting
		
		This must be reimplemented.
		
		\return	a new Setting
	*/
    abstract public function newSettingObject();

  /*!
	\brief	Returns the name of the database table in which the
			ParameterSetting's are stored
			
	This must be reimplemented.
	
	\todo	Check if this is used!
	\return	table name
  */
  abstract function table();
	
    /*!
		\brief	Loads and returns all the Setting's for current user (does not
				load the Parameter values)
		\return	the array of Setting's
	*/
	public function settings() {
	  $settings = array();
	  $user = $this->user;
	  $db = new DatabaseConnection();
	  $results = $db->getSettingList( $user->name(), $this->table() );
	  foreach ($results as $row) {
		$setting = $this->newSettingObject();
		$setting->setName($row['name']);
		$setting->setOwner($user);
		if($row['standard'] == 't') {
		  $setting->beDefault();
		}
		$settings[$row['name']] = $setting;
	  }
	  return $settings;
	}
	
	/*!
	  \brief	Returns the Setting with given name
	  \param	$name	name of the ParameterSetting
	  \return	the ParameterSetting
	*/
	public function setting($name) {
	  $user = $this->user;
	  $db = new DatabaseConnection();
	  $results = $db->getSettingList( $user->name(), $this->table() );
	  foreach ($results as $row) {
	    if ($row['name'] == $name) {
	      $setting = $this->newSettingObject();
	      $setting->setName($row['name']);
	      $setting->setOwner($user);
	      $setting = $setting->load();
	      return $setting;
	    }
	  }
	  return null;
	}

  /*!
		\brief	Returns the name of the currently selected Setting
				or NULL if none is selected
		\return	the name of the selected Setting or NULL
	*/
    public function selected() {
        return $this->selected;
    } 

    /*!
		\brief	Sets the Setting with given name as selected
		\todo	Check that the parameter exists!
		\param	$name	Name of the Setting to be selected
	*/
    public function setSelected($name) {
        $this->selected = $name;
    } 

    /*!
		\brief	Returns the name of the User
		\return	the name of the User
	*/
    public function user() {
        return $this->user;
    } 

    /*!
		\brief	Create and set a new Setting with given name. 
		
		If a Setting with the same name already exists, return NULL. Otherwise,
		the new Setting is set into the Editor and also returned.
		
		\param	$name	Name of the Setting to be created
		\return	the created Setting object, or NULL if a Setting 
				with the same name already exists
	*/
    public function createNewSetting($name) {
        if (!$this->checkNewSettingName($name)) {
            return NULL;
        }
        $setting = $this->newSettingObject(); 
        $setting->setName($name);
        $setting->setOwner($this->user);
        $this->setSelected($name);
        return $setting;
    }

    /*!
		\brief	Creates a new Setting with the given new name in the 
				database and copies the Parameter values of the 
				existing Parameter to it.
		\param  $newName    The name of the new Setting
		\return	true if the copy was successful, false otherwise
	*/
    public function copySelectedSetting($newName) {
        if (!$this->checkSelectedSetting()) {
            return False;
        }
        $settings = $this->settings();
        $oldSettingName = $this->selected();
        $oldSetting = $settings[$oldSettingName];
        $oldSetting = $oldSetting->load();
        $newSetting = $this->createNewSetting($newName);
        if ($newSetting==NULL) {
            return False;
        } 
        $newSetting->copyParameterFrom($oldSetting);
        $result = $newSetting->save();
        $this->message = $newSetting->message();
        return $result;
    }

    /*!
		\brief	Creates a new Setting in the database and copies
				the values from a public Setting
		
		The new Setting will have the same name as the old Setting.
		This is because this function is used to copy a preset (public
		Setting) created by the admin into the user list of Setting's.
		
		\param	$setting	An existing Setting
		\return	true if the copy was successful, false otherwise
	*/
    public function copyPublicSetting( Setting $setting ) {
        $newSetting = $this->createNewSetting($setting->name());
        if ($newSetting == null) {
            return False;
        } 
        $newSetting->copyParameterFrom($setting);
        $result = $newSetting->save();
        $this->message = $newSetting->message();
        return $result;
      } 

    /*!
		\brief	Loads the values for the selected Setting and returns
				the Setting object
		\return	the loaded, selected Setting if successful, false otherwise
	*/
      public function loadSelectedSetting() {
        if (!$this->checkSelectedSetting()) {
          return False;
        }
        $name = $this->selected();
        $settings = $this->settings();
        $setting = $settings[$name];
        $setting = $setting->load();
        $this->setSelected($name);
        return $setting;
      }

    /*!
		\brief	Make the selected Setting the default one
		
		The selection will be stored in the database.
		
		\return	true if it worked, false otherwise
	*/
    public function makeSelectedSettingDefault() {
      if (!$this->checkSelectedSetting()) {
		$this->message = "Please select a setting in the list before pressing the button!";
		return False;
      }
      $name = $this->selected();
      foreach ($this->settings() as $setting) {
        if ($setting->isDefault()) {
          $setting->resetDefault();
          $db = new DatabaseConnection();
          $db->updateDefault($setting);
        }
        if ($setting->name() == $name) {
          $setting->beDefault();
          $db = new DatabaseConnection();
          $db->updateDefault($setting);
        }
      }
      return true;
    }

    /*!
		\brief	Delete the Setting the selected Setting the default one
		
		The selection will be stored in the database.
		
		\return	true if it worked, false otherwise
	*/
    public function deleteSelectedSetting() {
      if (!$this->checkSelectedSetting()) {
        return False;
      }
      $name = $this->selected();
      $settings = $this->settings();
      if (!isset($settings[$name])) {
        return False;
      }
      $db = new DatabaseConnection();
      if (!$db->deleteSetting($settings[$name])) {
        $this->message = "delete setting - database error";
        return False;
      }
      return True;
    } 

	/*!
	  \brief	Returns the error message that was set by last operation
		
	  The message string will be empty if the last operation was successful.
		
	  \return	error message
	*/
	public function message() {
	  return $this->message;
	} 

	/*!
	  \brief	Checks that the given name for the new Setting is not empty and
				that and there does not exist already a Setting with that name
	  \param	$name	Name for the new Setting		
	  \return	true if the name is valid, false otherwise
	*/
  public function checkNewSettingName($name) { 
    $this->message = '';
    $names = array();
    foreach ($this->settings() as $setting) {
      $names[] = $setting->name(); 
    } 
    if (trim($name)=='') {
      $this->message = "Please enter a name for the setting and try again!";      
      return False;
    } 
    if (in_array($name, $names)) { 
      $this->message = "A setting with the name $name already exists. Please enter another name!";
      return False;
    } 
    return True;
  }
  
  /*!
	\brief	Returns the name of the default Setting
	\todo	This function does not seem to be used anymore
	\return	name of the default Setting
  */
  function defaultSettingName() {
    foreach ($this->settings() as $setting) {
      if ($setting->isDefault()) {
	return $setting->name();
      }
    }
    return NULL;
  }

	/*!
		\brief	Checks whether a Setting is selected and whether the selection
				points to an actually existing Setting
		\return	true if an existing Setting is selected, false otherwise
	*/
    public function checkSelectedSetting() {
      $this->message = '';
      $nameOfSelectedSetting = $this->selected();
      if ($nameOfSelectedSetting=='') {
        return False;
      }
      $settings = $this->settings();
      if (!isset($settings[$nameOfSelectedSetting])) {
        return False;
      }
      return True;
    } 
} // End of SettingEditor class

/*
	============================================================================
*/

/*!
	\class	SettingEditor
	\brief	Implements an Editor for ParameterSetting
*/
class SettingEditor extends BaseSettingEditor {

  /*!
	\brief	Constructor: creates a new SettingEditor and
			selects the default Setting if a default 
			Setting exists.
	\todo	Check: how can a SettingEditor have a list of Setting even before creation?
	\param	$user	Current User
  */
  public function __construct( User $user) {
	parent::__construct( $user );
  }

  /*!
	\brief	Returns the name of the database table in which the
			ParameterSetting's are stored
	\todo	Check if this is used!
	\return	table name
  */
  function table() { 
    return "parameter_setting";
  }
  
  /*!
	\brief	Creates and returns a new ParameterSetting
	\return	a new PatameterSetting
  */
  public function newSettingObject() {
        return (new ParameterSetting());
  }

}

/*
	============================================================================
*/

/*!
	\class	TaskSettingEditor
	\brief	Implements an Editor for TaskSetting
*/
class TaskSettingEditor extends BaseSettingEditor {

  /*!
	\brief	Constructor: creates a new SettingEditor and
			selects the default Setting if a default 
			Setting exists.
	\todo	Check: how can a SettingEditor have a list of Setting even before creation?
	\param	$user	Current User
  */
  public function __construct( User $user) {
	parent::__construct( $user );
  }
  
  /*!
	\brief	Returns the name of the database table in which the
			TaskSetting's are stored
	\todo	Check if this is used!
	\return	table name
  */
  function table() { 
    return "task_setting";
  } 

  /*!
	\brief	Creates and returns a new TaskSetting
	\return	a new TaskSetting
  */
  function newSettingObject() {
    return (new TaskSetting());
  }

}