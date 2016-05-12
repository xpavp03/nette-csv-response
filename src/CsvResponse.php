<?php

namespace OHWeb\Application\Responses;

use Nette;

/**
 * CSV download response.
 * Under New BSD license.
 *
 * @package OHWeb\Application\Responses
 */
class CsvResponse extends Nette\Object implements Nette\Application\IResponse
{
	/** standard glues */
	const COMMA = ',',
		SEMICOLON = ';',
		TAB = ' ';



	/** @var bool */
	protected $addBom;



	/** @var bool */
	protected $addHeading;



	/** @var string */
	protected $glue = self::COMMA;



	/** @var string */
	protected $outputCharset = 'utf-8';



	/** @var string */
	protected $contentType = 'text/csv';



	/** @var callable */
	protected $headingFormatter = 'self::firstUpperNoUnderscoresFormatter';



	/** @var callable */
	protected $dataFormatter;



	/** @var array */
	protected $data;



	/** @var string */
	protected $filename;



	/**
	 * In accordance with Nette Framework accepts only UTF-8 input. For output @see setOutputCharset().
	 *
	 * @param array[]|\Traversable $data
	 * @param string               $filename
	 * @param bool                 $addHeading	whether add first row from data array keys (keys are taken from first row)
	 * @param bool                 $addBom		whether byte order mark should be added
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct($data, $filename = 'output.csv', $addHeading = TRUE, $addBom = FALSE) {
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}
		if (!is_array($data)) {
			throw new \InvalidArgumentException(
				__CLASS__ . ": data must be two dimensional array or instance of Traversable."
			);
		}
		$this->data = array_values($data);
		$this->filename = $filename;
		$this->addHeading = $addHeading;
		$this->addBom = $addBom;
	}



	/**
	 * Value separator.
	 *
	 * @param string $glue
	 *
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function setGlue($glue) {
		if (empty($glue) || preg_match('/[\n\r"]/s', $glue)) {
			throw new \InvalidArgumentException(
				__CLASS__ . ": glue cannot be an empty or reserved character."
			);
		}
		$this->glue = $glue;
		return $this;
	}



	/**
	 * @param string $charset
	 *
	 * @return self
	 */
	public function setOutputCharset($charset) {
		$this->outputCharset = $charset;
		return $this;
	}



	/**
	 * @param string $contentType
	 *
	 * @return self
	 */
	public function setContentType($contentType) {
		$this->contentType = $contentType;
		return $this;
	}



	/**
	 * When heading added, it is formatted by given callback.
	 * Default @see firstUpperNoUnderscoresFormatter(); erase it by calling setHeadingFormatter(NULL).
	 *
	 * @param callable $formatter
	 *
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function setHeadingFormatter($formatter) {
		if ($formatter !== NULL && !is_callable($formatter)) {
			throw new \InvalidArgumentException(
				__CLASS__ . ": heading formatter must be callable."
			);
		}
		$this->headingFormatter = $formatter;
		return $this;
	}



	/**
	 * If given, every value is formatted by given callback.
	 *
	 * @param callable $formatter
	 *
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function setDataFormatter($formatter) {
		if ($formatter !== NULL && !is_callable($formatter)) {
			throw new \InvalidArgumentException(
				__CLASS__ . ": data formatter must be callable."
			);
		}
		$this->dataFormatter = $formatter;
		return $this;
	}



	/**
	 * @param string $heading
	 *
	 * @return string
	 */
	public static function firstUpperNoUnderscoresFormatter($heading) {
		$heading = str_replace("_", ' ', $heading);
		$heading = mb_strtoupper(mb_substr($heading, 0, 1)) . mb_substr(
				$heading, 1
			);
		return $heading;
	}



	/**
	 * Sends response to output.
	 *
	 * @param Nette\Http\IRequest $httpRequest
	 * @param Nette\Http\IResponse $httpResponse
	 */
	public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse) {
		$httpResponse->setContentType($this->contentType, $this->outputCharset);
		$attachment = 'attachment';
		if (!empty($this->filename)) {
			$attachment .= '; filename="' . $this->filename . '"';
		}
		$httpResponse->setHeader('Content-Disposition', $attachment);
		$data = $this->formatCsv();
		$httpResponse->setHeader('Content-Length', strlen($data));
		print $data;
	}



	protected function formatCsv() {
		if (empty($this->data)) {
			return '';
		}
		ob_start();
		$buffer = fopen("php://output", 'w');
		// if output charset is not UTF-8
		$recode = strcasecmp($this->outputCharset, 'utf-8');
		foreach ($this->data as $n => $row) {
			if ($row instanceof \Traversable) {
				$row = iterator_to_array($row);
			}
			if (!is_array($row)) {
				throw new \InvalidArgumentException(
					__CLASS__ . ": row $n must be array or instance of Traversable, " . gettype(
						$row
					) . ' given.'
				);
			}
			if ($n === 0 && $this->addHeading) {
				$labels = array_keys($row);
				if ($this->headingFormatter) {
					foreach ($labels as &$label) {
						$label = call_user_func(
							$this->headingFormatter, $label
						);
					}
				}
				fputcsv($buffer, $labels, $this->glue);
			}
			if ($this->dataFormatter) {
				foreach ($row as &$value) {
					$value = call_user_func($this->dataFormatter, $value);
				}
			}
			fputcsv($buffer, $row, $this->glue);
		}
		fclose($buffer);
		$output = ob_get_clean();

		if ($recode) {
			$output = iconv('utf-8', "$this->outputCharset//TRANSLIT", $output);
		}

		if ($this->addBom) {
			$output = $this->getBom() . $output;
		}
		return $output;
	}



	/**
	 * @see http://en.wikipedia.org/wiki/Byte_order_mark#Representations_of_byte_order_marks_by_encoding
	 */
	protected function getBom()
	{
		$boms = array(
			'utf-8' => "\xEF\xBB\xBF",
			'utf-16be' => "\xFE\xFF",
			'utf-16le' => "\xFF\xFE",
			'utf-32be' => "\x00\x00\xFE\xFF",
			'utf-32le' => "\xFF\xFE\x00\x00",
			'utf-7' => "\x2B\x2F\x76\x38", // one of many
			'utf-1' => "\xF7\x64\x4C",
			'utf-ebcdic' => "\xDD\x73\x66\x73",
			'scsu' => "\x0E\xFE\xFF",
			'bocu-1' => "\xFB\xEE\x28",
			'bg-18030' => "\x84\x31\x95\x33",
		);
		$charset = strtolower($this->outputCharset);

		return isset($boms[$charset]) ? $boms[$charset] : null;
	}
}
