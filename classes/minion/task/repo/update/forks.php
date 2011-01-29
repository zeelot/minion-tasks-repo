<?php defined('SYSPATH') or die('No direct script access.');

class Minion_Task_Repo_Update_Forks extends Minion_Task {

	public function execute(array $config)
	{
		$modules = Kohana::config('minion-repo-forks');

		foreach ($modules as $path => $options)
		{
			$repo = new Git($path);

			CLI::output('# '.$path);

			CLI::output('Adding remotes...');

			CLI::output('git remote add minion-fetch '.$options['fetch_from']);
			CLI::output('git remote add minion-push '.$options['push_to']);

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
						CLI::output('# Updating Branch "'.$local.'" ... ', FALSE);

						$repo->execute('checkout -b '.$local.' '.$branch);
						$repo->execute('push minion-push '.$local);
						$repo->execute('checkout '.$branch);
						$repo->execute('branch -D '.$local);

						CLI::output('Done.');
					}
				}
			}
			catch (Exception $e)
			{
				CLI::error('');
				CLI::error('######################');
				CLI::error('# Problem Encountered:');
				CLI::error($e->getMessage());
				CLI::error('######################');
			}

			// Always clean up our remotes!
			$repo->execute('remote rm minion-fetch');
			$repo->execute('remote rm minion-push');

			// Update 'origin' now
			$repo->execute('fetch origin');
			$repo->execute('checkout '.$options['checkout']);
			CLI::output('Switched to branch "'.$options['checkout'].'"');
		}
	}
}
