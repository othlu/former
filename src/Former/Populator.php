<?php
namespace Former;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Populates the class with values, and fetches them
 * from various places
 */
class Populator extends Collection
{
	/**
	 * Create a new collection.
	 *
	 * @param  array|Model $items
	 *
	 * @return void
	 */
	public function __construct($items = array())
	{
		$this->items = $items;
	}

	////////////////////////////////////////////////////////////////////
	///////////////////////// INDIVIDUAL VALUES ////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get the value of a field
	 *
	 * @param string $field The field's name
	 *
	 * @return mixed
	 */
	public function get($field, $fallback = null)
	{
		// Anonymous fields should not return any value
		if ($field == null) {
			return null;
		}

		// Plain array
		if (is_array($this->items) and !str_contains($field, '[')) {
			return parent::get($field, $fallback);
		}

		// Transform the name into an array
		$value = $this->items;
		$field = $this->parseFieldAsArray($field);

		// Dive into the model
		foreach ($field as $relationship) {

			// Get attribute from model
			if (!is_array($value)) {
				$value = $this->getAttributeFromModel($value, $relationship, $fallback);
				if ($value === $fallback) {
					break;
				}

				continue;
			}

			// Get attribute from model
			if (array_key_exists($relationship, $value)) {
				$value = $value[$relationship];
			} else {
				foreach ($value as $key => $submodel) {
					$value[$key] = $this->getAttributeFromModel($submodel, $relationship, $fallback);
				}
			}
		}

		return $value;
	}

	////////////////////////////////////////////////////////////////////
	///////////////////////////// SWAPPERS /////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Replace the items
	 *
	 * @param  mixed $items
	 *
	 * @return void
	 */
	public function replace($items)
	{
		$this->items = $items;
	}

	/**
	 * Reset the current values array
	 *
	 * @return void
	 */
	public function reset()
	{
		$this->items = array();
	}

	////////////////////////////////////////////////////////////////////
	////////////////////////////// HELPERS /////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Parses the name of a field to a tree of fields
	 *
	 * @param string $field The field's name
	 *
	 * @return array A tree of field
	 */
	protected function parseFieldAsArray($field)
	{
		if (Str::contains($field, '[]')) {
			return (array) $field;
		}

		// Transform array notation to dot notation
		if (Str::contains($field, '[')) {
			$field = preg_replace("/[\[\]]/", '.', $field);
			$field = str_replace('..', '.', $field);
			$field = trim($field, '.');
		}

		// Parse dot notation
		if (Str::contains($field, '.')) {
			$field = explode('.', $field);
		} else {
			$field = (array) $field;
		}

		return $field;
	}

	/**
	 * Get an attribute from a model
	 *
	 * @param object $model     The model
	 * @param string $attribute The attribute's name
	 * @param string $fallback  Fallback value
	 *
	 * @return mixed
	 */
	public function getAttributeFromModel($model, $attribute, $fallback)
	{
		// ignore Larave'l submit token
		if ($attribute == '_token') {
			return $fallback;
		}

		// in case of mongator groups
		if ($model instanceof \Mongator\Group\AbstractGroup) {
			foreach ($model as $key => $value) {
				if ($key == $attribute) {
					return $value;
				}
			}
		}

		// in case of mongator document
		if ($model instanceof \Mongator\Document\AbstractDocument) {
			if (method_exists($model, 'get')) {
				return $model->get($attribute);
			}
		}

		// in case of Eloquent Model - not used in everyglobe
		if ($model instanceof Model) {
			return $model->getAttribute($attribute);
		}

		// in case model can be converted to an array
		if (method_exists($model, 'toArray')) {
			$model = $model->toArray();
			if (array_key_exists($attribute, $model)) {
				return $model[$attribute];
			}
		}

		// in case of objects that have custom set methods (like Money)
		if ($model instanceof \JsonSerializable) {
			$functionName = 'get'.ucfirst($attribute);
			if (method_exists($model, $functionName)) {
				return $model->$functionName();
			}
		}

		// one last try
		$model = (array) $model;
		if (array_key_exists($attribute, $model)) {
			return $model[$attribute];
		}

		return $fallback;
	}
}
