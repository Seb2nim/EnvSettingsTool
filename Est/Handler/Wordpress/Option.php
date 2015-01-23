<?php

/**
 * Parameters
 *
 * - scope
 * - scopeId
 * - path
 */
class Est_Handler_Wordpress_Option extends Est_Handler_Wordpress_AbstractDatabase
{
    /**
     * Protected method that actually applies the settings. This method is implemented in the inheriting classes and
     * called from ->apply
     *
     * @throws Exception
     * @return bool
     */
    protected function _apply()
    {
        $this->_checkIfTableExists('options');

        $option_name    = $this->param1;

        $sqlParameters       = $this->_getSqlParameters($option_name);
        $containsPlaceholder = $this->_containsPlaceholder($sqlParameters);
        $action              = self::ACTION_NO_ACTION;

        if (strtolower(trim($this->value)) == '--delete--') {
            $action = self::ACTION_DELETE;
        } else {
            $query = 'SELECT `option_value` FROM `' . $this->_tablePrefix . 'options` WHERE `option_name` LIKE :option_name';
            $firstRow = $this->_getFirstRow($query, $sqlParameters);

            if ($containsPlaceholder) {
                // scope, scope_id or path contains '%' char - we can't build an insert query, only update is possible
                if ($firstRow === false) {
                    $this->addMessage(
                        new Est_Message('Trying to update using placeholders but no rows found in the db', Est_Message::SKIPPED)
                    );
                } else {
                    $action = self::ACTION_UPDATE;
                }
            } else {
                if ($firstRow === false) {
                     $action = self::ACTION_INSERT;
                } elseif ($firstRow['option_value'] == $this->value) {
                    $this->addMessage(
                        new Est_Message(sprintf('Value "%s" is already in place. Skipping.', $firstRow['option_value']), Est_Message::SKIPPED)
                    );
                } else {
                     $action = self::ACTION_UPDATE;
                }
            }
        }

        switch ($action) {
            case self::ACTION_DELETE:
                $query = 'DELETE FROM `' . $this->_tablePrefix . 'options` WHERE `option_name` LIKE :option_name';
                $this->_processDelete($query, $sqlParameters);
                break;
            case self::ACTION_INSERT:
                $sqlParameters[':option_value'] = $this->value;
                $query = 'INSERT INTO `' . $this->_tablePrefix . 'options` (`option_name`, `option_value`) VALUES (:option_name, :option_value)';
                $this->_processInsert($query, $sqlParameters);
                break;
            case self::ACTION_UPDATE:
                $sqlParameters[':option_value'] = $this->value;
                $query = 'UPDATE `' . $this->_tablePrefix . 'options` SET `option_value` = :option_value WHERE `option_name` LIKE :option_name';
                $this->_processUpdate($query, $sqlParameters, $firstRow['option_value']);
                break;
            case self::ACTION_NO_ACTION;
            default:
                break;
        }

        $this->destroyDb();

        return true;
    }

    /**
     * Protected method that actually extracts the settings. This method is implemented in the inheriting classes and
     * called from ->extract and only echos constructed csv
     */
    protected function _extract()
    {
        $this->_checkIfTableExists('options');

        $scope   = $this->param1;
        $scopeId = $this->param2;
        $path    = $this->param3;

        $sqlParameters = $this->_getSqlParameters($scope, $scopeId, $path);

        $query = 'SELECT option_name, option_value FROM `' . $this->_tablePrefix
                 . 'options` WHERE `option_name` LIKE :option_name';

        return $this->_outputQuery($query, $sqlParameters);
    }

    /**
     * Constructs the sql parameters
     *
     * @param string $scope
     * @param string $scopeId
     * @param string $path
     * @return array
     * @throws Exception
     */
    protected function _getSqlParameters($option_name)
    {
        if (empty($option_name)) {
            throw new Exception("No option_name found");
        }

        return array(
            ':option_name'   => $option_name
        );
    }
}
