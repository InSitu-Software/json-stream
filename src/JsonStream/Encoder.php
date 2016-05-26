<?php

namespace JsonStream;

class Writer {

	private $_buffer = "";
	private $_buffer_entries = 0;

	private $_stream;
	private $_stream_buffer_size;

	/**
	 * @param resource $stream A stream resource.
	 * @param int buffer_size Number of buffered write queries before stream output
	 * @throws \InvalidArgumentException If $stream is not a stream resource.
	 */
	public function __construct($stream = null, $stream_buffer_size = false) {

		if (!is_null($stream)) {

			if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
				throw new \InvalidArgumentException("Resource is not a stream");
			}

			$this->_stream = $stream;


			if ($stream_buffer_size === false) {

				// disable stream buffer
				$stream_buffer_size = 0;

			} else if (is_int($stream_buffer_size)) {

				$this->_stream_buffer_size = $stream_buffer_size;
			}
		}
	}

	/**
	 * Writes a value to the stream.
	 *
	 * @param string $value
	 */
	public function write($value)
	{
		$this->_buffer .= $value;
		$this->_buffer_entries++;

		if ($this->_stream && $this->_buffer_entries >= $this->_stream_buffer_size) {

			$this->flush();
		}
	}

	/**
	 * empties output buffer into stream
	 * @return string flushed buffer
	 */
	public function flush() {

		$buffer = $this->_buffer;

		// reset buffer
		$this->_buffer = "";
		$this->_buffer_entries = 0;

		// output buffered
		if ($this->_stream) {
			fwrite($this->_stream, $buffer);
		}
		return $buffer;
	}
}

class Encoder
{

	private $_writer;

	/**
	 * @param resource $stream A stream resource.
	 */
	public function __construct($stream = null)
	{
		$this->_writer = new Writer($stream);
	}

	/**
	 * Encodes a value and writes it to the stream.
	 *
	 * @param mixed $value
	 * @return last written buffer segment / encoded json in streamless mode
	 */
	public function encode($value) {

		$this->_encode($value);

		return $this->_writer->flush();
	}

	private function _encode($value) {
		// invoke preprocessing
		$value = $value instanceof \JsonSerializable
			? $value->jsonSerialize()
			: $value;

		if(is_null($value)) {
			$this->_writer->write('null');
		}
		elseif (is_bool($value)) {
			$this->_writer->write($value ? 'true' : 'false');
		}
		elseif (is_int($value)) {
			$this->_writer->write($value);
		}
		elseif (is_float($value)) {
			$this->_writer->write($value);
		}
		elseif (is_string($value)) {
			$this->_writer->write( $this->_encodeString($value) );
		}
		elseif ($this->_isList($value)) {
			$this->_encodeList($value);
		}
		elseif (is_object($value)) {
			$this->_encodeObject($value);
		}
		elseif (is_resource($value)) {
			// do nothing
		}
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

			$this->_encode($value);
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

			$this->_writer->write( $this->_encodeString((string)$key) );
			$this->_writer->write(':');
			$this->_encode($value);
		}

		$this->_writer->write('}');
	}
}
