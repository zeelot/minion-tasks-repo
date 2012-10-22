<?php defined('SYSPATH') or die('No direct script access.');

class Task_Repo_Submodules_Update extends Minion_Task {

	/**
	 * A set of config options that this task accepts
	 * @var array
	 */
	protected $_options = array(
		'submodules',
		'upstream',
	);

	public function execute(array $config)
	{
		$submodules_config = Kohana::$config->load('minion-repo')->as_array();
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

			try
			{
				if (array_key_exists('upstream', $config))
				{
					$repo->execute('fetch minion-fetch');

					// We need to find the branches
					$branches = array_map('trim', explode(PHP_EOL, trim($repo->execute('branch -a'))));
					$local_branches = array(
						'remotes' => array(),
						'locals'  => array(),
					);

					foreach ($branches as $branch)
					{
						if (substr($branch, 0, 21) !== 'remotes/minion-fetch/')
							continue;

						$local = substr($branch, 21);
						self::output('# Fetching Branch "'.$local.'" ... ', FALSE);

						// Make sure the branch are are using isn't here from previous runs
						$this->_force_execute($repo, 'branch -D minion/'.$local);
						$repo->execute('branch minion/'.$local.' '.$branch);

						// Keep track of all the branches to push
						$local_branches['remotes'][] = 'minion/'.$local.':'.$local;
						$local_branches['locals'][] = 'minion/'.$local;

						self::output('Done.');
					}
					if (count($local_branches))
					{
						self::output('Pushing new branches.');
						// Push all the new local branches, then delete them
						$repo->execute('push minion-push --tags '.implode(' ', $local_branches['remotes']));
						$repo->execute('branch -D '.implode(' ', $local_branches['locals']));
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

			try
			{
				// Update 'origin' now
				$repo->execute('fetch origin');
				$repo->execute('remote prune origin');
				$repo->execute('checkout '.$options['checkout']);
				self::output('Switched to branch "'.$options['checkout'].'"');
			}
			catch (Exception $e)
			{
				self::error('# Error: '.$e->getMessage());
			}
		}
	}

	/**
	 * Just a simple function to run a git command and ignore the exception.
	 * This is useful when you want to make sure a branch or remote is deleted
	 * before running other commands.
	 *
	 * @param  Object $repo    The Git object to run commands with
	 * @param  String $command The command to run
	 * @return Object          Self
	 */
	protected function _force_execute($repo, $command)
	{
		try
		{
			$repo->execute($command);
		}
		catch (Exception $e) {}

		return $this;
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
