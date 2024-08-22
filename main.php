<?php

class CodeBase
{

	private static $instance = NULL;
	private static $argv = [];
	private static $commands = ['merge'];

	private static $RESET = "\033[0m";
	private static $RED = "\033[31m";
	private static $GREEN = "\033[32m";
	private static $GRAY = "\033[90m";
	private static $BOLD = "\033[1m";

	private static $MERGE_SECTION_SLUG = 'codebase-merge';

	function __construct(array|NULL $argv)
	{
		if ($argv !== NULL) self::$argv = $argv;
	}

	/**
	 * Get
	 */

	static function instance(array|NULL $argv = NULL)
	{
		if (self::$instance !== NULL) return self::$instance;
		return self::$instance = new self($argv);
	}

	protected static function argvList()
	{
		return self::$argv;
	}

	protected static function commands()
	{
		return self::$commands;
	}

	/**
	 * Commands
	 */

	static function runCommand()
	{
		$argv = self::argvList();
		$command = $argv[1] ?? NULL;

		if (!self::hasCommand($command)) self::die("Command not found: $command", 'warning', [self::class, 'help']);
		if (!method_exists('CodeBase', $command)) self::die("Method not found: $command", 'warning');

		call_user_func([self::class, $command]);
	}

	private static function merge()
	{

		$logs = [];
		$sourceFiles = [];
		$sourceIsFile = false;

		$argv = self::argvList();
		$sourcePath = $argv[2] ?? NULL;
		$output = [];

		if (empty($sourcePath)) self::die("Source path is not correct.", 'warning');
		if (is_file($sourcePath)) $sourceIsFile = true;

		if ($sourceIsFile) $sourceFiles[] = $sourcePath;

		foreach ($sourceFiles as $sourceFile) {
			$ext = pathinfo($sourceFile, PATHINFO_EXTENSION);
			$fileName = pathinfo($sourceFile, PATHINFO_BASENAME);
			$dir = pathinfo($sourceFile, PATHINFO_DIRNAME);
			$content = file_get_contents($sourceFile);
			
			if(strtolower($ext ?? '') === 'css') {
				$output[] = [
					'file'=>$dir.DIRECTORY_SEPARATOR.'merge-'.$fileName,
					'content'=>self::mergeCSSFileContent($content, $logs)
				];
			}
		}


		foreach($output as $row){
			file_put_contents($row['file'], $row['content']);
		}

		foreach($logs as $log){
			self::message($log, 'success');
		}
		

		self::message("Completed!", "success");
	}

	private static function mergeCSSFileContent($content, &$logs = []) {
		
		$newContent = $content;
		$newContent = self::removeCSSMergeSections($newContent);

		$pattern = '/\*?\s*codebase:\s*(https?:\/\/[^\s]+|www\.[^\s]+)/i';
		$lines = explode("\n", $content);
		$mergeSlug = self::$MERGE_SECTION_SLUG;

		foreach($lines as $line){
			if (preg_match($pattern, $line, $matches)) {
				$url = $matches[1];
				try {
					$urlContent = file_get_contents($url);
					$urlContent = "$line\n\n/* $mergeSlug */\n$urlContent\n/* #$mergeSlug */\n";

					$newContent = str_replace($line, $urlContent, $newContent);
					$logs[] = "Content replaced for: $url";
				}
				catch(Exception $e){}
			}
		}

		return $newContent;
	}

	private static function removeCSSMergeSections($content){
		$removePattern = '/\/\* '.self::$MERGE_SECTION_SLUG.' \*\/.*?\/\* #'.self::$MERGE_SECTION_SLUG.' \*\//s';
		return preg_replace($removePattern, '', $content);
	}


	/**
	 * Help
	 */

	private static function mergeHelp()
	{
		// Info: dynamically invoked
		echo "merge [source-path] --ext=[file-extension] ";
	}

	private static function help()
	{

		echo self::$BOLD;
		echo self::$GRAY;
		echo "\n===== Help =====\n\n";

		foreach (self::commands() as $index => $command) {
			$helperName = $command . 'Help';
			if (!method_exists('CodeBase', $helperName)) return;
			call_user_func([self::class, $helperName]) . "\n";
		}

		echo self::$RESET;
	}


	/**
	 * Message
	 */

	private static function message($text, $type = 'default')
	{
		if ($type === 'default') echo $text . "\n";
		else if ($type === 'warning') echo sprintf('%s%s%s%s', self::$RED, $text, self::$RESET, "\n");
		else if ($type === 'success') echo sprintf('%s%s%s%s', self::$GREEN, $text, self::$RESET, "\n");
	}

	private static function die($message, $type = 'default', $callback = NULL)
	{
		self::message($message, $type);
		if (is_callable($callback)) call_user_func($callback);
	}


	/**
	 * Checks
	 */

	protected static function hasCommand($command)
	{
		return in_array($command, self::commands());
	}
}

$codebase = CodeBase::instance($argv);
$codebase::runCommand();
