<?php defined('SYSPATH') or die('No direct script access.');

class Minion_Task_Repo_Update_Forks extends Minion_Task {

	/**
	 * A set of config options that this task accepts
	 * @var array
	 */
	protected $_config = array(
		'submodules',
	);

	public function execute(array $config)
	{
		$submodules_config = Kohana::config('minion-repo-forks')->as_array();
		$submodules = Arr::get($config, 'submodules');
		if ($submodules)
		{
			$submodules = explode(',', $submodules);
			$submodules_config = Arr::extract($submodules_config, $submodules);
		}

		foreach ($submodules_config as $name => $options)
		{
			$path = $options['path'];
			$repo = new Git($path);

			self::output('# '.$name);

			self::output('Adding remotes...');

			self::output('git remote add minion-fetch '.$options['fetch_from']);
			self::output('git remote add minion-push '.$options['push_to']);

			// Make sure both minion remotes are created
			try
			{
				$repo->execute('remote add minion-fetch '.$options['fetch_from']);
			}
			catch (Exception $e) {} // Remote must already exist (ignore)

			try
			{
				$repo->execute('remote add minion-push '.$options['push_to']);
			}
			catch (Exception $e) {} // Remote must already exist (ignore)

			$repo->execute('fetch minion-fetch');

			try
			{
				// We need to find the branches
				$branches = array_map('trim', explode(PHP_EOL, trim($repo->execute('branch -a'))));
				foreach ($branches as $branch)
				{
					if (substr($branch, 0, 21) === 'remotes/minion-fetch/')
					{
						$local = substr($branch, 21);
						self::output('# Updating Branch "'.$local.'" ... ', FALSE);

						$repo->execute('checkout -b minion/'.$local.' '.$branch);
						$repo->execute('push minion-push minion/'.$local.':'.$local);
						$repo->execute('checkout '.$branch);
						$repo->execute('branch -D minion/'.$local);

						self::output('Done.');
					}
				}
			}
			catch (Exception $e)
			{
				self::error('');
				self::error('######################');
				self::error('# Problem Encountered:');
				self::error($e->getMessage());
				self::error('######################');
			}

			// Always clean up our remotes!
			$repo->execute('remote rm minion-fetch');
			$repo->execute('remote rm minion-push');

			// Update 'origin' now
			$repo->execute('fetch origin');
			$repo->execute('checkout '.$options['checkout']);
			self::output('Switched to branch "'.$options['checkout'].'"');
		}
	}

	public static function input($message, $default = NULL)
	{
		$message = (is_string($default)) ? $message.' ['.$default.']: ' : $message.': ';
		self::output($message, FALSE);
		$value = trim(fgets(STDIN));

		return ($default !== NULL AND empty($value))
			? $default
			: $value;
	}

	public static function output($messages, $eol = TRUE)
	{
		if (is_array($messages))
		{
			foreach ($messages as $message)
			{
				self::output($message);
			}
		}
		else
		{
			fwrite(STDOUT, $messages);
		}

		if ($eol)
		{
			fwrite(STDOUT, PHP_EOL);
		}
	}

	public static function error($messages, $eol = TRUE)
	{
		if (is_array($messages))
		{
			foreach ($messages as $message)
			{
				self::error($message);
			}
		}
		else
		{
			fwrite(STDERR, $messages);
		}

		if ($eol)
		{
			fwrite(STDERR, PHP_EOL);
		}
	}
}
