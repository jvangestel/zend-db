<?php

namespace Zend\Db\Adapter\Driver\Mysqli;

use Zend\Db\Adapter\Driver\StatementInterface,
    Zend\Db\Adapter\Exception,
    Zend\Db\Adapter\ParameterContainer,
    Zend\Db\Adapter\ParameterContainerInterface;


class Statement implements StatementInterface
{

    /**
     * @var \mysqli
     */
    protected $mysqli = null;

    /**
     * @var Mysqli
     */
    protected $driver = null;

    /**
     * @var string
     */
    protected $sql = '';

    protected $parameterContainer = null;
    
    /**
     * @var \mysqli_stmt
     */
    protected $resource = null;

    protected $isPrepared = false;

    public function setDriver(Mysqli $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    public function initialize(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        return $this;
    }
    
    public function setSql($sql)
    {
        $this->sql = $sql;
        return $this;
    }
    
    public function setParameterContainer(ParameterContainerInterface $parameterContainer)
    {
        $this->parameterContainer = $parameterContainer;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setResource(\mysqli_stmt $mysqliStatement)
    {
        $this->resource = $mysqliStatement;
        $this->isPrepared = true;
        return $this;
    }

    public function getSQL()
    {
        return $this->sql;
    }

    /**
     * @return ParameterContainer
     */
    public function getParameterContainer()
    {
        return $this->parameterContainer;
    }

    /**
     * @return bool
     */
    public function isPrepared()
    {
        return $this->isPrepared;
    }

    /**
     * @param string $sql
     */
    public function prepare($sql = null)
    {
        if ($this->isPrepared) {
            throw new \Exception('This statement has already been prepared');
        }

        $sql = ($sql) ?: $this->sql;

        $this->resource = $this->mysqli->prepare($this->sql);
        if (!$this->resource instanceof \mysqli_stmt) {
            throw new Exception\InvalidQueryException(
                'Statement couldn\'t be produced with sql: "' . $sql . '"',
                null,
                new ErrorException($this->mysqli->error, $this->mysqli->errno)
            );
        }

        $this->isPrepared = true;
    }

    public function execute($parameters = null)
    {
        if (!$this->isPrepared) {
            $this->prepare();
        }

        $parameters = ($parameters) ?: $this->parameterContainer;

        if ($parameters != null) {
            if (is_array($parameters)) {
                $parameters = new ParameterContainer($parameters);
            }
            if (!$parameters instanceof ParameterContainerInterface) {
                throw new \InvalidArgumentException('ParameterContainer expected');
            }
            $this->bindParametersFromContainer($parameters);
        }
            
        if ($this->resource->execute() === false) {
            throw new \RuntimeException($this->resource->error);
        }

        $result = $this->driver->createResult($this->resource);
        return $result;
    }
    
    protected function bindParametersFromContainer(ParameterContainerInterface $pContainer)
    {
        $parameters = $pContainer->toArray();
        $type = '';
        $args = array();

        foreach ($parameters as $position => &$value) {
            switch ($pContainer->offsetGetErrata($position)) {
                case ParameterContainerInterface::TYPE_DOUBLE:
                    $type .= 'd';
                    break;
                case ParameterContainerInterface::TYPE_NULL:
                    $value = null; // as per @see http://www.php.net/manual/en/mysqli-stmt.bind-param.php#96148
                case ParameterContainerInterface::TYPE_INTEGER:
                    $type .= 'i';
                    break;
                case ParameterContainerInterface::TYPE_STRING:
                default:
                    $type .= 's';
                    break;
            }
            $args[] = &$value;
        }

        if ($args) {
            array_unshift($args, $type);
            call_user_func_array(array($this->resource, 'bind_param'), $args);
        }
    }

}