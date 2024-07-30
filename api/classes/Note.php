<?php

class Note
{
	private string $html;

	// Template variables
	const css = 'TEMPLATE_CSS';
	const width = 'TEMPLATE_WIDTH';
	const title = 'TEMPLATE_TITLE';
	const ogTitle = 'TEMPLATE_OG_TITLE';
	const metaDescription = 'TEMPLATE_META_DESCRIPTION';
	const encryptedData = 'TEMPLATE_ENCRYPTED_DATA';
	const noteContent = 'TEMPLATE_NOTE_CONTENT';
	const scripts = 'TEMPLATE_SCRIPTS';
	const elements = [
		'body'    => 'TEMPLATE_BODY',
		'preview' => 'TEMPLATE_PREVIEW',
		'pusher'  => 'TEMPLATE_PUSHER'
	];
	const assetsWebroot = 'TEMPLATE_ASSETS_WEBROOT';

	function __construct()
	{
		$this->html = file_get_contents('classes/note-template.html');
		$f3 = Base::instance();
		$this->replace(self::assetsWebroot, $f3->get('assets_webroot'));
	}

	private function replace($variable, $value = ''): void
	{
		if (!is_string($value)) {
			$value = '';
		}
		$this->html = str_replace($variable, $value, $this->html);
	}

	/**
	 * Remove any double-quotes from a string, to make it safe to use in an HTML attribute
	 * @param string $string
	 * @return string
	 */
	private function unquote(string $string): string
	{
		return str_replace('"', '', $string);
	}

	function setCss(string $url): void
	{
		$this->replace(self::css, $url);
	}

	function setTitle($title): void
	{
		if (is_string($title) && strlen($title)) {
			$this->replace(self::title, $title);
			$this->replace(self::ogTitle, "<meta property=\"og:title\" content=\"{$this->unquote($title)}\">");
		}
	}

	function setWidth($width): void
	{
		if (!is_string($width)) {
			$width = '';
		}
		$width = preg_replace('/[^a-z. ]/', '', $width);
		if ($width) {
			$width = ".markdown-preview-sizer.markdown-preview-section { max-width: $width !important; margin: 0 auto; }";
		}
		$this->replace(self::width, $width);
	}

	function addUnencryptedContents($data): void
	{
		if (is_string($data)) {
			$this->replace(self::noteContent, $data);
		}
	}

	function addEncryptedData($data): void
	{
		if (is_string($data)) {
			$this->replace(self::encryptedData, $data);
		}
		// Add the section which will be replaced by the inline Javascript
		// when the note decrypts
		$this->replace(self::noteContent, '<div id="template-user-data">Encrypted note</div>');
	}

	function setMetaDescription($desc): void
	{
		if (is_string($desc) && strlen($desc)) {
			$desc = $this->unquote($desc);
			$meta = "\t<meta name=\"description\" content=\"$desc\">";
			$meta .= "\n\t<meta content=\"$desc\" property=\"og:description\">";
			$this->replace(self::metaDescription, $meta);
		}
	}

	function enableMathjax(bool $enable): void
	{
		if ($enable) {
			$this->replace(self::scripts, '<script async src="https://cdn.jsdelivr.net/npm/mathjax@3.2.2/es5/tex-chtml-full.js"></script>');
		}
	}

	function setClassAndStyle(string $elShortname, $classes, $style): void
	{
		if (empty(self::elements[$elShortname])) {
			return;
		}

		if (!is_array($classes)) {
			$classes = [];
		}
		if (!is_string($style)) {
			$style = '';
		}

		// Sanitise
		$style   = $this->unquote($style);
		$classes = array_map(function ($class) {
			return preg_replace('/[^\w-]/', '', $class);
		}, $classes);

		// Output
		$content = [];
		if (count($classes)) {
			$content[] = 'class="' . join(' ', $classes) . '"';
		}
		if ($style) {
			$content[] = 'style="' . $this->unquote($style) . '"';
		}
		$this->replace(self::elements[$elShortname], join(' ', $content));
	}

	function contents(): string
	{
		// Remove any leftover template placeholders
		$reflect = new ReflectionClass(__CLASS__);
		foreach ($reflect->getConstants() as $variable) {
			if (is_string($variable)) {
				$this->replace($variable, '');
			} else if (is_array($variable)) {
				foreach ($variable as $var2) {
					$this->replace($var2, '');
				}
			}
		}

		// Return the final note contents
		return $this->html;
	}
}