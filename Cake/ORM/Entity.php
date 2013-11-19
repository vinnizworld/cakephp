<?php
/**
 * PHP Version 5.4
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 3.0.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\ORM;

use Cake\ORM\Table;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

/**
 * An entity represents a single result row from a repository. It exposes the
 * methods for retrieving and storing properties associated in this row.
 */
class Entity implements \ArrayAccess, \JsonSerializable {

/**
 * Holds all properties and their values for this entity
 *
 * @var array
 */
	protected $_properties = [];

/**
 * Holds the name of the class for the instance object
 *
 * @var string
 */
	protected $_className;

/**
 * Holds a list of the properties that were modified or added after this object
 * was originally created.
 *
 * @var array
 */
	protected $_dirty = [];

/**
 * Holds a cached list of methods that exist in the instanced class
 *
 * @var array
 */
	protected static $_accessors = [];

/**
 * Indicates whether or not this entity has already been persisted.
 * A null value indicates an unknown persistence status
 *
 * @var boolean
 */
	protected $_persisted = null;

/**
 * List of errors per field as stored in this object
 *
 * @var array
 */
	protected $_errors = [];

/**
 * Initializes the internal properties of this entity out of the
 * keys in an array
 *
 * ### Example:
 *
 * ``$entity = new Entity(['id' => 1, 'name' => 'Andrew'])``
 *
 * @param array $properties hash of properties to set in this entity
 * @param array $options list of options to use when creating this entity
 * the following list of options can be used:
 *
 * - useSetters: whether use internal setters for properties or not
 * - markClean: whether to mark all properties as clean after setting them
 * - markNew: whether this instance has not yet been persisted
 */
	public function __construct(array $properties = [], array $options = []) {
		$options += [
			'useSetters' => true,
			'markClean' => false,
			'markNew' => null
		];
		$this->_className = get_class($this);
		$this->set($properties, $options['useSetters']);

		if ($options['markClean']) {
			$this->clean();
		}

		if ($options['markNew'] !== null) {
			$this->isNew($options['markNew']);
		}
	}

/**
 * Magic getter to access properties that has be set in this entity
 *
 * @param string $property name of the property to access
 * @return mixed
 */
	public function &__get($property) {
		return $this->get($property);
	}

/**
 * Magic setter to add or edit a property in this entity
 *
 * @param string $property the name of the property to set
 * @param mixed $value the value to set to the property
 * @return void
 */
	public function __set($property, $value) {
		$this->set([$property => $value]);
	}

/**
 * Returns whether this entity contains a property named $property
 * regardless of if it is empty.
 *
 * @see \Cake\ORM\Entity::has()
 * @param string $property
 * @return boolean
 */
	public function __isset($property) {
		return $this->has($property);
	}

/**
 * Removes a property from this entity
 *
 * @param string $property
 * @return void
 */
	public function __unset($property) {
		$this->unsetProperty($property);
	}

/**
 * Sets a single property inside this entity.
 *
 * ### Example:
 *
 * ``$entity->set('name', 'Andrew');``
 *
 * It is also possible to mass-assign multiple properties to this entity
 * with one call by passing a hashed array as properties in the form of
 * property => value pairs
 *
 * ## Example:
 *
 * {{
 *	$entity->set(['name' => 'andrew', 'id' => 1]);
 *	echo $entity->name // prints andrew
 *	echo $entity->id // prints 1
 * }}
 *
 * Some times it is handy to bypass setter functions in this entity when assigning
 * properties. You can achieve this by setting the third argument to false when
 * assigning a single property or the second param when using an array of
 * properties.
 *
 * ### Example:
 *
 * ``$entity->set('name', 'Andrew', false);``
 *
 * ``$entity->set(['name' => 'Andrew', 'id' => 1], false);``
 *
 * @param string|array $property the name of property to set or a list of
 * properties with their respective values
 * @param mixed|boolean $value the value to set to the property or a boolean
 * signifying whether to use internal setter functions or not
 * @param boolean $useSetters whether to use setter functions in this object
 * or bypass them
 * @return \Cake\ORM\Entity
 */
	public function set($property, $value = true, $useSetters = true) {
		if (is_string($property)) {
			$property = [$property => $value];
		} else {
			$useSetters = $value;
		}

		foreach ($property as $p => $value) {
			$markDirty = true;
			if (isset($this->_properties[$p])) {
				$markDirty = $value !== $this->_properties[$p];
			}

			if ($markDirty) {
				$this->dirty($p, true);
			}

			if (!$useSetters) {
				$this->_properties[$p] = $value;
				continue;
			}

			$setter = 'set' . Inflector::camelize($p);
			if ($this->_methodExists($setter)) {
				$value = $this->{$setter}($value);
			}
			$this->_properties[$p] = $value;
		}
		return $this;
	}

/**
 * Returns the value of a property by name
 *
 * @param string $property the name of the property to retrieve
 * @return mixed
 */
	public function &get($property) {
		$method = 'get' . Inflector::camelize($property);
		$value = null;

		if (isset($this->_properties[$property])) {
			$value =& $this->_properties[$property];
		}

		if ($this->_methodExists($method)) {
			$value = $this->{$method}($value);
		}
		return $value;
	}

/**
 * Returns whether this entity contains a property named $property
 * regardless of if it is empty.
 *
 * ### Example:
 *
 * {{{
 *		$entity = new Entity(['id' => 1, 'name' => null]);
 *		$entity->has('id'); // true
 *		$entity->has('name'); // false
 *		$entity->has('last_name'); // false
 * }}}
 *
 * @param string $property
 * @return boolean
 */
	public function has($property) {
		return $this->get($property) !== null;
	}

/**
 * Removes a property or list of properties from this entity
 *
 * ### Examples:
 *
 * ``$entity->unsetProperty('name');``
 *
 * ``$entity->unsetProperty(['name', 'last_name']);``
 *
 * @param string|array $property
 * @return \Cake\ORM\
 */
	public function unsetProperty($property) {
		$property = (array)$property;
		foreach ($property as $p) {
			unset($this->_properties[$p]);
		}

		return $this;
	}

/**
 * Returns an array with all the properties that have been set
 * to this entity
 *
 * This method will recursively transform entities assigned to properties
 * into arrays as well.
 *
 * @return array
 */
	public function toArray() {
		$result = [];
		foreach ($this->_properties as $property => $value) {
			$value = $this->get($property);
			if (is_array($value) && isset($value[0]) && $value[0] instanceof self) {
				$result[$property] = [];
				foreach ($value as $k => $entity) {
					$result[$property][$k] = $entity->toArray();
				}
			} elseif ($value instanceof self) {
				$result[$property] = $value->toArray();
			} else {
				$result[$property] = $value;
			}
		}
		return $result;
	}

/**
 * Implements isset($entity);
 *
 * @param mixed $offset
 * @return void
 */
	public function offsetExists($offset) {
		return $this->has($offset);
	}
/**
 * Implements $entity[$offset];
 *
 * @param mixed $offset
 * @return void
 */

	public function &offsetGet($offset) {
		return $this->get($offset);
	}

/**
 * Implements $entity[$offset] = $value;
 *
 * @param mixed $offset
 * @param mixed $value
 * @return void
 */

	public function offsetSet($offset, $value) {
		$this->set([$offset => $value]);
	}

/**
 * Implements unset($result[$offset);
 *
 * @param mixed $offset
 * @return void
 */
	public function offsetUnset($offset) {
		$this->unsetProperty($offset);
	}

/**
 * Determines whether a method exists in this class
 *
 * @param string $method the method to check for existence
 * @return boolean true if method exists
 */
	protected function _methodExists($method) {
		if (empty(static::$_accessors[$this->_className])) {
			static::$_accessors[$this->_className] = array_flip(get_class_methods($this));
		}
		return isset(static::$_accessors[$this->_className][$method]);
	}

/**
 * Returns the properties that will be serialized as json
 *
 * @return array
 */
	public function jsonSerialize() {
		return $this->toArray();
	}

/**
 * Returns an array with the requested properties
 * stored in this entity, indexed by property name
 *
 * @param array $properties list of properties to be returned
 * @param boolean $onlyDirty Return the requested property only if it is dirty
 * @return array
 */
	public function extract(array $properties, $onlyDirty = false) {
		$result = [];
		foreach ($properties as $property) {
			if (!$onlyDirty || $this->dirty($property)) {
				$result[$property] = $this->get($property);
			}
		}
		return $result;
	}

/**
 * Sets the dirty status of a single property. If called with no second
 * argument, it will return whether the property was modified or not
 * after the object creation.
 *
 * @param string $property the field to set or check status for
 * @param null|boolean true means the property was changed, false means
 * it was not changed and null will make the function return current state
 * for that property
 * @return boolean whether the property was changed or not
 */
	public function dirty($property, $isDirty = null) {
		if ($isDirty === null) {
			return isset($this->_dirty[$property]);
		}

		if (!$isDirty) {
			unset($this->_dirty[$property]);
			return false;
		}

		$this->_dirty[$property] = true;
		return true;
	}

/**
 * Sets the entire entity as clean, which means that it will appear as
 * no properties being modified or added at all. This is an useful call
 * for an initial object hydration
 *
 * @return void
 */
	public function clean() {
		$this->_dirty = [];
		$this->_errors = [];
	}

/**
 * Returns whether or not this entity has already been persisted.
 * This method can return null in the case there is no prior information on
 * the status of this entity.
 *
 * If called with a boolean it will set the known status of this instance,
 * true means that the instance is not yet persisted in the database, false
 * that it already is.
 *
 * @param boolean $new true if it is known this instance was persisted
 * @return boolean if it is known whether the entity was already persisted
 * null otherwise
 */
	public function isNew($new = null) {
		if ($new === null) {
			return $this->_persisted;
		}
		return $this->_persisted = (bool)$new;
	}

/**
 * Validates the internal properties using a validator object. The resulting
 * errors will be copied inside this entity and can be retrieved using the
 * `errors` method.
 *
 * The second argument can be used to restrict the fields that need to be passed
 * to the validator object.
 *
 * This function returns true if there were no validation errors or false
 * otherwise.
 *
 * @param \Cake\Validation\Validator $validator
 * @param array $fieldList The fields to be passed to the validator
 * @return boolean
 */
	public function validate(Validator $validator, array $fieldList = []) {
		if (empty($fieldList)) {
			$fieldList = array_keys($this->_properties);
		}

		$missing = array_diff_key(array_flip($fieldList), $this->_properties);
		$data = $this->extract($fieldList);

		if ($missing) {
			foreach ($data as $field => $value) {
				if ($value === null && isset($missing[$field])) {
					unset($data[$field]);
				}
			}
		}

		$new = $this->isNew();
		$this->errors($validator->errors($data, $new === null ? true : $new));
		return empty($this->_errors);
	}

	public function errors($field = null, $errors = null) {
		if ($field === null) {
			return $this->_errors;
		}

		if (is_string($field) && $errors === null && isset($this->_errors[$field])) {
			return $this->_errors[$field];
		}

		if (!is_array($field)) {
			$field = [$field => $errors];
		}

		foreach ($field as $f => $error) {
			$this->_errors[$f] = (array)$error;
		}

		return $this;
	}
}
