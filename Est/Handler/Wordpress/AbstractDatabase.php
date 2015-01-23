<?php

/**
 * Abstract magento database handler class
 *
 * @author Dmytro Zavalkin <dmytro.zavalkin@aoe.com>
 */
abstract class Est_Handler_Wordpress_AbstractDatabase extends Est_Handler_AbstractDatabase
{
    /**@+
     * Actions to apply on row
     *
     * @var string
     */
    const ACTION_NO_ACTION = 0;
    const ACTION_INSERT = 1;
    const ACTION_UPDATE = 2;
    const ACTION_DELETE = 3;
    /**@-*/

    /**
     * Table prefix
     *
     * @var string
     */
    protected $_tablePrefix = '';

    /**
     * Read database connection parameters from local.xml file
     *
     * @return array
     * @throws Exception
     */
    protected function _getDatabaseConnectionParameters()
    {
        $localXmlFile = 'app/etc/local.xml';

        if (!is_file($localXmlFile)) {
            throw new Exception(sprintf('File "%s" not found', $localXmlFile));
        }

        $config = simplexml_load_file($localXmlFile);
        if ($config === false) {
            throw new Exception(sprintf('Could not load xml file "%s"', $localXmlFile));
        }

        $this->_tablePrefix = (string) $config->global->resources->wordpress->connection->table_prefix;

        return array(
            'host'     => (string) $config->global->resources->wordpress->connection->host,
            'database' => (string) $config->global->resources->wordpress->connection->dbname,
            'username' => (string) $config->global->resources->wordpress->connection->username,
            'password' => (string) $config->global->resources->wordpress->connection->password
        );
    }

    /**
     * Check if at least one of the paramters contains a wildcard
     *
     * @param array $parameters
     * @return bool
     */
    protected function _containsPlaceholder(array $parameters)
    {
        foreach ($parameters as $value) {
            if (strpos($value, '%') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $table
     * @throws Exception
     */
    protected function _checkIfTableExists($table)
    {
        $result = $this->getDbConnection()
                       ->query("SHOW TABLES LIKE \"{$this->_tablePrefix}{$table}\"");
        if ($result->rowCount() == 0) {
            throw new Exception("Table \"{$this->_tablePrefix}{$table}\" doesn't exist");
        }
    }

    /**
     * Output constructed csv
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws Exception
     * @return string
     */
    protected function _outputQuery($query, array $sqlParameters)
    {
        $statement = $this->getDbConnection()->prepare($query);
        $statement->execute($sqlParameters);
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        $rows = $statement->fetchAll();

        $buffer = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            array_unshift($row, get_class($this));
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $output = stream_get_contents($buffer);
        fclose($buffer);

        return $output;
    }

    /**
     * Get first row query
     *
     * @param string $query
     * @param array $sqlParameters
     * @return mixed
     */
    protected function _getFirstRow($query, array $sqlParameters)
    {
        $statement = $this->getDbConnection()->prepare($query);
        $statement->execute($sqlParameters);
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        return $statement->fetch();
    }

    /**
     * Process delete query
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws Exception
     */
    protected function _processDelete($query, array $sqlParameters)
    {
        $pdoStatement = $this->getDbConnection()->prepare($query);
        $result       = $pdoStatement->execute($sqlParameters);

        if ($result === false) {
            throw new Exception('Error while deleting rows');
        }

        $rowCount = $pdoStatement->rowCount();
        if ($rowCount > 0) {
            $this->addMessage(new Est_Message(sprintf('Deleted "%s" row(s)', $rowCount)));
        } else {
            $this->addMessage(new Est_Message('No rows deleted.', Est_Message::SKIPPED));
        }
    }

    /**
     * Process insert query
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws Exception
     */
    protected function _processInsert($query, array $sqlParameters)
    {
        $result = $this->getDbConnection()
            ->prepare($query)
            ->execute($sqlParameters);

        if ($result === false) {
            // TODO: include speaking error message
            throw new Exception('Error while updating value');
        }

        $this->addMessage(new Est_Message(sprintf('Inserted new value "%s"', $this->value)));
    }

    /**
     * Process update query
     *
     * @param string $query
     * @param array $sqlParameters
     * @param string $oldValue
     * @throws Exception
     */
    protected function _processUpdate($query, array $sqlParameters, $oldValue)
    {
        $result = $this->getDbConnection()
            ->prepare($query)
            ->execute($sqlParameters);

        if ($result === false) {
            // TODO: include speaking error message
            throw new Exception('Error while updating value');
        }

        $this->addMessage(new Est_Message(sprintf('Updated value from "%s" to "%s"',
            $oldValue,
            $this->value))
        );
    }
}
