<?php

namespace EcclesiaCRM\Base;

use \DateTime;
use \Exception;
use \PDO;
use EcclesiaCRM\Deposit as ChildDeposit;
use EcclesiaCRM\DepositQuery as ChildDepositQuery;
use EcclesiaCRM\Pledge as ChildPledge;
use EcclesiaCRM\PledgeQuery as ChildPledgeQuery;
use EcclesiaCRM\Map\DepositTableMap;
use EcclesiaCRM\Map\PledgeTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;

/**
 * Base class that represents a row from the 'deposit_dep' table.
 *
 * This records deposits / payments
 *
 * @package    propel.generator.EcclesiaCRM.Base
 */
abstract class Deposit implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\EcclesiaCRM\\Map\\DepositTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the dep_id field.
     *
     * @var        int
     */
    protected $dep_id;

    /**
     * The value for the dep_date field.
     *
     * @var        DateTime
     */
    protected $dep_date;

    /**
     * The value for the dep_comment field.
     *
     * @var        string
     */
    protected $dep_comment;

    /**
     * The value for the dep_enteredby field.
     *
     * @var        int
     */
    protected $dep_enteredby;

    /**
     * The value for the dep_closed field.
     *
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $dep_closed;

    /**
     * The value for the dep_type field.
     *
     * Note: this column has a database default value of: 'Bank'
     * @var        string
     */
    protected $dep_type;

    /**
     * @var        ObjectCollection|ChildPledge[] Collection to store aggregation of ChildPledge objects.
     */
    protected $collPledges;
    protected $collPledgesPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|ChildPledge[]
     */
    protected $pledgesScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->dep_closed = false;
        $this->dep_type = 'Bank';
    }

    /**
     * Initializes internal state of EcclesiaCRM\Base\Deposit object.
     * @see applyDefaults()
     */
    public function __construct()
    {
        $this->applyDefaultValues();
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !!$this->modifiedColumns;
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return $this->modifiedColumns && isset($this->modifiedColumns[$col]);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return $this->modifiedColumns ? array_keys($this->modifiedColumns) : [];
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return boolean true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            if (isset($this->modifiedColumns[$col])) {
                unset($this->modifiedColumns[$col]);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>Deposit</code> instance.  If
     * <code>obj</code> is an instance of <code>Deposit</code>, delegates to
     * <code>equals(Deposit)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param  mixed   $obj The object to compare to.
     * @return boolean Whether equal to the object specified.
     */
    public function equals($obj)
    {
        if (!$obj instanceof static) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey() || null === $obj->getPrimaryKey()) {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @param  string  $name The virtual column name
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @param  string $name The virtual column name
     * @return mixed
     *
     * @throws PropelException
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return $this|Deposit The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        $cls = new \ReflectionClass($this);
        $propertyNames = [];
        $serializableProperties = array_diff($cls->getProperties(), $cls->getProperties(\ReflectionProperty::IS_STATIC));

        foreach($serializableProperties as $property) {
            $propertyNames[] = $property->getName();
        }

        return $propertyNames;
    }

    /**
     * Get the [dep_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->dep_id;
    }

    /**
     * Get the [optionally formatted] temporal [dep_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getDate($format = NULL)
    {
        if ($format === null) {
            return $this->dep_date;
        } else {
            return $this->dep_date instanceof \DateTimeInterface ? $this->dep_date->format($format) : null;
        }
    }

    /**
     * Get the [dep_comment] column value.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->dep_comment;
    }

    /**
     * Get the [dep_enteredby] column value.
     *
     * @return int
     */
    public function getEnteredby()
    {
        return $this->dep_enteredby;
    }

    /**
     * Get the [dep_closed] column value.
     *
     * @return boolean
     */
    public function getClosed()
    {
        return $this->dep_closed;
    }

    /**
     * Get the [dep_closed] column value.
     *
     * @return boolean
     */
    public function isClosed()
    {
        return $this->getClosed();
    }

    /**
     * Get the [dep_type] column value.
     *
     * @return string
     */
    public function getType()
    {
        return $this->dep_type;
    }

    /**
     * Set the value of [dep_id] column.
     *
     * @param int $v new value
     * @return $this|\EcclesiaCRM\Deposit The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->dep_id !== $v) {
            $this->dep_id = $v;
            $this->modifiedColumns[DepositTableMap::COL_DEP_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Sets the value of [dep_date] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\EcclesiaCRM\Deposit The current object (for fluent API support)
     */
    public function setDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->dep_date !== null || $dt !== null) {
            if ($this->dep_date === null || $dt === null || $dt->format("Y-m-d") !== $this->dep_date->format("Y-m-d")) {
                $this->dep_date = $dt === null ? null : clone $dt;
                $this->modifiedColumns[DepositTableMap::COL_DEP_DATE] = true;
            }
        } // if either are not null

        return $this;
    } // setDate()

    /**
     * Set the value of [dep_comment] column.
     *
     * @param string $v new value
     * @return $this|\EcclesiaCRM\Deposit The current object (for fluent API support)
     */
    public function setComment($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->dep_comment !== $v) {
            $this->dep_comment = $v;
            $this->modifiedColumns[DepositTableMap::COL_DEP_COMMENT] = true;
        }

        return $this;
    } // setComment()

    /**
     * Set the value of [dep_enteredby] column.
     *
     * @param int $v new value
     * @return $this|\EcclesiaCRM\Deposit The current object (for fluent API support)
     */
    public function setEnteredby($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->dep_enteredby !== $v) {
            $this->dep_enteredby = $v;
            $this->modifiedColumns[DepositTableMap::COL_DEP_ENTEREDBY] = true;
        }

        return $this;
    } // setEnteredby()

    /**
     * Sets the value of the [dep_closed] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\EcclesiaCRM\Deposit The current object (for fluent API support)
     */
    public function setClosed($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->dep_closed !== $v) {
            $this->dep_closed = $v;
            $this->modifiedColumns[DepositTableMap::COL_DEP_CLOSED] = true;
        }

        return $this;
    } // setClosed()

    /**
     * Set the value of [dep_type] column.
     *
     * @param string $v new value
     * @return $this|\EcclesiaCRM\Deposit The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->dep_type !== $v) {
            $this->dep_type = $v;
            $this->modifiedColumns[DepositTableMap::COL_DEP_TYPE] = true;
        }

        return $this;
    } // setType()

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
            if ($this->dep_closed !== false) {
                return false;
            }

            if ($this->dep_type !== 'Bank') {
                return false;
            }

        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : DepositTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dep_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : DepositTableMap::translateFieldName('Date', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->dep_date = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : DepositTableMap::translateFieldName('Comment', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dep_comment = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : DepositTableMap::translateFieldName('Enteredby', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dep_enteredby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : DepositTableMap::translateFieldName('Closed', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dep_closed = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : DepositTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dep_type = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 6; // 6 = DepositTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\EcclesiaCRM\\Deposit'), 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
    } // ensureConsistency

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(DepositTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildDepositQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collPledges = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Deposit::setDeleted()
     * @see Deposit::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(DepositTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildDepositQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $this->setDeleted(true);
            }
        });
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($this->alreadyInSave) {
            return 0;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(DepositTableMap::DATABASE_NAME);
        }

        return $con->transaction(function () use ($con) {
            $ret = $this->preSave($con);
            $isInsert = $this->isNew();
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
            } else {
                $ret = $ret && $this->preUpdate($con);
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                DepositTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }

            return $affectedRows;
        });
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                    $affectedRows += 1;
                } else {
                    $affectedRows += $this->doUpdate($con);
                }
                $this->resetModified();
            }

            if ($this->pledgesScheduledForDeletion !== null) {
                if (!$this->pledgesScheduledForDeletion->isEmpty()) {
                    foreach ($this->pledgesScheduledForDeletion as $pledge) {
                        // need to save related object because we set the relation to null
                        $pledge->save($con);
                    }
                    $this->pledgesScheduledForDeletion = null;
                }
            }

            if ($this->collPledges !== null) {
                foreach ($this->collPledges as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[DepositTableMap::COL_DEP_ID] = true;
        if (null !== $this->dep_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . DepositTableMap::COL_DEP_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(DepositTableMap::COL_DEP_ID)) {
            $modifiedColumns[':p' . $index++]  = 'dep_ID';
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_DATE)) {
            $modifiedColumns[':p' . $index++]  = 'dep_Date';
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_COMMENT)) {
            $modifiedColumns[':p' . $index++]  = 'dep_Comment';
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_ENTEREDBY)) {
            $modifiedColumns[':p' . $index++]  = 'dep_EnteredBy';
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_CLOSED)) {
            $modifiedColumns[':p' . $index++]  = 'dep_Closed';
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_TYPE)) {
            $modifiedColumns[':p' . $index++]  = 'dep_Type';
        }

        $sql = sprintf(
            'INSERT INTO deposit_dep (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'dep_ID':
                        $stmt->bindValue($identifier, $this->dep_id, PDO::PARAM_INT);
                        break;
                    case 'dep_Date':
                        $stmt->bindValue($identifier, $this->dep_date ? $this->dep_date->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'dep_Comment':
                        $stmt->bindValue($identifier, $this->dep_comment, PDO::PARAM_STR);
                        break;
                    case 'dep_EnteredBy':
                        $stmt->bindValue($identifier, $this->dep_enteredby, PDO::PARAM_INT);
                        break;
                    case 'dep_Closed':
                        $stmt->bindValue($identifier, (int) $this->dep_closed, PDO::PARAM_INT);
                        break;
                    case 'dep_Type':
                        $stmt->bindValue($identifier, $this->dep_type, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = DepositTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getId();
                break;
            case 1:
                return $this->getDate();
                break;
            case 2:
                return $this->getComment();
                break;
            case 3:
                return $this->getEnteredby();
                break;
            case 4:
                return $this->getClosed();
                break;
            case 5:
                return $this->getType();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {

        if (isset($alreadyDumpedObjects['Deposit'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Deposit'][$this->hashCode()] = true;
        $keys = DepositTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getDate(),
            $keys[2] => $this->getComment(),
            $keys[3] => $this->getEnteredby(),
            $keys[4] => $this->getClosed(),
            $keys[5] => $this->getType(),
        );
        if ($result[$keys[1]] instanceof \DateTimeInterface) {
            $result[$keys[1]] = $result[$keys[1]]->format('c');
        }

        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collPledges) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'pledges';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'pledge_plgs';
                        break;
                    default:
                        $key = 'Pledges';
                }

                $result[$key] = $this->collPledges->toArray(null, false, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
        }

        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param  string $name
     * @param  mixed  $value field value
     * @param  string $type The type of fieldname the $name is of:
     *                one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                Defaults to TableMap::TYPE_PHPNAME.
     * @return $this|\EcclesiaCRM\Deposit
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = DepositTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\EcclesiaCRM\Deposit
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setDate($value);
                break;
            case 2:
                $this->setComment($value);
                break;
            case 3:
                $this->setEnteredby($value);
                break;
            case 4:
                $this->setClosed($value);
                break;
            case 5:
                $this->setType($value);
                break;
        } // switch()

        return $this;
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = DepositTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setDate($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setComment($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setEnteredby($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setClosed($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setType($arr[$keys[5]]);
        }
    }

     /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     * @param string $keyType The type of keys the array uses.
     *
     * @return $this|\EcclesiaCRM\Deposit The current object, for fluid interface
     */
    public function importFrom($parser, $data, $keyType = TableMap::TYPE_PHPNAME)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        $this->fromArray($parser->toArray($data), $keyType);

        return $this;
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(DepositTableMap::DATABASE_NAME);

        if ($this->isColumnModified(DepositTableMap::COL_DEP_ID)) {
            $criteria->add(DepositTableMap::COL_DEP_ID, $this->dep_id);
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_DATE)) {
            $criteria->add(DepositTableMap::COL_DEP_DATE, $this->dep_date);
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_COMMENT)) {
            $criteria->add(DepositTableMap::COL_DEP_COMMENT, $this->dep_comment);
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_ENTEREDBY)) {
            $criteria->add(DepositTableMap::COL_DEP_ENTEREDBY, $this->dep_enteredby);
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_CLOSED)) {
            $criteria->add(DepositTableMap::COL_DEP_CLOSED, $this->dep_closed);
        }
        if ($this->isColumnModified(DepositTableMap::COL_DEP_TYPE)) {
            $criteria->add(DepositTableMap::COL_DEP_TYPE, $this->dep_type);
        }

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @throws LogicException if no primary key is defined
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = ChildDepositQuery::create();
        $criteria->add(DepositTableMap::COL_DEP_ID, $this->dep_id);

        return $criteria;
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        $validPk = null !== $this->getId();

        $validPrimaryKeyFKs = 0;
        $primaryKeyFKs = [];

        if ($validPk) {
            return crc32(json_encode($this->getPrimaryKey(), JSON_UNESCAPED_UNICODE));
        } elseif ($validPrimaryKeyFKs) {
            return crc32(json_encode($primaryKeyFKs, JSON_UNESCAPED_UNICODE));
        }

        return spl_object_hash($this);
    }

    /**
     * Returns the primary key for this object (row).
     * @return int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (dep_id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {
        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \EcclesiaCRM\Deposit (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setDate($this->getDate());
        $copyObj->setComment($this->getComment());
        $copyObj->setEnteredby($this->getEnteredby());
        $copyObj->setClosed($this->getClosed());
        $copyObj->setType($this->getType());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getPledges() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addPledge($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param  boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return \EcclesiaCRM\Deposit Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }


    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param      string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('Pledge' == $relationName) {
            $this->initPledges();
            return;
        }
    }

    /**
     * Clears out the collPledges collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addPledges()
     */
    public function clearPledges()
    {
        $this->collPledges = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collPledges collection loaded partially.
     */
    public function resetPartialPledges($v = true)
    {
        $this->collPledgesPartial = $v;
    }

    /**
     * Initializes the collPledges collection.
     *
     * By default this just sets the collPledges collection to an empty array (like clearcollPledges());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initPledges($overrideExisting = true)
    {
        if (null !== $this->collPledges && !$overrideExisting) {
            return;
        }

        $collectionClassName = PledgeTableMap::getTableMap()->getCollectionClassName();

        $this->collPledges = new $collectionClassName;
        $this->collPledges->setModel('\EcclesiaCRM\Pledge');
    }

    /**
     * Gets an array of ChildPledge objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildDeposit is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return ObjectCollection|ChildPledge[] List of ChildPledge objects
     * @throws PropelException
     */
    public function getPledges(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collPledgesPartial && !$this->isNew();
        if (null === $this->collPledges || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collPledges) {
                // return empty collection
                $this->initPledges();
            } else {
                $collPledges = ChildPledgeQuery::create(null, $criteria)
                    ->filterByDeposit($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collPledgesPartial && count($collPledges)) {
                        $this->initPledges(false);

                        foreach ($collPledges as $obj) {
                            if (false == $this->collPledges->contains($obj)) {
                                $this->collPledges->append($obj);
                            }
                        }

                        $this->collPledgesPartial = true;
                    }

                    return $collPledges;
                }

                if ($partial && $this->collPledges) {
                    foreach ($this->collPledges as $obj) {
                        if ($obj->isNew()) {
                            $collPledges[] = $obj;
                        }
                    }
                }

                $this->collPledges = $collPledges;
                $this->collPledgesPartial = false;
            }
        }

        return $this->collPledges;
    }

    /**
     * Sets a collection of ChildPledge objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $pledges A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return $this|ChildDeposit The current object (for fluent API support)
     */
    public function setPledges(Collection $pledges, ConnectionInterface $con = null)
    {
        /** @var ChildPledge[] $pledgesToDelete */
        $pledgesToDelete = $this->getPledges(new Criteria(), $con)->diff($pledges);


        $this->pledgesScheduledForDeletion = $pledgesToDelete;

        foreach ($pledgesToDelete as $pledgeRemoved) {
            $pledgeRemoved->setDeposit(null);
        }

        $this->collPledges = null;
        foreach ($pledges as $pledge) {
            $this->addPledge($pledge);
        }

        $this->collPledges = $pledges;
        $this->collPledgesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Pledge objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Pledge objects.
     * @throws PropelException
     */
    public function countPledges(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collPledgesPartial && !$this->isNew();
        if (null === $this->collPledges || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collPledges) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getPledges());
            }

            $query = ChildPledgeQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByDeposit($this)
                ->count($con);
        }

        return count($this->collPledges);
    }

    /**
     * Method called to associate a ChildPledge object to this object
     * through the ChildPledge foreign key attribute.
     *
     * @param  ChildPledge $l ChildPledge
     * @return $this|\EcclesiaCRM\Deposit The current object (for fluent API support)
     */
    public function addPledge(ChildPledge $l)
    {
        if ($this->collPledges === null) {
            $this->initPledges();
            $this->collPledgesPartial = true;
        }

        if (!$this->collPledges->contains($l)) {
            $this->doAddPledge($l);

            if ($this->pledgesScheduledForDeletion and $this->pledgesScheduledForDeletion->contains($l)) {
                $this->pledgesScheduledForDeletion->remove($this->pledgesScheduledForDeletion->search($l));
            }
        }

        return $this;
    }

    /**
     * @param ChildPledge $pledge The ChildPledge object to add.
     */
    protected function doAddPledge(ChildPledge $pledge)
    {
        $this->collPledges[]= $pledge;
        $pledge->setDeposit($this);
    }

    /**
     * @param  ChildPledge $pledge The ChildPledge object to remove.
     * @return $this|ChildDeposit The current object (for fluent API support)
     */
    public function removePledge(ChildPledge $pledge)
    {
        if ($this->getPledges()->contains($pledge)) {
            $pos = $this->collPledges->search($pledge);
            $this->collPledges->remove($pos);
            if (null === $this->pledgesScheduledForDeletion) {
                $this->pledgesScheduledForDeletion = clone $this->collPledges;
                $this->pledgesScheduledForDeletion->clear();
            }
            $this->pledgesScheduledForDeletion[]= $pledge;
            $pledge->setDeposit(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Deposit is new, it will return
     * an empty collection; or if this Deposit has previously
     * been saved, it will retrieve related Pledges from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Deposit.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return ObjectCollection|ChildPledge[] List of ChildPledge objects
     */
    public function getPledgesJoinDonationFund(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPledgeQuery::create(null, $criteria);
        $query->joinWith('DonationFund', $joinBehavior);

        return $this->getPledges($query, $con);
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Deposit is new, it will return
     * an empty collection; or if this Deposit has previously
     * been saved, it will retrieve related Pledges from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Deposit.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return ObjectCollection|ChildPledge[] List of ChildPledge objects
     */
    public function getPledgesJoinFamily(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPledgeQuery::create(null, $criteria);
        $query->joinWith('Family', $joinBehavior);

        return $this->getPledges($query, $con);
    }

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        $this->dep_id = null;
        $this->dep_date = null;
        $this->dep_comment = null;
        $this->dep_enteredby = null;
        $this->dep_closed = null;
        $this->dep_type = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->applyDefaultValues();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references and back-references to other model objects or collections of model objects.
     *
     * This method is used to reset all php object references (not the actual reference in the database).
     * Necessary for object serialisation.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->collPledges) {
                foreach ($this->collPledges as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collPledges = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(DepositTableMap::DEFAULT_STRING_FORMAT);
    }

    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preSave')) {
            return parent::preSave($con);
        }
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postSave')) {
            parent::postSave($con);
        }
    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preInsert')) {
            return parent::preInsert($con);
        }
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postInsert')) {
            parent::postInsert($con);
        }
    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preUpdate')) {
            return parent::preUpdate($con);
        }
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postUpdate')) {
            parent::postUpdate($con);
        }
    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preDelete')) {
            return parent::preDelete($con);
        }
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postDelete')) {
            parent::postDelete($con);
        }
    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}
