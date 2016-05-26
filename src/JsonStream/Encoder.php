<?php

namespace JsonStream;

class Writer {

	private $_stream;

	public function __construct($stream) {
		if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
			throw new \InvalidArgumentException("Resource is not a stream");
		}

		$this->_stream = $stream;
	}

	/**
	 * Writes a value to the stream.
	 *
	 * @param string $value
	 */
	public function write($value)
	{
		fwrite($this->_stream, $value);
	}
}

class Encoder
{

	private $_writer;

	/**
	 * @param resource $stream A stream resource.
	 * @throws \InvalidArgumentException If $stream is not a stream resource.
	 */
	public function __construct($stream = null)
	{
		// default to output
		if (is_null($stream)) {
			$stream = fopen('php://output', 'w');
		}

		$this->_writer = new Writer($stream);
	}

	/**
	 * Encodes a value and writes it to the stream.
	 *
	 * @param mixed $value
	 */
	public function encode($value)
	{
		// invoke preprocessing
		$value = $value instanceof \JsonSerializable
			? $value->jsonSerialize()
			: $value;

		// null, bool and scalar values
		if(is_null($value)) {
			$this->_writer->write('null');
			return;
		}
		elseif ($value === false) {
			$this->_writer->write('false');
			return;
		}
		elseif ($value === true) {
			$this->_writer->write('true');
			return;
		}
		elseif (is_scalar($value)) {
			$this->_encodeScalar($value);
			return;
		}

		// array of values
		if ($this->_isList($value)) {
			$this->_encodeList($value);
			return;
		}
		// objects and associative arrays
		else {
			$this->_encodeObject($value);
			return;
		}
	}

	/**
	 * Encodes a scalar value.
	 *
	 * @param mixed $value
	 */
	private function _encodeScalar($value)
	{
		if (is_float($value)) {
			// Always use "." for floats.
			$encodedValue = floatval(str_replace(",", ".", strval($value)));
		}
		elseif (is_string($value)) {
			$encodedValue = $this->_encodeString($value);
		}
		else {
			// otherwise this must be an int
			$encodedValue = $value;
		}

		$this->_writer->write($encodedValue);
	}

	/**
	 * Encodes a string.
	 *
	 * @param $string
	 * @return string
	 */
	private function _encodeString($string)
	{
		static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"', "\0"), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"', '\u0000'));
		return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $string) . '"';
	}

	/**
	 * Checks if a value is a flat list of values (simple array) or a map (assoc. array or object).
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private function _isList($value)
	{
		// objects that are not explicitly traversable could never have integer keys, therefore they are not a list
		if (is_object($value) && !($value instanceof \Traversable)) {
			return false;
		}

		// check if the array/object has only integer keys.
		$i = 0;
		foreach ($value as $key => $element) {
			if ($key !== $i) {
				return false;
			}
			$i++;
		}

		return true;
	}

	/**
	 * Encodes a list of values.
	 *
	 * @param array $list
	 */
	private function _encodeList($list)
	{
		$this->_writer->write('[');

		$firstIteration = true;

		foreach ($list as $x => $value) {
			if (!$firstIteration) {
				$this->_writer->write(',');
			}
			$firstIteration = false;

			$this->encode($value);
		}

		$this->_writer->write(']');
	}

	/**
	 * Encodes an object or associative array.
	 *
	 * @param array|object $object
	 */
	private function _encodeObject($object)
	{
		$this->_writer->write('{');

		$firstIteration = true;

		foreach ($object as $key => $value) {
			if (!$firstIteration) {
				$this->_writer->write(',');
			}
			$firstIteration = false;

			$this->_encodeScalar((string)$key);
			$this->_writer->write(':');
			$this->encode($value);
		}

		$this->_writer->write('}');
	}
}
