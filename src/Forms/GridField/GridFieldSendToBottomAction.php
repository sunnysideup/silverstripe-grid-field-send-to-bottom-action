<?php

namespace Sunnysideup\GridFieldSendToBottomAction\Forms\GridField;

use GridField_ColumnProvider;
use GridField_ActionProvider;
use GridField_FormAction;
use GridField;
use Config;
use CONFIG;
use ClassInfo;
use DB;
use SS_Object;


class GridFieldSendToBottomAction implements GridField_ColumnProvider, GridField_ActionProvider
{

    protected $sortColumn;
	protected $update_versioned_stage=null;

	/**
	 * @param string $sortColumn Column that should be used to update the sort information
	 * @param string $updateVersionStage Name of the versioned stage to update this disabled by default unless this is set
	 */
	public function __construct($sortColumn, $updateVersionStage = null) {
		$this->sortColumn = $sortColumn;
		$this->update_versioned_stage = $updateVersionStage;
    }
    
    /**
	 * Add the 'Actions' column if it doesn't already exist
	 *
	 * @param GridField $gridField
	 * @param array $columns
	 */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }

        return $columns;
    }
    
    /**
	 * Return any special attributes that will be used for FormField::create_tag()
	 *
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 * @return array
	 */
	public function getColumnAttributes($gridField, $record, $columnName) {
        return array('class' => 'col-buttons');
    }
    
    /**
	 * Add the title
	 *
	 * @param GridField $gridField
	 * @param string $columnName
	 * @return array
	 */
	public function getColumnMetadata($gridField, $columnName) {
		if($columnName == 'Actions') {
			return array('title' => '');
		}
	}

	/**
	 * Which columns are handled by this component
	 *
	 * @param GridField $gridField
	 * @return array
	 */
	public function getColumnsHandled($gridField) {
		return array('Actions');
	}
    
    /**
	 * Which GridField actions are this component handling
	 *
	 * @param GridField $gridField
	 * @return array
	 */
	public function getActions($gridField) {
        return array('sendrecordtobottom');
    }
    
    /**
	 *
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 * @return string - the HTML for the column
	 */
    public function getColumnContent($gridField, $record, $columnName)
    {
        if ($columnName === 'Actions') {
            $field = GridField_FormAction::create(
                $gridField,  
                'SendRecordToBottom'.$record->ID, false, "sendrecordtobottom",
                [
                    'RecordID' => $record->ID
                ]
            )
            ->setAttribute('title', 'Send To Bottom of List')
            ->setDescription('Send To Bottom')
            ->setAttribute('data-icon', 'chain--arrow');
                
            return $field->Field();
        }

        return null;
    }

    /**
	 * Handle the actions and apply any changes to the GridField
	 *
	 * @param GridField $gridField
	 * @param string $actionName
	 * @param mixed $arguments
	 * @param array $data - form data
	 * @return void
	 */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'sendrecordtobottom') {
            $item = $gridField->getList()->byID($arguments['RecordID']);
			if(!$item) {
				return;
            }

            $recordID = $item->ID;
            $sortColumn = $this->sortColumn;
            $versionedStage = $this->update_versioned_stage;
            $table = false;

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            $className = $gridField->getModelClass();
            

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            $db = Config::inst()->get($className, "db", CONFIG::UNINHERITED);
            if(!empty($db) && array_key_exists($sortColumn, $db)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $table = $className;
            }else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $classes = ClassInfo::ancestry($className, true);
                foreach($classes as $class) {
                    $db = Config::inst()->get($class, "db", CONFIG::UNINHERITED);
                    if(!empty($db) && array_key_exists($sortColumn, $db)) {
                        $table = $class;
                        break;
                    }
                }
            }
            
            if($table === false) {
                user_error('Sort column '.$this->sortColumn.' could not be found in '.$gridField->getModelClass().'\'s ancestry', E_USER_ERROR);
                exit;
            }


/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            $baseDataClass = ClassInfo::baseDataClass($className);

            self::sendToBottomOfList($table, $sortColumn, $recordID, $versionedStage, $baseDataClass);
            
        }
    }

     /**
	 * Updates a record in the database with a new value in the sort column  
	 *
	 * @param string $table
	 * @param string $sortColumn
	 * @param int recordID
	 * @param string $versionedStage
     * @param string $baseDataClass
	 * @return void
	 */
    public static function sendToBottomOfList($table, $sortColumn, $recordID, $versionedStage = '', $baseDataClass = '')
    {
        if(!$baseDataClass){
            $baseDataClass = $table;
        }

        $highestSortValue = DB::query('
            SELECT "' . $sortColumn . '"
            FROM "' . $table . '" 
            ORDER BY "' . $table . '"."' . $sortColumn . '" DESC 
            LIMIT 1
        ')->value();

        $newSortValue = intval($highestSortValue) + 1;

        DB::query('
            UPDATE "' . $table . '" 
            SET "' . $sortColumn . '" = ' . $newSortValue . ' 
            WHERE "ID" = '. $recordID
        );
        //LastEdited
        DB::query('
            UPDATE "' . $baseDataClass . '" 
            SET "LastEdited" = \'' . date('Y-m-d H:i:s') . '\'' . ' 
            WHERE "ID" = '. $recordID
        );
        

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD:  Object:: (case sensitive)
  * NEW:  SilverStripe\\Core\\Injector\\Injector::inst()-> (COMPLEX)
  * EXP: Check if this is the right implementation, this is highly speculative.
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
        if($versionedStage && class_exists($table) && SilverStripe\Core\Injector\Injector::inst()->has_extension($table, 'Versioned')) {
            DB::query('
                UPDATE "' . $table . '_' . $versionedStage . '" 
                SET "' . $sortColumn . '" = ' . $newSortValue . ' 
                WHERE "ID" = '. $recordID
            );
            

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD: (Object:: (ignore case)
  * NEW: (SS_Object:: (COMPLEX)
  * EXP: Check usage for Object (PHP) vs SS_Object (Silverstripe)
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            if(SS_Object::has_extension($baseDataClass, 'Versioned')) {
                DB::query('
                    UPDATE "' . $baseDataClass . '_' . $versionedStage . '" 
                    SET "LastEdited" = \'' . date('Y-m-d H:i:s') . '\'' . ' 
                    WHERE "ID" = '. $recordID
                );
            }
        }
    }
}

