<?php

namespace Sunnysideup\GridFieldSendToBottomAction\Forms\GridField;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extensible;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class GridFIeldSendToBottomAction implements GridField_ColumnProvider, GridField_ActionProvider
{
    use Extensible;

    protected $sortColumn;

    protected $update_versioned_stage = null;

    /**
     * @param string $sortColumn Column that should be used to update the sort information
     * @param string $updateVersionStage Name of the versioned stage to update this disabled by default unless this is set
     */
    public function __construct($sortColumn, $updateVersionStage = null)
    {
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
        if (! in_array('Actions', $columns, true)) {
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
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'col-buttons'];
    }

    /**
     * Add the title
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }
    }

    /**
     * Which columns are handled by this component
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    /**
     * Which GridField actions are this component handling
     *
     * @param GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return ['sendrecordtobottom'];
    }

    /**
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
                'SendRecordToBottom' . $record->ID,
                false,
                'sendrecordtobottom',
                [
                    'RecordID' => $record->ID,
                ]
            )
                ->setAttribute('title', 'Send To Bottom of List')
                ->setDescription('Send To Bottom')
                ->addExtraClass('icon font-icon-down-circled');

            return $field->Field();
        }

        return null;
    }

    /**
     * Handle the actions and apply any changes to the GridField
     *
     * @param string $actionName
     * @param mixed $arguments
     * @param array $data - form data
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'sendrecordtobottom') {
            $item = $gridField->getList()->byID($arguments['RecordID']);
            if (! $item) {
                return;
            }

            $recordID = $item->ID;
            $sortColumn = $this->sortColumn;
            $versionedStage = $this->update_versioned_stage;
            $tableClass = false;

            $className = $gridField->getModelClass();

            $db = Config::inst()->get($className, 'db', Config::UNINHERITED);

            if (! empty($db) && array_key_exists($sortColumn, $db)) {
                $tableClass = $className;
            } else {
                $classes = ClassInfo::ancestry($className, true);
                foreach ($classes as $class) {
                    $db = Config::inst()->get($class, 'db', Config::UNINHERITED);
                    if (! empty($db) && array_key_exists($sortColumn, $db)) {
                        $tableClass = $class;
                        break;
                    }
                }
            }

            if ($tableClass === false) {
                user_error('Sort column ' . $this->sortColumn . ' could not be found in ' . $gridField->getModelClass() . '\'s ancestry', E_USER_ERROR);
                exit;
            }

            $baseDataClass = ClassInfo::baseDataClass($className);

            $this->sendToBottomOfList($tableClass, $sortColumn, $recordID, $versionedStage, $baseDataClass);
        }
    }

    /**
     * Updates a record in the database with a new value in the sort column
     *
     * @param string $sortColumn
     * @param int $tableClass recordID
     * @param string $versionedStage
     * @param string $baseDataClass
     */
    public function sendToBottomOfList($tableClass, $sortColumn, $recordID, $versionedStage = '', $baseDataClass = '')
    {
        $baseDataTable = '';
        $table = DataObject::getSchema()->tableName($tableClass);

        if (! $baseDataClass) {
            $baseDataTable = $table;
        } else {
            $baseDataTable = DataObject::getSchema()->tableName($baseDataClass);
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
            WHERE "ID" = ' . $recordID);
        //LastEdited
        DB::query('
            UPDATE "' . $baseDataTable . '"
            SET "LastEdited" = \'' . date('Y-m-d H:i:s') . '\'' . '
            WHERE "ID" = ' . $recordID);
    }
}
